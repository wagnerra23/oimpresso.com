# Charter — `TransactionPayment/Index.tsx`

> Rota Inertia: `/payments/v2` · Controller: `TransactionPaymentController::indexInertia` · Wave Blade T1 Migration B (2026-05-17).

## Intent

Listar todos os pagamentos (recebidos + pagos) cross-transaction com KPIs deferred e filtros persistentes em localStorage.

## Diferenças vs Blade legacy `/payments`

- Blade `/payments` é gerado por DataTables AJAX dentro de telas individuais de venda/compra (não há lista global cross-tx)
- v2 oferece **lista cross-tx** com KPIs agregados — UX nova
- Coexiste; legado preservado intocado

## Props (server)

- `pagamentos: LengthAwarePaginator<TransactionPayment>` — paginate(50), com `payment_ref_no`, `amount`, `method`, `paid_on`, `transaction_id`, `transaction_ref_no`, `transaction_type`, `payment_status`, `contact_name`, `contact_type`
- `filtros: { tipo: 'recebido'|'pago'|null, status: 'paid'|'partial'|'due'|null, from: string|null, to: string|null }`
- `kpis: Deferred<{ recebido_30d: number, pago_30d: number, pendentes_count: number }>` — Inertia::defer

## UX

- **KpiGrid** (3 cards): Total Recebido 30d · Total Pago 30d · Pendentes (count). Fallback skeleton enquanto defer carrega.
- **Filter chips:** tipo (Todos/Recebidos/Pagos), status (Todos/Pago/Parcial/Em aberto)
- **DataTable:** Data · Ref · Cliente/Fornecedor · Método · Valor · Status · Ações (Ver/Editar)
- **Paginação:** native Laravel paginate (links inferiores)
- **Sem actions destrutivas** na lista (Cancel = via Show)

## Persistência local

- `oimpresso.transaction_payment.index.tipo`
- `oimpresso.transaction_payment.index.status`

## Layout

- `AppShellV2` title="Pagamentos (v2)" breadcrumb=[Financeiro, Pagamentos]
- Container `max-w-7xl mx-auto p-6`

## Validações Tier 0

- ✅ RBAC: requer `sell.payments` OR `purchase.payments` (RBAC server)
- ✅ Multi-tenant: query scopa `t.business_id = session('user.business_id')`
- ✅ PT-BR labels, format BRL `pt-BR`
