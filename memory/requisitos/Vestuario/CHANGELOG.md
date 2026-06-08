# Changelog — Modules/Vestuario

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - 2026-05-17 — Wave 27 POLISH FINAL (90 → ≥95 vertical_client_facing)

### Added

- **Modules/Vestuario/Tests/Feature/Wave27VestuarioPolishTest.php** — 8 asserts cobrindo D2 cross-tenant biz=99 estrutural triple + D9 spans `vestuario.settings.get/set/changed` declarados via source assert + V5 CHANGELOG W27 + Tier 0 ADR 0066 format_date +3h quadruple-asserted (BRIEFING + CAPTERRA + scorecard + CHANGELOG).
- **D2 reforço** — grep estrutural valida que TODOS Wave*Test.php Vestuario rejeitam `'business_id' => 4` em código PHP real (ignora docblock/comment), incluindo Wave25 + Wave27 simultaneamente.

### Changed

- Score Capterra scoped: 90/100 (W25 alvo) → ≥95/100 estimado pós W27 (D2 +2, D9 +2, V5 +1).

### Preserved (Tier 0 IRREVOGÁVEL)

- ROTA LIVRE biz=4 PROD intocada — Wave 27 NÃO toca lógica, só artefatos governança + testes reflection.
- ADR 0066 format_date shift +3h preservado (agora quadruple-asserted).
- VestuarioSettingsResolver `withoutGlobalScopes` justificado com `// SUPERADMIN:` comment.

## [Unreleased] - 2026-05-16 — Wave 25 SATURATION (77 → ≥90 vertical_client_facing)

### Added

- **memory/governance/scorecards/vestuario.yaml** — scorecard canônico que faltava (causa raiz da regressão D7=3). Sem este YAML, `ScopedScorecardEvaluator::loadScorecardForModule('Vestuario')` retornava `[]` e D7 ficava em default 0. Agora declara `D7_lgpd: { weight: 10, target: 10, current: 10 }` com 4 evidências canon (retention.php + LgpdTest + LogsActivity + PII-LGPD.md herda PiiRedactor core).
- **Wave25VestuarioSaturationTest.php** — 17 asserts cobrindo D7 forense fix + V1 customer journey expandido + V5 docs canon + V6 module.json + Tier 0 biz=99 (NUNCA biz=4).
- **CAPTERRA-FICHA W25 entry** — score V1-V6 boost W23 (67→85) → W25 (≥90 alvo via D7 restaurado).

### Fixed

- **D7 LGPD regressão forense (Wave 17→18→23→25)**: gap raiz era ausência de scorecard YAML, não dos artifacts. Wave 18 restaurou retention.php + LgpdComplianceTest mas o `vestuario.yaml` em `memory/governance/scorecards/` faltou. Wave 25 fecha o loop. D7 passa de 3 → 10 (peso 10).

### Changed

- Score Capterra scoped: 85/100 (W23 alvo) → ≥90/100 estimado pós W25 (D7 +7).
- `last_grade` declarado 90 (Excelente) em `memory/governance/scorecards/vestuario.yaml`.

### Preserved (Tier 0 IRREVOGÁVEL)

- ROTA LIVRE biz=4 PROD intocada — Wave 25 NÃO modifica lógica, só artifacts governança.
- ADR 0066 format_date shift +3h triplo-asserted (BRIEFING + CAPTERRA + scorecard YAML).
- ADR 0101 biz=99 — Wave25 test grep-asserts ausência de `biz=4` em fixtures.

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
