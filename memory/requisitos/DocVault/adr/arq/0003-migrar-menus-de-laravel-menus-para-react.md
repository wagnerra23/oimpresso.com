# ADR ARQ-0003 (DocVault) · Migrar menus do `nwidart/laravel-menus` para React

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Desbloqueia**: ADR arq/0002 Fase 2 (P0 blocker)

## Contexto

`nwidart/laravel-menus 6.0.x-dev` vive em fork custom (`github.com/dineshsailor/nWidart-laravel-menus`) sem versão compatível com Laravel 10 no momento. É P0 blocker do upgrade L10.

Como o sistema já migrou ~13 telas pra React/Inertia e tem `LegacyMenuAdapter` alimentando o menu via props, migrar os menus **100% pra React** é o caminho natural.

## Inventário de usos (2026-04-22)

Busca por `Nwidart\Menus`, `laravel-menus`, `MenuBuilder`, `Menu::`:

**18 arquivos** afetados:
- **1 Blade principal**: `resources/views/layouts/partials/sidebar.blade.php` — sidebar legado
- **1 Blade secundário**: `Modules/Crm/Resources/views/layouts/sidebar.blade.php`
- **1 config**: `config/app.php` (registro do provider)
- **2 middlewares**: `app/Http/Middleware/AdminSidebarMenu.php` + `Modules/Crm/.../ContactSidebarMenu.php`
- **1 Kernel**: `app/Http/Kernel.php` (registra middleware)
- **12 DataControllers**: cada módulo (Woocommerce, Superadmin, Repair, Project, ProductCatalogue, Officeimpresso, Manufacturing, Jana, Help, Essentials, Connector, Chat) tem `DataController::modifyAdminMenu()`

## Decisão

**Eliminar `nwidart/laravel-menus` completamente.** Menu passa a ser renderizado apenas via componente React (já parcialmente implementado em `AppShell`).

### Arquitetura alvo

```
┌──────────────────────────────────────────────────┐
│ Controller (qualquer)                            │
│   ↓                                              │
│ LegacyMenuAdapter::buildMenuTree($user)          │
│   → lê permissões Spatie                         │
│   → lê modules_statuses.json                     │
│   → aplica isInertiaRoute() pra cada URL         │
│   ↓                                              │
│ Inertia::share('menu', $tree)                    │
│   ↓                                              │
│ React AppShell.tsx                               │
│   → renderiza sidebar com componentes shadcn     │
└──────────────────────────────────────────────────┘
```

### Migração por fase

**Fase A — Desacoplar registro (1 sessão curta)**
1. Mover lógica de `DataController::modifyAdminMenu()` de cada módulo pra um array declarativo
2. Criar `LegacyMenuAdapter::registerModuleMenus()` que lê esses arrays
3. Remover hooks antigos (`app.menu.show` events)

**Fase B — Substituir rendering (1 sessão)**
1. Expandir `AppShell.tsx` pra renderizar tree completo com sub-menus
2. Remover `resources/views/layouts/partials/sidebar.blade.php` do layout
3. Blade ainda usado em páginas legadas passa a estender layout sem sidebar (Inertia assume)

**Fase C — Limpar dependência (1 sessão curta)**
1. Remover `app/Http/Middleware/AdminSidebarMenu.php` + `ContactSidebarMenu.php`
2. Remover `nwidart/laravel-menus` do `composer.json`
3. Remover entry do `repositories` apontando pro fork `dineshsailor`
4. `composer update nwidart/laravel-menus --remove` efetiva
5. Validar que `php artisan route:list` funciona + UI abre normal

### Como não quebrar no caminho

- **Feature flag** `menu_source=react|legacy` em `config/app.php` durante transição
- Cada controller de módulo continua respondendo mesmo sem `DataController::modifyAdminMenu()` registrado
- Rollback: `composer require nwidart/laravel-menus:6.0.x-dev` reinstala tudo (commit anterior tem config)

## Consequências

**Positivas:**
- Destrava Laravel 10 (P0 resolvido).
- Menu vira single source of truth — hoje é fragmentado em 12 DataControllers.
- UX consistente (hoje sidebar Blade e React têm estilos diferentes).
- Remove uma dependência de fork custom (risco de manutenção).

**Negativas:**
- Telas Blade ainda em uso (repair, accounting, crm) ficam sem sidebar até terminar migração React dessas telas.
- Mitigação: layout Blade de compatibilidade renderiza "Home / Voltar" apenas — pouca fricção.

## Consequências técnicas observáveis

Depois da migração:
- `php artisan docvault:audit-module --all` deve continuar ≥88 (não mexe em docs).
- `MultiTenantIsolationTest` + `SpatiePermissionsTest` devem passar (não dependem de menu).
- Rotas Inertia continuam em `LegacyMenuAdapter::isInertiaRoute()`.

## Alternativas consideradas

- **Forkar `nwidart/laravel-menus` próprio e atualizar**: rejeitado — mais fork pra manter, mesmo problema quando Laravel 11/12 chegar.
- **Manter fork `dineshsailor`**: rejeitado — sem controle sobre timing de updates.
- **Substituir por `laravel-menu` (caouecs)**: rejeitado — mesma categoria de lib Blade-centric.
- **Harmony / adminLTE / Nova menu**: overkill pra caso simples.
