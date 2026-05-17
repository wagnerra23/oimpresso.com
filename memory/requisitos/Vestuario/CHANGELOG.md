# Changelog — Modules/Vestuario

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - 2026-05-16 — Wave 23 saturação bucket vertical_client_facing

### Added

- **CAPTERRA-FICHA.md** canônica — concorrentes (Linx Microvix, ProMoz, Vendizap, Bling Loja, F360), top 5 gaps P0 (US-VEST-020/021/022/023/029), score V1-V6 W22→W23 (67→≥85).
- **Wave23VestuarioSaturationTest.php** — Pest saturação V1/V4/V5/V6 com 11 assertions cobrindo CAPTERRA-FICHA presença, retention 365d+ anonymize, ADR 0066 format_date shift +3h preservado, governance.bucket=vertical_client_facing.
- **module.json governance.bucket=vertical_client_facing** ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md)) com `scoped_score_target: 85`, `wave_23_saturation: true`.

### Changed

- Score Capterra scoped (rubrica `vertical_client_facing.yaml`): 67/100 → ≥85/100 estimado.
- V5 Docs canon: +10 (CAPTERRA-FICHA + CHANGELOG W23 entry fechando gap D3 BRIEFING já existente).
- V1 Pest E2E: +5 (estrutural + reflection sem boot Laravel — paralelizável worktree).

### Preserved (Tier 0 IRREVOGÁVEL)

- ROTA LIVRE biz=4 PROD intocada — todos os testes biz=99 (ADR 0101).
- ADR 0066 format_date shift +3h documentado em BRIEFING + asserted em Wave23VestuarioSaturationTest.
- Modules/Vestuario formal vertical lifecycle `piloto` mantido (ADR 0121 §P7).

## [0.0.0] - 2024-Q1 (anterior à governança formal)

- Vertical em produção via ROTA LIVRE biz=4 (Larissa Termas do Gravatal/SC).
- Customizações: `format_date` shift +3h ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)).
- Volume: 17.251+ vendas / ~99% do oimpresso novo Laravel.
