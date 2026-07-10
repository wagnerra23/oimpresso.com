# RUNBOOK — Criar novo módulo no oimpresso ERP

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0002](../../decisions/0002-nwidart-laravel-modules.md) (nWidart), [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) (imitar referências), [ADR 0024](../../decisions/0024-instalacao-1-clique-modulos.md) (Install 1-clique), [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) (zero auto-mem)
> **Validado:** Modules/ADS/ (2026-05-03), Modules/ConsultaOs/ (2026-05-04)

Receita pra criar módulo Laravel modular (nWidart v10) no oimpresso garantindo que aparece em `/manage-modules` com botão Install funcional, aparece na sidebar admin se cabível, e roda migrations + System property automaticamente.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Módulo aparece em `/manage-modules` | Login superadmin → Manage Modules — card do módulo visível |
| Botão "Install" tem ação (não vai pra `#`) | Inspecionar `<a href>` no card — deve apontar pra `/<prefix>/install`, não `#` |
| Após Install, entra em `system` | `SELECT * FROM system WHERE key='<modulesystemkey>_version'` retorna versão |
| Aparece na sidebar admin (se DataController.modifyAdminMenu populado) | Login admin → menu lateral mostra item |
| Migrations rodaram | `module:migrate` listado em `migrations` |

## Pré-requisitos

- Ler [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) — imitar `Modules/Jana/`, `Modules/Repair/` ou `Modules/ProjectMgmt/`
- Saber se o módulo terá:
  - Apenas rotas públicas? (ex: ConsultaOs) → DataController stub é OK
  - Sidebar admin? → DataController precisa de `modifyAdminMenu()` populado
  - CRUD multi-tenant? → ativar skill `multi-tenant-patterns`

## Estrutura mínima — 8 peças

```
Modules/<Nome>/
├── module.json              ← provider list
├── composer.json            ← psr-4: "Modules\\<Nome>\\": ""
├── Config/config.php
├── Database/Migrations/     ← (opcional se módulo não tem schema próprio)
├── Providers/
│   ├── <Nome>ServiceProvider.php   ← register config + migrations + lang
│   └── RouteServiceProvider.php     ← mapWebRoutes (+ mapApiRoutes se houver)
├── Http/Controllers/
│   ├── DataController.php           ← OBRIGATÓRIO (3 hooks UltimatePOS)
│   └── InstallController.php        ← OBRIGATÓRIO (extends BaseModuleInstallController)
├── Routes/web.php           ← OBRIGATÓRIO ter as 3 rotas Install (ver §3)
├── Resources/lang/pt-BR/<alias>.php
└── Resources/menus/topnav.php       ← (opcional, só se for ter topnav declarativo)
```

## Passos

### 1. module.json + composer.json

```json
// module.json
{
    "name": "<Nome>",
    "alias": "<alias>",
    "description": "...",
    "keywords": [...],
    "priority": 0,
    "providers": ["Modules\\<Nome>\\Providers\\<Nome>ServiceProvider"],
    "files": []
}
```

```json
// composer.json
{
    "name": "oimpresso/<alias>",
    "description": "...",
    "extra": {
        "laravel": {
            "providers": ["Modules\\<Nome>\\Providers\\<Nome>ServiceProvider"]
        }
    },
    "autoload": {
        "psr-4": { "Modules\\<Nome>\\": "" }
    }
}
```

### 2. ServiceProvider + RouteServiceProvider

Imitar `Modules/ADS/Providers/AdsServiceProvider.php` e `Modules/ADS/Providers/RouteServiceProvider.php`. Mínimo no `<Nome>ServiceProvider`:

```php
public function boot(): void { $this->registerConfig(); }
public function register(): void { $this->app->register(RouteServiceProvider::class); }
```

### 3. ⚠️ Routes/web.php — 3 rotas Install OBRIGATÓRIAS

**Sem essas rotas o `action()` em [Install/ModulesController.php:57](../../../app/Http/Controllers/Install/ModulesController.php) cai no catch e `install_link` vira `'#'` — botão Install fica visível mas SEM AÇÃO** (incidente Wagner 2026-05-04 ao criar Modules/ConsultaOs/).

```php
use Modules\<Nome>\Http\Controllers\InstallController;

Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('<modulo-prefix>')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });
```

Vale mesmo se o módulo só tiver rotas públicas (caso ConsultaOs).

### 4. DataController — 3 hooks UltimatePOS

```php
class DataController extends Controller
{
    public function superadmin_package(): array {
        return [['name' => '<alias>_module', 'label' => '...', 'default' => false]];
    }

    public function user_permissions(): array {
        return [['value' => '<alias>.access', 'label' => '...', 'default' => false]];
    }

    public function modifyAdminMenu(): void {
        // Imitar ADS DataController. Stub vazio é OK se módulo não tem sidebar.
    }
}
```

**Falta de DataController** → módulo não aparece no menu admin (auditoria 2026-04-26).

### 5. InstallController — extends BaseModuleInstallController

```php
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string { return '<Nome>'; }
    protected function moduleSystemKey(): string { return '<alias>'; }   // lowercase
    protected function moduleVersion(): string { return '0.1.0'; }
    protected function successMessage(): string { return '...'; }
}
```

