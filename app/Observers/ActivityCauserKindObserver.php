<?php

namespace App\Observers;

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
 */
class ActivityCauserKindObserver
{
    public function saving(Activity $activity): void
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
