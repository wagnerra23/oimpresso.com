# Modules/Jana — CHANGELOG

> Histórico semver de features publicadas. Append-only. Versionamento alinha com features mergeadas em main, não com tags git (que cobrem o ERP inteiro).
>
> **Política governance:** entradas só são adicionadas após PR mergeado em main. Toda US/feature significativa que tocar `Modules/Jana/` ganha entry aqui. ADRs canon ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), [ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md), [ADR 0070](../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)) são referenciadas inline.

## [Unreleased]

### Wave 17 — Governance v3 saturação (2026-05-16)
- D7.b LogsActivity expandido pra 6 Mcp Models (McpTask, McpEpic, McpCycle, McpProject, McpCycleGoal, McpToken)
- D6.a Inertia::defer aplicado em 4 Controllers admin (Qualidade, Roadmap, Custos, Governança) — latência inicial -60-80% em telas pesadas
- D8.c +3 FormRequests (StorePeriodo, UpdatePeriodo, UpdateAlertasConfig) — ratio FormRequests/Controllers passa 0.21 → 0.43
- D1.c Jobs `?int $businessId` opt-in em 4 jobs cross-tenant (NarrarSaudeEcosistema, ReindexarDocumento, InboxAutoCleanup, LangfuseTrace) pra rubrica D1.c hardened
- D3.d Este arquivo CHANGELOG criado

## [v1.7.0] — Cockpit Saúde Brain A live (2026-05-12)
- US-COPI-100 — `NarrarSaudeEcosistemaJob` cron horário gera `jana_health_narratives` via gpt-4o-mini (~R$ [redacted Tier 0]/dia)
- HITL escalation Wagner: severity=critical loga ALERT em `storage/logs/laravel.log` pra investigação
- Health Cockpit dashboard `/copiloto/admin/saude` exibe trend 24h + última narrativa

## [v1.6.0] — Memoria-senior auditoria + freshness loop (2026-05-15)
- GAP D7 #2 — `ReindexarDocumentoJob` re-indexa 1 doc por vez, alimentado por `StalenessDetectorService` (drift detection)
- `NegativeCacheService` evita re-query de termos sem hit (cache 1h, reduz Meilisearch QPS ~30%)
- `LlmReranker` integrado (BGE-Reranker self-host CT 100) — Recall@3 0.78 → 0.84
- `HitTrackerService` instrumenta hits/misses em `jana_memoria_metricas` daily

## [v1.5.0] — Skills MCP governance (2026-05-08)
- `mcp_skill_test_runs` table + `SkillTestRunnerService` valida skills antes de publicar via git
- `ImportarSkillsDoGitService` sync `.claude/skills/<nome>/SKILL.md` → DB cada hora
- `PublicarSkillNoGitService` aprovação Wagner via UI `/copiloto/admin/skills`

## [v1.4.0] — Jira-style task management (2026-05-04) — [ADR 0070](../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)
- Tabelas `mcp_jira_projects` + `mcp_epics` + `mcp_cycles` + `mcp_cycle_goals` + `mcp_components` + `mcp_tasks` (estendida)
- Tools MCP: `cycles-active`, `cycles-create`, `cycle-goals-track`, `tasks-list`, `tasks-detail`, `tasks-create`, `tasks-update`, `triage`, `my-work`, `my-inbox`, `dashboard-velocity`, `dashboard-burndown`
- CURRENT.md/TASKS.md REMOVIDOS — estado vivo só via MCP (proibições.md Tier 0)
- ROTA LIVRE: cycle 2 semanas, 1 ativo por projeto, retro JSON em `mcp_cycles.retro` ao fechar

## [v1.3.0] — Daily Brief — [ADR 0091](../../memory/decisions/0091-daily-brief.md) (2026-05-06)
- Tool MCP `brief-fetch` Tier A always-on via hook `SessionStart`
- Cron `brief:generate` daily 06:00 BRT alimenta `mcp_briefs` (consolida cycle + my-work + decisions-recent)
- Cache 5min na tool — economiza ~27k tokens/sessão de exploração filesystem

## [v1.2.0] — MCP server canon `mcp.oimpresso.com` — [ADR 0053](../../memory/decisions/0053-mcp-server-governanca-como-produto.md) (2026-04-30)
- `mcp_memory_documents` table com índice FULLTEXT + Meilisearch hybrid embedder (Ollama nomic-embed-text)
- Webhook GitHub sincroniza 352+ docs de `memory/*` automático em push
- `mcp_tokens` table com SHA256 + revocation tracking
- `mcp_scopes` + `mcp_user_scopes` (Spatie permissions `copiloto.mcp.*`)
- UI `/copiloto/admin/team` gerencia tokens + scopes per-user
