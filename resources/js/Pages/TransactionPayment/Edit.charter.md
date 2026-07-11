---
page: /payments/v2/{id}/edit
component: resources/js/Pages/TransactionPayment/Edit.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
status: draft
---

# Charter — `TransactionPayment/Edit.tsx`

> Rota Inertia: `/payments/v2/{id}/edit` · Controller: `TransactionPaymentController::editInertia` · Submit reusa `update($id)` legacy.

## Intent

Editar um pagamento existente (amount/method/paid_on/note/account) em página full ao invés de modal AJAX.

## Diferenças vs Blade legacy

- Blade: modal AJAX `edit($id)` renderiza `transaction_payment.edit_payment_row`
- v2: full page route Inertia
- Mesmo endpoint POST `PUT /payments/{id}` reusado — sem duplicar lógica de update

## Props (server)

- `payment_line: TransactionPayment` — com denominations
- `transaction: Transaction` — com contact + location
- `payment_types: Record<string, string>` — { cash: 'Cash', card: 'Card', ... } per location
- `accounts: Array<{ id, label }>` — dropdown contas bancárias

## UX

- **Header card:** Cliente/Fornecedor + Ref transação + Total
- **Form fields:**
  - Method (select payment_types)
  - Paid On (date)
  - Amount (number, BRL mask)
  - Account (select accounts, opcional)
  - Note (textarea)
- **Conditional fields by method:**
  - card: card_holder_name + card_number + card_transaction_number
  - cheque: cheque_number
  - bank_transfer: bank_account_number
  - custom_pay_1/2/3: transaction_no
- **Actions:** Salvar (POST PUT) · Cancelar (router.visit /payments/v2)

## Tier 0

- ✅ RBAC: `edit_purchase_payment` OR `edit_sell_payment`
- ✅ Multi-tenant: editInertia já valida Transaction.business_id == session → 404 cross-tenant
- ✅ PT-BR
- ✅ NÃO altera US-RB-044 (lógica NFe-de-boleto-pago segue em jobs/observers)

## Validação cliente

- `amount > 0` (positivo)
- `method` obrigatório
- `paid_on` obrigatório
