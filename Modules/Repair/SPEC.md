# SPEC — Modulo Repair

> Status: legado (UltimatePOS upstream). Em uso ativo. Recomendacao: **manter**.

## Proposito

Gestao de OS (ordens de servico) para reparos de equipamentos: cadastro de
modelos de dispositivo, statuses configuraveis, job sheets com pecas, fluxo
de impressao (etiqueta + customer copy) e endpoint **publico** para o cliente
final consultar o status da OS via numero de OS / NF / celular.

## Fluxos principais

1. **Tecnico cria JobSheet** (`/repair/job-sheet`) -> seleciona contato + device + checklist
2. **Tecnico edita status** (`/repair/job-sheet/{id}/status`) -> dispara notificacao
3. **Cliente consulta** publicamente em `/repair-status` (sem auth) digitando o
   numero da OS (`job_sheet_no`) — agindo como token compartilhado
4. **Faturamento** vira Sale via integracao `repair_job_sheets.transaction_id`

## Rotas publicas

| Verbo | Rota | Controller / metodo |
|---|---|---|
| GET  | `/repair-status` | `CustomerRepairStatusController@index` |
| POST | `/post-repair-status` | `CustomerRepairStatusController@postRepairStatus` |

`postRepairStatus` so responde a XHR (`$request->ajax()`), com payload:

```json
{ "search_type": "job_sheet_no|invoice_no|mobile_num", "search_number": "<token>" }
```

Retorno padrao: `{ success: true|false, msg: string, repair_html?: string }`.

## Rotas autenticadas (prefix `/repair`)

Stack: `web, authh, auth, SetSessionData, language, timezone, AdminSidebarMenu`.

- `repair` resource (CRUD - exceto create/edit)
- `status` resource (CRUD - exceto show)
- `device-models` resource (CRUD - exceto show)
- `dashboard` resource
- `job-sheet` resource + endpoints custom (`add-parts`, `save-parts`, `print-label`, `upload-docs`, `delete-image`, etc.)
- `repair-settings` index/store + `update-repair-jobsheet-settings`
- Install hooks (`/install`, `/install/uninstall`, `/install/update`)

## Entities-chave

- `JobSheet` (`repair_job_sheets`) — heart of the module
- `RepairStatus` — status configurable per business
- `DeviceModel`, `RepairChecklist`, `JobSheetPart`

## Testes (este PR)

- `Modules/Repair/Tests/Feature/RepairStatusPublicTest.php` — token publico valido vs invalido + contrato XHR.

## Pendencias / decisoes pendentes

- Endpoint publico nao tem rate-limit nem captcha; oportunidade de hardening.
- Notificacoes por SMS/email dependem de gateways do `Modules/Connector` e `Modules/Essentials` — sem mock nos testes.
