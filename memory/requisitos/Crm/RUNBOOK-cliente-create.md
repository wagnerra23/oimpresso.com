# RUNBOOK — Migração MWART /contacts/create → Cliente/Create (W1-B3)

## 1. Tela
- **Legacy:** Blade `contact.create`
- **Inertia destino:** `resources/js/Pages/Cliente/Create.tsx`
- **Controller method:** `ContactController::create()` (linha 536)
- **Flag MWART:** `mwart.cliente_create.enabled`

## 2. Objetivo
Form de cadastro de cliente/fornecedor com layout 3xl single-column, 4 seções lógicas (Identificação, Contato, Endereço, Financeiro), validação Inertia useForm.

## 3. Pré-flight
- [x] Pattern reuse Index blueprint Cowork
- [x] ADR 0149 derived_screens: [Create]

## 4. Variáveis env
```env
MWART_CLIENTE_CREATE=false
MWART_CLIENTE_CREATE_BIZ=1
```

## 5. Submit flow
- `useForm.post('/contacts')` mantém endpoint legacy
- Backend `store()` valida + redireciona ou retorna errors
- Pre-fill `?prefill_name=` vindo de Sells/Create autocomplete

## 6. Multi-tenant
- Backend `store()` força `business_id` da sessão
- Validação tax_number único por business

## 7. Pest tests
- `tests/Feature/Cliente/Wave1CreateBaselineTest.php`
- `tests/Feature/Cliente/Wave1CreateInertiaTest.php`

## 8. Rollback
`MWART_CLIENTE_CREATE=false` no .env (instantâneo)

## 9. Sunset legacy
Após canary 30d: deletar `contact.create.blade.php` + remover branch dual.
