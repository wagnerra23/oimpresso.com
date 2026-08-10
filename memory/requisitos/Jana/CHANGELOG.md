---
id: requisitos-jana-changelog
---

# Changelog — Jana

Formato inspirado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).

## [Unreleased] — `spec-ready`

### Decision — 2026-04-26 (proposta, aguarda aval)
- **Camada administrativa, governança e ROI** — proposta em 3 ondas (custo IA + visão admin + orçamento → audit + LGPD → insights + grupo econômico). Identifica gap entre MVP de chat (já em produção) e visibilidade/controle que destrava ROI pra dono de PME. ADR [`arq/0003`](adr/arq/0003-administracao-roi-governance.md).
  - Onda 1 (P0): dashboard de custo IA, controle de orçamento, visão admin das conversas com transparência (chat avisa quando admin lê).
  - Onda 2 (P1): audit log via spatie/activitylog, LGPD (export/delete/anonimização).
  - Onda 3 (P1-P2): insights agregados (top tópicos, taxa de aceite, heatmap), tags, visibilidade cross-business pra grupo econômico (depende de ADR 0020 implementado).
  - Estima 5 sprints total. Recomendações de usabilidade incluídas (10 itens, ex.: "transparência cria confiança", "wizard de orçamento no setup").

### Decision — 2026-04-24
- **Nome comercial + técnico = "Jana"** (escolhido em conversa Wagner ↔ Claude, 2026-04-24).
  - Alternativas consideradas e rejeitadas: `MetasNegocio` (descritivo, sem punch), `BI` (seguro mas genérico, compete com gigantes), `Farol` (forte PT-BR, 2ª opção), `Compass`/`Pulse`/`Norte` (nomes-marca abstratos).
  - Justificativa: conceito literal = copiloto (conversa, sugere, monitora junto); surfa trend de mercado (GitHub/MS Copilot já educaram percepção); escalável como guarda-chuva (v1 metas → v2 comercial → v3 operacional → v4 financeiro).

### Decision — 2026-04-24
- **Tenancy híbrida** — `business_id` nullable; `null` = meta da plataforma oimpresso (superadmin-only). ADR [`arq/0001`](adr/arq/0001-tenancy-hibrida.md).

### Decision — 2026-04-24
- **Chat conversacional é o entry-point principal**, não dashboard. Dashboard é consequência. ADR [`ui/0001`](adr/ui/0001-chat-inline-no-dashboard.md).

### Decision — 2026-04-24
- **Dependência IA é soft** via adapter — LaravelAI preferido, fallback openai-php direto. ADR [`tech/0002`](adr/tech/0002-adapter-ia-laravelai-ou-openai.md).

### Added — 2026-04-24 (documentação, sem código)
- `README.md` — frontmatter + pitch comercial + índice.
- `ARCHITECTURE.md` — 7 áreas funcionais, 7 entidades, 4 camadas, integrações.
- `SPEC.md` — 4 personas, 18+ user stories (US-COPI-NNN), regras Gherkin.
- `GLOSSARY.md` — vocabulário canônico + termos a evitar.
- `RUNBOOK.md` — operação, seed, debug, problemas comuns.
- 5 ADRs: `arq/{0001,0002}`, `tech/{0001,0002}`, `ui/0001`.

### Related
- Nasce a partir do ADR [`decisions/0022-meta-5mi-ano-financeira.md`](../../decisions/0022-meta-5mi-ano-financeira.md).
- Seed inicial virá de [`memory/11-metas-negocio.md`](../../11-metas-negocio.md).

---

**Última atualização:** 2026-04-24

---

## Implementação (histórico movido de `Modules/Jana/CHANGELOG.md`)

> Movido em 2026-08-10. Os dois changelogs registravam eventos DIFERENTES — acima as
> decisões/requisitos, aqui o que foi de fato mergeado. Medido antes de fundir:
> sobreposição de datas entre os dois era 0-2 de 2-7, logo nenhum era cópia do outro
> e escolher um lado perderia registro. Conteúdo preservado na íntegra.

# Modules/Jana — CHANGELOG

> Histórico semver de features publicadas. Append-only. Versionamento alinha com features mergeadas em main, não com tags git (que cobrem o ERP inteiro).
>
> **Política governance:** entradas só são adicionadas após PR mergeado em main. Toda US/feature significativa que tocar `Modules/Jana/` ganha entry aqui. ADRs canon ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), [ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md), [ADR 0070](../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)) são referenciadas inline.

## [Unreleased]

### Wave 25 — Governance v3 SATURATION MÁXIMA (2026-05-16)
- D1.c hardening: marker canônico `REPO-WIDE: ADR 0070 jira-style cross-tenant intencional` adicionado em 8 Entities Mcp (McpScope, McpComponent, McpTaskComment, McpTaskDependency, McpTaskEvent, McpTaskWatcher, McpCcBlob, McpInboxNotification) — distingue "esqueceu trait" de "governance OK" na rubrica D1.c v3.2 hardened
- D1.c novo test `MultiTenantIsolationComprehensiveTest::D1.c declara marker canônico` (Pest reflection PHPDoc) — exige marker explícito em TODAS 18 entities cross-tenant by design
- D9.a OpenAiDirectDriver wrapado com `OtelHelper::span` (business_id explícito do ContextoNegocio — queue worker CT 100 sem session HTTP) — Services com OTel passa 45 → 46
- D2.b hallucination golden expandido 22 → 30 questions (cobertura +ADR 0053 MCP + jobs Tier 0 + Brain A custo + block-automem + OtelHelper + workflow MEXEU REGISTRA + PiiRedactor + Vaultwarden)
- D3.d CHANGELOG + BRIEFING update entry Wave 25 — score 95 → 96 estimado

### Wave 18 — Governance v3 SATURATION FULL (2026-05-16)
- D1.a/b cobertura comprehensive: `MultiTenantIsolationComprehensiveTest.php` valida 40 Entities (17 scoped + 10 via parent + 13 cross-tenant by design) — Jana ≥95 contrato Tier 0 ADR 0093
- D2 FSM N/A explícito: `FsmCanonicalTest.php` + `module.json` `governance.fsm_n_a: true` — Jana é chat IA, não pipeline transacional (elimina falso-positivo rubrica)
- D3.b BRIEFING.md canônico criado (template canon `memory/requisitos/_DesignSystem/BRIEFING-TEMPLATE.md`)
- D4.d AuditLog: `JanaAuditService` Wave 17 já tem OTel span explícito + ActivityLog + log structured (3 sinks)
- D7 retention.php validado (Wave 17 canon) — LGPD Art. 16 declaração por entidade
- D8.c +2 FormRequests: SendChatMessageRequest + UpdateAlertasConfigRequest validados em test
- D9.a OTel batch +30 Services Jana wrapados com `OtelHelper::spanBiz` (44 total → ~43 instrumentados)
- `module.json` governance.d1_overrides + d4_audit + wave_18 metadata canônico

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
