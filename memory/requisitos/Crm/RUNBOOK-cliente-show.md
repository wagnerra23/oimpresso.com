# RUNBOOK — Migração MWART /contacts/{id} → Cliente/Show (W1-B3)

## 1. Tela
- **Legacy:** Blade `contact.show`
- **Inertia:** `resources/js/Pages/Cliente/Show.tsx`
- **Controller:** `ContactController::show($id)` (linha 713)
- **Flag:** `mwart.cliente_show.enabled`

## 2. Objetivo
Página de detalhe rica do cliente: header + 4 stats + sidebar contato + tabela histórico transações.

## 3. Inertia::defer obrigatório
- `stats`: agregação total_invoice/invoice_due/etc — query pesada
- `transactions`: últimas 20 — JOIN com transactions

## 4. PII
`tax_number_masked` server-side. Nunca enviar plain.

## 5. Multi-tenant
- `Contact::find($id)` + ownership check via $user_contacts
- `Transaction::where('business_id', ...)` no defer

## 6. Variáveis env
```env
MWART_CLIENTE_SHOW=false
MWART_CLIENTE_SHOW_BIZ=1
```

## 7. Pest tests
- `Wave1ShowBaselineTest.php`
- `Wave1ShowInertiaTest.php`

## 8. Sunset
Após canary 30d: deletar `contact.show.blade.php` + remover branch dual + sunset legacy tabs (`activities`, etc) que migram pra Tabs futuras.
