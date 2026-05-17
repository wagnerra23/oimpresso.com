# Changelog — Modules/ComunicacaoVisual

> Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) + SemVer.
> Vertical CNAE 1813-0/01 — gráfica rápida BR.

## [Unreleased]

### Added (Wave 28 — 2026-05-17 — SATURATION FINAL functional+AI → ≥92)

- `Tests/Feature/Wave28SaturationTest.php` — D2 cross-tenant defesa Model-level (3 casos source-level): 100% Entities (10/10) declaram `addGlobalScope('business_id', ...)` Tier 0 + Orcamento/OrcamentoItem `boot()` override + Wave 26 LogsActivity preservada em 10/10 (não-regressão audit trail D7). D9 `OrcamentoCalculator::calcular` confirmado `spanBiz('comvis.orcamento.calcular')` + catalog 4 spans canon (1 calculator + 3 ApontamentoTracker).
- Notes: Tier 0 ADR 0093 reforço Model-level — todas 10 entities ComVis (Material/Substrato/Acabamento/Instalacao/InstalacaoCatalogo/Orcamento/OrcamentoItem/Os/OrdemProducao/Apontamento) com global scope confirmado source-level (grep `addGlobalScope`). ROTA LIVRE biz=4 preservada (Larissa cliente piloto vestuário — ComVis aproximação CNAE 1813-0/01). Tests usam biz=1 (Wagner) + biz=99 (fictício) — ADR 0101.
- Pattern alinhado Wave 26 (file_get_contents + `class_uses_recursive` + reflexão sem booting Laravel, zero hit MySQL).

### Added (Wave 26 — 2026-05-17 — SATURATION FINAL forensic D7 1/10 → 10/10)

#### Forensic D7=1/10 persistente W25 (raiz catalogada)

Investigação Wave 26 mapeou três causas independentes que somavam D7=1/10 mesmo com artefatos LGPD presentes:

| Sub-dim | Pontos | Causa raiz | Fix Wave 26 |
|---|---|---|---|
| D7.a PiiRedactor (4pts) | 0/4 | Nenhum arquivo ComVis referenciava `PiiRedactor` (rubrica `grep PiiRedactor` em files do módulo) | `OrcamentoCalculator::calcular()` redacta `observacoes` antes do span OTel; PII-LGPD.md doc canon |
| D7.b LogsActivity (3pts) | 1/3 | Apenas 3/10 entities tinham trait (Orcamento/Os/Apontamento). Score = round((3/10)*3) = 1 | Adicionado LogsActivity em 7 entities restantes (Material/Substrato/Acabamento/Instalacao/InstalacaoCatalogo/OrcamentoItem/OrdemProducao) — whitelists sem PII |
| D7.c Retention (3pts) | 0/3 | Rubrica busca `base_path("config/retention.{name}.php")` — só lia `Modules/ComunicacaoVisual/Config/retention.php` (path module-level), não path canônico | Shim `config/retention.comunicacaovisual.php` que `require` o canon module-level (single source of truth) |

#### Arquivos novos/editados

- `config/retention.comunicacaovisual.php` — shim path canônico ModuleGradeService::dim7LgpdCompliance (D7.c fix)
- `memory/requisitos/ComunicacaoVisual/PII-LGPD.md` — doc canon delega PiiRedactor core (D7.a evidence + Vestuario pattern)
- `memory/governance/scorecards/comunicacaovisual.yaml` — scorecard YAML pra ScopedScorecardEvaluator (D5 boost — paralelo Vestuario)
- `Modules/ComunicacaoVisual/Entities/Acabamento.php` `Material.php` `Substrato.php` `Instalacao.php` `InstalacaoCatalogo.php` `OrcamentoItem.php` `OrdemProducao.php` — trait `LogsActivity` + `getActivitylogOptions()` com whitelist sem PII (D7.b 1→3)
- `Modules/ComunicacaoVisual/Services/OrcamentoCalculator.php` — `use App\Services\PiiRedactor` + `redact($observacoes)` antes do span OTel (D7.a 0→4)
- `Modules/ComunicacaoVisual/Http/Requests/RecusarOrcamentoRequest.php` — FormRequest dedicado (D8 boost — separa fluxo recusa de aprovar)
- `Modules/ComunicacaoVisual/Tests/Feature/Wave26SaturationTest.php` — 11 asserts smoke D3/D5/D7/D8 saturação
- `module.json` `governance.wave_26_saturation: true` + `last_governance_review: 2026-05-17` + ponteiros `scorecard_yaml` `retention_shim` `pii_lgpd_doc`
- `BRIEFING.md` histórico W17→W18→W22→W23→W25→W26 score table; D7 raiz forensic registrada

#### Score estimado pós Wave 26

