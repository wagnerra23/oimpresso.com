---
id: requisitos-crm-chamadas-parity
titulo: Paridade de migração — /crm/call-log (Registro de chamadas) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-13'
tela: /crm/call-log
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-chamadas.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# Paridade — Registro de chamadas (`/crm/call-log`)

## Metadados

- **Tela React:** `Modules/Crm/Resources/js/Pages/Crm/Chamadas/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `Modules/Crm/Resources/views/call_logs/index.blade.php`
- **Controller:** `Modules\Crm\Http\Controllers\CallLogController`
- **Protótipo:** `prototipo-ui/cowork/Wagner/crm-blade-telas.jsx` → `Chamadas()`
- **Auditado em:** `2026-10-13` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Na F3? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Sem `enable_crm_call_log` (config) → 403 | ✅ mesmo portão | `index()` | **alta** | F2 baseline |
| 2 | Sem `crm.view_all_call_log` nem `view_own_call_log` → 403 | ✅ | `index()` | **alta** | F2 baseline |
| 3 | Lista só registros do negócio da sessão | ✅ mesma consulta | `where('crm_call_logs.business_id')` | **alta** | F2 baseline (T0) |
| 4 | Sem `view_all_call_log` → só os registros que criou | ✅ | `where('created_by')` | **alta** | F2 baseline |
| 5 | Filtro Contato | ✅ | `contact_id` | média | F2 baseline |
| 6 | Filtro "Registro criado por" (`created_by`) | ✅ | `user_id` | média | F2 baseline |
| 7 | Filtro intervalo de datas (`start_time`) | ✅ | `start_time`/`end_time` | média | UC da F3 |
| 8 | Colunas: início · término · duração · tipo · nº do contato (+ nome no celular) · contato (+ empresa) · usuário · criado por | ✅ | `index.blade.php:45-52` | média | UC da F3 |
| 9 | Seleção de linhas + "Excluir selecionados" só para `is_admin` | ✅ | `index.blade.php:42,55-63` | média | UC da F3 |
| 10 | Exclusão em massa só dentro do negócio da sessão | ✅ mesma rota | `massDestroy()` | **alta** | F2 baseline (T0) |
| 11 | Busca textual (contato, número, usuários) | ✅ | `filterColumn` | baixa | UC da F3 |
| 12 | Protótipo: tipo como pílula (Recebida / Efetuada / Perdida) | ✅ novo | `Chamadas()` | baixa | UC da F3 |
| 13 | Protótipo: rodapé "N registro(s) selecionado(s)" | ✅ novo | `Chamadas()` | baixa | UC da F3 |

## Achados (não corrigidos neste PR)

- **B1.** A tela está desligada em todos os ambientes: `config/constants.php:83` fixa `enable_crm_call_log => false` (sem `env()`). Migrar a tela não a liga; ligar é decisão [W].
- **B2.** `massDestroy` checa só `crm_module`; não exige `is_admin` nem `view_all_call_log`. O botão some na Blade para não-admin, mas a rota aceita: quem só vê os próprios apaga o de colega do mesmo negócio pelo id.
- **B3.** `allUsersCallLog` (widget do painel) não checa permissão nenhuma além de ser ajax: qualquer usuário logado do negócio lê a contagem de chamadas de todos.

## Fora de escopo da F3
Criação de registro (vem do app pela API) e o widget `allUsersCallLog` do painel.
