# RUNBOOK — Migração MWART /contacts/{id}/edit → Cliente/Edit (W1-B3)

## 1. Tela
- **Legacy:** Blade `contact.edit` (era ajax-modal)
- **Inertia:** `resources/js/Pages/Cliente/Edit.tsx`
- **Controller:** `ContactController::edit($id)` (linha 768) — branch non-ajax
- **Flag:** `mwart.cliente_edit.enabled`

## 2. Objetivo
Form de edição idêntica visualmente a Create, pré-preenchido + submit PUT.

## 3. Diferença vs Create
- `PUT /contacts/{id}` em vez de `POST /contacts`
- opening_balance ajustado por TransactionUtil
- Sem prefill_name (tem dados já)

## 4. Multi-tenant
- `Contact::where('business_id', $business_id)->findOrFail($id)` no branch Inertia
- Backend `update()` valida ownership

## 5. Variáveis env
```env
MWART_CLIENTE_EDIT=false
MWART_CLIENTE_EDIT_BIZ=1
```

## 6. Pest tests
- `Wave1EditBaselineTest.php`
- `Wave1EditInertiaTest.php`

## 7. Pegadinha
Legacy `edit()` só aceitava ajax (modal). Branch Inertia adiciona full-page response. Coexiste seguro porque flag default OFF.
