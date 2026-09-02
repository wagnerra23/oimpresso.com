<?php

namespace Modules\Fiscal\Services;

use Illuminate\Support\Collection;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NFSe\Models\NfseEmissao;

/**
 * Lista unificada de notas (NF-e/NFC-e + NFS-e) do cockpit Fiscal — DADO REAL.
 *
 * POR QUE EXISTE (CU-FISC-16 · 2026-09-02)
 *   O `CockpitController` servia `mockNotasUnificadas()`: 10 notas fictícias, com
 *   cliente, valor e prazo inventados. Medido em produção no mesmo dia, o efeito era
 *   uma tela que se contradiz — header "Maio 2026 · 0 notas" (KPI real, vindo de
 *   NfeEmissao), lista com 10 linhas "Autorizada" (mock) e chip "Todas 18" (outro
 *   mock). Três números para a mesma coisa, e o operador não tem como saber qual é o
 *   real. Com o certificado A1 vencido há 26 dias no mesmo cockpit, a leitura natural
 *   de quem abre a tela é "está tudo funcionando" — e não está.
 *
 *   Este é o `NotasUnifiedService::query()` que o TODO[CL] do controller já previa.
 *   Não inventa padrão: espelha `NfeCockpitController::mapRow()`, que já servia dado
 *   real na tela irmã (/fiscal/nfe), inclusive a janela legal de cancelamento.
 *
 * TIER 0
 *   Ambos os modelos carregam HasBusinessScope (ADR 0093): as queries aqui são
 *   escopadas por business automaticamente e NÃO usam withoutGlobalScopes.
 */
class NotasUnifiedService
{
    /** Teto de linhas servidas ao cockpit (é resumo; a lista completa vive em /fiscal/nfe). */
    public const LIMITE = 50;

    /**
     * @return array<int, array<string, mixed>> Notas da mais recente pra mais antiga.
     */
    public function listar(): array
    {
        return $this->nfe()
            ->concat($this->nfse())
            ->sortByDesc(fn (array $n) => $n['sortKey'] ?? '')
            ->take(self::LIMITE)
            ->map(function (array $n) {
                unset($n['sortKey']);

                return $n;
            })
            ->values()
            ->all();
    }

    /**
     * Contadores das visões salvas — derivados da MESMA fonte da lista.
     *
     * Antes vinham de `mockSavedViewCounts()`, e era daí que saía o "Todas 18" que
     * contradizia as 10 linhas renderizadas. Derivar da lista faz o chip e a tabela
     * concordarem por construção.
     *
     * @param  array<int, array<string, mixed>>  $notas
     * @return array<string, int>
     */
    public function contadores(array $notas): array
    {
        $c = new Collection($notas);

        // ⚠️ As chaves são os `id` das SAVED_VIEWS do Cockpit.tsx, VERBATIM — o frontend
        // lê `savedViewCounts[v.id] ?? 0`, então chave divergente não dá erro: o chip
        // mostra 0 em silêncio (verde no CI, inerte na tela). Medido em Cockpit.tsx:158-163.
        return [
            'todas'       => $c->count(),
            'resolver'    => $c->where('statusKind', 'sefaz')
                ->filter(fn (array $n) => in_array((int) $n['status'], [110, 204, 220, 539, 778], true))
                ->count(),
            'janela24'    => $c->filter(fn (array $n) => ($n['prazoCancel'] ?? null) !== null)->count(),
            'processando' => $c->filter(fn (array $n) => $n['status'] === 'processando')->count(),
            'nfse'        => $c->where('kind', 'nfse')->count(),
            'nfce'        => $c->where('modelo', 65)->count(),
        ];
    }

