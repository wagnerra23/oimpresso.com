---
slug: sells-runbook-edit
title: "Sells — Runbook da tela Editar venda /sells/{id}/edit (migração MWART)"
type: runbook
module: Sells
status: active
date: 2026-05-15
wave: W1-A (Bucket B1 Sells)
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/vendas-cockpit/"
  blueprint_screenshot_approval: "SYNC_LOG pendente verificar Wagner (ADR 0149)"
  derived_from: "Sells/Create (mesma entidade + mesmo form layout)"
  divergence_from_blueprint: "Edit pre-fill com transaction existente; bloqueia campos se já tem return/edit_days expirou"
---

# RUNBOOK — Editar venda (`/sells/{id}/edit`)

> **Tipo:** runbook MWART (Blade → Inertia/React)
> **Refs:** ADR 0104, ADR 0149, ADR 0143 (FSM trait GuardsFsmTransitions), ADR 0093, ADR 0107
> **Estado origem:** Blade `sell.edit` via `SellController@edit($id)` linha 1689 (300+ LOC controller + 60+ partials).
> **Estado alvo:** `Pages/Sells/Edit.tsx` derivado de `Create.tsx` com props pré-populadas.
> **Persona alvo:** Larissa edita venda dentro do `transaction_edit_days` (config session) — ajusta produtos, descontos, pagamento.

## 1. Objetivo

Migrar `/sells/{id}/edit` mantendo:
- Bloqueios de negócio: `canBeEdited($id, $edit_days)` legacy
- Bloqueios fiscais: `isReturnExist($id)` → não pode editar se já tem devolução
- FSM safety: respeita trait `GuardsFsmTransitions` (NUNCA UPDATE direto em `current_stage_id`)
- Pre-fill completo: produtos, pagamentos, frete, impostos, comissão

## 2. Pré-condições

- [ ] Permissão `direct_sell.update` OU `so.update`
- [ ] `transaction_edit_days` configurado (session)
- [ ] Venda sem return associada (`isReturnExist=false`)
- [ ] Multi-tenant scope `business_id` (ADR 0093)
- [ ] FSM stage da venda permite edit (não está em `cancelled`/`completed`)

## 3. Passo-a-passo

### 1. Alterar `SellController@edit($id)` — dual response

```php
public function edit($id)
{
    if (! auth()->user()->can('direct_sell.update') && ! auth()->user()->can('so.update')) {
        abort(403, 'Unauthorized action.');
    }

    // Guards legacy preservados
    $edit_days = request()->session()->get('business.transaction_edit_days');
    if (! $this->transactionUtil->canBeEdited($id, $edit_days)) {
        if (request()->header('X-Inertia')) {
            return response()->json([
                'success' => 0,
                'msg' => __('messages.transaction_edit_not_allowed', ['days' => $edit_days]),
            ], 422);
        }
        return back()->with('status', ['success' => 0, 'msg' => __('messages.transaction_edit_not_allowed', ['days' => $edit_days])]);
    }

    if ($this->transactionUtil->isReturnExist($id)) {
        if (request()->header('X-Inertia')) {
            return response()->json([
                'success' => 0,
                'msg' => __('lang_v1.return_exist'),
            ], 422);
        }
        return back()->with('status', ['success' => 0, 'msg' => __('lang_v1.return_exist')]);
    }

    $business_id = request()->session()->get('user.business_id');

    // Headline leve eager
    $transaction = Transaction::where('business_id', $business_id)
        ->whereIn('type', ['sell', 'sales_order'])
        ->findOrFail($id);

    if ($transaction->type == 'sales_order' && ! auth()->user()->can('so.update')) {
        abort(403, 'Unauthorized action.');
    }

    if (request()->header('X-Inertia') || request()->wantsJson()) {
        return \Inertia\Inertia::render('Sells/Edit', [
            'saleId' => (int) $id,
            'headline' => [
                'id' => $transaction->id,
                'invoice_no' => $transaction->invoice_no,
                'type' => $transaction->type,
                'status' => $transaction->status,
                'current_stage_key' => optional($transaction->currentStage ?? null)->key,
            ],
            'form' => \Inertia\Inertia::defer(fn () => $this->buildEditFormPayload($id, $business_id)),
            'permissions' => [
                'editPrice' => auth()->user()->can('edit_product_price_from_sale_screen'),
                'editDiscount' => auth()->user()->can('edit_product_discount_from_sale_screen'),
                'update' => true,
            ],
            'urls' => [
                'submit' => '/sells/' . $id,
                'cancel' => '/sells/' . $id,
                'back' => '/sells',
            ],
        ]);
    }

    return $this->buildEditBladeResponse($id, $business_id);
}
```

