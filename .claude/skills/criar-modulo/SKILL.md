---
name: criar-modulo
description: Use ao criar novo módulo Laravel modular (nWidart) no oimpresso — qualquer pasta nova em `Modules/<Nome>/`, ou pedido explícito "criar módulo", "novo módulo", "scaffold módulo". Carrega checklist das 8 peças obrigatórias + 3 rotas admin Install (sem elas botão Install fica sem ação) + padrão `Route::has()` pra link público condicional + pegadinhas. Substitui leitura repetida de ADR 0011 + 0024 + receita ADS/Repair.
trust_level: L2
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: 0080
tier: B
parent_adr: 0095
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

E mais 3 fora da pasta do módulo:
- `modules_statuses.json` (raiz) — entrada `"<Nome>": true`
- (Se rotas públicas) link condicional via `Route::has()` em [home_header.blade.php](../../resources/views/layouts/partials/home_header.blade.php) + [auth2.blade.php](../../resources/views/layouts/auth2.blade.php) + [HandleInertiaRequests.php](../../app/Http/Middleware/HandleInertiaRequests.php) `publicRoutes` + `SiteHeader.tsx`
- ⚠️ **`phpunit.xml`** — quando criar a primeira `Tests/Feature/*Test.php` do módulo, adicionar `<directory>./Modules/<Nome>/Tests/Feature</directory>` (e Unit se houver) dentro da `<testsuite name="Feature">`. **Esquecer = testes no repo mas CI nunca roda → falsa sensação de cobertura**. Erro recorrente; ver [`memory/requisitos/Infra/RUNBOOK-pest-suite.md`](../../memory/requisitos/Infra/RUNBOOK-pest-suite.md).

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

## ⚠️ Erros frequentes em DataController (pattern UltimatePOS exige formato exato)

**`superadmin_package`** — DEVE retornar **array de arrays com `name` field**, NÃO array com keys string:

```php
// ❌ ERRADO — quebra com "Undefined array key 0" em get_module_names()
public function superadmin_package() {
    return [
        'meu_modulo' => [
            'label'   => '...',
            'default' => false,
        ],
    ];
}

// ✅ CERTO
public function superadmin_package() {
    return [
        [
            'name'    => 'meu_modulo',
            'label'   => '...',
            'default' => false,
        ],
    ];
}
```

Por quê: [`Modules/Accounting/Helpers/general_helper.php:303`](../../Modules/Accounting/Helpers/general_helper.php) faz `$permission[0]['name']` — se você passou key string, `$permission` não tem índice 0.

**Middleware stack das rotas admin** — pattern canônico tem `'authh'` (com duplo h) + `'SetSessionData'`:

```php
// ✅ CERTO (skill criar-modulo §Críticas)
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('meu-modulo')
    ->group(function () { ... });
```

**Rotas Install** usam `index` (não `install`) e URL `install` (não `install/install`):

```php
// ✅ CERTO
Route::get('install',           [InstallController::class, 'index']);
Route::get('install/uninstall', [InstallController::class, 'uninstall']);
Route::get('install/update',    [InstallController::class, 'update']);
```

**`moduleSystemKey()` é lowercase SEM hífen** — DEVE bater com `strtolower($moduleName)`:

```php
// ❌ ERRADO — install grava chave kebab-case no `system` table
protected function moduleName(): string { return 'OficinaAuto'; }
protected function moduleSystemKey(): string { return 'oficina-auto'; }  // ← kebab

// ✅ CERTO
protected function moduleSystemKey(): string { return 'oficinaauto'; }  // ← lowercase sem hífen
```

Por quê: `app/Utils/ModuleUtil.php::isModuleInstalled()` faz `System::getProperty(strtolower($module_name).'_version')` — busca `oficinaauto_version`, NÃO `oficina-auto_version`. Se moduleSystemKey for kebab, install grava chave errada → `isModuleInstalled()` sempre false → DataController nunca rodado → sidebar não monta item → tela `/manage-modules` mostra "Instalar" perpetuamente.

Bug real catalogado 2026-05-13: OficinaAuto + ComunicacaoVisual. Pest `tests/Feature/Modules/InstallControllerKeyConventionTest.php` agora trava CI.

## ⚠️ Schemas DB que controllers acessam — VERIFICAR antes de escrever query

Erros comuns (não chute schema):

