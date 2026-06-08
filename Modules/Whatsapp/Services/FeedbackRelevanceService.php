<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services;

use App\Contact;
use Carbon\Carbon;
use Modules\Whatsapp\Entities\ClientFeedback;

/**
 * FeedbackRelevanceService — checksum/signature + ranking adaptativo.
 *
 * Wagner 2026-05-27: índice de feedback com dedup + decay temporal.
 * Mantém só os relevantes em memória ativa (INDEX.md top 20), arquiva resto.
 *
 * Refs: ADR 0195-feedback-relevance-scoring-decay-adaptativo, ADR 0105 (cliente-como-sinal),
 * ADR 0131 (tiering memória canon/local/segredo).
 *
 * 4 camadas de relevância:
 *   HOT     score >= 70   → INDEX.md auto-loaded
 *   WARM    30..69        → DB only, sob demanda
 *   COLD    < 30 OU closed >= 90d → archive trimestral
 *   FROZEN  resolved >= 365d → LGPD retention
 *
 * Score formula (0-100):
 *   40 * severity/4                            severidade NN/g (dor real)
 * + 25 * log10(recorrente+1)/log10(5)           plateau em 5 ocorrências
 * + 15 * (cliente_pagante ? 1.0 : 0.2)          ADR 0105 sinal qualificado
 * + 10 * persona_priority                      primary=1 / secondary=0.6 / outras=0.3
 * + 10 * exp(-days_since_last_seen/60)         decay meia-vida 60d
 *
 * Signature (sha1 40c):
 *   sha1(business_id|persona_slug|modulo|acao|literal_normalized)
 *
 *   literal_normalized = lowercase + strip punctuation + 5 primeiras palavras
 *   significativas (>= 3 chars, não-stopword).
 *
 * Personas primary mapping vem de memory/requisitos/_DesignSystem/personas-por-modulo.yml
 * — hardcoded aqui pra evitar I/O em hot path. Atualizar quando expandir personas.
 */
class FeedbackRelevanceService
{
    /**
     * Personas primary por módulo (ADR UI-0016 design contextualizado).
     */
    public const PRIMARY_PERSONAS = [
        'sells' => ['larissa-rota-livre'],
        'oficinaauto' => ['daniela-martinho'],
        'financeiro' => ['kamila-martinho'],
        'cliente' => ['larissa-rota-livre', 'daniela-martinho'],
        'fiscal' => ['kamila-martinho'],
        'nfe-brasil' => ['kamila-martinho'],
        'nfse' => ['kamila-martinho'],
        'whatsapp' => ['larissa-rota-livre', 'daniela-martinho'],
    ];

    public const SECONDARY_PERSONAS = [
        'sells' => ['daniela-martinho'],
        'oficinaauto' => ['jair-martinho'],
        'financeiro' => ['jair-martinho'],
        'whatsapp' => ['kamila-martinho'],
    ];

    public const STOPWORDS_PT = [
        'a', 'o', 'e', 'de', 'do', 'da', 'das', 'dos', 'em', 'no', 'na', 'que',
        'um', 'uma', 'pra', 'para', 'por', 'com', 'se', 'ou', 'ao', 'tem',
        'tá', 'ta', 'to', 'tô', 'sou', 'eu', 'me', 'meu', 'minha',
        'é', 'foi', 'ser', 'mas', 'só', 'já', 'aqui', 'isso', 'esse',
        'esta', 'este', 'eles', 'elas', 'voce', 'você', 'nao', 'não',
        'sim', 'tudo', 'aí', 'lá', 'todo', 'toda',
    ];

    /**
     * Computa signature determinística do feedback pra dedup.
     */
    public function computeSignature(ClientFeedback $fb): string
    {
        $persona = $fb->persona_slug ?? 'desconhecido';
        $modulo = $fb->modulo_afetado ?? 'global';
        $acao = $fb->acao_afetada ?? 'sem-acao';
        $literalNorm = $this->normalizeLiteral($fb->literal);

        return sha1(implode('|', [$fb->business_id, $persona, $modulo, $acao, $literalNorm]));
    }

