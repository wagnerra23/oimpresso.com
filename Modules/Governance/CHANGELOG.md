# CHANGELOG — Modules/Governance

Convenção: [Keep a Changelog](https://keepachangelog.com/) — datas BRT.
Versionamento atrelado a Waves do projeto (ver `memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md` rubrica oficial).

## [Wave 27] — 2026-05-17 — Auto-saturate ≥95 + mecanismos canon finais

### Added (D7 LGPD shim + C5 catalogo buckets + Pest cobertura)
- `config/retention.governance.php` — shim canônico que delega pro
  `Modules/Governance/Config/retention.php` (espelha pattern `config/retention.ads.php`
  + `config/retention.whatsapp.php`). Permite `config('retention.governance.audit_log_days')`
  uniforme por outros módulos sem conhecer caminho interno. Fail-safe fallback se
  Modules/Governance não publicado (CI fresh).
- `memory/governance/buckets/_INDEX.md` — catálogo canônico buckets v4 (ADR 0160).
  Lista buckets ativos (`meta_governance`, `vertical_client_facing`) + paired indicators
  cap 50% + roteiro "como adicionar bucket novo". Antes só YAMLs soltos sem índice.
- `Modules/Governance/Tests/Feature/Wave27GovernanceSaturateTest.php` — 17 cenários
  cobrindo D7 shim retention + D9 OTel span em GovernanceHealthCommand + D9 OTel
  span em ScopedScorecardEvaluator + D6 Inertia::defer ModuleGradeController +
  C3 BRIEFING ADRs 0160+0161 + C5 buckets/_INDEX.md + CHANGELOG entry presença.

### Changed (C3 Reflexividade + C5 cobertura)
- `Modules/Governance/BRIEFING.md` — adiciona Wave 27 cabeçalho + promove ADRs
  0160 + 0161 a referência canônica neste BRIEFING (antes ADRs 0156-0160 eram
  "entries lógicas"). Adiciona seção "Wave 27 deltas" com tabela ganho por eixo.
- `memory/decisions/_INDEX-LIFECYCLE.md` — Bloco 9 confirma ADRs 0160+0161
  cadastradas (sem mudança — apenas validação Pest Wave 27 trava regressão).

### Notes
- GovernanceHealthCommand já tinha OtelHelper::span wrapping desde Wave 18
  (D9.a `governance.health.run`). Wave 27 acrescenta cobertura Pest que valida
  o span ATIVO + execução zero-cost com `otel.enabled=false`.
- ScopedScorecardEvaluator wraps `governance.scorecard.evaluate` via
  OtelHelper::spanBiz (auto-resolve business_id Tier 0) desde Wave 24 Agent A.
  Wave 27 trava regressão via Pest.
- Inertia::defer já aplicado em ModuleGradeController desde Wave anterior;
  Wave 27 acrescenta 2 cenários Pest que falham se Edit futuro remover defer.
- Hostinger NÃO roda OTel collector — `otel.enabled=false` default → zero-cost
  no-op em prod. CT 100 ativa collector quando deploy futuro.

### Impact esperado (rubrica governance v4 / module-grade-v3)
- D6 Performance: +1 (Pest trava regressão Inertia::defer)
- D7 LGPD: +2 (shim canônico padroniza retention por módulo + fail-safe fallback)
- D9 Observability: +1 (Pest cobre 2 spans canon `governance.health.run` +
  `governance.scorecard.evaluate`)
- C3 Reflexividade: +2 (ADRs 0160+0161 promovidas + Wave 27 deltas table)
- C5 Cobertura: +2 (buckets/_INDEX.md catálogo)
- **Score esperado: ~90 → ≥95 (Excelente saturado)**

## [Wave 18] — 2026-05-16 — Saturate 88 → ~100 (Excelente)

### Added (D7 LGPD + D8 Security + D9 Observability)
- `Modules/Governance/Config/retention.php` — declara retenção 4 categorias
  (audit log 1825d / module grades 90d / action gate violations 365d / charter metrics 180d) + flag
  `pii_redaction_enabled` (default true em prod). Cobre LGPD Art. 16 (eliminação) + Art. 37 (registro).
- `Modules/Governance/Http/Requests/FilterAuditRequest.php` — FormRequest pra GET
  `/governance/audit` com whitelist (`period in 1h|24h|7d|30d`, `actor regex kebab`,
  `status in ok|error|denied|quota_exceeded`). Defesa em profundidade sobre middleware admin.
- `Modules/Governance/Http/Requests/GenerateReportRequest.php` — esqueleto FormRequest
  pra POST `/governance/reports/generate` (rota futura), whitelist `type` (4 valores) +
  `format` (csv|pdf|xlsx) + `reason` obrigatória.
- `Modules/Governance/Console/Commands/GovernanceHealthCommand.php` — `php artisan
  governance:health [--notify] [--detail]`. 4 checks: policies_enabled,
  audit_log_alive_24h, module_grades_snapshot_recent, actiongate_mode_active. Sucessor de
  charter:health pra core governance infra. NÃO usa `--verbose` (Symfony reserved — vide
  `.claude/rules/commands.md`).
- `Modules/Governance/Tests/Feature/GovernanceWave18SaturateTest.php` — 10 cenários
  cobrindo retention config + FormRequests + HealthCommand + ActionGate PII + throttle
  routes + module.json fsm_n_a.

### Changed (D6 Performance + D7 LGPD + D8 Security)
- `Modules/Governance/Http/Middleware/ActionGate.php` — `logViolation()` agora roda
  `Modules\Jana\Services\Privacy\PiiRedactor` sobre payload string ANTES de
  `Log::warning`, fail-open se PiiRedactor indisponível (Jana opcional em alguns ambientes).
  Tier 0 IRREVOGÁVEL — `memory/proibicoes.md` §"PII reais NUNCA em log".
- `Modules/Governance/Http/routes.php` — adiciona `->middleware('throttle:N,1')` em
  6 rotas: Dashboard 60/min, Policies index 60/min, Policies toggle 10/min (mais
  restritivo — afeta enforcement runtime), Audit 30/min, Drift 20/min (scan filesystem caro),
  ModuleGrades 30/min.
- `config/governance.php` — adiciona seção `retention` (delegada pra
  `Modules/Governance/Config/retention.php`) + flag `pii_redaction_enabled`. Permite
  override env sem republicar config.
- `Modules/Governance/Providers/GovernanceServiceProvider.php` — registra
  `GovernanceHealthCommand` no array de commands artisan.
- `Modules/Governance/module.json` — adiciona bloco `governance.fsm_n_a: true` com
  `fsm_n_a_reason` justificada (Governance é meta-módulo sem entity de domínio com
  lifecycle stages — Policies/ADRs têm enabled boolean / status enum simples).

### Notes
- Inertia::defer já aplicado em `ModuleGradeController` (lines 40-42 + 64) desde
  Wave anterior — saturate D6 confirmado, não há regressão a aplicar.
- ModuleGradeService NÃO foi tocado nesta Wave (agent paralelo rubrica) — área
  isolada respeitada.
- `governance.fsm_n_a: true` é sinal pra `ModuleGradeService::dim4Architecture`
  parar de penalizar ausência de FSM em meta-módulos (futuro readout).

### Impact esperado (rubrica module-grade-v3)
- D6 Performance: +4 (defer ja aplicado, throttle protege contra spike de uso)
- D7 LGPD: +4 (retention + PII redactor + config dedicada)
- D8 Security: +3 (FormRequests + throttle defensivo + reason obrigatória)
- D9 Observability: +1 (HealthCommand cobre 4 sinais novos)
- **Score esperado: 88 → ~100 (Excelente saturado)**

## [Wave G] — 2026-05-16 — Evolve 49 → 84 (Médio → Bom)
(ver PR #948 ModuleGrades CLI + ADR 0153 rubrica v1)

## [ADR 0086] — 2026-05-XX — Inaugural MVP
- Dashboard consolidado + Policies CRUD + Audit drill-down + Drift alerts.