    /** NF-e / NFC-e — espelha NfeCockpitController::mapRow(). */
    protected function nfe(): Collection
    {
        return NfeEmissao::query()
            ->orderByDesc('emitido_em')
            ->limit(self::LIMITE)
            ->get()
            ->map(function (NfeEmissao $e): array {
                $meta = $e->metadata ?? [];
                $modelo = (int) $e->modelo;

                return [
                    'id'          => 'nfe-' . $e->id,
                    'tipo'        => $modelo === 65 ? 'NFC-e' : 'NF-e',
                    'kind'        => 'nfe',
                    'num'         => (string) $e->numero,
                    'serie'       => (string) $e->serie ?: null,
                    'when'        => $e->emitido_em?->format('d/m H:i'),
                    'cliente'     => $meta['dest_name'] ?? '—',
                    'doc'         => $meta['dest_cnpj'] ?? $meta['dest_cpf'] ?? '—',
                    'cnpj'        => $meta['dest_cnpj'] ?? null,
                    'uf'          => $meta['dest_uf'] ?? null,
                    'venda'       => $e->transaction_id ? 'V-' . $e->transaction_id : null,
                    'ref'         => null,
                    'keyOrCode'   => $e->chave_44,
                    'status'      => (int) ($e->cstat ?? 0),
                    'statusKind'  => 'sefaz',
                    'rejMsg'      => $e->motivo,
                    'modelo'      => $modelo,
                    'value'       => (float) $e->valor_total,
                    'prazoCancel' => $this->janelaCancelamento($e, $modelo),
                    'prazoCce'    => null,
                    'sortKey'     => $e->emitido_em?->toIso8601String() ?? '',
                ];
            });
    }

    /** NFS-e — municipal: sem cstat nem modelo da SEFAZ estadual. */
    protected function nfse(): Collection
    {
        return NfseEmissao::query()
            ->orderByDesc('created_at')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (NfseEmissao $n): array => [
                'id'          => 'nfse-' . $n->id,
                'tipo'        => 'NFS-e',
                'kind'        => 'nfse',
                'num'         => (string) ($n->numero ?? $n->rps_numero ?? '—'),
                'serie'       => (string) $n->serie ?: null,
                'when'        => $n->competencia?->format('m/Y') ?? $n->created_at?->format('m/Y'),
                'cliente'     => $n->tomador_nome ?? '—',
                'doc'         => $n->tomador_cnpj ?? $n->tomador_cpf ?? '—',
                'cnpj'        => $n->tomador_cnpj,
                'uf'          => $n->tomador_municipio_ibge,
                'venda'       => $n->transaction_id ? 'OS #' . $n->transaction_id : null,
                'ref'         => null,
                'keyOrCode'   => $n->lc116_codigo,
                'codServ'     => $n->lc116_codigo,
                'iss'         => ((string) $n->aliquota_iss) === '' ? null : (float) $n->aliquota_iss,
                'competencia' => $n->competencia?->format('m/Y'),
                'status'      => $n->status ?? 'processando',
                'statusKind'  => 'nfse',
                'rejMsg'      => $n->erro_mensagem,
                'modelo'      => null,
                'value'       => (float) $n->valor_servicos,
                'prazoCancel' => null,
                'prazoCce'    => null,
                'sortKey'     => $n->created_at?->toIso8601String() ?? '',
            ]);
    }

    /**
     * Janela legal de cancelamento — 24h NFC-e (65) / 168h NF-e (55).
     * CONFAZ Ajuste SINIEF 07/2005 Art. 14. Mesma regra de NfeCockpitController::isCancelavel().
     *
     * @return array{label: string, urgency: string}|null
     */
    protected function janelaCancelamento(NfeEmissao $e, int $modelo): ?array
    {
        if ($e->status !== 'autorizada' || ! $e->emitido_em) {
            return null;
        }

        $limite = $modelo === 65 ? 24 : 168;
        // `diffInHours()` devolve FLOAT no Carbon 3 — sem o cast, `intdiv` abaixo
        // recebe float e o PHPStan reprova (foi o que aconteceu no 1º push deste PR).
        $restantes = $limite - (int) $e->emitido_em->diffInHours(now());

        if ($restantes <= 0) {
            return null;
        }

        return [
            'label'   => $restantes >= 24 ? intdiv($restantes, 24) . 'd' : $restantes . 'h',
            'urgency' => $restantes <= 6 ? 'crit' : ($restantes <= 24 ? 'warn' : 'ok'),
        ];
    }
}
