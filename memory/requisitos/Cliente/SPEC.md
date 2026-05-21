---
module: Cliente
version: "1.0"
status: ativo
owners: [W]
last_updated: 2026-05-21
us_count: 5
---

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

## US ativas

> US canônicas vivem em [`memory/requisitos/Crm/SPEC.md`](../Crm/SPEC.md). Esta seção lista apenas as US tocadas pela tela `/cliente/{id}` (Cliente/Show.tsx).

| ID | Título | Status | Prioridade | Estimate |
|---|---|---|---|---|
| US-CRM-063 | Tab Pagamentos (lista completa de payments do cliente) | todo→done (PR #1298) | p0 | 3h |
| US-CRM-064 | Tab Ledger inline (range + Formato + export PDF/email) | todo→done (PR #1298) | p0 | 5h |
| US-CRM-065 | Tab Vendas DataTable (paginação Inertia partial reload) | todo→done (PR #1298) | p0 | 3h |
| US-CRM-066 | Tab Documents & Note (upload + autosave notas) | todo→done (PR #1298) | p0 | 2h |
| US-CRM-067 | Menu Ações dropdown + Add Discount modal | todo→done (PR #1298) | p0 | 2h |

### Backlog futuro (gaps remanescentes Show ~15%)

- Tab Atividades (activity log) — p1, escopo futuro
- Tab Pessoas de contato (sub-contatos) — p2
- Tab Assinaturas (recorrência) — p3
- Tab Reward Points — p3
- Contact picker header (trocar contato sem voltar) — p2
- Ledger inline 100% (sem abrir Blade legacy ao filtrar) — p2

## Histórico

- **2026-05-21** — Pointer SPEC criado pra resolver MWART gate path mismatch entre `Cliente/` (page) e `Crm/` (módulo canônico). Wave 5 paralela fechou paridade 40%→85% via 5 sub-components em `_show/` (PR #1298). 5 US-CRM-063..067 transicionaram todo→done.

## Referências

- ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0104 — processo MWART canônico (5 fases)
- ADR 0107 — gate F1.5 visual-comparison
- ADR 0149 — pattern reuse Crm
- [`memory/requisitos/Crm/SPEC.md`](../Crm/SPEC.md) — SPEC canônico do módulo
- [`memory/requisitos/Cliente/RUNBOOK-show.md`](RUNBOOK-show.md)
- [`memory/requisitos/Cliente/show-visual-comparison.md`](show-visual-comparison.md)
- [`resources/js/Pages/Cliente/Show.charter.md`](../../../resources/js/Pages/Cliente/Show.charter.md) — charter v2 status live
- PR #1298 — Wave 5 paralela paridade Show

---

_Pointer-spec criado 2026-05-21 pra resolver MWART gate path mismatch — module Crm canon vs page Cliente._
