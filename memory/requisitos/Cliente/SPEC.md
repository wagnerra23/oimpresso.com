# SPEC — Tela `/cliente/{id}` (Cliente Show React)

> Esta SPEC é um **redirect canon** pro módulo `Crm/` — historicamente as US relacionadas a contato/cliente vivem em `memory/requisitos/Crm/SPEC.md` (ADR 0149 pattern reuse, módulo Modules/Crm). Não duplicar conteúdo aqui.

## US canônicas da tela Show

Ver `memory/requisitos/Crm/SPEC.md` seções US-CRM-063..067 (Wave paridade 5 tabs 2026-05-21):

- **US-CRM-063** Tab Pagamentos
- **US-CRM-064** Tab Ledger inline (range + Formato + export)
- **US-CRM-065** Tab Vendas DataTable
- **US-CRM-066** Tab Documents & Note
- **US-CRM-067** Menu Ações dropdown + Add Discount

## Charter

`resources/js/Pages/Cliente/Show.charter.md` (charter_version 2, status live 2026-05-21).

## RUNBOOK

`memory/requisitos/Cliente/RUNBOOK-show.md` (redirect pra `Crm/RUNBOOK-cliente-show.md`).

## Visual comparison

`memory/requisitos/Cliente/show-visual-comparison.md` (redirect pra `Crm/cliente-show-visual-comparison.md`).

## Backend canon

- Controller: `app/Http/Controllers/ContactController.php::show($id)` + helper `buildClienteSalesPaginator()`
- Routes: `/cliente/{id}` (canon) + `/contacts/{id}` (legacy dual-render via flag `mwart.cliente_show.enabled`)
- Multi-tenant: `business_id` Tier 0 obrigatório (ADR 0093)

---

_Pointer-spec criado 2026-05-21 pra resolver MWART gate path mismatch — module Crm canon vs page Cliente._
