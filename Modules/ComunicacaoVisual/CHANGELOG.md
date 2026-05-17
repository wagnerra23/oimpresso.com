# Changelog — Modules/ComunicacaoVisual

> Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) + SemVer.
> Vertical CNAE 1813-0/01 — gráfica rápida BR.

## [Unreleased]

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
