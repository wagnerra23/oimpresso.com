# SPEC — Módulo BI (Business Intelligence)

> Origem: `Modules/BI`. Stack: Laravel 5.8 + Passport (OAuth2 clients).

## Objetivo
Servir como bridge OAuth2 entre o ERP e o app móvel/integrações. Expõe:
- UI web em `/bi/api` e `/bi/client` para gerenciar OAuth clients;
- API REST em `/bi/api/*` autenticada com `auth:api` (Passport).

## Rotas públicas
Definidas em `Modules/BI/Http/routes.php`. Três grupos:

1. **Web (auth + SetSessionData)** — prefixo `bi/`:
   - `GET /bi/api` → BIController@index (view bi::index)
   - `RESOURCE /bi/client` → ClientController (CRUD de OAuth clients)
   - `GET /bi/regenerate` → ClientController@regenerate

2. **API (auth:api)** — prefixo `bi/api/`:
   - business-location, contactapi, unit, taxonomy, brand, product, tax,
     table, user, types-of-service, sell, sell-return, expense,
     cash-register, payment-accounts/methods, business-details,
     profit-loss-report, product-stock-report, notifications,
     active-subscription, packages, attendance/clock-in-out.

3. **Install (auth + CheckUserLogin)** — prefixo `bi/`:
   - install/install-update/install-uninstall.

## Tenancy / Authorization
- **business_id**: idem Essentials — lido da sessão.
- **superadmin gate**: ClientController exige `auth()->user()->can('superadmin')`
  em todos os métodos.
- **Passport**: clientes OAuth são filtrados por `users.business_id`.

## Cobertura de testes (lote 5)
- `BIControllerTest` — index auth.
- `ClientControllerTest` — index/show/store/update/destroy/regenerate auth.
- `InstallControllerTest` — install/install-post/update/uninstall auth.

Todos estendem `BITestCase` em `Modules/BI/Tests/Feature/`.

## TODO
- Testes da API Passport (`Modules/BI/Http/Controllers/Api/*`) — exigem
  fixtures de oauth_clients + token grant; deferidos para um lote
  dedicado.
- Teste do gate `superadmin` (necessita usuário autenticado com role).