### 2. `buildEditFormPayload($id, $business_id)` — privado

Encapsula a lógica pesada do controller legacy (sell_details join 6 tables + payment_lines + commission_agents + tax + business_details + redeem_details + customer_due + invoice_schemes + waiters + accounts + shipping_statuses + warranties + statuses + sales_orders) em closure deferida.

### 3. Criar `Pages/Sells/Edit.tsx`

Mesmo padrão do `Create.tsx` (form com filter-pills + sticky footer) **mas com defaults pré-populados via `useForm()`** a partir do `form` deferido.

```tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

interface EditPageProps {
  saleId: number;
  headline: { id: number; invoice_no: string; type: string; status: string; current_stage_key: string | null };
  form?: EditFormPayload;  // deferred
  permissions: { editPrice: boolean; editDiscount: boolean; update: boolean };
  urls: { submit: string; cancel: string; back: string };
}

export default function SellsEdit(props: EditPageProps) {
  // ... usar useForm + watchProps.form (deferred preenche depois)
}
```

### 4. FSM Pipeline safety

⚠️ **NUNCA** alterar `current_stage_id` diretamente. Trait `GuardsFsmTransitions` bloqueia. Edit form trata apenas dados de transação (linhas, pagamentos, etc) — mudança de stage vai pelo `FsmActionPanel` no Show.

### 5. Reuso de componentes Create

`ProductSearchAutocomplete`, `CustomerSearchAutocomplete`, `PaymentRow`, `dropdownEntries`, `<FieldError>` — todos reusados sem cópia.

## 4. Tokens CSS — idem Create

## 5. Estados visuais

| Estado | Trigger | Comportamento |
|---|---|---|
| `loading form` | aguarda defer | `<Deferred fallback={<FormSkeleton/>}>` |
| `bloqueado edit_days` | controller back() | toast erro + redirect /sells |
| `bloqueado return_exist` | idem | toast erro + redirect /sells/{id} |
| `submitting` | put em curso | spinner footer + `aria-busy` |

## 6. Responsividade — idem Create

## 7. Atalhos — idem Create (`/`, `Esc`, `⌘+Enter`)

## 8. Component contract

Ver Edit.tsx interface `EditPageProps`. Tipo `EditFormPayload` espelha 27 props legacy mas camelCase.

## 9. DoD checklist

- [ ] Permission gate `direct_sell.update`/`so.update`
- [ ] `canBeEdited` + `isReturnExist` guards preservados (resposta 422 se header X-Inertia)
- [ ] Multi-tenant Tier 0 em toda query
- [ ] FSM safety: NÃO toca `current_stage_id`
- [ ] Defer payload + Deferred wrap frontend
- [ ] Persistent Layout AppShellV2
- [ ] Reusa Create componentes
- [ ] Pest baseline + Pest cross-tenant passam

## 10. Pegadinhas

- ❌ NÃO permitir submit se `headline.status == 'cancelled'` (UI bloqueia + backend valida)
- ❌ NÃO usar `Transaction::find($id)` sem scope `business_id` — Tier 0 viola
- ❌ NÃO permitir set `current_stage_id` no useForm — trait bloqueia
- ❌ NÃO pode editar venda com return — `isReturnExist` retorna 422 (header X-Inertia)

## 11. Cutover plan (parent executa)

- Smoke biz=1 venda recém-criada → edit → ajustar 1 produto → save
- Canary 7d
- Remover Blade `resources/views/sell/edit.blade.php` após 30d

---

**Última atualização:** 2026-05-15 (Wave 1 Agent W1-A)
