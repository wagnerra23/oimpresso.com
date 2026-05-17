# CHANGELOG — Modules/Brief

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versionamento alinhado a Wave governance ([ModuleGradeService](../Governance/Services/ModuleGradeService.php) D3.d).

## [Wave 28] - 2026-05-17

### Test (D2 — Pest +2 sentry BriefFallback ADR 0091)
- `Tests/Feature/Wave28PolishTest.php` — +2 testes sentry Wave 28:
  - `BriefGeneratorService` preserva API canônica `generateNow` +
    `generateFromAggregated` (regression guard se alguém renomear/remover).
  - `BriefGeneratorService` preserva wrap `OtelHelper::spanBiz('brief.generate_now')`
    + constantes Brain B canônicas (gpt-4o-mini + sentinela `---END---`).
- Tier 0 IRREVOGÁVEL ADR 0091: brief repo-wide (sem business_id no fallback),
  Brain B OpenAI gpt-4o-mini canônico. Mock mode reflection-only (zero custo LLM).

### Governance
- Saturação 76-95 → 96 (polish final excelência).

## [Não publicado]

### Wave 27 POLISH (2026-05-17)

- **ADD D8** `Http/Requests/ExportBriefMarkdownRequest.php` — endpoint admin
  `GET /brief/admin/{id}/export.md` (Wagner cola brief individual em handoff
  ou relatório). Defaults seguros: `redact_pii=true` + `include_metadata=true`.
  `wrap_columns` whitelist (`0,40,60,80,100,120,160,200`) — defesa anti-injection.
  Permission `brief.history.view` (Wagner/superadmin RBAC).
- **ADD D8** `Http/Requests/CompareBriefRequest.php` — endpoint admin
  `GET /brief/admin/compare?a=X&b=Y` (side-by-side debug regressão Brain B).
  Anti-erro operador: `a` e `b` exigem `different` cross-reference.
  `diff_mode` whitelist `full|sections|headers` pra granularidade do diff.
- **ADD D2** `Tests/Feature/BriefFallbackTest.php` — +9 testes Wave 27:
  fallback edge cases (429 rate-limit, 200 com body malformado, defesa PII
  em serveCachedWithStaleFlag), ExportBriefMarkdownRequest (whitelist
  wrap_columns + coerce booleans + defaults), CompareBriefRequest (different
  a==b + diff_mode whitelist + messages PT-BR + optionsOrDefaults).
- **NOOP D6** — Brief permanece módulo MCP/CLI puro sem Inertia pages.

### Wave 25 POLISH (2026-05-16)

- **ADD D8** `Http/Requests/FetchBriefHistoryRequest.php` — paginação read-only
  `mcp_briefs` (Wagner debug regressão Brain B). Cap `per_page` max 100
  (anti-DoS). Filtros temporais `from`/`to`, `valid` whitelist, `min/max_tokens`.
- **ADD D8** `Http/Requests/MarkBriefValidRequest.php` — endpoint simétrico ao
  `InvalidateBriefRequest`. Restaura `valid=1` quando review humana confirma
  falso positivo. Permission `brief.purge` (mesma da invalidate — operação
  simétrica). Audit log evento `brief.marked_valid`.
- **ADD D2** `Tests/Feature/BriefFallbackTest.php` — +8 testes Wave 25:
  fallback fail-fast com key vazia, 504 Gateway Timeout, staleness minutes
  precision, FetchBriefHistoryRequest (cap/filtros/whitelist/defaults),
  MarkBriefValidRequest (motivo/messages/coerce).
- **NOOP D6** — Brief permanece módulo MCP/CLI puro sem Inertia pages.

### Wave 18 governance (2026-05-16)

- **ADD D3.d** `CHANGELOG.md` (este arquivo) — histórico per-wave.
- **ADD D3.a** `BRIEFING.md` — estado consolidado L7 (1 página executiva) por
  PR mexido (regra Tier 0 2026-05-15 — `memory/proibicoes.md`).
- **ADD D2** `Tests/Feature/BriefValidatorTest.php` — 10 testes Pest cobrindo
  os 4 invariantes ADR 0091 (headers ordem, sentinela `---END---`, token cap
  3500, PII CPF/CNPJ) + smoke das constantes `REQUIRED_HEADERS` e `MAX_TOKENS`
  + ValidationResult imutável.
- **ADD D2** `Tests/Feature/GenerateBriefRequestTest.php` — 5 testes Pest do
  novo FormRequest (nullable defaults, motivo>255, dry_run bool coerce,
  bypass_cap bool coerce).
- **ADD D8.c** `Http/Requests/GenerateBriefRequest.php` — FormRequest dedicado
  para futuro endpoint `POST /brief/admin/generate` (separação clara das 3
  responsabilidades: tool MCP / refresh cache / generate on-demand).
- **UPDATE `module.json`** — `governance.fsm_n_a: true` (Brief é gerador L7
  stateless — ciclo linear, sem máquina de estados de entidade de negócio).
- **NOOP D6** — Brief não renderiza Inertia pages (módulo MCP/CLI puro). D6
  defer é N/A; score pré-Wave 18 mantido (10/10) por ausência de overhead.
- **NOOP D9** — `OtelHelper::spanBiz` já instrumentado nas 3 superfícies
  (BriefFetchController, BriefValidator, ValidationResult) desde Wave 17.

### Wave 17 governance (2026-05-13)

- **FIX D9** `OtelHelper::spanBiz` em `ValidationResult::ok/fail` para rastrear
  taxa de fail/ok do brief gerado pelo Brain B (signal de regressão LLM).
- **ADD D9.c** `brief:health` command — 4 sinais (cache_table_present,
  brief_table_present, recent_valid_brief <24h, failure_rate_24h <30%).

### Sprint 1 inicial (2026-04-26 — ADR 0091)

- Módulo Brief scaffold completo (ADR 0024 8 peças obrigatórias).
- `BriefGeneratorService` + `BriefValidator` + `ValidationResult`.
- `BriefFetchController` (tool MCP) + `BriefFetchToolRequest` + `Routes/api.php`.
- `GenerateBriefCommand` agendado cron 6x/dia (7/11/14/17/20/23h BRT).
- `mcp_briefs` table + `mcp_brief_inputs_cache` + procedure `refresh_brief_inputs_cache()`.
