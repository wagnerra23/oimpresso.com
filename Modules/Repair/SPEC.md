# Modules/Repair — SPEC (resumo legado)

> Spec resumida criada no batch de testes 7 (legados). Não é exaustiva
> — descreve apenas as superfícies cobertas pelos Pest tests.

## Propósito

Módulo de oficina/reparo (mecânica, eletrônicos). Permite abrir ordens
de serviço (job sheets), associar dispositivos/marcas/modelos, controlar
status do reparo e expor uma página pública pra cliente final consultar
o status da própria OS.

## Superfícies relevantes

### Pública (sem auth)

- `GET /repair-status` → `CustomerRepairStatusController@index`
  - Renderiza `repair::customer_repair.index` (formulário de busca).
- `POST /post-repair-status` → `CustomerRepairStatusController@postRepairStatus`
  - Body: `search_type` ∈ {`job_sheet_no`, `invoice_no`}, `search_number`, opcional `serial_no`.
  - Retorna JSON `{success, msg, repair_html?}`.
  - **Token público** = nº da OS (job_sheet_no) — não há JWT/HMAC.

### Administrativa (`web + auth + SetSessionData + AdminSidebarMenu`, prefixo `/repair`)

- `resource('/repair', RepairController)` exceto create/edit
- `resource('/status', RepairStatusController)` exceto show
- `resource('/repair-settings', RepairSettingsController)` apenas index/store
- `resource('/device-models', DeviceModelController)` exceto show
- `resource('/dashboard', DashboardController)`
- `resource('/job-sheet', JobSheetController)` + endpoints customizados
- Install: `GET/POST /repair/install`, `/install/uninstall`, `/install/update`

## Entidades chave

- `repair_job_sheets` (JobSheet)
- `repair_statuses` (RepairStatus, com cor)
- `repair_device_models` (DeviceModel)

## Riscos regressivos conhecidos

1. Quebrar `/repair-status` e `/post-repair-status` (clientes finais
   acessam via link em e-mail/SMS).
2. Mudar shape do JSON `success/msg/repair_html` consumido por JS
   na view pública.
3. Alterar middleware do grupo `/repair` (perda de SetSessionData
   quebra multi-tenant).

## Cobertura de testes (batch 7)

- `tests/Feature/Modules/Repair/RepairStatusPublicTest.php`
- `tests/Feature/Modules/Repair/RepairRoutesTest.php`

Filtro: `vendor/bin/pest --filter=Repair`

## Recomendação

**MANTER.** Módulo ativo, legítimo, em uso. Boa candidata a refatoração
no futuro (controller público mistura query builder + view rendering),
mas funciona.
