---
page: /cliente/{id} (canon) · /contacts/{id} (legacy dual-render)
component: resources/js/Pages/Cliente/Show.tsx
owner: wagner
status: live
last_validated: 2026-05-21
parent_module: Cliente
related_adrs: [0110, 0107, 0093, 0094, 0104, 0149]
tier: A
charter_version: 2
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/clientes/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Show]
  divergence_from_blueprint: "tab-based content area (não no blueprint Cowork original)"
---

# Page Charter — /cliente/{id}

> Backend canon: `ContactController::show($id)`. Pattern reuse blueprint Cowork Index — header pattern + KPI cards idênticos. Wave 2026-05-21 adicionou paridade funcional 5 tabs (US-CRM-063..067) substituindo o "histórico minimal" anterior.

## Mission

Tela completa de detalhe do cliente com paridade funcional ao Blade legacy (`resources/views/contact/show.blade.php`). Header rico + 4 stats + 4 tabs (Extrato, Vendas, Pagamentos, Documentos & Notas) + dropdown ações + sidebar contato.

## Goals

- **Header**: avatar quadrado 56px, nome, doc mascarado, badge tipo, badge "Inativo" condicional, botão **Editar** + dropdown **Ações** (Pagar, Excluir, Deactivate/Activate, Add Discount, atalhos)
- **Stats**: 4 cards (total_invoice, invoice_due, total_purchase, opening_balance) — `Inertia::defer`
- **Tab Extrato (Ledger)**: range datas + Formato 1/2/3 + filtro localização + export PDF/email (`/contacts/send-ledger` + abre `/contacts/ledger`)
- **Tab Vendas**: paginação server-side via Inertia partial reload (`only:['sales']`) + filtros range/status/q
- **Tab Pagamentos**: self-fetch via AJAX `/contacts/payments/{id}` — colunas Data/Ref/Valor/Método/Pago por/Ação
- **Tab Documentos & Notas**: upload + lista + delete + textarea notas autosave
- **Sidebar Contato**: Celular, Fixo, E-mail, Endereço
- Multi-tenant: `App\Contact::where('business_id', ...)` global scope em TODA query (ADR 0093)

## Non-Goals

- ❌ Edição inline do contato (botão "Editar" leva pra `/contacts/{id}/edit`)
- ❌ Histórico de mensagens WhatsApp (vai pra Modules/Whatsapp)
- ❌ Tab Atividades (activity log) — escopo futuro (gap conhecido)
- ❌ Tab Pessoas de contato (sub-contatos) — escopo futuro
- ❌ Tab Assinaturas (recorrência) — escopo futuro
- ❌ Tab Reward Points — escopo futuro

## UX Targets

- p95 first-paint header < 600ms
- p95 stats defer < 800ms
- p95 sales defer < 1200ms (paginated 20/page)
- Switch entre tabs sem fetch quando partial reload `only:['sales']` aplicável

## Automation Anti-hooks

- ❌ Não dispara emails ao abrir
- ❌ Não emite log de "viewed" (privacidade LGPD)
- ❌ Não acessa Contact de outro `business_id`
- ❌ Dados bancários nunca plain (bank_account_number mascarado backend)
- ❌ CPF/CNPJ mascarado via `maskTaxNumber`

## Sub-components

- `_show/PaymentsTab.tsx` (W-A · US-CRM-063)
- `_show/LedgerTab.tsx` (W-B · US-CRM-064)
- `_show/SalesTab.tsx` (W-C · US-CRM-065)
- `_show/DocumentsTab.tsx` (W-D · US-CRM-066)
- `_show/ActionsMenu.tsx` + `_show/AddDiscountModal.tsx` (W-E · US-CRM-067)

## Refs

- Backend: `ContactController::show()` + `buildClienteSalesPaginator()`
- Legacy source: `resources/views/contact/show.blade.php`
- Pattern reuse: ADR 0149
- Coordenação paralela: `memory/sessions/2026-05-21-coord-cliente-show-paridade-5waves.md`
