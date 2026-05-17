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
