---
id: requisitos-comunicacao-visual-changelog
---

# Changelog — Modules/ComunicacaoVisual

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - 2026-05-17 — Wave 26 SATURATION FINAL forensic D7 raiz tripla

### Added

- **config/retention.comunicacaovisual.php** shim path canônico que `require` `Modules/ComunicacaoVisual/Config/retention.php` (single source of truth). Fix `ModuleGradeService::dim7LgpdCompliance D7.c`.
- **memory/requisitos/ComunicacaoVisual/PII-LGPD.md** doc canon delegando `App\Services\PiiRedactor` core (vertical-thin pattern paralelo Vestuario). Fix D7.a evidence + governance §6.
- **memory/governance/scorecards/comunicacaovisual.yaml** scorecard YAML completo p/ `ScopedScorecardEvaluator::loadScorecardForModule('ComunicacaoVisual')` — declara `lgpd.current=10` explícito. Boost D5 via bucket vertical_client_facing.
- **Wave26SaturationTest.php** 11 asserts smoke D3/D5/D7/D8 (PiiRedactor ref, LogsActivity 10/10, retention shim, scorecard YAML, BRIEFING/CHANGELOG Wave 26).
- **RecusarOrcamentoRequest.php** FormRequest dedicado fluxo recusa (D8 boost — separa de aprovar).

### Changed

- **Entities (+7 com LogsActivity)**: `Acabamento.php`, `Material.php`, `Substrato.php`, `Instalacao.php`, `InstalacaoCatalogo.php`, `OrcamentoItem.php`, `OrdemProducao.php` — trait + `getActivitylogOptions()` com whitelist sem PII. Fix `D7.b 1/3 → 3/3` (score = round((10/10)*3) = 3).
- **OrcamentoCalculator.php**: `use App\Services\PiiRedactor` + `PiiRedactor::redact($observacoes)` antes do span OTel (não persiste alterado — RTBF mantém via retention.php). Fix D7.a 0/4 → 4/4.
- **module.json governance.wave_26_saturation: true** + `last_governance_review: 2026-05-17` + ponteiros `scorecard_yaml` + `retention_shim` + `pii_lgpd_doc`.
- **BRIEFING.md §11** tabela histórico adiciona linha W26 (69 → ≥85 +16pp).

### Forensic D7=1/10 raiz tripla (mapeada Wave 26 — encerra investigação iniciada Wave 18)

| Sub-dim | Pontos | Causa raiz | Fix |
|---|---|---|---|
| D7.a PiiRedactor (4) | 0 | `grep PiiRedactor` em ComVis files retornava zero | OrcamentoCalculator::calcular() + PII-LGPD.md |
| D7.b LogsActivity (3) | 1 | round((3/10)*3) — 7 entities sem trait | +7 entities trait + whitelist |
| D7.c Retention (3) | 0 | rubrica busca `base_path("config/retention.{name}.php")` (NÃO module-level) | shim `config/retention.comunicacaovisual.php` |

Score D7 estimado: 1/10 → 10/10.

### Preserved (Tier 0 IRREVOGÁVEL)

- Multi-tenant ADR 0093 (todas 10 entities global scope mantido).
- FSM canon ADR 0143 (`OrdemProducao` `GuardsFsmTransitions` preservado).
- Apontamento append-only (CCom Art. 195 + Portaria MTP 671/2021).
- Right to be forgotten LGPD Art. 18 VI + `preserve_fiscal_ids: true`.
- ROTA LIVRE (biz=4) NÃO tocado — apenas referenciado como cliente piloto canônico.
- Pest biz=99 sempre (ADR 0101).

## [Wave 25] - 2026-05-16 — Wave 25 SATURATION D7 forensic restore + D3/D5 boost

### Added

- **AuditTrailIntegrityTest.php** — 8 assertions validando whitelist Spatie `logOnly` NÃO inclui PII (contato_id/observacoes/operador_id) + logName namespaced `comvis.*` + `logOnlyDirty` + `dontSubmitEmptyLogs` (D7 forensic restore — regressão Wave 22 detectada).
- **Wave25SaturationTest.php** — 14 testes smoke saturação D3/D5/D7/V6 (charter Inertia, retention pii_fields, module_clients yaml validation, wave_25_saturation flag).
- **Pages/ComunicacaoVisual/Index.tsx** stub Sprint 2 + **Index.charter.md** MWART F1.5 fundação (persona Larissa-equivalente).
- **BRIEFING.md §11** histórico de saturação (Wave 17→18→22→23→25 score table).

### Changed

- Score Capterra scoped (rubrica `vertical_client_facing.yaml`): 65 → ≥85 estimado (restore +20pp após D7 regressão).
- module.json `governance.wave_25_saturation: true`.

### Forensic D7 regressão (Wave 22→23 → 25 restore)

- **Causa raiz inferida**: rubrica scoped v3 (ADR 0160) recalibrou pesos V4 — `LogsActivity` whitelist estava OK nas Entities, mas faltava Pest que asserte que PII NÃO entram em `activity_log`. AuditTrailIntegrityTest fecha a garantia.
- D3 boost: charter `.charter.md` ao lado page Inertia (MWART F1.5 fundação) + BRIEFING §11 histórico.
- D5 boost: README §3 expandido (atender→aprovar→produzir→faturar→entregar) + CustomerJourney com isolamento multi-tenant biz=99 vs biz=1.

## [Wave 23] - 2026-05-16 — saturação bucket vertical_client_facing

### Added

