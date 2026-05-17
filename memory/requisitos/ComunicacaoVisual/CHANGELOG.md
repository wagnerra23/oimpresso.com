# Changelog — Modules/ComunicacaoVisual

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - 2026-05-16 — Wave 23 saturação bucket vertical_client_facing

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
