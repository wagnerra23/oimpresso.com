---
title: "RUNBOOK — Criar novo módulo no oimpresso ERP"
owner: W
status: ativo
last_validated: "2026-07-30"
---

# RUNBOOK — Criar novo módulo no oimpresso ERP

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0002](../../decisions/0002-nwidart-laravel-modules.md) (nWidart), [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) (imitar referências), [ADR 0024](../../decisions/0024-instalacao-1-clique-modulos.md) (Install 1-clique), [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) (zero auto-mem)
> **Validado:** Modules/ADS/ (2026-05-03), Modules/ConsultaOs/ (2026-05-04),
> contrato documental confrontado com Modules/VozDoCliente/ (2026-07-30)

Receita pra criar módulo Laravel modular (nWidart v10) no oimpresso garantindo que aparece em `/manage-modules` com botão Install funcional, aparece na sidebar admin se cabível, e roda migrations + System property automaticamente.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Módulo aparece em `/manage-modules` | Login superadmin → Manage Modules — card do módulo visível |
| Botão "Install" tem ação (não vai pra `#`) | Inspecionar `<a href>` no card — deve apontar pra `/<prefix>/install`, não `#` |
| Após Install, entra em `system` | `SELECT * FROM system WHERE key='<modulesystemkey>_version'` retorna versão |
| Aparece na sidebar admin (se DataController.modifyAdminMenu populado) | Login admin → menu lateral mostra item |
| Migrations rodaram | `module:migrate` listado em `migrations` |
| Fronteira e dependências estão declaradas | `memory/requisitos/<Nome>/SCOPE.md` |
| Contrato, estado e inventário estão navegáveis | `BRIEFING.md` + `SPEC.md` + `SUPERFICIE.md` |
| Existe prova executável do comportamento | ao menos um teste em `Modules/<Nome>/Tests/` |
| Catálogo, painel e índices refletem o módulo | validação de ativação termina com exit 0 |

## Pré-requisitos

- Ler [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) — imitar `Modules/Jana/`, `Modules/Repair/` ou `Modules/Forja/`
- Saber se o módulo terá:
  - Apenas rotas públicas? (ex: ConsultaOs) → DataController stub é OK
  - Sidebar admin? → DataController precisa de `modifyAdminMenu()` populado
  - CRUD multi-tenant? → ativar skill `multi-tenant-patterns`

## Estrutura mínima — runtime + contrato verificável

```
Modules/<Nome>/
├── module.json              ← provider list
├── composer.json            ← psr-4: "Modules\\<Nome>\\": ""
├── SCOPE.md                  ← fronteira + depends_on/delegates_to/migrates_to
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
├── Resources/menus/topnav.php       ← (opcional, só se for ter topnav declarativo)
└── Tests/                            ← ao menos uma prova do contrato

memory/requisitos/<Nome>/
├── BRIEFING.md              ← estado factual e próximo passo
├── SPEC.md                  ← contrato funcional / critérios de aceite
└── SUPERFICIE.md            ← inventário gerado; nunca editar à mão
```

`module.json` é o evento de ativação. No mesmo commit, a máquina exige todo o
contrato acima, a entrada em `modules_statuses.json` e as projeções derivadas. Criar
somente a pasta PHP deixa o módulo em estado parcial e reprova o check.

### `CHANGELOG.md` e `README.md` — fora do padrão, e o mesmo nome cobre DOIS eixos

