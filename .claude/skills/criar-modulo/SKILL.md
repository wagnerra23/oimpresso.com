---
name: criar-modulo
description: Use ao criar novo módulo Laravel modular (nWidart) no oimpresso — qualquer pasta nova em `Modules/<Nome>/`, ou pedido explícito "criar módulo", "novo módulo", "scaffold módulo". Carrega checklist das 8 peças obrigatórias + 3 rotas admin Install (sem elas botão Install fica sem ação) + padrão `Route::has()` pra link público condicional + pegadinhas. Substitui leitura repetida de ADR 0011 + 0024 + receita ADS/Repair.
trust_level: L2
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: 0080
---

# Criar módulo Laravel no Oimpresso ERP

## Quando ativa

- Pedido explícito: "criar módulo", "novo módulo", "scaffold um módulo X"
- Edit/Write em qualquer arquivo dentro de `Modules/<Nome novo>/`
- Adição de entrada nova em `modules_statuses.json`

## Fonte canônica completa

[`memory/requisitos/Infra/RUNBOOK-criar-modulo.md`](../../memory/requisitos/Infra/RUNBOOK-criar-modulo.md) — receita reproduzível com troubleshooting.

## Checklist mínimo (não pular nenhum)

Módulo aparecer em `/manage-modules` com botão Install funcional + opcionalmente sidebar exige **8 peças**:

| # | Arquivo | Por quê |
|---|---|---|
| 1 | `module.json` | nWidart enxerga + provider list |
| 2 | `composer.json` | psr-4 `Modules\\<Nome>\\: ""` |
| 3 | `Config/config.php` | mergeConfigFrom + publishes |
| 4 | `Providers/<Nome>ServiceProvider.php` | register Config + lang + RouteServiceProvider |
| 5 | `Providers/RouteServiceProvider.php` | mapWebRoutes (e api se houver) |
| 6 | `Http/Controllers/DataController.php` | 3 hooks: superadmin_package + user_permissions + modifyAdminMenu |
| 7 | `Http/Controllers/InstallController.php` | extends BaseModuleInstallController |
| 8 | `Routes/web.php` | rotas do módulo + **3 rotas admin Install (§críticas)** |

E mais 2 fora da pasta do módulo:
- `modules_statuses.json` (raiz) — entrada `"<Nome>": true`
- (Se rotas públicas) link condicional via `Route::has()` em [home_header.blade.php](../../resources/views/layouts/partials/home_header.blade.php) + [auth2.blade.php](../../resources/views/layouts/auth2.blade.php) + [HandleInertiaRequests.php](../../app/Http/Middleware/HandleInertiaRequests.php) `publicRoutes` + `SiteHeader.tsx`

## §Críticas — 3 rotas admin Install OBRIGATÓRIAS

**Sem isso o botão Install na tela `/manage-modules` fica visível mas SEM AÇÃO** (vai pra `#`). [Install/ModulesController.php:57](../../app/Http/Controllers/Install/ModulesController.php) usa `action()` que precisa de rota registrada apontando pro `InstallController`.

```php
// Modules/<Nome>/Routes/web.php
use Modules\<Nome>\Http\Controllers\InstallController;

Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('<modulo-prefix>')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });
```

Vale **mesmo se o módulo só expõe rotas públicas** (caso ConsultaOs).

## Link público condicional (`Route::has()`)

Se o módulo expõe rota pública (ex: `/consulta-os`, `/repair-status`) que deve aparecer no header do CMS APENAS quando o módulo está ativo:

**Blade legado:** adicionar em [home_header.blade.php](../../resources/views/layouts/partials/home_header.blade.php) + [auth2.blade.php](../../resources/views/layouts/auth2.blade.php):
```blade
@if(Route::has('<rota-nomeada>'))
    <li><a href="{{ route('<rota-nomeada>') }}">Acompanhar pedido</a></li>
@endif
```

**Inertia:** adicionar flag em [HandleInertiaRequests::share() chave `publicRoutes`](../../app/Http/Middleware/HandleInertiaRequests.php) e ler em `SiteHeader.tsx` via `usePage().props.publicRoutes`. Quando módulo é desativado em `/manage-modules`, a rota some, `Route::has()` vira false, link some.

## Referências canônicas pra imitar

| Caso | Imitar |
|---|---|
| Só rotas públicas + Install routes | `Modules/ConsultaOs/` (validado 2026-05-04) |
| Sidebar admin completa + service singletons | `Modules/ADS/` (validado 2026-05-03) |
| CRUD multi-tenant | `Modules/Repair/`, `Modules/Project/`, `Modules/Jana/` |
| Spec-driven | `Modules/NFSe/` |

## Pegadinhas críticas

- ❌ NÃO usar `__('alias::file.key')` em DataController/topnav — `LegacyMenuAdapter` lê literal, não resolve traduções → labels saem crus em prod. Hardcodar PT-BR (NFSe sempre fez assim).
- ❌ NÃO usar `route('xxx.yyy')` em Pages React — Ziggy não está disponível. Usar template literal: `` href={`/<prefix>/admin/${id}`} ``.
- ❌ NÃO esquecer das rotas admin Install se o módulo tem só rotas públicas — botão Install fica sem ação.
- ❌ NÃO rodar `npm run build` (config errado) — sempre `npm run build:inertia` pra gerar Pages no manifest.
- ❌ NÃO esquecer de rodar `composer install` no Hostinger pós-deploy se mexeu em `composer.json/lock` — sintoma: tela branca Inertia (`null.component`).

## Validação local antes de comitar

```bash
# 1. PHP lint
php -l Modules/<Nome>/Http/Controllers/InstallController.php
php -l Modules/<Nome>/Routes/web.php

# 2. Rota Install resolvida
php artisan route:list --path=<prefix>/install
# → 3 linhas (index, uninstall, update). Se menos, action() vai pra #.

# 3. Bundle Inertia (se mexeu em Pages/Components React)
npm run build:inertia
grep -i "Pages/<Nome>" public/build-inertia/manifest.json
```

## Deploy Hostinger pós-merge

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd ~/domains/oimpresso.com/public_html && git pull && composer dump-autoload --no-scripts && php artisan cache:clear && php artisan view:clear'
```

Depois login superadmin → `/manage-modules` → clicar **Install** no card do módulo.

## Refs

- [ADR 0002 — nWidart Laravel Modules](../../memory/decisions/0002-nwidart-laravel-modules.md)
- [ADR 0011 — Alinhamento padrão Jana (imitação)](../../memory/decisions/0011-alinhamento-padrao-jana.md)
- [ADR 0024 — Instalação 1-clique](../../memory/decisions/0024-instalacao-1-clique-modulos.md)
- [Runbook completo](../../memory/requisitos/Infra/RUNBOOK-criar-modulo.md)
