---
page: /cliente/{id} (canon) · /contacts/{id} (legacy dual-render)
component: resources/js/Pages/Cliente/Show.tsx
related_us: [US-CRM-063, US-CRM-064, US-CRM-065, US-CRM-066, US-CRM-067, US-CRM-068, US-CRM-069, US-CRM-070]
owner: wagner
status: deprecated
status_detail: superseded
last_validated: "2026-05-21"
parent_module: Cliente
related_adrs: [110, 107, 93, 94, 104, 149]
tier: A
charter_version: 2
superseded_by: [Pages/Cliente/Index.charter.md v3]
superseded_at: 2026-05-21
superseded_reason: "Paradigma cadastral 8 tabs drawer 760px lateral substitui página full-page operacional (ADR 0179)"
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/clientes-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Show]
  divergence_from_blueprint: "tab-based content area (não no blueprint Cowork original)"
---

> **⚠️ CHARTER SUPERSEDED 2026-05-21** — Esta versão v2 é mantida pra histórico append-only. O paradigma de detalhe de Cliente foi invertido pela [ADR 0179](../../../../memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md): de página full-page para drawer lateral 760px abrindo de `Index.tsx`. Charter ativo agora é [`Index.charter.md`](Index.charter.md) v3.

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