Nenhum dos dois é peça obrigatória: as fontes são `module.json`, `SCOPE.md`,
`BRIEFING.md`, `SPEC.md` e os testes. Módulo novo **não precisa** criar nenhum dos
dois. Eles existem por herança, e quem abre um módulo hoje encontra ambos sem nada
dizendo o que são — foi o que motivou esta seção ([W] 2026-08-10: *"parece que eles
deveriam ir para outro lugar, são perdidos, duplicados, lugar errado"*).

**Quando o mesmo nome aparece nos dois lugares, NÃO é duplicata — são temas distintos:**

| arquivo | registra | exemplo de entrada |
|---|---|---|
| `Modules/<X>/CHANGELOG.md` | **implementação** — o que shipou | *"entradas só após PR mergeado em main"*; Waves de saturação; hardening |
| `memory/requisitos/<X>/CHANGELOG.md` | **requisito/decisão** — o que foi decidido, shipado ou não | `[Unreleased] — spec-ready`; *"Decision — 2026-04-26 (proposta, aguarda aval)"* |

⚠️ **Não "remova a duplicidade" apagando um lado** — apagar destrói um eixo inteiro.
É a mesma armadilha de `memory/dominio/` (singular) × `memory/dominios/` (plural),
que pareciam pasta duplicada e eram **dois donos** ([proibicoes.md §5 2026-07-22](../../proibicoes.md)).

**Medição de 2026-08-10** (recibo datado — se incomodar, re-rode os comandos, não
edite os números). Universo: `git ls-files ':(glob)Modules/*/*.md'` e
`':(glob)memory/requisitos/*/*.md'`:

- **18 pares** com o mesmo `<Modulo>/<arquivo>.md` nos dois lados — 15 `CHANGELOG.md`, 3 `README.md`
- Cobertura: `SCOPE.md` **32/32**, `CHANGELOG.md` 28/32, `README.md` 16/32
- Dono por último toque de **conteúdo** (`git log --numstat`, ignorando churn ≤4 linhas):
  **4 `Modules/` · 9 `memory/requisitos/` · 5 empate** — sem lado vencedor, porque não há disputa

> ⚠️ **Armadilha de medição, registrada porque quase virou veredito:** medir por
> `git log -1` cru dá **18/18 a favor de `memory/requisitos/`** — e está errado. Os 18
> foram tocados no mesmo dia pelo carimbo de `id:` físico em 172 docs
> ([#4729](https://github.com/wagnerra23/oimpresso.com/pull/4729), 2026-07-23), que
> altera 2 linhas de frontmatter. Frescor de arquivo ≠ frescor de conteúdo.

**Por que os `CHANGELOG.md` de `Modules/` pararam:** eles não vieram do padrão — vieram
das Waves de saturação (`Modules/Jana/CHANGELOG.md` nasceu em `feat(governance-v3): Wave 17`;
`Wave28CmsSaturationTest.php` asserta *"CHANGELOG.md tem entrada Wave 28"*, dimensão D3 do
scorecard). A campanha terminou e o artefato ficou: dos 28, **18 pararam em jun/2026, 10 em
jul/2026, nenhum tocado em ago/2026**. O de `Modules/Jana/` declara *"toda US que tocar
`Modules/Jana/` ganha entry aqui"* e, de 2026-06-08 até 2026-08-10, **177 commits tocaram o
módulo e 0 tocaram o CHANGELOG**.

**Quem enforça o quê** (dono é a branch protection + [`gates-registry.json`](../../../scripts/governance/gates-registry.json), não esta linha):
`SCOPE.md` tem `scope-guard.yml` + `bin/check-scope.php` e está em 32/32. `CHANGELOG.md`
e `README.md` de módulo não têm gate próprio. A correlação entre ter gate e sobreviver é
a [ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)
(*derivado+enforçado sobrevive; escrito+lembrado apodrece*) se cumprindo — não é acidente.

**Antes de mover qualquer `.md` de dentro de `Modules/<X>/`:** confira se algo o lê em
runtime. `memory/requisitos/Jana/LICOES-OPERACAO.md` é lido por
`HealthCheckCommand` via `base_path(...)`, e quando o arquivo não está lá o check
`jana_lesson_ledger_graduation` retorna `'ok' => true` com mensagem *"Skipped"* — mover sem
atualizar o path **desliga o check e ele fica verde**
([proibicoes.md §5 2026-07-29](../../proibicoes.md)). A máquina de realocação também não
cobre este caso: `Modules/` não é área declarada em
[`document-placement.json`](../../../scripts/governance/document-placement.json), então o
classificador devolve `review` por construção — ele nunca adivinha.

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
# (depois de mergeado em main, rodar no servidor — ver "Deploy Hostinger")
```

### 9. Declarar e regenerar a documentação

1. Escrever `SCOPE.md`, `BRIEFING.md` e `SPEC.md` com fatos e critérios verificáveis.
2. Declarar relações estruturadas no frontmatter do `SCOPE.md`; por exemplo:

```yaml
depends_on:
  - Financeiro
delegates_to:
  - Fiscal
```

3. Regenerar apenas pelos donos:

```bash
node scripts/governance/module-surface.mjs <Nome> --write
node scripts/governance/catalog-graph.mjs --write
node scripts/governance/system-map.mjs
php artisan module:requirements --index-only
php artisan module:specs --index-only
```

Não editar `SUPERFICIE.md`, `memory/governance/catalog.json`,
`memory/reference/PAINEL-SISTEMA.md` ou os índices na mão. Eles são projeções;
`module.json`, `SCOPE.md`, `BRIEFING.md`, `SPEC.md` e os testes são as fontes.

### 10. Fechar a transação de ativação

Com a base do PR disponível:

```bash
node scripts/governance/documentation-loop.mjs \
  --impact-ref <sha-base> \
  --head-ref HEAD \
  --enforce-activation \
  --json
```

O comando inventaria os arquivos rastreados pelo Git, calcula dependências
transitivas, detecta um novo `module.json` e confere runtime, documentação, teste,
catálogo, painel e índices. Exit diferente de zero significa módulo ainda parcial;
é preciso corrigir o dono apontado e rodar novamente, não suprimir a cobrança.

## Validação local

```bash
# 1. Contrato documental e ativação
node scripts/governance/documentation-loop.mjs --selftest
node scripts/governance/module-surface.mjs --all --check
node scripts/governance/catalog-graph.mjs --check
node scripts/governance/system-map.mjs --check
node scripts/governance/documentation-loop.mjs --impact-ref <sha-base> --head-ref HEAD --enforce-activation --json

# 2. PHP lint — somente no CT 100 (ADR 0062)
php -l Modules/<Nome>/Http/Controllers/InstallController.php
php -l Modules/<Nome>/Routes/web.php

# 3. Rota Install resolvida pelo action() — CT 100
php artisan route:list --path=<prefix>/install
# Deve listar 3 linhas — index, uninstall, update.

# 4. Composer enxerga namespace — CT 100
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
- **CRUD multi-tenant**: `Modules/Repair/`, `Modules/Forja/`, `Modules/Jana/`
- **Spec-driven**: `Modules/NFSe/`
