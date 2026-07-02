---
title: "RUNBOOK — /purchases/{id}/edit (Compras · edição Inertia)"
module: Purchase
tela: Purchase/Edit
owner: F
status: ativo
last_validated: "2026-05-15"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0149-pattern-reuse-mwart-create-edit
spec_ref: memory/requisitos/Compras/SPEC.md
blueprint_cowork: prototipo-ui/prototipos/compras/visual-source.html
---

# RUNBOOK — `purchase/edit` (FORM EDIT)

## 1. Contexto

- **Rota:** `GET /purchases/{id}/edit`
- **Controller:** `App\Http\Controllers\PurchaseController@edit($id)`
- **Blade legacy:** `resources/views/purchase/edit.blade.php`
- **Inertia destino:** `resources/js/Pages/Purchase/Edit.tsx`

## 2. Persona
Maiara/Felipe ajustam compra dentro de `transaction_edit_days`.

## 3. Multi-tenant Tier 0
- `business_id` validado em sessão.
- `transactionUtil->canBeEdited($id, $edit_days)` — gate temporal.
- `transactionUtil->isReturnExist($id)` — bloqueia se devolução já criada.
- Permission `purchase.update` obrigatória.

## 4. Props

| Prop | Tipo | Origem |
|---|---|---|
| `purchase` | objeto serializado (id, ref_no, contact_id, transaction_date, location_id, status, discount_*, tax_*, final_total, shipping_charges, additional_notes, purchase_lines[]) | `Transaction::with(purchase_lines.product, contact, location)` |
| `business_locations` | `Record<id, name>` | `BusinessLocation::forDropdown($business_id)` |
| `taxes` | `{id, name, amount}[]` | `TaxRate::where('business_id')->ExcludeForTaxGroup()->get()` |
| `order_statuses` | `Record<key, label>` | `ProductUtil::orderStatuses()` |
| `currency` | currency_details | idem create |
| `permissions` | idem create | idem |

## 5. Layout
Mesmo layout do Create.tsx, com formulário pré-populado.

## 6. Validação
Mesma do `update()`: status, contact_id, transaction_date, total_before_tax, location_id, final_total.

## 7. POST `/purchases/{id}` (`update()`)
PATCH/PUT em `useForm().put()` ou `post()` com `_method: 'put'`.

## 8. F2 BACKEND BASELINE
Pest `tests/Feature/Purchase/Wave2EditBaselineTest.php` — Blade legacy preservado.

## 9. F3 FRONTEND
Edit.tsx com pré-população via prop `purchase`, mesma estrutura Create.tsx.

## 10. F4 QA
Pest `tests/Feature/Purchase/Wave2EditInertiaTest.php` — estrutural + Tier 0.

## 11. F5 CUTOVER
Dual path `?v=2`.
