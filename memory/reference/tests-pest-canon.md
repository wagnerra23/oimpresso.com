---
name: Pest tests no oimpresso — guia consolidado
description: Como rodar/escrever Pest no oimpresso — workflow Modules Pest CI status, YAML traps, SQLite guard pattern, dual-mode SQLite/MySQL, bridge listeners Event::fake, setup local em worktree
type: reference
---
# Pest tests no oimpresso — guia consolidado

Consolidado. Última revisão 2026-05-10.

## 1. Workflow Modules Pest (CI status)

Workflow `Modules Pest` no GitHub Actions roda 5 suites separadas (matrix):
- Pest Arquivos
- Pest NfeBrasil
- Pest Repair
- Pest Vestuario
- Pest ComunicacaoVisual

**Status histórico em main**: RED. Verificado 2026-05-10 — runs ambos `failure` em commits sem mudança que devesse quebrar.

**Causa raiz**: SQLite in-memory no CI **não roda migrations dos modules** (workflow `modules-pest.yml` removeu `migrate` em PR #475 — UPos legacy tem `MODIFY COLUMN`/`ENUM` MySQL-only que travavam o job). Falhas típicas:
```
SQLSTATE[HY000]: General error: 1 no such table: comvis_materiais
SQLSTATE[HY000]: General error: 1 no such table: comvis_os
SQLSTATE[HY000]: General error: 1 no such table: nfe_emissoes
```

**Como interpretar CI de PRs**:
- Confiável: `PHP / Pest (Unit)` (workflow `ci.yml`) + `Frontend / Vite build`
- Ignorar (pré-existente): `Pest Arquivos/NfeBrasil/Repair/Vestuario/ComunicacaoVisual` do workflow `Modules Pest`

Comparar com run mais recente de main pra confirmar pré-existência:
```bash
gh run list --branch main --workflow "Modules Pest" --limit 3 \
  --json databaseId,conclusion,createdAt
```

**Gate real:** Pest local com MySQL Laragon (Wagner regra 2026-05-09 + ADR 0101). Follow-up estrutural: criar migration job em CI ou MySQL service container — issue separada.

## 2. YAML traps em modules-pest.yml

Workflow `.github/workflows/modules-pest.yml` foi corrigido em PR #466 (mergeado 2026-05-10).

**(1) Em-dash e seta em `name:` quebram parser:**
```yaml
# QUEBRA — GitHub renderiza run com nome do file path em vez de "Modules Pest"
- name: "Pest — Modules/${{ matrix.module }}/Tests"

# OK
- name: "Pest ${{ matrix.module }}"
```

**(2) Heredoc multi-line dentro de step `run:` é frágil:**
```yaml
# Heredoc pode falhar dependendo do shell/encoding
run: |
  cat > .env <<'EOF'
  KEY=value
  EOF

# printf é portável e robusto
run: |
  printf '%s\n' \
    'KEY=value' \
    'OTHER=value' > .env
```

**(3) `:memory:` em valor unquoted é ambíguo (colon + space é mapping indicator):**
```yaml
# Python yaml strict rejeita; GitHub Actions mais lenient mas pode falhar
DB_DATABASE: :memory:

# Quote sempre
DB_DATABASE: ":memory:"
```

**(4) Migrate manual no workflow é incompatível com SQLite:**
```yaml
# UltimatePOS tem MODIFY COLUMN MySQL-only que falha em SQLite
- name: Migrate
  run: php artisan migrate --force --no-interaction

# Pular migrate; tests usam markTestSkipped(sqlite) defensivo
```

**Validar localmente antes de push:**
```bash
python -c "import yaml; yaml.safe_load(open('.github/workflows/modules-pest.yml', encoding='utf-8'))"
```

**Sintoma de YAML rejeitado pelo GitHub:** run aparece com `name: ".github/workflows/modules-pest.yml"` (file path) em vez de `name: "Modules Pest"`.

## 3. Pattern SQLite guard em tests Modules (PR #478)

PR #478 (mergeado em main 2026-05-10) estabeleceu o pattern canônico pra skip de tests dependentes de schema MySQL UPos em CI SQLite:

```php
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (Wagner Pest local segue mandatory — ADR 0101)');
    }
    // ... resto do beforeEach
});
```

**Quando usar:**
- Tests que fazem request HTTP completo via `$this->get(...)` que carrega controllers/middlewares UPos
- Tests que dependem de tabelas core (`users`, `business`, `permissions`, `transactions`) sempre presentes em prod
- Default pra qualquer test novo em `Modules/<X>/Tests/Feature/` que faça query DB

**Quando NÃO usar (preferir `Schema::hasTable(...)`):**
- Tests puro-logic que tolerariam SQLite se uma migration específica do módulo rodasse
- Tests onde o gap é tabela específica, não driver inteiro

**Arquivos cobertos por PR #478:**
- `Modules/Arquivos/Tests/Feature/ConsumersTraitTest.php`
- `Modules/ComunicacaoVisual/Tests/Feature/MaterialSeederTest.php`
- `Modules/ComunicacaoVisual/Tests/Feature/MigrationsTest.php`
- `Modules/ComunicacaoVisual/Tests/Feature/MultiTenantTest.php`
- `Modules/NfeBrasil/Tests/Feature/EmitirNfceAoFinalizarVendaTest.php`
- `Modules/Repair/Tests/Feature/ProducaoOficinaRefactorTest.php`

## 4. Pattern dual-mode SQLite/MySQL pra tests com FK

**Validado em** PR #486 (`fix(test): ImportRegrasCsvServiceTest dual-mode`) — 12/12 passed em ambos modos.

### Quando usar

Test que precisa de schema de uma tabela específica e:
- **CI Modules Pest:** SQLite `:memory:` sem migrate
- **Pest local:** MySQL `oimpresso` com schema real + FKs prod

`Schema::dropIfExists` em afterEach é **destrutivo em MySQL** quando outra tabela tem FK referenciando — ex: `nfe_fiscal_rule_tax_rate_links.fiscal_rule_id_foreign` impede drop de `nfe_fiscal_rules`.

### Receita

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        // CI sanity: schema não existe; cria isolado in-memory
        Schema::dropIfExists('<tabela>');
        Schema::create('<tabela>', function ($t) { /* schema mínimo viável */ });
    } elseif (Schema::hasTable('<tabela>')) {
        // MySQL: schema real prod; só limpa rows do biz de teste
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('<tabela_dependente_fk>')) {
            DB::table('<tabela_dependente_fk>')->whereIn('business_id', [1, 99])->delete();
        }
        DB::table('<tabela>')->whereIn('business_id', [1, 99])->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    // Se model tem listener registrado global no Provider que toca outras tabelas:
    Event::fake([<EventoCreated>::class, <EventoUpdated>::class, <EventoDeleted>::class]);
});

