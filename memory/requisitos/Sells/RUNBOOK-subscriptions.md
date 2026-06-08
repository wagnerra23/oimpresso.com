---
slug: sells-runbook-subscriptions
title: "Sells — Runbook da tela Assinaturas /sells/subscriptions (migração MWART)"
type: runbook
module: Sells
status: active
date: 2026-05-15
wave: W1-A (Bucket B1 Sells)
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/vendas-cockpit/"
  blueprint_screenshot_approval: "SYNC_LOG pendente verificar Wagner (ADR 0149)"
  derived_from: "Sells/Index (mesma entidade transactions + filtro is_recurring=1)"
  divergence_from_blueprint: "Colunas específicas: intervalo, próxima fatura, repetições. Toggle start/stop por linha."
---

# RUNBOOK — Assinaturas (`/sells/subscriptions`)

> **Tipo:** runbook MWART (Blade → Inertia/React)
> **Refs:** ADR 0104, ADR 0149, ADR 0110, ADR 0093, Modules/RecurringBilling
> **Estado origem:** Blade `sale_pos.subscriptions` via `SellPosController@listSubscriptions()` linha 2277.
> **Estado alvo:** `Pages/Sells/Subscriptions.tsx` (Cockpit V2 com cols específicas recurring).
> **Persona alvo:** Larissa olha assinaturas recorrentes (cobranças mensais) — start/stop, ver próxima fatura, histórico de faturas geradas.

## 1. Objetivo

Lista de assinaturas (`is_recurring=1` + `status='final'`) com:
- Coluna intervalo (Ex: "30 dias", "Mensal", "Anual")
- Coluna próxima fatura (calculada baseada em última gerada)
- Coluna repetições (limite ou ilimitado)
- Coluna faturas geradas (count + last_generated)
- Toggle start/stop por linha (POST `/sells/recurring-toggle/{id}`)

## 2. Pré-condições

- [ ] Permissão `sell.view` OU `direct_sell.access`
- [ ] AJAX `listSubscriptions` preservado (DataTables back-compat)
- [ ] Multi-tenant `business_id` (Tier 0)
- [ ] Module RecurringBilling se presente (não obrigatório)

## 3. Passo-a-passo

### 1. Alterar `SellPosController@listSubscriptions()` — dual response

```php
public function listSubscriptions()
{
    if (! auth()->user()->can('sell.view') && ! auth()->user()->can('direct_sell.access')) {
        abort(403, 'Unauthorized action.');
    }

    if (request()->ajax()) {
        // Lógica AJAX DataTables legacy preservada
        return $this->buildSubscriptionsDatatable();
    }

    $business_id = request()->session()->get('user.business_id');

    $recurringBase = Transaction::where('business_id', $business_id)
        ->where('type', 'sell')
        ->where('status', 'final')
        ->where('is_recurring', 1);

    $kpis = [
        'total' => (clone $recurringBase)->count(),
        'active' => (clone $recurringBase)->whereNull('recur_stopped_on')->count(),
        'stopped' => (clone $recurringBase)->whereNotNull('recur_stopped_on')->count(),
    ];

    if (request()->header('X-Inertia') || request()->wantsJson()) {
        return \Inertia\Inertia::render('Sells/Subscriptions', [
            'kpis' => $kpis,
            'filters' => [
                'customers' => \Inertia\Inertia::defer(fn () => \App\Contact::customersDropdown($business_id, false)),
            ],
            'permissions' => [
                'update' => auth()->user()->can('sell.update'),
                'delete' => auth()->user()->can('direct_sell.delete') || auth()->user()->can('sell.delete'),
            ],
            'urls' => [
                'datatable' => '/sells/subscriptions?ajax=1',
                'toggle' => '/sells/recurring-toggle',
                'back' => '/sells',
            ],
        ]);
    }

    return view('sale_pos.subscriptions');
}
```

### 2. Criar `Pages/Sells/Subscriptions.tsx`

```tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import { Button } from '@/Components/ui/button';
import { Repeat, Play, Pause, FileText, Plus } from 'lucide-react';

interface SubscriptionRow {
  id: number;
  transaction_date: string;
  invoice_no: string;
  subscription_no: string | null;
  customer_name: string | null;
  business_location: string;
  recur_stopped_on: string | null;
  recur_interval: number;
  recur_interval_type: 'days' | 'months' | 'years';
  recur_repetitions: number | null;
  invoices_count: number;
  last_generated: string | null;
  upcoming_invoice: string | null;
}
```

### 3. Tabela (8 cols)

| Coluna | Source | Notas |
|---|---|---|
| Data início | `transaction_date` | format BRT |
| Nº cobrança | `subscription_no` ?? `invoice_no` | clicar abre drawer SaleSheet |
| Cliente | `customer_name` | |
| Local | `business_location` | |
| Intervalo | `recur_interval` + `recur_interval_type` | "30 dias", "Mensal" |
| Próxima fatura | `upcoming_invoice` calc backend | data |
| Faturas geradas | `invoices_count` | counter `Receipt` icon |
| Ações | Toggle start/stop + Editar + Excluir | dropdown |

### 4. Toggle start/stop

```tsx
const toggleSubscription = (id: number) => {
  router.post(`${urls.toggle}/${id}`, {}, {
    preserveScroll: true,
    onSuccess: () => router.reload({ only: ['rows', 'kpis'] }),
  });
};
```

## 4. Tokens — Cockpit V2

## 5. Estados

| Estado | UI |
|---|---|
| `active` | badge `bg-emerald-50 text-emerald-700` "Ativa" |
| `stopped` | badge `bg-amber-50 text-amber-700` "Pausada" |
| `empty` | `<EmptyState/>` "Nenhuma assinatura ativa" |

## 6. Responsividade — 1280px

## 7. Atalhos — `N` (nova venda recorrente), `Esc`

## 8. Component contract

```tsx
interface SubscriptionsPageProps {
  kpis: { total: number; active: number; stopped: number };
  filters: { customers?: Record<number, string> };
  permissions: { update: boolean; delete: boolean };
  urls: { datatable: string; toggle: string; back: string };
}
```

## 9. DoD

- [ ] Permission gate `sell.view`/`direct_sell.access`
- [ ] Tier 0 `business_id` (filtra `is_recurring=1`)
- [ ] AJAX DataTables back-compat (preservar `listSubscriptions` AJAX branch)
- [ ] Defer customers dropdown
- [ ] Pest baseline + cross-tenant + toggle action

## 10. Pegadinhas

- ❌ NÃO esquecer `permitted_locations` scoping (assinatura linkada a location)
- ❌ NÃO usar `Carbon` factory inválido — tipo `recur_interval_type` é enum
- ❌ NÃO permitir delete em assinatura ativa sem confirmação (tem invoices geradas)

## 11. Cutover

- Smoke biz=1 venda recurring (is_recurring=1) → /sells/subscriptions → toggle stop → toggle start
- Canary 7d
- Remover Blade após 30d

---

**Última atualização:** 2026-05-15 (Wave 1 Agent W1-A)
