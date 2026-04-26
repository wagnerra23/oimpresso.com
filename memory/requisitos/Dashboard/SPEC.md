# SPEC — Módulo Dashboard

> Origem: `Modules/Dashboard`. Stack: Laravel 5.8 + Blade.

## Objetivo
"Utilizado para mostrar relatórios gerenciais" (`module.json`). Atualmente
o controller é um boilerplate gerado por `nwidart/laravel-modules` e não
implementa lógica de negócio.

## Rotas públicas
Definidas em `Modules/Dashboard/Routes/web.php` e `Routes/api.php`.

| Método | URI            | Handler                            | Middleware (declarado) |
| ------ | -------------- | ---------------------------------- | ---------------------- |
| GET    | /dashboard     | DashboardController@index          | (somente "web" global) |
| GET    | /api/dashboard | closure                            | auth:api               |

## ⚠️ Discrepância encontrada
O grupo `Route::prefix('dashboard')` em `Routes/web.php` **não declara**
`auth` no middleware stack — diferente de Essentials/BI. Comportamento real
depende do `RouteServiceProvider` global. O teste
`DashboardControllerTest::index_responde_com_status_http_valido` apenas
garante que `/dashboard` não retorna 5xx. Antes de promover para
`assertRedirect('login')` é preciso decidir a ACL desejada.

## Cobertura de testes (lote 5)
- `DashboardControllerTest` — sanidade do endpoint.

Estende `DashboardTestCase` em `Modules/Dashboard/Tests/Feature/`.

## TODO
- Definir e aplicar middleware `auth` + permissão à rota `/dashboard`.
- Implementar widgets/relatórios reais (controller hoje só faz `view(...)`).
- Testes de cobertura quando houver lógica.