afterEach(function () {
    // Mesmo padrão — drop só em SQLite, clean rows em MySQL
});
```

### Convenções

- **biz IDs de teste:** `1` (Wagner WR2, ADR 0101 default) + `99` (cross-tenant). NUNCA biz=4 (cliente ROTA LIVRE)
- **`FOREIGN_KEY_CHECKS=0`** só em volta da limpeza, **sempre re-habilitar** com `=1`
- **Event::fake** quando model tem listener bridge auto-registrado (ver §5)

### FK reverse map (verificado via INFORMATION_SCHEMA)

| Tabela | FK reverso de | Estratégia dual-mode |
|---|---|---|
| `nfe_fiscal_rules` | `nfe_fiscal_rule_tax_rate_links.fiscal_rule_id` | preserve + cleanup (PR #486) |
| `nfe_certificados` | `nfse_provider_configs.cert_id` | preserve + cleanup (PR #487) |
| `nfe_emissoes` | `nfe_eventos.emissao_id` | preserve + cleanup (PR #488/#490) |
| `business` | ~75 FKs (assets, contacts, products, transactions, etc) | **NÃO drop**; skip ou usar real biz=1 |
| `tax_rates` | `products.tax`, `transactions.tax_id`, `purchase_lines.tax_id`, `transaction_sell_lines.tax_id`, `business.default_sales_tax`, `group_sub_taxes` (~8 FKs) | **skip-em-MySQL** (PR #490 SyncFiscalRuleToTaxRateTest) |
| `arquivos` | (nenhum reverso) | drop OK em SQLite; preserve em MySQL |

### Aplicado em batch (PRs #487-490, sessão 2026-05-10)

8 test files NfeBrasil cobertos. Suite completa NfeBrasil em MySQL: **107→79 failed (-28), 50→133 passed (+83)**.

## 5. Bridge listeners em SQLite (Event::fake)

**Bug encontrado** sessão 2026-05-10 PR #486 — `ImportRegrasCsvServiceTest::aplicar()` retornava `criadas=0, falhas=2` ao invés de `criadas=2, falhas=0`.

### Cadeia de eventos

1. `aplicar()` chama `NfeFiscalRule::create($linha)` (em `Modules/NfeBrasil/Services/Tributacao/ImportRegrasCsvService.php`)
2. Eloquent boot do `NfeFiscalRule` dispara `static::created → FiscalRuleCreated::dispatch($rule)` (em `Modules/NfeBrasil/Models/NfeFiscalRule.php`)
3. `Event::dispatch` invoca listener registrado global em `NfeBrasilServiceProvider::boot()`:
   ```php
   Event::listen(FiscalRuleCreated::class, [SyncFiscalRuleToTaxRate::class, 'handleCreated']);
   ```
4. Listener tenta `DB::table('nfe_fiscal_rule_tax_rate_links')->where(...)->first()` (ADR ARQ-0005 bridge)
5. CI Modules Pest pós-PR #475 removeu `migrate` do workflow → tabela ausente → QueryException
6. `aplicar()` envolve em try/catch genérico → conta como `falha` (não loga, não re-throwa)

### Fix canônico — `Event::fake` em tests de domain

Tests que validam **lógica do método** (counts, retornos, side effects DIRETOS) devem isolar dos listeners bridge:

```php
beforeEach(function () {
    Event::fake([
        FiscalRuleCreated::class,
        FiscalRuleUpdated::class,
        FiscalRuleDeleted::class,
    ]);
});
```

Listener tem cobertura **PRÓPRIA** em `SyncFiscalRuleToTaxRateTest` — não duplica.

### Regra

Se Eloquent model tem `static::created/updated/deleted` no boot disparando event público e Provider registra listener via `Event::listen`, tests de domain do model devem `Event::fake([...])` esse evento.

### Anti-pattern

```php
// NÃO: criar bridge tables in-memory pra tests de domain
beforeEach(function () {
    Schema::create('nfe_fiscal_rule_tax_rate_links', ...);
    Schema::create('tax_rates', ...);
});
```

Aumenta acoplamento, schema test diverge de prod, listener test fica redundante.

## 6. Setup Pest local em worktree

Worktrees em `.claude/worktrees/<nome>/` (criadas pelo harness Claude Code) **não têm `vendor/`, `.env`, `storage/`** — são ignoradas pelo `.gitignore` raiz. Rodar `vendor/bin/pest.bat` direto falha.

### Setup completo (1 vez por worktree)

```powershell
# Junctions pro vendor + node_modules do repo principal
cmd /c "mklink /J vendor D:\oimpresso.com\vendor"
cmd /c "mklink /J node_modules D:\oimpresso.com\node_modules"

