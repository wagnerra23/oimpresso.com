---
title: "RUNBOOK — Tela `/home` (Dashboard pós-login)"
owner: W
status: ativo
last_validated: "2026-06-08"
---

# RUNBOOK — Tela `/home` (Dashboard pós-login)

> **Tipo:** RUNBOOK MWART (ADR 0104 §F1 PLAN) — fase F6 Soft wrapper
> **Status:** vivo (entrega 2026-05-21)
> **Refs:** ADR 0093 multi-tenant Tier 0, ADR 0104 MWART canônico

## Pages cobertas

Hook `block-mwart-violation.ps1` matcha pelo nome do arquivo.

- `resources/js/Pages/Home/Index.tsx` — landing page pós-login Inertia (F6 Soft)

## Contrato canônico

### Props

```ts
interface Props {
  user_name: string;                       // session('user.first_name')
  is_admin: boolean;
  can_dashboard_data: boolean;
  all_locations: Record<number, string>;   // forDropdown business_id
  totals: {                                // null se !can_dashboard_data
    total_sell: number;
    net: number;
    invoice_due: number;
    total_expense: number;
  } | null;
  endpoints: {
    totals: string;                        // '/home/get-totals'
    stock_alert: string;                   // '/home/product-stock-alert'
    purchase_dues: string;                 // '/home/purchase-payment-dues'
    sales_dues: string;                    // '/home/sales-payment-dues'
  };
}
```

### Componentes

- **AppShellV2** layout com breadcrumb único `Início`
- **Welcome banner** ("Bem-vindo, {primeiro_nome}")
- **4 KPI cards** (grid 1col mobile / 4col desktop): Total Sells (sky), Net (emerald), Invoice Due (amber), Total Expense (rose)
- **Filtro loja** dropdown (se `all_locations.length > 1` E `is_admin`)

### Ações

- ❌ Nenhuma mutação (read-only — Soft wrapper)

### Endpoints AJAX (preservados, sem mudança)

- `GET /home/get-totals?start=…&end=…&location_id=…&user_id=…` — JSON com totals
- `GET /home/product-stock-alert` — DataTables JSON
- `GET /home/purchase-payment-dues` — DataTables JSON
- `GET /home/sales-payment-dues` — DataTables JSON
- `GET /calendar` — Blade legacy preservado

### Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL)

- `business_id = session('user.business_id')` em **toda** query
- `BusinessLocation::forDropdown($business_id)` para dropdown loja
- `TransactionUtil::getSellTotals($business_id, ...)` — service core preserva scope

### Permission gate

- `auth()->user()->can('dashboard.data')` — sem permission, retorna shell minimal sem `totals`
- Customer redirect: `user_type == 'user_customer'` → `Modules\Crm\Http\Controllers\DashboardController::index`

### Fallback Blade — ENCERRADO em 2026-08-28

- O Blade legado foi **removido** (`views/home/index.blade.php`, os 8 partials, `home.js` nas duas
  cópias, `indexLegacy()`, `__chartOptions()` e o ramo `?legacy=1`).
- `?legacy=1` sobrevive só como link velho em favorito: é **inerte** e cai na mesma tela React.
- O único motivo restante pra abrir o Blade eram os widgets pluggable de outros módulos, e o ponto
  de extensão foi medido **vazio** — `dashboard_widget()` tem zero produtores nos 32 `DataController`.

> ⚠️ **Reconciliação parcial (2026-08-28):** este RUNBOOK foi atualizado **só** nos pontos que a
> remoção do Blade tornou falsos. As seções de layout/KPI ainda descrevem a tela anterior às ondas
> 2 e 3 (gráficos e abas de grade) — dívida daqueles PRs, não desta remoção.

## Charter

[resources/js/Pages/Home/Index.charter.md](../../../resources/js/Pages/Home/Index.charter.md)

## Pest GUARD

`tests/Feature/Home/HomeIndexInertiaTest.php`:

1. ✅ Renderiza Inertia component `Home/Index` com shape esperado
2. ✅ Customer redirect preservado (`user_type=user_customer` → 302)
3. ✅ Sem `dashboard.data` permission → `totals` é null
4. ✅ `?legacy=1` é inerte — não existe mais fallback Blade (v5)
5. ✅ Tier 0 multi-tenant — não vaza locations de outro business

## Padrões transversais

- **AppShellV2** layout
- **Multi-tenant Tier 0** ADR 0093
- **Read-only** Soft wrapper — sem mutações
- **Charter ao lado do .tsx** (hook bloqueador)

## Refs

- [SPEC.md](SPEC.md) — User Stories US-DASH-*
- [BRIEFING.md](BRIEFING.md) — 1-pager executivo
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- Pattern Soft wrapper precedente: PR [#1288 Caixa](https://github.com/wagnerra23/oimpresso.com/pull/1288)
