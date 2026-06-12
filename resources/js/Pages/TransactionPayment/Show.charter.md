---
page: /payments/v2/{id}
component: resources/js/Pages/TransactionPayment/Show.tsx
status: draft
---

# Charter — `TransactionPayment/Show.tsx`

> Rota Inertia: `/payments/v2/{id}` · Controller: `TransactionPaymentController::showInertia` · Equivalente `viewPayment()` modal Blade.

## Intent

Mostrar detail completo de um pagamento (header status + amount/method/dates/customer + audit + Print receipt).

## Diferenças vs Blade legacy

- Blade: modal AJAX renderiza `transaction_payment.single_payment_view`
- v2: full page route Inertia com layout AppShellV2

## Props (server)

- `single_payment_line: TransactionPayment` — amount, method, paid_on, payment_ref_no, note, document_path, card_*, cheque_number, transaction_no
- `transaction: Transaction` — com contact + location + transaction_for + business
- `payment_types: Record<string, string>` — labels

## UX

- **Header:**
  - Title "Pagamento {payment_ref_no}"
  - Badge status (paid/partial/due) com cor
  - Botão Print (window.print) · Voltar
- **Card 1 (Contato):** Cliente OR Fornecedor OR Payroll-for — nome, doc fiscal, mobile, email
- **Card 2 (Pagamento):**
  - Valor (BRL grande)
  - Método (label PT)
  - Data pagamento (format datetime BR)
  - Note
  - Campos condicionais por método (card_holder/number, cheque_number, transaction_no etc)
- **Card 3 (Documento anexo):** se `document_path` presente, link Download
- **Trail audit (futuro):** placeholder pro `LogsActivity` — não bloqueante v2

## Tier 0

- ✅ RBAC: permissões payment view (mesmo set que viewPayment)
- ✅ Multi-tenant: showInertia valida Transaction.business_id → 404 cross-tenant (ADR 0093)
- ✅ PT-BR
- ✅ Print-friendly CSS (`.no-print` em botões)