### 6. modules_statuses.json (raiz do projeto)

```json
{
    ...
    "<Nome>": true,
    ...
}
```

Sem essa entrada o nWidart não ativa o módulo.

### 7. Lang file

`Resources/lang/pt-BR/<alias>.php` retornando array. ServiceProvider precisa de `loadTranslationsFrom(__DIR__.'/../Resources/lang', '<alias>')` ou as chaves saem cruas em produção.

### 8. composer dump-autoload + ativar

```bash
composer dump-autoload --no-scripts
# (depois de mergeado em main, rodar no servidor — ver passo 10)
```

## Validação local

```bash
# 1. PHP lint
php -l Modules/<Nome>/Http/Controllers/InstallController.php
php -l Modules/<Nome>/Routes/web.php

# 2. Rota Install resolvida pelo action()
php artisan route:list --path=<prefix>/install
# Deve listar 3 linhas — index, uninstall, update.

# 3. Composer enxerga namespace
composer dump-autoload --no-scripts 2>&1 | grep -i "Modules.<Nome>"
```

Se PR mexe em arquivos React (Pages/Components Inertia):

```bash
npm run build:inertia    # NÃO build comum — esse roda config errado e gera só tailwind
grep -i "Pages/<Nome>" public/build-inertia/manifest.json
```

## Deploy Hostinger (depois de mergear PR)

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'cd ~/domains/oimpresso.com/public_html && git pull && composer install --no-dev=false && composer dump-autoload --no-scripts'
```

⚠️ Se PR alterou `composer.json/lock`: rodar `composer install` é OBRIGATÓRIO (auto-mem `reference_composer_install_obrigatorio_pos_deploy`). Quick-Sync GitHub Action NÃO faz isso. Sintoma de pular = tela branca Inertia (`null.component`).

⚠️ NUNCA rodar `npm install` ou `npm run build` no Hostinger — shared hosting não suporta. Build do front-end é feito local + commitado em `public/build-inertia/`.

## Troubleshooting

| Sintoma | Causa | Fix |
|---|---|---|
| Card aparece mas botão Install vai pra `#` | Faltam as 3 rotas admin Install no Routes/web.php | Adicionar bloco do passo §3 |
| Card NÃO aparece em /manage-modules | Falta entrada em `modules_statuses.json` ou `module.json` inválido | Validar JSON + entrada `"<Nome>": true` |
| Módulo instalado mas sumiu da sidebar | DataController.modifyAdminMenu vazio OU faltando | Imitar DataController do ADS/Repair |
| Labels saem como `<alias>::file.key` cru | ServiceProvider não tem `loadTranslationsFrom` ou `LegacyMenuAdapter` lê literal | Hardcodar PT-BR (NFSe sempre fez assim) — NÃO usar `__('alias::xxx')` em DataController/topnav |
| Inertia retorna `null.component` em prod | `composer install` não rodou pós-deploy | SSH + `composer install` no servidor |
| Bundle Page React não aparece em `manifest.json` | Rodou `npm run build` (config errado) em vez de `npm run build:inertia` | Sempre `npm run build:inertia` pra Inertia |

## Link público condicional (padrão `Route::has`)

Se o módulo expõe rota pública (ex: `/consulta-os`, `/repair-status`) que deve aparecer no header do CMS APENAS quando o módulo está ativo, espelhar o padrão antigo do Repair:

**Blade legado** (`resources/views/layouts/partials/home_header.blade.php` + `auth2.blade.php`):
```blade
@if(Route::has('<rota-nomeada>'))
    <li><a href="{{ route('<rota-nomeada>') }}">Acompanhar pedido</a></li>
@endif
```

**Inertia/React** — adicionar flag em [HandleInertiaRequests::share()](../../../app/Http/Middleware/HandleInertiaRequests.php) chave `publicRoutes`, e ler em `SiteHeader.tsx` via `usePage().props.publicRoutes`. Quando módulo é desativado em /manage-modules, a rota some, `Route::has()` vira false, link some do menu.

## Pegadinhas (descobertas em ADS 2026-05-03 + ConsultaOs 2026-05-04)

- ❌ NÃO usar `__('alias::file.key')` em DataController/topnav — `LegacyMenuAdapter` lê literal, não resolve traduções → labels saem crus.
- ❌ NÃO usar `route('xxx.yyy')` em Pages React — Ziggy não está disponível neste Inertia. Usar strings literais: `href={\`/<prefix>/admin/decisoes/${id}\`}` (padrão `Pages/copiloto/Dashboard.tsx`).
- ❌ NÃO esquecer das rotas admin Install se o módulo tem só rotas públicas — botão fica sem ação.
- ✅ Pra validar página Inertia em prod: Chrome MCP com cookies do user logado + `read_console_messages` pega erros JS instantâneo.

## Referências de imitação canônica

- **Mais recente** (validado 2026-05-04): `Modules/ConsultaOs/` — só rota pública + Install routes
- **Estrutura cheia** (validado 2026-05-03): `Modules/ADS/` — sidebar + admin + service singletons
- **CRUD multi-tenant**: `Modules/Repair/`, `Modules/ProjectMgmt/`, `Modules/Jana/`
- **Spec-driven**: `Modules/NFSe/`
