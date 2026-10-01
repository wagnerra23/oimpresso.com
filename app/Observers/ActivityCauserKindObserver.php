<?php

namespace App\Observers;

use App\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity;

/**
 * US-AUDIT-006 — resolve causer_kind + agent_run_id antes de gravar Activity.
 *
 * Detecta contexto e popula a coluna causer_kind ENUM (adicionada em
 * 2026_05_10_160000_add_causer_kind_and_revert_to_activity_log) com:
 *
 *   - 'agent'  : acao veio de tool MCP / Jana Agent. Detecta via container
 *                binding `jana.agent_run_id` (Modules/Copiloto/Ai/Agents/*
 *                bind o ID do run atual antes de chamar tools que mexem em
 *                Models). agent_run_id e populado tambem.
 *   - 'system' : runningInConsole() — comando Artisan / cron / queue worker
 *                rodando sem request HTTP (ex: arquivos:health-check daily).
 *   - 'api'    : request veio em rota /api/* (clientes terceiros via Passport).
 *   - 'user'   : default — request HTTP web autenticado por usuario humano.
 *
 * Refs: ADR 0127 §princípio 3 (causer dual), ADR 0093 multi-tenant Tier 0.
 *
 * Defensive: se Activity ja tem causer_kind setado (consumer override), respeita.
 *
 * TAMBEM resolve o business_id (Tier 0, ADR 0093) — este observer e o unico ponto
 * por onde TODA linha de activity_log passa (trait LogsActivity, helper activity(),
 * Util::activityLog, Activity::create). Medido 2026-10-01: o trait LogsActivity
 * nao setava business_id NUNCA (Contact/Transaction/Product... gravavam NULL e
 * sumiam da tela /auditoria, que filtra por business_id), e o Util::activityLog
 * usava a SESSAO antes do registro — superadmin com sessao do negocio A editando
 * registro do negocio B gravava o log no tenant A. Regra: o tenant do log e o do
 * REGISTRO auditado (subject). Sem subject com tenant, mantem o que o chamador
 * setou (nunca inventa a partir da sessao aqui).
 */
class ActivityCauserKindObserver
{
    public function saving(Activity $activity): void
    {
        $this->resolverBusinessId($activity);
        $this->resolverCauserKind($activity);
    }

    /**
     * business_id do REGISTRO auditado. Vence qualquer valor setado pelo chamador:
     * o subject e a verdade do tenant; sessao/causer podem ser de outro negocio.
     */
    private function resolverBusinessId(Activity $activity): void
    {
        try {
            $subject = $activity->getRelationValue('subject'); // performedOn() já deixa carregada
        } catch (\Throwable $e) {
            return; // subject_type de classe que não existe mais: mantém o do chamador
        }

        $doSubject = self::businessIdDoSubject($subject);
        if ($doSubject === null) {
            return;
        }

        // Schema mínimo (sqlite das lanes Unit) pode não ter a coluna: setar o atributo
        // faria o INSERT morrer com "no column named business_id" (medido 2026-10-01,
        // 214 falhas no PR #8384). Mesmo padrão defensivo do causer_kind abaixo.
        try {
            $temColuna = \Schema::hasColumn('activity_log', 'business_id');
        } catch (\Throwable $e) {
            return;
        }
        if ($temColuna) {
            $activity->setAttribute('business_id', $doSubject);
        }
    }

    /**
     * Tenant de um registro: Business -> o proprio id; coluna business_id; ou,
     * sem a coluna, o business_id da Transaction-pai (SellLine, PurchaseLine...).
     * Null quando o registro nao carrega tenant.
     */
    public static function businessIdDoSubject($subject): ?int
    {
        if (! $subject instanceof Model) {
            return null;
        }

        if ($subject instanceof Business) {
            return $subject->getKey() ? (int) $subject->getKey() : null;
        }

        $direto = $subject->getAttribute('business_id');
        if (! empty($direto)) {
            return (int) $direto;
        }

        if (method_exists($subject, 'transaction')) {
            if ($subject->transaction() instanceof BelongsTo) {
                $pai = $subject->getRelationValue('transaction');
                if ($pai instanceof Model && ! empty($pai->getAttribute('business_id'))) {
                    return (int) $pai->getAttribute('business_id');
                }
            }
        }

        return null;
    }

    private function resolverCauserKind(Activity $activity): void
    {
        // Respeita override explicito do consumer (ex: testes setando manualmente)
        if (! empty($activity->causer_kind)) {
            return;
        }

        // Migration US-AUDIT-005 ainda nao aplicada? Sai silenciosamente.
        // (defensivo pra dev environment com schema desatualizado — nao quebra
        // o flow de logging do Spatie)
        try {
            $hasColumn = \Schema::hasColumn('activity_log', 'causer_kind');
        } catch (\Throwable $e) {
            return;
        }
        if (! $hasColumn) {
            return;
        }

        // Prioridade 1: tool MCP / Jana Agent
        if (app()->bound('jana.agent_run_id')) {
            $activity->causer_kind = 'agent';
            $activity->agent_run_id = app('jana.agent_run_id');

            return;
        }

        // Prioridade 2: console (Artisan / cron / queue worker)
        // (excluindo testing pra nao mascarar testes web simulando user)
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            $activity->causer_kind = 'system';

            return;
        }

        // Prioridade 3: API publica (Passport / token-based)
        if (request()->is('api/*')) {
            $activity->causer_kind = 'api';

            return;
        }

        // Default: web request usuario humano autenticado
        $activity->causer_kind = 'user';
    }
}
