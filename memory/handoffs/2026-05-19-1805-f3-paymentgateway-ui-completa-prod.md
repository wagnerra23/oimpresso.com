---
date: 2026-05-19
time: '18:05'
slug: f3-paymentgateway-ui-completa-prod
authors: [W, CL]
tldr: F3 PaymentGateway UI completa em prod (6 PRs · 3 telas · Onda 4d.5+4d.6) + 3 hotfixes (null-guard Inertia::defer · sidebar cleanup · refactor grupo via DataController). 13 tasks completas.
cycle: CYCLE-06
related_adrs:
  - 0144-paymentgateway-extracao-camada-cobranca
  - 0170-paymentgateway-module-extraction
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# Handoff 2026-05-19 18:05 — F3 PaymentGateway UI completa prod

## Estado MCP no momento do fechamento

- Cycle ativo: CYCLE-06 (Martinho prod + FSM rollout + Jana V2 demo)
- ADRs novas: 0144 (já existia) + 0170 (Onda 0 documentação)
- Sessions recentes: [2026-05-19-f3-paymentgateway-ui-completa.md](../sessions/2026-05-19-f3-paymentgateway-ui-completa.md)
- 13 tasks completadas hoje

## TL;DR

Sessão entregou **ADR 0170 Ondas 4d.2 → 4d.6 completas** em prod biz=WR2 (~4h, 6 PRs):

| PR | Onda | Status |
|---|---|---|
| [#1135](https://github.com/wagnerra23/oimpresso.com/pull/1135) | 4d.2-4 F3 UI 3 telas | ✅ Merged |
| [#1139](https://github.com/wagnerra23/oimpresso.com/pull/1139) | 4d.5 wire-up emissão | ✅ Merged |
| [#1140](https://github.com/wagnerra23/oimpresso.com/pull/1140) | 4d.6 cobrarCartao + CardToken | ✅ Merged |
| [#1141](https://github.com/wagnerra23/oimpresso.com/pull/1141) | hotfix null-guard Inertia::defer | ✅ Merged |
| [#1142](https://github.com/wagnerra23/oimpresso.com/pull/1142) | sidebar Cobrança + cleanup Pages/Boletos | ✅ Merged |
| [#1144](https://github.com/wagnerra23/oimpresso.com/pull/1144) | refactor grupo via DataController | ✅ Merged |

(PR #1143 hardcode reverted pelo #1144 arquitetural.)

## Validação prod biz=WR2 (Chrome MCP) — confirmada

- ✅ `/financeiro/cobranca` renderiza (header + funil + 4 KPIs + filtros + empty state)
- ✅ `/settings/payment-gateways` renderiza (3 KPIs + 5 cards drivers)
- ✅ Sidebar mostra **"Cobrança"** entre "DRE / Relatórios" e "Cobrança Recorrente"
- ✅ Redirect `/financeiro/boletos` → 301 → `/financeiro/cobranca`
- ✅ Bundle CSS `.pg-shell-scope` aplicado

## Aprendizados catalogados

| Memória | Sintoma | Pattern correto |
|---|---|---|
| [feedback-inertia-defer-null-guard-first-paint.md](../reference/feedback-inertia-defer-null-guard-first-paint.md) | Tela branca + TypeError undefined.filter() | `(prop ?? []).filter(...)` no body |
| [feedback-sidebar-grupo-via-datacontroller.md](../reference/feedback-sidebar-grupo-via-datacontroller.md) | Item novo sidebar não aparece | `'group' => 'fin'` no DataController, nunca hardcode label frontend |

## Próximas ondas backlog (ADR 0170)

- **Onda 4d.6.1**: Widget Asaas JS (tokenização script.asaas.com)
- **Onda 4d.6.2**: SheetNovaCobranca UI tipo=card
- **Onda 5**: Dogfooding Superadmin — Plan "SaaS Oimpresso Premium" RB biz=1 + tenants viram Contact biz=1 + Superadmin::Subscription projection + PesaPal deprecated
- **Onda 6**: Cleanup colunas legacy `rb_gateway_credential_id` + `gateway_*` em `accounts` + remover redirects 301 (após 90d)

## Pendências não-funcionais

- Configurar credentials sandbox Inter/Asaas/C6/BCB em biz=1 pra teste real de emissão (Wagner manual)
- Widget Asaas JS integração (Onda 4d.6.1 backlog)
- Smoke teste real emissão com sandbox token (depende credentials)

## Tasks completadas (13)

1. F3 setup — artefatos + RUNBOOKs + charters
2. PR-1 Tela Cobrança
3. PR-2 Tela Settings/PaymentGateways
4. PR-3 Sells drawer chip
5. Onda 4d.5 wire-up emissão backend
6. Onda 4d.5 wire-up emissão frontend
7. Onda 4d.5 Pest GUARDs
8. Validar prod via Chrome MCP — 3 telas
9. Onda 4d.6 cobrarCartao + CardToken
10. Hotfix Cobrança undefined deferred crash
11. Sidebar label Cobrança + cleanup Boletos
12. Hotfix SIDEBAR_GROUPS sync (reverted)
13. Refactor grupo via DataController

## Como continuar

Próxima sessão pode escolher:
- **Onda 5** (Dogfooding Superadmin — Plan "SaaS Oimpresso Premium" biz=1)
- **Onda 4d.6.1+.2** (widget Asaas JS + UI cartão completa)
- **Outro escopo** se cliente piloto pivotar

ADR 0170 roadmap original tem 6 ondas — 4 ondas completadas (4d.2 a 4d.6), ondas 5+6 backlog.
