# RUNBOOK — Migração MWART `TransactionPayment` (Index/Edit/Show)

> **Status:** Em construção (Wave Blade T1 Migration B — 2026-05-17)
> **Origem:** Tarefa Blade T1 Migration B — migração paralela Blade → Inertia/React
> **Caminho:** `/payments` (Blade legacy mantido) ↔ `/payments/v2` (Inertia coexiste — NÃO cutover nesta wave)

## Escopo

3 telas Inertia coexistindo com Blade legado:

| Tela | Rota Blade | Rota Inertia (nova) | Page tsx |
|---|---|---|---|
| **Index** | `/payments` (Blade — DataTables AJAX) | `/payments/v2` | `resources/js/Pages/TransactionPayment/Index.tsx` |
| **Edit** | `/payments/{id}/edit` (modal AJAX) | `/payments/v2/{id}/edit` | `resources/js/Pages/TransactionPayment/Edit.tsx` |
| **Show** | `/payments/view-payment/{id}` (modal AJAX) | `/payments/v2/{id}` | `resources/js/Pages/TransactionPayment/Show.tsx` |

## F1 — Snapshot Blade legado

Source: `resources/views/transaction_payment/*.blade.php`. Telas alvo:
- `single_payment_view.blade.php` — detail modal (header status + amount/method/dates/customer/note + audit + Print receipt + Download document)
- `edit_payment_row.blade.php` — form modal (amount/method/paid_on/note/account/document)
- Index: NÃO existe blade próprio — venda/compra individuais chamam `addPayment()`/`viewPayment()` via DataTables; lista geral cross-tx é nova UX adicionada na v2 (KpiGrid + DataTable filtrável)

## F2 — Backend baseline (`*Inertia()` paralelos)

`app/Http/Controllers/TransactionPaymentController.php` ganha 3 métodos:

- **`indexInertia()`** — lista `TransactionPayment` join `transactions` paginate(50) com filter `tipo` (recebido=sell/sell_return/opening_balance | pago=purchase/purchase_return/expense), `status` (paid/partial/due), `from`/`to`. Multi-tenant via `Transaction::business_id`. KPIs deferred (Inertia::defer + OtelHelper::spanBiz).
- **`editInertia($id)`** — full page Edit (vs Blade modal): retorna payment_line + transaction + payment_types + accounts. POST update reusa `update()` existente.
- **`showInertia($id)`** — detail full page (vs modal): equivalente a `viewPayment()` retornando single_payment_line + transaction + payment_types + audit trail.

Reuso de service: `TransactionUtil` (cálculos, payment_types) + `ModuleUtil::accountsDropdown` (lista contas) + `LogsActivity` (audit). **NÃO toca lógica US-RB-044 NFe-de-boleto-pago** (segue no Observer Asaas/Inter).

## F3 — Frontend (3 Pages tsx + 3 charters)

`resources/js/Pages/TransactionPayment/{Index,Edit,Show}.{tsx,charter.md}`.

**Padrão:**
- `AppShellV2` layout
- shadcn/ui (`Button`, `Card`, `Badge`, `Select`)
- `lucide-react` icons
- localStorage `oimpresso.transaction_payment.index.*` (filtros persistentes)
- `Inertia::defer` deferred no Controller + `<Deferred data="..." fallback={skeleton}>` no Page
- PT-BR labels, format BRL (`toLocaleString('pt-BR', ...)`)

## F4 — QA Pest

`Modules/Financeiro/Tests/Feature/TransactionPaymentInertiaSmokeTest.php` — 8 cenários:

1. `test_index_inertia_responde_200_com_admin` — baseline
2. `test_index_inertia_403_sem_permissao` — RBAC
3. `test_index_inertia_filtro_tipo_recebido` — UI filter
4. `test_show_inertia_responde_200_payment_proprio` — multi-tenant own
5. `test_show_inertia_404_payment_outro_business` — multi-tenant cross-tenant biz=99 (ADR 0093)
6. `test_edit_inertia_responde_200_payment_proprio` — happy edit
7. `test_edit_inertia_404_payment_outro_business` — cross-tenant edit blocked
8. `test_index_inertia_pagina_segunda_pagina` — paginate

Skip se MySQL/seeders ausentes (`Business::first()` check).

## F5 — Cutover

**NÃO** nesta wave. Coexiste em prod com Blade. Wagner valida UX paralelamente; cutover é Wave separada com:
- Aviso prévio Larissa (ROTA LIVRE) — `/payments` é fluxo core
- Canary 7d
- Redirect `/payments` → `/payments/v2` após sign-off

## Tier 0 IRREVOGÁVEIS preservados

- ✅ Multi-tenant: `Transaction::where('business_id', $businessId)` em todo query
- ✅ Permission gates idênticos (`sell.payments` / `purchase.payments` / `edit_sell_payment` etc)
- ✅ NÃO substitui Blade — coexistência total
- ✅ PT-BR
- ✅ US-RB-044 NFe-de-boleto-pago não tocada (lógica vive em jobs/observers, fora deste fluxo UI)
- ✅ `Inertia::defer` em props caras (ADR Wave 17 D6 + RUNBOOK-inertia-defer-pattern.md)
- ✅ Charter `<Tela>.charter.md` ao lado do `.tsx` (Skill `charter-first` Tier A)
