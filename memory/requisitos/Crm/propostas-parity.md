---
id: requisitos-crm-propostas-parity
titulo: Paridade de migração — /crm/proposals (Propostas) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-13'
tela: /crm/proposals
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-propostas.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# Paridade — Propostas (`/crm/proposals`)

## Metadados

- **Tela React:** `Modules/Crm/Resources/js/Pages/Crm/Propostas/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `Modules/Crm/Resources/views/proposal/{index,show}.blade.php`
- **Controller:** `Modules\Crm\Http\Controllers\ProposalController` — `index()` (lista + DataTable), `show()` (modal);
  envio e modelo em `ProposalTemplateController` (fora do prefixo)
- **Rota:** a mesma `Route::resource('proposals')` sob `/crm`; a Blade segue com a flag desligada
- **Auditado em:** `2026-10-13` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Lista só as propostas do negócio | ✅ UC-CRMPRO-03 | `index()` `crm_proposals.business_id` | **alta** (Tier 0) | `CrmPropostasBaselineTest` |
| 2 | Quem não é `Admin#` vê só as que enviou | ✅ UC-CRMPRO-04 | `index()` `sent_by` | **alta** | `CrmPropostasBaselineTest` |
| 3 | Detalhe de proposta de outro negócio não abre | ✅ UC-CRMPRO-03/04 | `show()` `business_id` | **alta** (Tier 0) | `CrmPropostasBaselineTest` |
| 4 | Colunas: contato, assunto, enviado por, data | ✅ colunas da lista | `index()` select | média | UC da F3 |
| 5 | Ordem padrão: data decrescente | ✅ `orderByDesc(created_at)` | `aaSorting [[3,'desc']]` | baixa | UC da F3 |
| 6 | Busca por contato/assunto/quem enviou | ✅ busca `q` | DataTable `filterColumn` | média | UC da F3 |
| 7 | Botão Enviar só com modelo + `crm.access_proposal` | ✅ UC-CRMPRO-05 | `index.blade.php` `@if` | média | `CrmPropostasBaselineTest` |
| 8 | Detalhe: enviado por, data, cc, bcc, assunto, corpo, anexos | ✅ drawer (corpo em texto, UC-CRMPRO-06) | `show.blade.php` | média | UC da F3 |
| 9 | Coluna "Anexos" (contagem) | ✅ `withCount(media)` | `crm-blade-telas.jsx` | baixa | UC da F3 |

`⬜ F3` = a coluna React é preenchida no PR da F3, que cita o UC de cada linha `alta`.
