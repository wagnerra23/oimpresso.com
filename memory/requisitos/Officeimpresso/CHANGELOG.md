# Changelog — Modules/Officeimpresso

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - 2026-05-16 — Wave 23 saturação bucket vertical_client_facing

### Added

- **CAPTERRA-FICHA.md** canônica — concorrentes (Mubisys, Sigma Soft, Calcgraf), top 5 gaps P0 (G1 LicencaImporter idempotente, G2 dashboard saúde, G3 retention-purge command, G4 onboarding wizard, G5 API REST/webhooks), score V1-V6 W22→W23 (60→≥85).
- **Wave23OfficeimpressoSaturationTest.php** — Pest saturação V1/V4/V5/V6 com 12 assertions cobrindo entities LicencaLog/LicencaComputador, ParseLicencaLogCommand idempotência (offset cursor), retention granular por evento (api_call 365d, admin_actions 2555d CC Art. 206), middleware LogDelphiAccess/LogDesktopAccess, governance.bucket=vertical_client_facing + FSM N/A justificado.
- **module.json governance.bucket=vertical_client_facing** ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md)) com `scoped_score_target: 85`, `wave: 23`, `wave_23_saturation: true`.

### Changed

- Score Capterra scoped (rubrica `vertical_client_facing.yaml`): 60/100 → ≥85/100 estimado.
- V1 Pest E2E: +6 (estrutural + reflection ParseLicencaLogCommand idempotência).
- V5 Docs canon: +9 (CAPTERRA-FICHA + CHANGELOG W23 — SPEC/PROPOSTA-COMERCIAL/RUNBOOK migração já existiam, asserted em Pest).
- V6 Capterra ROI Top 5: +4 (FICHA fechando gap W22).

### Preserved (Tier 0 IRREVOGÁVEL)

- LicencaLog append-only contract (audit trail Passport + Delphi sync — sem SoftDeletes).
- Retention granular por evento ([LGPD Art. 16](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm) + [CC Art. 206 §5 III](https://www.planalto.gov.br/ccivil_03/leis/2002/l10406.htm)) — janelas distintas: api_call 1y (debug), login 2y (auditoria), admin 7y (audit legal).
- Bridge desktop Delphi 26+ anos preservada (clientes legados Vargas/Extreme/Gold/Zoom/Fixar/Mhundo/Produart sem fricção).
- ParseLicencaLogCommand idempotente (cursor offset Cache + hash dedup).
- FSM N/A justificado (bridge audit-only sem state machine — `fsm_n_a_reason` em module.json).
- Schema legado Firebird intocado (cliente desktop não quebra).
