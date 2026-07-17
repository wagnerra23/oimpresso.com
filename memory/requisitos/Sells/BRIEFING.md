---
distilled_at: "2026-07-17"
distilled_by: "manual [CC] — redistilação por releitura (telas + controllers + flags + guards de valor). Substitui o destilado de 2026-07-10, que tinha H1 duplicado, dizia 'canário aguardando' (morto há ~7 semanas) e citava a ADR 0192 errada"
module: Sells
status: producao
updated_at: "2026-07-17"
---

# BRIEFING — Sells (verdade destilada)

## Estado atual

**Sells é feature CORE do UltimatePOS, não um módulo nWidart próprio** — não há dir físico dela em `Modules/`; as telas vivem em `resources/js/Pages/Sells/*.tsx` (servidas por `SellController` + `SellPosController`), a Model é `app/Models/Transaction.php` (`type='sell'`), e o pipeline FSM em `app/Domain/Fsm/` ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)). A migração MWART (Blade→Inertia/React) da tela de venda está **em produção para ROTA LIVRE (biz=4, 99% do volume)**.

> **Sells não tem module-grade canônico** — sem dir físico em `Modules/`, o grader não varre. No baseline está em `deprecated_pending_decision` (score_v3:58 congelado, *"decisão Wagner pende: criar wrapper OU deprecar entry"*). As notas vivas de Sells são de **eixos diferentes, não somáveis**: **capacidade 60/100** ([CAPTERRA-FICHA.md](CAPTERRA-FICHA.md), mede cálculo/fiscal/offline) e **design 88-90** (screen-grade, *cega a cálculo/fiscal* — não confundir com "o módulo está 90%").

> ⛔ **Errata do destilado anterior — não re-alegar.** (1) H1 duplicado (bug do distiller). (2) *"canário 7d biz=1, biz=4 aguardando reavaliação"* — **falso desde 2026-05-27**: ver Estado do rollout. (3) *"Observer de venda (ADR 0192) p95<50ms"* — a [ADR 0192](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md) é *auto-faturar OS→Venda via JobSheetObserver* e **já está implementada** (não é gap); o "p95<50ms" é nota de guard-rail, não a ADR.

## Capacidades

Varridas em 2026-07-17: **8 telas** (`Index`, `Create`, `Edit`, `Show`, `Drafts`, `Quotations`, `Subscriptions`, `Caixa/Index`) + **35 componentes** em `_components/` + **~70 arquivos de teste** em `tests/Feature/Sells/`.

- **Drawer FSM** (`FsmActionPanel` + `SaleSheet` + `SaleJourneyStepper` + `SaleTimeline`): ações dinâmicas por stage + RBAC + timeline auditável.
- **Integração veículo/Oficina** (gated por `isModuleInstalled('OficinaAuto')`): `QuickAddVehicleSheet`, `CriarOsButton`, `CommissionSplitEditor` — com testes de gating.
- **Fiscal/NFe em lote**: `VdNfeEmitModal`, `VdNfseEmitModal`, `VdBulkEmitModal`, `FiscalSection`, `VdNextActionPanel` (next-best-action).
- **Cobrança/pagamento**: `CobrancaChip/Drawer`, `PaymentRow`, `QuickPaymentDialog`.
- **Caixa do dia por origem** (`Caixa/Index.tsx` + `VdSource.tsx`) · **IA** (`SaleAiPanel`) · **impressão** (orçamento A4, recibo 80mm, PDF, modo apresentação).

## Gaps

| Gap | Estado real | Âncora |
|---|---|---|
| **Rede E2E de valor ausente** — não há teste HTTP `POST /pos` provando que `final_total` grava certo; só invariantes estruturais (que a própria ficha chama de anti-padrão §5). É *"a rede mais barata contra um 2º incidente de valor"* | ❌ **ABERTO P0** | US-SELL-040 `_pendente_` (SPEC:211) |
| Remover Blade legacy (`sale_pos/create`, 996 LOC) pós-monitor 30d | ❌ **ABERTO** | US-SELL-009 `_pendente_` (SPEC:258) |
| Reverter/estornar um cancelamento (cancelar já existe, com `CancelarVendaCascade`) | 🟡 plausível, sem rota/SPEC achados | não-medido |

