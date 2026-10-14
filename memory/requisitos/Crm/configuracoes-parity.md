---
id: requisitos-crm-configuracoes-parity
titulo: Paridade de migração — /crm/settings (Configurações do CRM) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-13'
tela: /crm/settings
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-configuracoes.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# Paridade — Configurações do CRM (`/crm/settings`)

## Metadados

- **Tela React:** `Modules/Crm/Resources/js/Pages/Crm/Configuracoes/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `Modules/Crm/Resources/views/settings/index.blade.php`
- **Controller:** `Modules\Crm\Http\Controllers\CrmSettingsController` — `index()` (form), `updateSettings()` (POST)
- **Rotas:** `GET /crm/settings` e `POST /crm/update-settings`; a Blade segue com a flag desligada
- **Auditado em:** `2026-10-13` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Só `superadmin` ou `Admin#` (com `crm_module`) abre e grava | ✅ UC-CRMCFG-02 | `index()`/`updateSettings()` | **alta** | `CrmConfiguracoesBaselineTest` |
| 2 | Grava só no `business` da sessão | ✅ UC-CRMCFG-03 | `Business::where('id', $business_id)` | **alta** (Tier 0) | `CrmConfiguracoesBaselineTest` |
| 3 | Checkbox "Ativar solicitação de pedido" (ausente = desligado) | ✅ UC-CRMCFG-04 | `has('enable_order_request')` | **alta** (liga o portal) | `CrmConfiguracoesBaselineTest` |
| 4 | Prefixo da solicitação de pedido | ✅ UC-CRMCFG-03 | `order_request_prefix` | média | `CrmConfiguracoesBaselineTest` |
| 5 | Form abre com os valores gravados | ✅ UC-CRMCFG-01 | `getCrmSettings()` | média | UC da F3 |
| 6 | Mensagem de sucesso/erro após gravar | ✅ toast global (flash `status`) | `redirect()->back()->with('status')` | baixa | UC da F3 |

`⬜ F3` = a coluna React é preenchida no PR da F3, que cita o UC de cada linha `alta`.
