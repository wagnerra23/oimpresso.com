---
id: requisitos-financeiro-adr-ui-0003-amendment-0002-visao-unificada-cockpit-v2
---

# ADR UI-0003 (Financeiro) · Amendment 0002 — Visão Unificada Cockpit V2 (PR #349)

- **Status**: accepted
- **Data**: 2026-05-09
- **Decisores**: Wagner (aprovou protótipo Cowork "Visao Unificada" 2026-05-09)
- **Categoria**: ui
- **Amends**: [ui/0002](0002-dashboard-unificado-4-estados.md)
- **Relacionado**: PR [#349](https://github.com/wagnerra23/oimpresso.com/pull/349), PR #355, PR #358, PR #359, US-FIN-013, US-FIN-020

## Contexto

[ADR ui/0002](0002-dashboard-unificado-4-estados.md) (accepted 2026-04-24) propôs **dashboard unificado** com 4 KPIs específicos, aging buckets, mobile responsive, combobox de cliente, comparação delta_pct vs mês anterior, pagination 25/100.

Em 2026-05-09, sessão Cowork gerou protótipo "Visao Unificada" (HTML estático) com **shape diferente**: 5 KPIs (incluindo Saldo Previsto destacado), sem aging buckets, densidade configurável, CmdK palette, sem comparação delta. Wagner aprovou o protótipo visual como "está bem legal" → PR #349 implementou conforme protótipo.

PR #349 mergeou sem ADR de superseding/amendment, deixando **drift** entre o canon (ui/0002) e a implementação. Audit retroativo (sessão 2026-05-09 ~22h) detectou as divergências e formaliza-as nesta ADR.

## Decisão

**Implementação atual de `/financeiro/unificado` (PR #349 + #355 + #358) é canon — `ui/0002` fica como histórico do plano original.**

### Divergências aceitas vs ui/0002

| Item ui/0002 (original) | Implementação atual (canon) | Razão |
|---|---|---|
| **KPIs**: receber_aberto, pagar_aberto, recebido_mes, pago_mes (4) | saldo_previsto, recebido, a_receber, pago, a_pagar (5) | Wagner pediu Saldo Previsto destacado no card maior — fluxo de caixa do mês é decisão #1 da Eliana, não os 4 estados isolados |
| **Aging vencidos** com buckets <30/30-60/60-90/90+ + valores em cada KPI | Status `atrasado` simples, sem buckets | F1 enxuto. Aging fica como US-FIN-022 (backlog charter) |
| **Comparação `+12% vs mês anterior`** (delta_pct em recebido_mes/pago_mes) | Sem delta_pct | F1 simplifica. Vira US-FIN-023 |
| **Combobox cliente com autocomplete** | Filter de Conta + Categoria (sem cliente/contraparte autocomplete) | F1 prioriza filtros mais usados. Vira US-FIN-024 |
| **Mobile responsive** (cards stack 2×2 + lista) | Desktop only ≥1024px | Persona Eliana é desktop fixo. Mobile vira US-FIN-025 |
| **Pagination** 25/100 | `limit(200)` fixo no controller | Volume típico ~50-200 títulos/mês. Pagina quando virar dor (US-FIN-026) |
| **`<Combobox>` shadcn** | `<Input>` busca textual com debounce (atalho `/`) | F1 simplifica busca |
| **Tests obrigatórios**: DashboardKpiTest + DashboardFilterTest + DashboardIsolationTest + Vitest + Playwright (5) | UnificadoControllerTest com 5 tests Pest (PR #359) — Inertia component, KPIs shape, tab querystring, Tier 0 isolation, Non-Goals POST/PUT/DELETE | Pest cobre suficiente em F1. Vitest + Playwright vira US-FIN-027 fase 2 |

### Adições novas (não estavam em ui/0002)

| Feature nova | Origem |
|---|---|
| **Card Saldo Previsto destacado** (5º KPI) | Protótipo Cowork — Wagner pediu visão de fluxo |
| **Densidade configurável** (compact/comfortable/spacious) | Protótipo Cowork — persona Eliana usa monitor grande, prefere alta densidade |
| **CmdK palette** (`Cmd+K`) | Protótipo Cowork — atalho navegação |
| **1-clique baixa inline** (botão "✓ Recebi"/"✓ Paguei" na linha da tabela) | Protótipo Cowork — R-FIN-007 |
| **Drill-down KPI clicável** (ADR ui/0002 §UX item 1 — explicito) | Implementado em PR #358 retroativo |
| **Stub `/unificado/novo`** (picker Receber/Pagar) | PR #358 — substitui form unificado inline (US-FIN-021 backlog) |

## Princípios mantidos vs ui/0002

✅ Tela única como entry point Cockpit V2 (não 4 telas separadas)
✅ Filtros consolidados refletem em querystring (URL state)
✅ Server-side aggregation (KPIs vêm calculados do controller)
✅ Multi-tenant `business_id` Tier 0 (ADR 0093)
✅ Drill-down por click no KPI (implementado em PR #358 retroativo — antes faltava)

## Backlog declarado (charter Index.charter.md)

- **US-FIN-021** — Form unificado inline (modal/sheet) — substitui stub `/unificado/novo`
- **US-FIN-022** — Aging buckets <30/30-60/60-90/90+ + filtro
- **US-FIN-023** — Comparação `+X% vs mês anterior` (delta_pct)
- **US-FIN-024** — Combobox cliente/contraparte autocomplete
- **US-FIN-025** — Mobile responsive
- **US-FIN-026** — Pagination 25/100 quando volume passar 500
- **US-FIN-027** — Pest GUARD fase 2 (Vitest component + Playwright E2E)
- **US-FIN-028** — visual-comparison.md retroativo (entregue paralelo à esta ADR)

Cada US ativa só com sinal qualificado [ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente reporta OU métrica detecta drift. Hoje sem sinal pra nenhuma.

## Performance

| Métrica ui/0002 | Status atual |
|---|---|
| Endpoint p95 < 500ms (5k títulos) | Não medido. UnificadoController eager-loads 4 relações; `limit(200)` evita N+1 |
| KPIs cache TTL 5min | Não implementado. Re-query a cada request (ok pra volume atual) |
| Cache invalidação event-based | Não implementado. Idem |

Quando volume passar de 1k títulos/biz, abrir US dedicada pra cache.

## Referências

- [ui/0002](0002-dashboard-unificado-4-estados.md) — plano original (4 KPIs, aging, mobile, combobox)
- [PR #349](https://github.com/wagnerra23/oimpresso.com/pull/349) — implementação F1 (5 KPIs, sem aging, desktop only)
- [PR #355](https://github.com/wagnerra23/oimpresso.com/pull/355) — fix hardcode "ROTA LIVRE" + período + empty state
- [PR #358](https://github.com/wagnerra23/oimpresso.com/pull/358) — fix 404 /novo + sidebar entry + KPIs clicáveis (drill-down ui/0002 §UX)
- [PR #359](https://github.com/wagnerra23/oimpresso.com/pull/359) — charter retroativo + Pest GUARD (5 tests)
- [Index.charter.md](../../../../resources/js/Pages/Financeiro/Unificado/Index.charter.md) — contrato vivo da tela
- [ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — sinal qualificado (gating de US-FIN-021..028)
- [ADR 0114](../../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — Cowork loop formalizado