> ⛔ **Errata — gaps do destilado anterior:** o *"dashboard `/relatorios/vendas-origem`"* é **fantasma** (a string só existe no próprio BRIEFING; virou a tela `Caixa/Index`). E o "Observer ADR 0192" não é gap (já implementado).

## Diferencial + risco Tier 0 (valor/estoque)

Sells é o palco do incidente **`num_uf` / valor inflado ~×100k** (2026-06-05, biz=4 Larissa — `final_total` corrompido; fix [#2279](https://github.com/wagnerra23/oimpresso.com/pull/2279)). Guardas **vivos** hoje: `tests/Unit/Utils/IncidentValorInfladoNumUfTest.php` (guard literal do incidente) + `NumUfHeuristicPtBRTest` + frontend `NumericInputPtBR.tsx` (parse pt-BR, arredonda a 2 casas no submit) + `SellsFinalTotalAuditCommand` (audita corrupção histórica) + a rule path-scoped [`.claude/rules/calculo-valor-estoque.md`](../../../.claude/rules/calculo-valor-estoque.md). **Risco residual honesto:** os guards pegam a *classe* `num_uf`, mas a prova ponta-a-ponta de que "a conta persiste certa" (US-SELL-040) ainda é `_pendente_`, P0.

## Última mudança

Recibo: `git log --since=2026-07-10 -- resources/js/Pages/Sells memory/requisitos/Sells app/Http/Controllers/Sell*Controller.php`, rodado 2026-07-17 → **4 commits, todos design/docs, zero código funcional**: errata "sidebar é PRETA" ([#4378](https://github.com/wagnerra23/oimpresso.com/pull/4378), que tocou o doc mais novo), backfill de frontmatter ([#4274](https://github.com/wagnerra23/oimpresso.com/pull/4274)), Padrão de Tela em 6 charters ([#4109](https://github.com/wagnerra23/oimpresso.com/pull/4109)/[#4117](https://github.com/wagnerra23/oimpresso.com/pull/4117)).

> Nota honesta: como no OficinaAuto, **o gatilho desta redistilação foi ruído** — um bloco de errata num doc, não mudança de venda. O último trabalho substantivo de Sells é anterior: Onda 1.1 Capterra (2026-07-02/03 — ficha nota 60 + US-SELL-054..057, [#3699](https://github.com/wagnerra23/oimpresso.com/pull/3699)/[#3704](https://github.com/wagnerra23/oimpresso.com/pull/3704)).

## Estado do rollout (a correção que lidera este arquivo)

**V2 do Create está LIVE para biz=4 (ROTA LIVRE) desde 2026-05-27** — não "aguardando". Evidência tripla:
1. **Guard biz=4 removido** — `SellController.php:1000-1009`: *"HOTFIX 2026-05-13 (biz=4 rollback) REMOVIDO em 2026-05-27… Wagner: 'remova hardcode, ative para todos'."*
2. **Flag default `true`** — `FeatureFlagService.php:45` `'useV2SellsCreate' => true` (migrada pro GrowthBook self-hosted; valor vivo lá não consultado).
3. **Uso real** — [session 2026-05-27](../../sessions/2026-05-27-sells-v2-larissa-13-bugs-batch.md): Larissa reportou 13 bugs em prod → 13 PRs na mesma tarde. `Sells/Index` (React) é servido incondicionalmente; `Sells/Create` V2 é gated só pela flag. O relógio humano (remover Blade = US-SELL-009) é que segue aberto.

## Proveniência (destilado de)

Releitura direta em 2026-07-17:

- código: `resources/js/Pages/Sells/` (8 telas + 35 `_components/`) · `app/Http/Controllers/SellController.php` · `SellPosController.php` · `app/Services/FeatureFlagService.php`
- contrato: [SPEC.md](SPEC.md) (US-SELL-*) · [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md)
- guards de valor: `tests/Unit/Utils/IncidentValorInfladoNumUfTest.php` · [`.claude/rules/calculo-valor-estoque.md`](../../../.claude/rules/calculo-valor-estoque.md)
- números: [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) (deprecated_pending_decision) · [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (capacidade 60)
- janela: `git log --since=2026-07-10 …` (4 commits)