    /**
     * Normaliza literal pra 5 primeiras palavras significativas.
     *
     * "tô tentando emitir nota mas dá erro de SEFAZ! socorro"
     * → "tentando emitir nota dá erro"
     */
    public function normalizeLiteral(string $literal): string
    {
        $lower = mb_strtolower($literal);
        $stripped = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower);
        $words = preg_split('/\s+/', trim($stripped ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $significant = [];

        foreach ($words as $w) {
            if (mb_strlen($w) < 3) {
                continue;
            }
            if (in_array($w, self::STOPWORDS_PT, true)) {
                continue;
            }
            $significant[] = $w;
            if (count($significant) >= 5) {
                break;
            }
        }

        return implode(' ', $significant);
    }

    /**
     * Procura feedback com mesma signature nos últimos 90d.
     * Retorna null se não há match (criar novo).
     */
    public function findDuplicateWithin90d(string $signature, int $businessId): ?ClientFeedback
    {
        return ClientFeedback::query()
            ->where('business_id', $businessId)
            ->where('signature', $signature)
            ->where('created_at', '>=', now()->subDays(90))
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Computa relevance_score (0-100) do feedback baseado na fórmula canônica.
     */
    public function computeScore(ClientFeedback $fb): float
    {
        // Severidade (0..1)
        $sev = max(0, min(4, $fb->severity_nng)) / 4.0;
        $scoreSev = 40 * $sev;

        // Recorrência log-plateau em 5 ocorrências (0..1)
        $rec = max(1, (int) ($fb->recorrente_count ?? 1));
        $scoreRec = 25 * (log10($rec + 1) / log10(5 + 1));
        $scoreRec = min(25, $scoreRec);

        // Cliente pagante (1.0) vs lead/null (0.2)
        $isPaying = $this->isPayingCustomer($fb);
        $scorePag = 15 * ($isPaying ? 1.0 : 0.2);

        // Persona prioridade
        $personaPrio = $this->personaPriority($fb);
        $scorePersona = 10 * $personaPrio;

        // Decay temporal exp(-days/60)
        $lastSeen = $fb->last_seen_at ?? $fb->created_at ?? now();
        $days = max(0, Carbon::parse($lastSeen)->diffInDays(now()));
        $scoreRecency = 10 * exp(-$days / 60.0);

        return round($scoreSev + $scoreRec + $scorePag + $scorePersona + $scoreRecency, 2);
    }

    /**
     * É cliente pagante (Contact.type ∈ customer|both)?
     */
    protected function isPayingCustomer(ClientFeedback $fb): bool
    {
        if (! $fb->contact_id) {
            return false;
        }
        // withoutGlobalScopes pra resilir scope quando rescore via cli sem session
        $contact = Contact::withoutGlobalScopes()->find($fb->contact_id);
        if (! $contact) {
            return false;
        }
        return in_array($contact->type, ['customer', 'both'], true);
    }

    /**
     * Prioridade da persona (1=primary, 0.6=secondary, 0.3=outras).
     */
    protected function personaPriority(ClientFeedback $fb): float
    {
        $modulo = $fb->modulo_afetado;
        $persona = $fb->persona_slug;

        if (! $modulo || ! $persona) {
            return 0.3;
        }
        if (in_array($persona, self::PRIMARY_PERSONAS[$modulo] ?? [], true)) {
            return 1.0;
        }
        if (in_array($persona, self::SECONDARY_PERSONAS[$modulo] ?? [], true)) {
            return 0.6;
        }
        return 0.3;
    }

    /**
     * Classifica feedback em HOT/WARM/COLD/FROZEN baseado em score + status + age.
     */
    public function classify(ClientFeedback $fb): string
    {
        $score = (float) ($fb->relevance_score ?? $this->computeScore($fb));

        // FROZEN: resolved >= 365d (LGPD retention)
        if ($fb->status === ClientFeedback::STATUS_RESOLVED && $fb->data_resolvido) {
            if (Carbon::parse($fb->data_resolvido)->lt(now()->subDays(365))) {
                return 'FROZEN';
            }
        }

        // COLD: score < 30 OR closed >= 90d
        $isClosedOld = in_array($fb->status, [ClientFeedback::STATUS_CLOSED, ClientFeedback::STATUS_RESOLVED], true)
            && $fb->updated_at
            && Carbon::parse($fb->updated_at)->lt(now()->subDays(90));

        if ($score < 30 || $isClosedOld) {
            return 'COLD';
        }

        // HOT: score >= 70
        if ($score >= 70) {
            return 'HOT';
        }

        // WARM
        return 'WARM';
    }
}