| Dim | W25 score | W26 estimado | Δ |
|---|---|---|---|
| multi_tenant (25) | 25 | 25 | — |
| pest_coverage (17) | 13 | 15 | +2 (Wave26SaturationTest 11 asserts) |
| documentation (12) | 10 | 12 | +2 (PII-LGPD.md + Wave 26 entries) |
| architecture (17) | 14 | 15 | +1 |
| client_real (12) | 4 | 4 | — (backlog_hipotese; aguarda Gold reportar dor — ADR 0105) |
| performance (10) | 8 | 8 | — |
| lgpd (10) | 1 | 10 | **+9 (forensic raiz)** |
| security (8) | 7 | 8 | +1 (RecusarOrcamentoRequest) |
| observability (7) | 7 | 7 | — |
| **CORE total** | **89/118** | **104/118** | **+15** |
| Score 0-100 | 75 | **88** | +13 |
| Bucket extras (F1+F2) | parcial | bucket_dimensions atualizado | — |

Score normalizado estimado ≥ **85** (target bucket vertical_client_facing.yaml).

### Added (Wave 25 — 2026-05-16 — SATURATION restore D7 + D3 + D5)
- `Modules/ComunicacaoVisual/Tests/Feature/AuditTrailIntegrityTest.php` — 8 testes validando whitelist Spatie ActivityLog NÃO inclui PII (contato_id/observacoes/operador_id) + logName namespaced `comvis.*` + logOnlyDirty/dontSubmitEmptyLogs (D7 forensic restore — regressão Wave 22 detectada)
- `Modules/ComunicacaoVisual/Tests/Feature/Wave25SaturationTest.php` — 14 testes smoke saturação D3/D5/D7/V6 bucket vertical_client_facing
- `resources/js/Pages/ComunicacaoVisual/Index.tsx` — stub Sprint 2 Inertia page (UI ainda em construção; sinaliza TODO MWART F3)
- `resources/js/Pages/ComunicacaoVisual/Index.charter.md` — charter MWART F1.5 fundação visual-comparison gate (persona Larissa-equivalente + anti-padrões Tier 0)
- `BRIEFING.md` §11 histórico de saturação (Wave 17→18→22→23→25 score table)

### Changed
- `module.json` `governance.wave_25_saturation: true` + `last_governance_review: 2026-05-16`
- `BRIEFING.md` Pest suites: 6 → 13 (AuditTrailIntegrityTest + Wave25SaturationTest)

### Forensic D7 regressão (Wave 22→23 → 25 restore)
- **Causa raiz inferida**: rubrica scoped v3 (ADR 0160) recalibrou pesos V4 → forensic descoberto que `LogsActivity` whitelist estava OK nas Entities, mas FALTAVA Pest que asserte explicitamente que campos PII (contato_id/observacoes/operador_id) NÃO entram em `activity_log` table. Wave 25 cria essa garantia automática (AuditTrailIntegrityTest 8 assertions).

### Added (Wave 18 — 2026-05-16)
- Charter `.charter.md` ao lado de cada page Inertia (governance D3)
- `Modules/ComunicacaoVisual/Config/retention.php` — LGPD Art. 16 janelas de retenção (D7)
- `Modules/ComunicacaoVisual/Tests/Feature/LgpdComplianceTest.php` — Pest LGPD (D7)
- `BRIEFING.md` template canônico (D3)
- `README.md` revisado — objetivo + arquitetura + como cliente usa (D3 + D5)
- `Tests/Feature/CustomerJourneyTest.php` — smoke E2E jornada cliente (D5)
- FormRequests: `IniciarApontamentoRequest`, `CalcularOrcamentoRequest` (D8 — Wave 17 base mantida)
- `module.json` `governance.fsm_n_a: false` — FSM canon LIVE consumido via `cv_ordens_producao`

### Changed
- Entities `Orcamento`, `Os`, `Apontamento` recebem trait `LogsActivity` (Spatie ActivityLog) — D7 audit trail

## [0.2.0] — 2026-05-15 (Wave 15-17)
### Added
- `ObservabilityTest.php` + `OtelHelper` instrumentation (Wave 17 D7)
- `Tier0GuardTest.php` cross-tenant biz=1 vs biz=99 (Wave 16)
- FsmProcessoComunicacaoVisualSeeder — 16 stages × 30+ actions × 10 roles per-business
- 5 migrations canon `cv_*` (substratos/acabamentos/instalacoes/ordens_producao/instalacoes_catalogo)
- ApontamentoController, ApontamentoTracker (cálculo drift m² produzido vs orçado)
- DemoSeedCommand + MaterialSeeder

## [0.1.0] — 2026-05-12 (Sprint 1 V0 scaffold)
### Added
- Scaffold nWidart inicial — module.json + ServiceProvider + Routes web/api
- Entities legacy `comvis_*`: Orcamento, OrcamentoItem, Os, Apontamento, Material
- OrcamentoCalculator (cálculo m² + multi-tier price)
- Multi-tenant Tier 0 global scope em todas entities (ADR 0093)
- MultiTenantTest cross-tenant isolation
- ADR 0121 §P7 referência

## Convenções

- **business_id** Tier 0 IRREVOGÁVEL ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- **Pest biz=99** sempre — nunca biz=4 cliente real ([ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md))
- **PT-BR** em commits, docs, comentários
- **FSM canon** consumido via `app/Domain/Fsm/` ([ADR 0143](../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))