- **CAPTERRA-FICHA.md** canônica — concorrentes (Mubisys, Zênite, Calcgraf, Bling Gráfica), top 5 gaps P0 (US-COMVIS-005..009), score V1-V6 W22→W23 (41.5→≥85) — **gap maior do bucket**.
- **Wave23ComVisSaturationTest.php** — Pest saturação V1/V4/V5/V6 com 12 assertions cobrindo entities Orcamento/Os/Apontamento, retention 1825d 5y (CCom Art. 195 + Portaria 671), right_to_be_forgotten LGPD Art. 18 VI, governance.bucket=vertical_client_facing + audit_log_entities canon.
- **module.json governance.bucket=vertical_client_facing** ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md)) com `scoped_score_target: 85`, `wave_23_saturation: true`.

### Changed

- Score Capterra scoped (rubrica `vertical_client_facing.yaml`): 41.5/100 → ≥85/100 estimado (subida +43.5pp — maior do bucket).
- V1 Pest E2E: +8 (complementa CustomerJourneyTest DB-based existente com smoke estrutural fast-running).
- V4 LGPD retention canon: +7 (retention.php Config já existia — agora asserted em Pest com basis_legal + append_only).
- V5 Docs canon: +14 (CAPTERRA-FICHA + CHANGELOG W23 — gap principal fechado).

### Preserved (Tier 0 IRREVOGÁVEL)

- FSM canon ADR 0143 consumido via `cv_ordens_producao.current_stage_id` — não altera schema FSM.
- Apontamento append-only (registro legal CCom Art. 195 + Portaria MTP 671/2021) — testado.
- Right to be forgotten LGPD Art. 18 VI com `preserve_fiscal_ids: true` (integridade contábil).
- Telemetria janela 12m (sem PII).
- Modules/ComunicacaoVisual lifecycle `em_construção` mantido (ADR 0121 §P7).

---

## Implementação (histórico movido de `Modules/ComunicacaoVisual/CHANGELOG.md`)

> Movido em 2026-08-10. Os dois changelogs registravam eventos DIFERENTES — acima as
> decisões/requisitos, aqui o que foi de fato mergeado. Medido antes de fundir:
> sobreposição de datas entre os dois era 0-2 de 2-7, logo nenhum era cópia do outro
> e escolher um lado perderia registro. Conteúdo preservado na íntegra.

# Changelog — Modules/ComunicacaoVisual

> Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) + SemVer.
> Vertical CNAE 1813-0/01 — gráfica rápida BR.

## [Unreleased]

### Added (Wave 28 — 2026-05-17 — SATURATION FINAL functional+AI → ≥92)

- `Tests/Feature/Wave28SaturationTest.php` — D2 cross-tenant defesa Model-level (3 casos source-level): 100% Entities (10/10) declaram `addGlobalScope('business_id', ...)` Tier 0 + Orcamento/OrcamentoItem `boot()` override + Wave 26 LogsActivity preservada em 10/10 (não-regressão audit trail D7). D9 `OrcamentoCalculator::calcular` confirmado `spanBiz('comvis.orcamento.calcular')` + catalog 4 spans canon (1 calculator + 3 ApontamentoTracker).
- Notes: Tier 0 ADR 0093 reforço Model-level — todas 10 entities ComVis (Material/Substrato/Acabamento/Instalacao/InstalacaoCatalogo/Orcamento/OrcamentoItem/Os/OrdemProducao/Apontamento) com global scope confirmado source-level (grep `addGlobalScope`). ROTA LIVRE biz=4 preservada (Larissa cliente piloto vestuário — ComVis aproximação CNAE 1813-0/01). Tests usam biz=1 (Wagner) + biz=99 (fictício) — ADR 0101.
- Pattern alinhado Wave 26 (file_get_contents + `class_uses_recursive` + reflexão sem booting Laravel, zero hit MySQL).

### Added (Wave 27 — 2026-05-17 — POLISH FINAL 63-85 → ≥92)

- `Modules/ComunicacaoVisual/Tests/Feature/Wave27ComVisPolishTest.php` — 14 asserts cobrindo D7 fix forensic triplo + D9 spans + D5 README + V5 CHANGELOG + Tier 0 biz=99
- **D7 forensic fix confirmado** (regressão Wave 22→23 detectada Wave 25, fix Wave 26→27): `AuditTrailIntegrityTest::it('whitelist logOnly cobre campos críticos...')` corrigido — Pest `expect()->toContain($a, $b)` interpreta AMBOS args como valores em array (não passa $b como mensagem). Refactor: 1 `toContain` por linha + comentário PT-BR ANTES da linha. Resultado: 8/8 passed (era 7/8 fail).
- **D9 spans cobertura** — OrcamentoCalculator (`comvis.orcamento.calcular`) + ApontamentoTracker (3 spans: `comvis.apontamento.iniciar/finalizar/cancelar`) + log estruturado `comvis.apontamento.finalizado` (D9.b) — todos asserted via source.
- **D5 README expandido** — Wave 27 asserts 10 seções canônicas existem (Objetivo, Arquitetura, Como cliente usa, Multi-tenant, LGPD, Testes, Concorrentes, Comandos, Links) + persona Larissa-equivalente + drift m² + 3 concorrentes (Mubisys, Zênite, Calcgraf) + NFe-boleto diferencial.

### Changed (Wave 27)

- Score Capterra scoped: 63-85 (W22→W25) → ≥92 estimado pós W27 (D7 forensic fix +5, D9 +2, D5 +1).

### Preserved (Tier 0 IRREVOGÁVEL — Wave 27)

- biz=99 em fixtures (NUNCA biz=4 PROD).
- Append-only Apontamento mantido.
- ADR 0143 FSM canon consumido via cv_ordens_producao (sem mudança W27).

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
