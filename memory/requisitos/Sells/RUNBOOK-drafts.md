---
slug: sells-runbook-drafts
title: "Sells — Runbook da tela Rascunhos /sells/drafts (migração MWART)"
type: runbook
module: Sells
status: active
date: 2026-05-15
wave: W1-A (Bucket B1 Sells)
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/vendas-cockpit/"
  blueprint_screenshot_approval: "SYNC_LOG pendente verificar Wagner (ADR 0149)"
  derived_from: "Sells/Index (mesma entidade + lista Cockpit pattern V2)"
  divergence_from_blueprint: "Lista filtrada por status=draft + sub_status NULL/draft. Sem KPIs adversários (só 'Rascunhos pendentes' total)."
---

# RUNBOOK — Rascunhos de venda (`/sells/drafts`)

> **Tipo:** runbook MWART (Blade → Inertia/React)
> **Refs:** ADR 0104, ADR 0149, ADR 0110 (Cockpit Pattern V2), ADR 0093
> **Estado origem:** Blade `sale_pos.draft` via `SellController@getDrafts()` linha 1996 — usa `getDraftDatables` AJAX legacy linha 2040.
> **Estado alvo:** `Pages/Sells/Drafts.tsx` (lista Cockpit V2 com drawer SaleSheet — reuso completo).
> **Persona alvo:** Larissa retoma rascunhos parados (PROFORMA / cotação salva pra finalizar depois).

## 1. Objetivo

Migrar lista de rascunhos pra Cockpit Pattern V2 — header + KPI single ("X rascunhos pendentes") + tabela compacta (data + nº + cliente + total + ações) + drawer SaleSheet ao clicar linha. Reusa `getDraftDatables` para AJAX legacy (back-compat).

## 2. Pré-condições

- [ ] Permissão `draft.view_all` OU `draft.view_own`
- [ ] Endpoint AJAX `/sells/drafts` retorna DataTables JSON se `request()->ajax()` (preservado)
- [ ] Multi-tenant scope `business_id` (Tier 0)

## 3. Passo-a-passo

### 1. Alterar `SellController@getDrafts()` — dual response

```php
public function getDrafts()
{
    if (! auth()->user()->can('draft.view_all') && ! auth()->user()->can('draft.view_own')) {
        abort(403, 'Unauthorized action.');
    }

    $business_id = request()->session()->get('user.business_id');

    // KPI agregado
    $draftCount = Transaction::where('business_id', $business_id)
        ->where('type', 'sell')
        ->where('status', 'draft')
        ->whereNull('sub_status')
        ->count();

    $business_locations = BusinessLocation::forDropdown($business_id, false);
    $customers = Contact::customersDropdown($business_id, false);
    $sales_representative = User::forDropdown($business_id, false, false, true);

    if (request()->header('X-Inertia') || request()->wantsJson()) {
        return \Inertia\Inertia::render('Sells/Drafts', [
            'kpis' => [
                'total' => $draftCount,
            ],
            'filters' => [
                'businessLocations' => $business_locations,
                'customers' => \Inertia\Inertia::defer(fn () => Contact::customersDropdown($business_id, false)),
                'salesRepresentative' => $sales_representative,
            ],
            'permissions' => [
                'view_all' => auth()->user()->can('draft.view_all'),
                'view_own' => auth()->user()->can('draft.view_own'),
            ],
            'urls' => [
                'datatable' => '/sells/drafts',  // mesmo endpoint, content-type AJAX
                'back' => '/sells',
            ],
        ]);
    }

    return view('sale_pos.draft')
        ->with(compact('business_locations', 'customers', 'sales_representative'));
}
```

### 2. Criar `Pages/Sells/Drafts.tsx`

```tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import { Button } from '@/Components/ui/button';
import { FileText, Plus } from 'lucide-react';
// ... tabela via fetch('/sells/drafts', { headers: { Accept: 'application/json' } }) ou
// usa endpoint datatables compactado quando ajax=1
```

### 3. Tabela compacta (5 cols)

| Coluna | Source | Notas |
|---|---|---|
| Data | `transaction_date` | format BRT |
| Nº rascunho | `invoice_no` | clicar abre drawer |
| Cliente | `contacts.name` | mobile small abaixo |
| Itens | `total_items` | counter |
| Ações | finalizar / editar / excluir | dropdown |

### 4. Drawer reuso `SaleSheet`

Clicar linha abre `SaleSheet` (reuso Index) com `saleId={row.id}`. Ações dentro do drawer: Finalizar (POST `/sells/{id}/finalize` ou redireciona pra edit + save status=final), Editar (→ /sells/{id}/edit), Excluir.

## 4. Tokens — Cockpit V2 (mesmo Index)

## 5. Estados

| Estado | Trigger | UI |
|---|---|---|
| empty | 0 rascunhos | `<EmptyState/>` "Nenhum rascunho. Comece uma venda nova" |
| loading | aguardando defer customers | skeleton |
| error 403 | sem permissão | abort backend |

## 6. Responsividade — 1280px target

## 7. Atalhos — `N` (nova venda), `Esc` (fecha drawer)

## 8. Component contract

```tsx
interface DraftsPageProps {
  kpis: { total: number };
  filters: {
    businessLocations: Record<number, string>;
    customers?: Record<number, string>;  // deferred
    salesRepresentative: Record<number, string>;
  };
  permissions: { view_all: boolean; view_own: boolean };
  urls: { datatable: string; back: string };
}
```

## 9. DoD checklist

- [ ] Permission gate `draft.view_all`/`draft.view_own`
- [ ] Tier 0 `business_id` em queries
- [ ] AJAX `/sells/drafts` (request()->ajax()) preservado (DataTables legacy back-compat)
- [ ] Defer customers (dropdown pode ser grande)
- [ ] Reuso SaleSheet drawer
- [ ] Pest baseline + cross-tenant passa

## 10. Pegadinhas

- ❌ NÃO duplicar SaleSheet — reusa do Index
- ❌ NÃO esquecer fallback AJAX — DataTables legacy ainda alimenta tabela quando `?ajax=1`
- ❌ NÃO usar `Transaction::all()` — sempre scoped

## 11. Cutover

- Smoke biz=1 criar 1 rascunho → /sells/drafts → drawer → finalizar
- Canary 7d
- Remover Blade `resources/views/sale_pos/draft.blade.php` após 30d

---

**Última atualização:** 2026-05-15 (Wave 1 Agent W1-A)
