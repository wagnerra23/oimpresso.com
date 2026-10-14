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
| 1 | Lista só as propostas do negócio | ⬜ F3 | `index()` `crm_proposals.business_id` | **alta** (Tier 0) | `CrmPropostasBaselineTest` |
| 2 | Quem não é `Admin#` vê só as que enviou | ⬜ F3 | `index()` `sent_by` | **alta** | `CrmPropostasBaselineTest` |
| 3 | Detalhe de proposta de outro negócio não abre | ⬜ F3 drawer | `show()` `business_id` | **alta** (Tier 0) | `CrmPropostasBaselineTest` |
| 4 | Colunas: contato, assunto, enviado por, data | ⬜ F3 | `index()` select | média | UC da F3 |
| 5 | Ordem padrão: data decrescente | ⬜ F3 | `aaSorting [[3,'desc']]` | baixa | UC da F3 |
| 6 | Busca por contato/assunto/quem enviou | ⬜ F3 | DataTable `filterColumn` | média | UC da F3 |
| 7 | Botão Enviar só com modelo + `crm.access_proposal` | ⬜ F3 | `index.blade.php` `@if` | média | `CrmPropostasBaselineTest` |
| 8 | Detalhe: enviado por, data, cc, bcc, assunto, corpo, anexos | ⬜ F3 drawer | `show.blade.php` | média | UC da F3 |
| 9 | Coluna "Anexos" (contagem) | ⬜ F3 (só protótipo) | `crm-blade-telas.jsx` | baixa | UC da F3 |

`⬜ F3` = a coluna React é preenchida no PR da F3, que cita o UC de cada linha `alta`.
