---
id: requisitos-crm-relatorios-parity
titulo: Paridade de migração — /crm/reports (Relatórios do CRM) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-13'
tela: /crm/reports
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-relatorios.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# Paridade — Relatórios do CRM (`/crm/reports`)

## Metadados

- **Tela React:** `Modules/Crm/Resources/js/Pages/Crm/Relatorios/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `Modules/Crm/Resources/views/reports/index.blade.php` + `report_javascripts.blade.php` + `leads_to_customer_details.blade.php`
- **Controller:** `Modules\Crm\Http\Controllers\ReportController`
- **Protótipo:** `prototipo-ui/cowork/Wagner/crm-blade-telas.jsx` → `Relatorios()`
- **Auditado em:** `2026-10-13` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Na F3? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Sem `is_admin` → 403 | ✅ mesmo método | `index()` | **alta** | F2 baseline |
| 2 | Matriz "Acompanhamentos por usuário": usuário × status + Outros + total | ✅ | `followUpsByUser()` | **alta** | F2 baseline |
| 3 | Por usuário: só usuários do negócio da sessão, sem comissionado | ✅ mesma consulta | `users.business_id` + `is_cmmsn_agnt = 0` | **alta** | F2 baseline (T0) |
| 4 | Por usuário: filtro intervalo de datas (`start_datetime`) e categoria | ✅ | `start_date`/`end_date`/`followup_category_id` | média | F2 baseline |
| 5 | Por usuário: link "Ver" para a lista de acompanhamentos filtrada | ✅ | `editColumn('count_*')` | média | UC da F3 |
| 6 | Matriz "Acompanhamentos por contatos": contato (+ empresa) × status + Outros + total | ✅ | `followUpsContact()` | **alta** | F2 baseline (T0) |
| 7 | "Leads convertidos em cliente": usuário × total | ✅ | `leadToCustomerConversion()` | média | F2 baseline (T0) |
| 8 | Detalhe da conversão: contatos convertidos pelo usuário, mais recente primeiro | ✅ drawer PT-02 | `showLeadToCustomerConversionDetails()` | média | F2 baseline (T0) |
| 9 | Busca por nome nas matrizes | ✅ | `filterColumn` | baixa | UC da F3 |
| 10 | Protótipo: filtros no topo da página | ✅ (aplicados à matriz por usuário) | `Relatorios()` | baixa | UC da F3 |

## Achados (não corrigidos neste PR)

- **C1.** Os 4 endpoints ajax (`followUpsByUser`, `followUpsContact`, `leadToCustomerConversion`, `showLeadToCustomerConversionDetails`) não checam permissão: basta estar logado e mandar `X-Requested-With`. O `is_admin` do `index` só protege a casca. Ficam dentro do negócio da sessão.
- **C2.** A matriz por contato e a de conversão ignoram os filtros de data/categoria (a Blade só tem filtro na de usuário).
- **C3.** O protótipo pede `crm.view_reports`; o código usa `is_admin`. Escolher uma é decisão [W]; a F3 mantém `is_admin`.

## Fora de escopo da F3
Nenhuma escrita: a tela é só leitura.
