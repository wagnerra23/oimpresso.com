---
name: Shell nav — Menu:: próprio + topnav declarativo
description: Arquitetura de navegação pós-M3; nwidart/laravel-menus substituído por app\Services\Menu + topnav por Resources/menus/topnav.php em cada módulo
type: project
originSessionId: d094ef8e-0702-4f49-b9b9-c6fc2996415c
---
Em 2026-04-23 (M3 Laravel 10 upgrade) removemos `nwidart/laravel-menus` (fork dineshsailor abandonado, não aceita L10+) e implementamos Menu:: próprio + formalizamos o padrão de topnav declarativo. Esta nota explica a arquitetura pra que decisões futuras não quebrem a compatibilidade.

## Menu:: próprio em `app/Services/Menu/`

**Why**: nwidart/laravel-menus (fork `dineshsailor`) travava no L9. Pacote oficial nwidart parou no L8. Nenhum fork público aceita L11+. Escolhemos implementar localmente ao invés de vendorar (opção B sobre opção A, ~2-3h de trabalho).

**How to apply**:

- API drop-in — código existente (`AdminSidebarMenu` middleware, 30 `DataController::modifyAdminMenu()` dos módulos, Blade `Menu::render(...)`) **não mudou**. Só o Facade `Menu` em `config/app.php` aponta pra `App\Facades\Menu`.
- 4 classes principais em `app/Services/Menu/`:
  - `Menu.php` — manager singleton (binding `'menus'`)
  - `MenuBuilder.php` — items do menu (`url/dropdown/route/divider/header/order/whereTitle/findBy`)
  - `MenuItem.php` — item individual (children, active state, hideWhen)
  - `Presenter.php` — base abstrata (sem type hints estritos, compat com `AdminlteCustomPresenter` legado)
- `app/Facades/Menu.php` + `app/Providers/MenuServiceProvider.php` registram o binding.
- Se aparecer método ausente (tipo `whereTitle` quando portamos Superadmin/CRM), adicionar em `MenuBuilder` seguindo o padrão nwidart original — 30 minutos.
- **Divergência intencional**: `MenuItem::getAttributes()` agora renderiza atributos HTML manualmente (sem `Collective\Html\HtmlFacade`) porque `laravelcollective/html` também foi removido no M5.

## Topnav declarativo por módulo

**Why**: Sidebar + topnav são **fontes independentes** (ADR arq/0011). Sidebar vem do `DataController::modifyAdminMenu()` (legado, popula `Menu::`). Topnav React é um array PHP declarativo em `Modules/<Nome>/Resources/menus/topnav.php`.

**How to apply** — adicionar topnav em módulo novo:

1. Criar `Modules/<Nome>/Resources/menus/topnav.php`:
   ```php
   return [
       'label' => 'Nome do Módulo',
       'icon'  => 'IconLucide',
       'items' => [
           ['label' => 'Dashboard', 'href' => '/rota', 'icon' => 'LayoutDashboard', 'can' => 'perm.opcional'],
           // ...
       ],
   ];
   ```
2. `LegacyMenuAdapter::buildTopNavs()` varre automático e expõe em `shell.topnavs.<Nome>` via Inertia.
3. `useAutoModuleNav()` (em `resources/js/Hooks/usePageProps.ts`) detecta qual topnav renderizar pelo root da URL — zero configuração na page. Page só usa `<AppShell>` via `Component.layout` (ver `preference_persistent_layouts.md`).
4. Ícone é nome Lucide string; `can` é permission Spatie opcional.

**Exemplos existentes**: `Modules/PontoWr2/Resources/menus/topnav.php`, `Modules/MemCofre/Resources/menus/topnav.php`, `Modules/Essentials/Resources/menus/topnav.php`.

## Gotcha: inertiaGet em tests

`HandleInertiaRequests::version()` retorna `md5_file(public_path('build-inertia/manifest.json'))`. Se test helper enviar `X-Inertia-Version` literal (`'1'`), o middleware responde 409 + `X-Inertia-Location` → asserções de status 200 falham. Solução em `Modules/PontoWr2/Tests/Feature/PontoTestCase.php::inertiaGet()`: ler o manifest e enviar md5 real.
