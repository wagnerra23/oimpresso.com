---
page: /recurring-billing/faturas
component: resources/js/Pages/RecurringBilling/Faturas/Index.tsx
related_us: [US-RB-042, US-RB-047]
owner: wagner
status: live
last_validated: "2026-05-17"
parent_module: RecurringBilling
related_adrs: [93, 94, 101, 104, 107, 114, 143]
tier: A
charter_version: 1
visual_source: prototipo-ui/cowork/cobranca-recorrente-page.jsx (tab "Faturas" stub no Index)
canon_method: Cowork KB-9.75 — Onda 7
sidebar_group: fin (FINANCEIRO)
---

# Page Charter — /recurring-billing/faturas (Faturas · cobrança recorrente · v1 Cowork Onda 7)

> **Status:** live · Onda 7 v9,75 do plano [Index-visual-comparison.md](../../../../../memory/requisitos/RecurringBilling/Index-visual-comparison.md) linha 113.
>
> **Origem visual:** mesma família visual Cowork da Page principal (4 KPIs + filter bar + tabela), mas com semântica diferente — Faturas individuais (rb_invoices) em vez de Subscriptions.

---

## Mission

Listar **faturas individuais** (`rb_invoices`) com filtros por status (open/paid/overdue/canceled/refunded) · gateway (inter/c6/asaas) · período (mês atual / próximo mês / atrasados) · busca por cliente ou número do documento — drawer/dialog confirm pra cancelar invoice em status `open` ou `overdue`. Reusa endpoint legacy `POST /financeiro/rb-invoices/{invoice}/cancelar` (US-RB-042).

---

## Goals — Features (faz · v1)

- AppShellV2 layout + Header `Faturas · cobrança recorrente` + breadcrumb implícito
- CTA "Nova fatura" (stub disabled · em breve)
- 4 KPI cards: **Pago este mês** (hero verde) · **Pendente** (zinc) · **Atrasado** (rose) · **Total de faturas** (zinc)
- Filter bar horizontal:
  - Status pills (Todas / Pagas / Pendentes / Atrasadas / Canceladas)
  - Gateway dropdown (Todos / Inter / C6 / Asaas)
  - Período dropdown (Qualquer / Mês atual / Próximo mês / Atrasadas)
  - Search input (busca cliente OR número documento)
- Tabela:
  - Número documento (mono)
  - Cliente nome (truncate · CNPJ secundário)
  - Plano (subscription.plan.name · null se avulsa)
  - Valor (BRL · tabular-nums)
  - Vencimento (data BR · label "há Nd" / "em Nd" / "hoje")
  - Status badge (open=amber · paid=emerald · overdue=rose · canceled=zinc strikethrough · refunded=zinc)
  - Gateway badge (inter/c6/asaas pill colorida)
  - Ações: botão "Cancelar" (só se `is_cancelavel` true · abre dialog confirm)
- Inertia::defer em `kpis` + `invoices` (props caras — agregação + paginate eager)
- Multi-tenant Tier 0: `HasBusinessScope` automático em Invoice + Repository scopa explícito + Pest cross-tenant biz=1 vs biz=99
- Permission gate Spatie pra cancel: `recurringbilling.invoice.cancel`
- Tailwind 4 puro (sem `.rec-*` CSS escopado)

---

## Non-Goals — Features (NÃO faz neste PR)

- ❌ Criar fatura avulsa via UI (CTA "Nova fatura" stub · Onda 9 ou separado)
- ❌ Drawer detalhe completo da fatura (ChargeAttempts timeline · DanfeBoletoLink · ReceiptDownload — fica em Onda 9)
- ❌ Bulk cancel (selecionar N linhas)
- ❌ Reenviar boleto/PIX manual
- ❌ Export CSV/Excel
- ❌ Filtro avançado por intervalo de data customizado
- ❌ Refund manual (US-RB-051 separado)
- ❌ Reabrir fatura cancelada (regra de negócio + audit complexa)

---

## UX Targets

- p95 first-paint < 1500ms (KPIs + 50 linhas paginadas com Inertia::defer)
- 0 erros JS console em smoke biz=1 (monitor 1280px ROTA LIVRE canon)
- Cabe em monitor 1280px sem scroll horizontal
- Dialog cancel abre em < 100ms (já client-side)
- Cores semânticas consistentes com Index.tsx (mesma família Cowork)

---

## UX Anti-patterns

- ❌ Cor crua Tailwind sem semântica clara
- ❌ Modal pesado pra cancel (canon = AlertDialog confirm minimal com motivo opcional)
- ❌ `font-bold` em h1 (canon = `font-weight: 700` já implícito)
- ❌ Inflar Page com lógica de cancelamento — canon: dispara `router.post(route('rb-invoices.cancel'))` e Service backend trata gateway

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/recurring-billing/faturas` (X-Inertia) | Inertia render `RecurringBilling/Faturas/Index` props `{filters, kpis (defer), invoices (defer)}` |
| POST | `/financeiro/rb-invoices/{invoice}/cancelar` | JSON `{ok, gateway_call, skipped?}` — existente desde US-RB-042 |

---

## Tests anti-regressão

- [Modules/RecurringBilling/Tests/Feature/Wave7FaturasIndexTest.php](../../../../../Modules/RecurringBilling/Tests/Feature/Wave7FaturasIndexTest.php) — 5 cenários mínimos:
  1. `/recurring-billing/faturas` retorna Inertia render correto biz=1 autenticado
  2. Filtros aplicam (status=paid, gateway=inter, periodo=atrasados, busca=cliente)
  3. Cross-tenant isolation: invoice biz=1 NÃO aparece quando user biz=99
  4. Paginated meta correto (current_page/last_page/per_page/total)
  5. KPIs agregam corretamente (pago_mes, pendente, atrasado, total_faturas)

---

## Refs

- [Index-visual-comparison.md](../../../../../memory/requisitos/RecurringBilling/Index-visual-comparison.md) — plano canônico das ondas
- [BRIEFING.md](../../../../../memory/requisitos/RecurringBilling/BRIEFING.md) — estado consolidado
- [ADR 0093 Multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0101 Tests biz=1](../../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)
- [ADR 0104 MWART](../../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0114 Prototipo-ui Cowork loop](../../../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- Charter da Page principal: [../Index.charter.md](../Index.charter.md)
- Skill [inertia-defer-default](../../../../../.claude/skills/inertia-defer-default/SKILL.md)
