<?php

return [
    'name' => 'Governance',

    /*
     * ActionGate enforcement mode (Constituição Art. 8):
     * - 'off':    middleware loaded mas não checa nada
     * - 'warn':   loga warnings em mcp_audit_log mas não bloqueia (default MVP)
     * - 'strict': BLOCK_ALWAYS força reject quando rule decide
     */
    'actiongate_mode' => env('GOVERNANCE_ACTIONGATE_MODE', 'warn'),

    /*
     * Quarterly review schedule (Art. 10 §10.4 + Enforcement #7).
     * Próxima review em 2026-08-05 (3 meses pós-ratificação v1.0.0).
     */
    'next_review_at' => env('GOVERNANCE_NEXT_REVIEW_AT', '2026-08-05'),

    /*
     * D1 heurística hardening (ADR 0158 — aceita Wave 12, ATIVADA Wave 14 2026-05-16).
     *
     * Quando true, ModuleGradeService aplica 3 fixes na heurística D1 multi-tenant:
     *
     *   (1) phpFiles() recursivo em Entities/ + Models/ + Jobs/
     *       — captura subdiretórios (ex Jana/Entities/Mcp/*.php)
     *   (2) Regex isCrossTenantTestFile aceita `withoutGlobalScope` singular E plural
     *       (s? = 0 ou 1 ocorrência) — back-compat preservado
     *   (3) D1.c fallback regex: Job constructor `__construct(int $entityId)` +
     *       body referencia `->business_id` qualifica como multi-tenant safe
     *
     * Default `true` desde Wave 14 — Wagner aprovou após smoke run + diff por módulo
     * mostrar ajustes esperados (Jana/Entities/Mcp/* sobe, jobs Asaas/Inter qualificam).
     *
     * Quando desativar (`GOVERNANCE_D1_HARDENED=false` no .env):
     *   - Regressão massiva (>3 módulos perdem ≥3pts D1) detectada em prod
     *   - Investigação de bug exige isolar heurística legacy vs hardened
     *   - Rollback emergencial de ADR 0158 (via ADR nova de reversão)
     *
     * @see memory/decisions/0158-module-grade-v3-d1-heuristica-hardening.md
     */
    'd1_hardened' => env('GOVERNANCE_D1_HARDENED', true),

    /*
     * ADR 0216 — Drift Framework master switch.
     *
     * Quando true, GovernanceServiceProvider auto-registra DriftCheckers default
     * (5 da ADR 0216 PR1) + comando `governance:audit` itera registry.
     *
     * Quando false (rollback canary), framework dorme; comandos legacy
     * (`secrets:scan`, `secrets:audit`, `governance:detect-drift`) continuam
     * funcionando standalone.
     *
     * Default true em local; em live promover via env `GOVERNANCE_DRIFT_FRAMEWORK_ENABLED=true`
     * após canary 7d (ADR 0216 §D10).
     */
    'drift_framework_enabled' => env('GOVERNANCE_DRIFT_FRAMEWORK_ENABLED', true),

    /*
     * Lista canônica de DriftCheckers a auto-registrar no boot.
     * Override por ENV ou config:cache. Ordem importa pra `--all` (executa em sequência).
     *
     * Cada classe DEVE implementar Modules\Governance\Contracts\DriftChecker.
     *
     * PR1 (ADR 0216) ships com array vazio — checkers adicionados em PR2 (ADRs 0217+).
     * Exemplo futuro:
     *   \Modules\Governance\Services\Checkers\MultiTenantScopeChecker::class,
     *   \Modules\Governance\Services\Checkers\ComposerAuditChecker::class,
     *   ...
     *
     * @see memory/decisions/0216-governance-drift-framework-driftchecker-plugavel.md
     */
    'drift_checkers' => [
        \Modules\Governance\Services\Checkers\ComposerAuditChecker::class,       // ADR 0217
        \Modules\Governance\Services\Checkers\NpmAuditChecker::class,            // ADR 0223 — frontend supply chain
        \Modules\Governance\Services\Checkers\MultiTenantScopeChecker::class,    // ADR 0218 — Tier 0 IRREVOGÁVEL
        \Modules\Governance\Services\Checkers\AdrLinksChecker::class,            // ADR 0219
        \Modules\Governance\Services\Checkers\ChartersFreshnessChecker::class,   // ADR 0220 — adapter charter:audit
        \Modules\Governance\Services\Checkers\DesignDocsFreshnessChecker::class, // ADR 0236 — freshness gate dos docs de design (file-based)
        \Modules\Governance\Services\Checkers\RoutesZombieChecker::class,        // ADR 0221
        \Modules\Governance\Services\Checkers\MeilisearchSettingsDriftChecker::class, // 2026-05-29 — embedder do índice se perdeu 2× (recall degrada)
        \Modules\Governance\Services\Checkers\DeployDriftChecker::class,         // 2026-05-29 — código deployado != main (1302-commits cego)
        \Modules\Governance\Services\Checkers\McpServedDriftChecker::class,      // 2026-06-21 Onda 1 — commit servido por env remoto != main (CT100→main, ~19d cego)
        \Modules\Governance\Services\Checkers\McpIndexFreshnessChecker::class,   // 2026-06-21 Onda 1 — índice mcp_memory_documents defasado vs git memory/
    ],

    /*
     * Onda 1 (sentinela transporte CT100→main) — envs que o McpServedDriftChecker
     * consulta via GET <url>/api/mcp/version (ADR 0256) pra comparar o commit SERVIDO
     * por cada um com GitHub main. Cada item: {nome, url} (sem trailing slash).
     *
     * Default cobre só o MCP público (mcp.oimpresso.com). Override por env
     * GOVERNANCE_DEPLOY_DRIFT_ENVS (JSON: [{"nome":"...","url":"https://..."}]) —
     * ex pra adicionar o app Hostinger quando ele expuser /api/mcp/version.
     *
     * Hostinger ≠ CT 100 (ADR 0062) — adicionar Hostinger só quando tiver endpoint próprio.
     * Auth: Bearer config('copiloto.mcp.drift_token') (env MCP_DRIFT_TOKEN), o mesmo
     * token dedicado do endpoint. Falha de rede/HTTP NÃO derruba o audit (finding low/info).
     */
    'deploy_drift_envs' => (function () {
        $raw = env('GOVERNANCE_DEPLOY_DRIFT_ENVS');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [
            ['nome' => 'mcp', 'url' => 'https://mcp.oimpresso.com'],
        ];
    })(),

    /*
     * Onda 1 — lag máximo tolerado (horas) entre o último commit que tocou git memory/
     * e o doc mais recente em mcp_memory_documents (McpIndexFreshnessChecker). Acima
     * disso = índice defasado (sync IndexarMemoryGitParaDb falhou calado?). Default 6h.
     */
    'mcp_index_freshness_max_lag_hours' => env('GOVERNANCE_MCP_INDEX_FRESHNESS_MAX_LAG_HOURS', 6),

    /*
     * Onda 1 — escalonamento por persistência (trait PersistsDriftAlert). Se o MESMO
     * drift segue ABERTO há mais de N dias, a severidade efetiva do alerta é elevada
     * 1 nível (warn→high / high→critical) + flag escalated=true no metadata, pra que
     * `governance:audit --notify` dispare alerta ATIVO em vez de só log diário. Default 3.
     */
    'drift_escalation_days' => env('GOVERNANCE_DRIFT_ESCALATION_DAYS', 3),

    /*
     * Allowlist multi-tenant: Models legítimos sem HasBusinessScope.
     * Convenção: FQCN completo. System-wide entities ou catálogos read-only.
     * @see Modules\Governance\Services\Checkers\MultiTenantScopeChecker
     */
    'multi_tenant_scope_allowlist' => [
        // System-wide
        'App\Models\User',
        'App\Models\Business',
        'App\Models\Module',
        // Catálogo read-only
        'App\Models\Country',
        'App\Models\State',
        'App\Models\City',
        // Adicionar conforme MultiTenantScopeChecker flagar legitimamente
    ],

    /*
     * Routes zombie allowlist: regex (com #) ou match literal exato.
     * @see Modules\Governance\Services\Checkers\RoutesZombieChecker
     */
    'routes_zombie_allowlist' => [
        '#^/healthz#',
        '#^/up#',
        '#^/api/webhooks/#',
        '#^/api/asaas#',
        '#^/api/sicoob#',
        '#^/api/mailgun#',
        '#^/_centrifugo#',
    ],

    /*
     * Centrifugo channel padrão pra drift alerts (ADR 0216 §Trait PublishesDrift).
     * Substitui `governance:secrets` (ADR 0215) — mas trait permite override por checker.
     */
    'drift_centrifugo_channel' => env('GOVERNANCE_DRIFT_CHANNEL', 'governance:drift'),

    /*
     * Kill-switch GT-G8 (default ON) — linha SDD determinística no Daily Brief.
     * `GOVERNANCE_SDD_BRIEF_LINE=false` no .env desliga o inject() sem deploy:
     * SddBriefLineService devolve o brief intacto (zero linha, zero query).
     * @see Modules\Governance\Services\SddBriefLineService
     */
    'sdd_brief_line' => env('GOVERNANCE_SDD_BRIEF_LINE', true),

    /*
     * Kill-switch ADR 0294 Onda 1 (default ON) — linha de saúde dos PLANOS no
     * Daily Brief (shell-out de scripts/governance/plan-health.mjs --json).
     * `GOVERNANCE_PLAN_HEALTH_BRIEF_LINE=false` no .env desliga o inject() sem
     * deploy: PlanHealthBriefLineService devolve o brief intacto (zero linha,
     * zero shell-out). Útil em host sem Node ou pra silenciar a linha.
     * @see Modules\Governance\Services\PlanHealthBriefLineService
     */
    'plan_health_brief_line' => env('GOVERNANCE_PLAN_HEALTH_BRIEF_LINE', true),

    /*
    |--------------------------------------------------------------------------
    | Linha de saúde do SHIPPED-LOG no Daily Brief (porta de saída, ADR 0294 ext)
    |--------------------------------------------------------------------------
    | Kill-switch do ShippedLogBriefLineService. Default ON.
    |
    | @see Modules\Governance\Services\ShippedLogBriefLineService
    */
    'shipped_log_brief_line' => true, // literal (não env): evita larastan noEnvCallsOutsideOfConfig; toggle via config()
];
