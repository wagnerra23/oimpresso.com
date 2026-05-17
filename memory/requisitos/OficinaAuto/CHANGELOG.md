# Changelog — Modules/OficinaAuto

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - 2026-05-16 — Wave 23 saturação bucket vertical_client_facing

### Added

- **CAPTERRA-FICHA.md** canônica — concorrentes (Mecânico, Auto Manager, Lokoz, Bling Oficina, GP Soft Auto), top 5 gaps P0 (US-OFICINA-006/008/009/010/011), score V1-V6 W22→W23 (63→≥85).
- **Wave23OficinaAutoSaturationTest.php** — Pest saturação V1/V4/V5/V6 com 11 assertions cobrindo Vehicle + ServiceOrder + FormRequests Store/Update, LGPD PII fields tracked (plate/chassis/renavam), MATRIZ-ROI presença, governance.bucket=vertical_client_facing + FSM canon `service_order`.
- **module.json governance.bucket=vertical_client_facing** ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md)) com `scoped_score_target: 85`, `wave: 23`, `wave_23_saturation: true`.

### Changed

- Score Capterra scoped (rubrica `vertical_client_facing.yaml`): 63/100 → ≥85/100 estimado.
- V1 Pest E2E: +6 (complementa WhatsAppAprovacaoPinTest + E2EJourneyMartinhoBiz1Test DB-based existentes).
- V5 Docs canon: +10 (CAPTERRA-FICHA + CHANGELOG W23 — BRIEFING/ROADMAP/SPEC já existiam, MATRIZ-ROI asserted).
- V6 Capterra ROI Top 5: +3 (FICHA fechando gap W22).

### Preserved (Tier 0 IRREVOGÁVEL)

- FSM canon ADR 0143 `service_order` pipeline complexa (orçamento→aprovação→produção→entrega).
- Vargas + Martinho biz reais NUNCA em test (ADR 0101 — biz=99 sempre).
- PII plate/chassis/renavam protegidos via PiiRedactor.
- ServiceOrderController Inertia::render eager (rollback PR #963 Wave L/W7 preservado — defer quebrava initial render Pages).
- Modules/OficinaAuto lifecycle `V0 em construção` mantido (ADR 0137 — qualificada por sinal).