- `mcp_memory_documents` — tem coluna `status` direta (varchar20), não `frontmatter_json LIKE '%"status"%'`
- `mcp_audit_log` — usa `ts` como timestamp canonical (não só `created_at`); endpoint é ENUM(7 valores: tools/list, tools/call, resources/list, resources/read, prompts/list, prompts/get, initialize)
- `mcp_skill_approvals` — registra `decision` (approve/reject/request_changes), não `status`. "Pending" semanticamente = `mcp_skill_versions.status='review'`
- `mcp_alertas` — tem `kind` (enum 5 valores: cota_excedida/tool_destrutiva/ip_suspeito/taxa_errors/cliente_externo), NÃO `category`/`severity`/`module`/`detail`
- `mcp_governance_rules.category` — enum (promotion/archival/escalation/retry/budget/review)

**Sempre rodar `DESCRIBE <tabela>` antes de escrever query nova:**

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd ~/domains/oimpresso.com/public_html && PASS=$(grep "^DB_PASSWORD=" .env | cut -d= -f2- | tr -d "\"") && \
   mysql -u u906587222_oimpresso -p"$PASS" u906587222_oimpresso -e "DESCRIBE <tabela>;"'
```

## ⚠️ Translations: pasta `pt/` (não `pt-BR/`) é o pattern UltimatePOS

```
Modules/<Nome>/Resources/lang/
├── pt/
│   └── <alias>.php
└── en/
    └── <alias>.php
```

KB tem ambos `pt/` e `pt-BR/` por histórico, mas TeamMcp/ADS/NFSe canonical é só `pt/` + `en/`.

ServiceProvider.registerTranslations() carrega `__DIR__ . '/../Resources/lang'` — Laravel resolve por locale automático.

## ⚠️ Lição de aprendizado registrada

**Erro real cometido em 2026-05-06 ao criar Modules/Governance:**

1. ❌ Não invoquei skill `criar-modulo` antes de criar — perdi 4 round-trips de bugfix
2. ❌ `superadmin_package` formato errado (key string em vez de `name` field) — Wagner viu 'Undefined array key 0'
3. ❌ Middleware sem `authh` + `SetSessionData`
4. ❌ Rotas Install com `install/install` em vez de só `install`, action `install` em vez de `index`
5. ❌ Queries DB com colunas inventadas (`frontmatter_json`, `mcp_alertas.category`, `mcp_skill_approvals.status`)
6. ❌ Translations só em `pt-BR/` — pattern canonical é `pt/` + `en/`
7. ❌ `moduleSystemKey()` em kebab-case (`'oficina-auto'`) — system table grava chave errada, `isModuleInstalled()` sempre false, sidebar nunca monta item. Catalogado 2026-05-13 (OficinaAuto + ComunicacaoVisual em prod).
8. ❌ **Módulo mergeado mas nunca ATIVADO em runtime** — `modules_statuses.json` sem entrada → nWidart marca `[Disabled]` → `RouteServiceProvider` + `DataController` + `InstallController` nunca executam → bugs latentes invisíveis em CI. Habilitar tempos depois dispara cascata de fatais. Catalogado 2026-05-13: Auditoria merged em PR #474 (semanas antes), só apareceu em prod 4 bugs em sequência ao habilitar (PRs #750→#751→#752→#756→#760).

## ⚠️ Pegadinha #8 — ativar e fumigar ANTES de merge

CI verde NÃO valida módulo Disabled. O Laravel-modules nWidart só registra providers/rotas/menu de módulos `[Enabled]`. Se você cria um módulo sem entrada em `modules_statuses.json` (ou com `"<Nome>": false`), TUDO no `Modules/<Nome>/Providers/`, `DataController`, `InstallController`, `Routes/web.php` permanece **código morto** até alguém ativar — e bugs latentes (typo de namespace, método abstract não implementado, API errada de MenuBuilder etc) passam imunes.

**Antídoto antes do merge:**

```bash
# 1) Garantir entrada em modules_statuses.json
grep -E "\"<Nome>\"\s*:\s*true" modules_statuses.json || echo "FALTA"

# 2) Validar boot real do módulo (catch fatal sem precisar de Pest)
php artisan module:list | grep <Nome>           # deve mostrar [Enabled]
php artisan route:list --path=<prefix>/install  # 3 rotas
php artisan route:list --path=<prefix>          # rotas do módulo

# 3) Smoke runtime mínimo: render sidebar (executa DataController::modifyAdminMenu)
php artisan tinker --execute="
  Auth::loginUsingId(1);
  app('Illuminate\Routing\Router')->dispatch(
    Illuminate\Http\Request::create('/home', 'GET')
  );
  echo 'OK';
"
```

Se algum passo lança fatal, fix ANTES do merge — economiza N PRs de hotfix em cascata.

**Antídoto:** **PRIMEIRO comando ao iniciar criação de módulo: invocar skill `criar-modulo` via tool `Skill`.** Antes de escrever 1 linha de código novo em `Modules/<Nome>/`.

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
