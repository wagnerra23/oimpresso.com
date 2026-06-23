---
page: /sells/{id}/edit
component: resources/js/Pages/Sells/Edit.tsx
owner: wagner
status: draft
status_detail: wave1-draft
last_validated: "2026-05-15"
parent_module: Sells
related_adrs: [104, 107, 143, 149, 93]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/vendas-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG pendente verificar Wagner (ADR 0149)"
  derived_screens: [Edit]
  divergence_from_blueprint: "Pre-fill via form deferred; submit PUT; guards canBeEdited/isReturnExist preservados como 422 JSON."
---

# Page Charter — /sells/{id}/edit

> **Status:** wave1-draft. Migração MWART Wave 1 W1-A (2026-05-15).
> Reusa form pattern de `Sells/Create.tsx` via ADR 0149.

---

## Mission

Editar venda existente — produtos, descontos, pagamento, frete — preservando bloqueios de negócio legacy (`canBeEdited`, `isReturnExist`) e FSM safety (trait `GuardsFsmTransitions` ADR 0143). Substitui `sell.edit.blade.php` legacy.

---

## Goals — Features (faz)

- AppShellV2
- Header h1 24px "Editar venda #{invoice_no}" + status atual + stage FSM
- Form deferred via `Inertia::defer()` — pre-fill aguarda payload pesado (sell_details join 6 tables)
- `<Deferred data="form" fallback={<FormSkeleton/>}>` wrap frontend
- `useForm()` no top-level, re-populado via `useEffect` quando form chega
- 5+ seções: Dados venda · Produtos (com IMEI + desconto R$/% per-line) · Pagamento · Resumo · Responsável/Notas/Anexos · Frete (colapsável `<details>`)
- Footer sticky bottom: Cancelar + Salvar
- Atalho `⌘+Enter` chama handleSubmit programaticamente (passa products[] no payload — PR parking-lot P3)
- Auto-save draft localStorage debounced 500ms — key `oimpresso.sells.b{biz}.u{user}.edit.{id}.draft` TTL 24h, com botão "Descartar rascunho" e descarte automático se `transaction.updated_at > draft.savedAt` (PR parking-lot P2)
- Features só-no-Blade preservadas (PR parking-lot P1):
  - IMEI/nº série inline opcional por linha de produto
  - Desconto R$/% toggle per-line (`line_discount_type`)
  - Notas equipe (`staff_note`) separada de `additional_notes`
  - Assinatura recorrente (`is_recurring`) checkbox
  - Responsável/comissionado (`commission_agent`) select
  - Anexar documento (`sell_document`) — .pdf/.csv/.zip/.doc/.docx/.jpg/.png · máx 5MB · multipart via POST+_method=put
  - Endereço cobrança ≠ entrega (`customer_secondary_address`) textarea
- Multi-tenant Tier 0 backend (ADR 0093) — draft key inclui `bizId.userId.saleId`
- FSM safety: NUNCA setar `current_stage_id` no useForm

---

## Non-Goals — Features (NÃO faz)

- ❌ Mudança de status pra cancelled/completed direto (FSM via ActionPanel)
- ❌ Edição de venda com return associada → backend 422
- ❌ Edição após transaction_edit_days expirar → backend 422
- ❌ Edição de venda doutra biz (Tier 0 firstOrFail → 404)

---

## UX Targets

- p95 first-paint (headline) < 800ms
- p95 form deferred render < 1500ms
- 0 erros JS console
- 1280px sem scroll horizontal
- Footer sticky permanece visível
- Submit click → response < 1.2s

---

## UX Anti-patterns

- ❌ Form vazio mostrado antes de form chegar (use skeleton)
- ❌ Submit sem confirmar return_exist guard (backend trata)
- ❌ Mexer FSM direto via useForm
- ❌ Cor crua
- ❌ `font-bold` em h1
- ❌ AppShell sem V2

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/sells/{id}/edit` (X-Inertia) | Inertia render Sells/Edit (headline eager + form deferred) |
| GET | `/sells/{id}/edit` (sem X-Inertia) | Blade `sell.edit` (fallback) |
| GET | `/sells/{id}/edit` se return_exist | 422 JSON com msg |
| GET | `/sells/{id}/edit` se edit_days expirou | 422 JSON com msg |
| PUT | `/sells/{id}` | UPDATE transaction (rota legacy) |

---

## Tests anti-regressão

- [tests/Feature/Sells/Wave1EditBaselineTest.php](../../../../tests/Feature/Sells/Wave1EditBaselineTest.php) — 9 estruturais (baseline F2)
- [tests/Feature/Sells/Wave1EditInertiaTest.php](../../../../tests/Feature/Sells/Wave1EditInertiaTest.php) — Inertia render + cross-tenant + FSM safety

---

## Cutover plan (parent agent executa)

- Smoke biz=1 venda recém-criada → /edit → ajustar 1 produto → save
- Canary 7d
- Remover Blade `sell.edit.blade.php` após 30d

---

## Refs

- [ADR 0149](../../../../memory/decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- [ADR 0143 FSM Pipeline](../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [RUNBOOK-edit.md](../../../../memory/requisitos/Sells/RUNBOOK-edit.md)
- [edit-visual-comparison.md](../../../../memory/requisitos/Sells/edit-visual-comparison.md)
- Parent visual: `resources/js/Pages/Sells/Create.charter.md`
