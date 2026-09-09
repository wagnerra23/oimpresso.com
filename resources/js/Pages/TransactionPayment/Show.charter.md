---
id: resources-js-pages-transaction-payment-show-charter
page: /payments/v2/{id}
component: resources/js/Pages/TransactionPayment/Show.tsx
related_prototype: n/a (herda PT-03 Detalhe; segue o Padrão de Tela)  # 2026-09-09 [C]: declaração de PT herdado, verificada por conteúdo na wave #4109. Mesma medição da irmã `Edit.charter.md`: zero arquivo de design cita esta tela. ⚠️ O PT-03 que ela herda também não tem template renderizado (só PT-01/PT-05/PT-07 têm) — mas, ao contrário do PT-02, o DS TEM os componentes que o PT-03 exige (KpiCard/Timeline/EmptyState/DropdownMenu/Skeleton), então o template é construível hoje. Decisão [W] em §5.7. medido 2026-09-09 pelas 3 pernas (repo inteiro com --hidden, projeto Cowork por ID via DesignSync.list_files, espelho). Ver memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md §5.3 + §5.7.
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