# Copia .env (NÃO link — env é stateful)
Copy-Item "D:\oimpresso.com\.env" .env

# Cria storage dirs vazias (Laravel exige existirem)
New-Item -ItemType Directory -Path `
  "storage\framework\views","storage\framework\cache\data", `
  "storage\framework\sessions","storage\framework\testing", `
  "storage\app\public","storage\logs","bootstrap\cache" `
  -Force | Out-Null
```

Junction (`mklink /J`) NÃO precisa admin no Windows; symbolic link (`mklink /D`) precisa.

### Rodar Pest

```powershell
# Suite básica (SQLite in-memory default)
& ".\vendor\bin\pest.bat" tests/Unit/ --no-coverage

# Forçar MySQL pra tests Feature que precisam migrations core UltimatePOS
$env:DB_CONNECTION="mysql"
$env:DB_DATABASE="oimpresso"
& ".\vendor\bin\pest.bat" Modules/Ponto/Tests/Feature/ --no-coverage
```

**Tests Feature de Modules** (Ponto, Financeiro, Repair, Vestuario, ComunicacaoVisual, Arquivos, NfeBrasil) falham em SQLite por gap migrations core UltimatePOS. Precisam MySQL real do Laragon.

### Cleanup quando worktree não importa mais

```powershell
# Remover junctions (NÃO apaga conteúdo do target)
cmd /c "rmdir vendor"
cmd /c "rmdir node_modules"
```

### Tests sempre verdes em qualquer setup

- `tests/Unit/Concerns/HasBusinessScopeTest.php` (Reflection-only)
- `tests/Unit/Guards/*` (Symfony Finder, Reflection)
- `tests/Unit/MultiTenant/*` (Reflection-only)
- `tests/Unit/BusinessIdGuardTest.php` (Symfony Finder)

Sem DB → rodam mesmo em worktree fresh.

## 7. Pest local em PowerShell — env vars precisam ser inline

PowerShell **NÃO persiste env vars entre invocações** do tool Bash/PowerShell — cada chamada é shell fresh. Set-and-run em comandos separados não funciona.

```powershell
# ERRADO — env vars perdidas no segundo comando
$env:DB_CONNECTION="mysql"
vendor\bin\pest <files>
# Pest roda com SQLite (padrão phpunit.xml)

# CERTO — inline no mesmo comando
$env:DB_CONNECTION="mysql"; $env:DB_DATABASE="oimpresso"; $env:DB_HOST="127.0.0.1"; $env:DB_PORT="3306"; $env:DB_USERNAME="root"; $env:DB_PASSWORD=""; vendor\bin\pest <files>
```

`phpunit.xml` declara `<env name="DB_CONNECTION" value="sqlite"/>` sem `force="true"` — env vars OS sobrescrevem se presentes. Sintoma de env não propagada: erro vira `Connection: sqlite, Database: :memory:` ao invés de `Connection: mysql`.

## 8. Recovery dev DB — tabelas órfãs causadas por dropIfExists

Tests com `Schema::dropIfExists` em afterEach que rodam contra MySQL local podem dropar tabela quando run interrompido (Ctrl+C, segfault, FK conflict). Resultado: tabela ausente no DB mas registrada em `migrations` table → próximo `php artisan migrate` pula → schema permanentemente quebrado até intervenção.

**Sintoma:** `SQLSTATE[42S02]: Table '<x>' doesn't exist` em test que NÃO dropa essa tabela.

**Receita** (validada sessão 2026-05-10 PRs #487-490 — 3 tabelas órfãs recuperadas: `nfe_emissoes`, `nfe_eventos`, `nfe_business_configs`):

```php
// scripts/recover-orphan-table.php (one-shot)
$migrations = ['<file_name_sem_php>'];
foreach ($migrations as $m) {
    DB::table('migrations')->where('migration', $m)->delete();
}
// Depois: php artisan migrate --path=Modules/<X>/Database/Migrations --force
```

**Prevenção definitiva:** o pattern dual-mode §4 elimina o problema raiz — nenhum test em MySQL faz `dropIfExists`, só limpa rows com FK_CHECKS=0.

## Refs

- PR #466 — modules-pest.yml YAML fixes
- PR #475 — remove migrate step do modules-pest.yml
- PR #478 — pattern canônico SQLite guard
- PR #486 — fix ImportRegrasCsvServiceTest dual-mode
- PRs #487-490 — pattern dual-mode aplicado em 8 files NfeBrasil
- ADR 0101 — biz=1 default (nunca cliente)
- ADR ARQ-0005 — bridge listener nfe_fiscal_rules → tax_rates
- CI workflow: `.github/workflows/modules-pest.yml`
- `Modules/NfeBrasil/Listeners/SyncFiscalRuleToTaxRate.php`
- `Modules/NfeBrasil/Providers/NfeBrasilServiceProvider.php`
