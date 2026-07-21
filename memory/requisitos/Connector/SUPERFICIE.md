---
name: "SUPERFÍCIE — Connector"
description: "Índice GERADO dos artefatos do módulo Connector reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Connector
---

# 🗺️ Superfície de código — Connector

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Connector --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Connector/**` + `resources/js/Pages/Connector/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 81 arquivos em 13 papéis.

## Controllers — 30

- [ApiController.php](../../../Modules/Connector/Http/Controllers/Api/ApiController.php)
- [AttendanceController.php](../../../Modules/Connector/Http/Controllers/Api/AttendanceController.php)
- [BaseApiController.php](../../../Modules/Connector/Http/Controllers/Api/BaseApiController.php)
- [BrandController.php](../../../Modules/Connector/Http/Controllers/Api/BrandController.php)
- [BusinessController.php](../../../Modules/Connector/Http/Controllers/Api/BusinessController.php)
- [BusinessLocationController.php](../../../Modules/Connector/Http/Controllers/Api/BusinessLocationController.php)
- [CashRegisterController.php](../../../Modules/Connector/Http/Controllers/Api/CashRegisterController.php)
- [CategoryController.php](../../../Modules/Connector/Http/Controllers/Api/CategoryController.php)
- [CheckUpdateController.php](../../../Modules/Connector/Http/Controllers/Api/CheckUpdateController.php)
- [CommonResourceController.php](../../../Modules/Connector/Http/Controllers/Api/CommonResourceController.php)
- [ContactController.php](../../../Modules/Connector/Http/Controllers/Api/ContactController.php)
- [CallLogsController.php](../../../Modules/Connector/Http/Controllers/Api/Crm/CallLogsController.php)
- [FollowUpController.php](../../../Modules/Connector/Http/Controllers/Api/Crm/FollowUpController.php)
- [ExpenseController.php](../../../Modules/Connector/Http/Controllers/Api/ExpenseController.php)
- [FieldForceController.php](../../../Modules/Connector/Http/Controllers/Api/FieldForce/FieldForceController.php)
- [LicencaComputadorController.php](../../../Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php)
- [OImpressoRegistroController.php](../../../Modules/Connector/Http/Controllers/Api/OImpressoRegistroController.php)
- [ProductController.php](../../../Modules/Connector/Http/Controllers/Api/ProductController.php)
- [ProductSellController.php](../../../Modules/Connector/Http/Controllers/Api/ProductSellController.php)
- [SellController.php](../../../Modules/Connector/Http/Controllers/Api/SellController.php)
- [SuperadminController.php](../../../Modules/Connector/Http/Controllers/Api/SuperadminController.php)
- [TableController.php](../../../Modules/Connector/Http/Controllers/Api/TableController.php)
- [TaxController.php](../../../Modules/Connector/Http/Controllers/Api/TaxController.php)
- [TypesOfServiceController.php](../../../Modules/Connector/Http/Controllers/Api/TypesOfServiceController.php)
- [UnitController.php](../../../Modules/Connector/Http/Controllers/Api/UnitController.php)
- [UserController.php](../../../Modules/Connector/Http/Controllers/Api/UserController.php)
- [ClientController.php](../../../Modules/Connector/Http/Controllers/ClientController.php)
- [ConnectorController.php](../../../Modules/Connector/Http/Controllers/ConnectorController.php)
- [DataController.php](../../../Modules/Connector/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Connector/Http/Controllers/InstallController.php)

## Requests (validação) — 15

- [AcceptDelphiTokenHandshakeRequest.php](../../../Modules/Connector/Http/Requests/AcceptDelphiTokenHandshakeRequest.php)
- [RegisterUserRequest.php](../../../Modules/Connector/Http/Requests/RegisterUserRequest.php)
- [StoreAttendanceApiRequest.php](../../../Modules/Connector/Http/Requests/StoreAttendanceApiRequest.php)
- [StoreCashRegisterApiRequest.php](../../../Modules/Connector/Http/Requests/StoreCashRegisterApiRequest.php)
- [StoreContactApiRequest.php](../../../Modules/Connector/Http/Requests/StoreContactApiRequest.php)
- [StoreExpenseApiRequest.php](../../../Modules/Connector/Http/Requests/StoreExpenseApiRequest.php)
- [StoreFollowUpRequest.php](../../../Modules/Connector/Http/Requests/StoreFollowUpRequest.php)
- [StoreLicencaComputadorRequest.php](../../../Modules/Connector/Http/Requests/StoreLicencaComputadorRequest.php)
- [StoreNotificationDeliveryRequest.php](../../../Modules/Connector/Http/Requests/StoreNotificationDeliveryRequest.php)
- [StoreOauthClientRequest.php](../../../Modules/Connector/Http/Requests/StoreOauthClientRequest.php)
- [StoreProductApiRequest.php](../../../Modules/Connector/Http/Requests/StoreProductApiRequest.php)
- [StoreSellPosApiRequest.php](../../../Modules/Connector/Http/Requests/StoreSellPosApiRequest.php)
- [StoreSyncRequest.php](../../../Modules/Connector/Http/Requests/StoreSyncRequest.php)
- [UpdateContactApiRequest.php](../../../Modules/Connector/Http/Requests/UpdateContactApiRequest.php)
- [UpdateFollowUpRequest.php](../../../Modules/Connector/Http/Requests/UpdateFollowUpRequest.php)

## Middleware — 1

- [CheckDemo.php](../../../Modules/Connector/Http/Middleware/CheckDemo.php)

## Services — 2

- [ContactPayloadValidatorService.php](../../../Modules/Connector/Services/ContactPayloadValidatorService.php)
- [DelphiSyncService.php](../../../Modules/Connector/Services/DelphiSyncService.php)

## Console / Commands — 1

- [ConnectorHealthCommand.php](../../../Modules/Connector/Console/Commands/ConnectorHealthCommand.php)

## Providers — 3

- [AuthConnectorServiceProvider.php](../../../Modules/Connector/Providers/AuthConnectorServiceProvider.php)
- [ConnectorServiceProvider.php](../../../Modules/Connector/Providers/ConnectorServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Connector/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Connector/Routes/api.php)
- [web.php](../../../Modules/Connector/Routes/web.php)

## Migrations (schema) — 1

- [2020_08_18_123107_add_connector_module_version_to_system_table.php](../../../Modules/Connector/Database/Migrations/2020_08_18_123107_add_connector_module_version_to_system_table.php)

## Seeders — 1

- [ConnectorDatabaseSeeder.php](../../../Modules/Connector/Database/Seeders/ConnectorDatabaseSeeder.php)

## Config — 1

- [config.php](../../../Modules/Connector/Config/config.php)

## Views (Blade) — 2

- 2 arquivos em [Modules/Connector/Resources/views/clients/](../../../Modules/Connector/Resources/views/clients) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Testes (Pest) — 9

- 9 arquivos em [Modules/Connector/Tests/Feature/](../../../Modules/Connector/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 13

- [NewPassword.php](../../../Modules/Connector/Notifications/NewPassword.php)
- [BusinessLocationResource.php](../../../Modules/Connector/Transformers/BusinessLocationResource.php)
- [BusinessResource.php](../../../Modules/Connector/Transformers/BusinessResource.php)
- [CommonResource.php](../../../Modules/Connector/Transformers/CommonResource.php)
- [ExpenseResource.php](../../../Modules/Connector/Transformers/ExpenseResource.php)
- [NewContactResource.php](../../../Modules/Connector/Transformers/NewContactResource.php)
- [NewProductResource.php](../../../Modules/Connector/Transformers/NewProductResource.php)
- [NewSellResource.php](../../../Modules/Connector/Transformers/NewSellResource.php)
- [ProductResource.php](../../../Modules/Connector/Transformers/ProductResource.php)
- [SellResource.php](../../../Modules/Connector/Transformers/SellResource.php)
- [SellTransactionResource.php](../../../Modules/Connector/Transformers/SellTransactionResource.php)
- [TypesOfServiceResource.php](../../../Modules/Connector/Transformers/TypesOfServiceResource.php)
- [VariationResource.php](../../../Modules/Connector/Transformers/VariationResource.php)
