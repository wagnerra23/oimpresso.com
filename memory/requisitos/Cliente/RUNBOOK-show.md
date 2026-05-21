# RUNBOOK — `/cliente/{id}` (Cliente Show)

> Esta RUNBOOK é um **redirect canon** pra `memory/requisitos/Crm/RUNBOOK-cliente-show.md`. Histórico: módulo canônico = Crm (ADR 0149); page React = `resources/js/Pages/Cliente/Show.tsx`. Não duplicar conteúdo aqui.

## RUNBOOK canônica

→ **[memory/requisitos/Crm/RUNBOOK-cliente-show.md](../Crm/RUNBOOK-cliente-show.md)**

## Estado pós-Wave 2026-05-21 (US-CRM-063..067)

5 sub-components em `resources/js/Pages/Cliente/_show/`:

- `PaymentsTab.tsx` — self-fetch `/contacts/payments/{id}`
- `LedgerTab.tsx` — range + Formato 1/2/3 + export PDF/email via `/contacts/ledger` + `/contacts/send-ledger`
- `SalesTab.tsx` — Inertia partial reload `only:['sales']` via `/cliente/{id}?tab=sales&customer_sales_*=...`
- `DocumentsTab.tsx` — upload via `/post-document-upload`, notas autosave 1500ms `/note-documents`
- `ActionsMenu.tsx` + `AddDiscountModal.tsx` — dropdown header + modal canon

## Smoke pós-deploy

1. SSH prod: `sed -i 's/MWART_CLIENTE_SHOW=false/MWART_CLIENTE_SHOW=true/' .env && php artisan config:cache`
2. Acessar `/cliente/{id}` logado — 4 tabs (Extrato/Vendas/Pagamentos/Documentos) renderizam
3. Tab Vendas: clicar paginator → URL atualiza com `?customer_sales_page=2`, somente prop `sales` recarrega
4. Tab Pagamentos: lista pagamentos do cliente (ou empty state PT-BR)
5. Tab Documentos: upload + autosave nota funcionam
6. Dropdown Ações: Pagar/Excluir/Deactivate visíveis com permissions corretas
7. Multi-tenant: biz=4 não acessa cliente de biz=1 (404)

## Pre-flight rollback

Se quebrar: SSH prod `sed -i 's/MWART_CLIENTE_SHOW=true/MWART_CLIENTE_SHOW=false/' .env && php artisan config:cache`. `/cliente/{id}` volta a renderizar Blade legacy.

## Refs

- PR #1298 — Wave 5 paralela paridade Show
- ADR 0093 multi-tenant Tier 0
- ADR 0104 processo MWART canônico
- ADR 0149 pattern reuse Crm

---

_Pointer-runbook criado 2026-05-21 pra resolver MWART gate path mismatch._
