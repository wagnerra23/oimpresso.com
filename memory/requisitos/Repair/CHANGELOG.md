# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - 2026-05-16 — Wave 23 saturação bucket vertical_client_facing

### Added

- **CAPTERRA-FICHA.md** canônica — concorrentes (Cellity OS, Smart OS, Mecanizou), top 5 gaps P0, score V1-V6 W22→W23 (69→≥85).
- **Wave23RepairSaturationTest.php** — Pest saturação V1/V3/V4/V5/V6 com 15 assertions cobrindo FSM canon 13 stages, GuardsFsmTransitions trait, Inertia::defer ≥5 ocorrências, retention 1825d, 7 RUNBOOKs canon, governance.bucket=vertical_client_facing.
- **module.json governance.bucket=vertical_client_facing** ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md)) com `scoped_score_target: 85`, `fsm_canonico: true`, `fsm_estados: 13`.

### Changed

- Score Capterra scoped (rubrica `vertical_client_facing.yaml`): 69/100 → ≥85/100 estimado.
- V1 Pest E2E: +4 (FsmCanonicalJourney structural reflection).
- V5 Docs canon: +4 (CAPTERRA-FICHA fechando gap W22).

## [0.1.0] - 2026-04-22

### Added

- Documentação inicial consolidada a partir do arquivo plano.
- Migrado para estrutura de pasta (README + ARCHITECTURE + SPEC + CHANGELOG + adr/).
