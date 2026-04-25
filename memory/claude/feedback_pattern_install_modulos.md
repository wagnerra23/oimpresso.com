---
name: Pattern padronizado de instalação 1-click pra módulos UltimatePOS
description: Quando módulo novo aparecer (upstream ou interno), usar BaseModuleInstallController + 3 abstract methods + rotas Install registradas. Tela install-module.blade.php simplificada (sem License Code/Envato).
type: feedback
originSessionId: dbbb392d-952f-4d8d-9a4a-c93f6603c171
---
Padrão decidido por Wagner em 2026-04-25 (ADR 0024). **Todo InstallController de módulo deve estender `App\Http\Controllers\BaseModuleInstallController`** — nunca reescrever lógica de install do zero, nunca pedir License Code/Envato no formulário.

**Why:**
- Wagner já comprou os módulos no Codecanyon — validação contínua não agrega
- Tela `install-module.blade.php` legacy pedia License Code + Username + Envato Email + linkava sites externos (vetor de supply-chain attack)
- Pattern legacy 2-step gera ~150 linhas por InstallController; 1-step com base abstrata reduz pra ~25 linhas
- 2 bugs encontrados em 2026-04-25 (IProduction e Writebot tinham `namespace Modules\Boleto` e `module_name='boleto'`) — pattern padronizado evita esses copy-paste errors

**How to apply (módulo novo):**

1. Criar `Modules/<Name>/Http/Controllers/InstallController.php`:
   ```php
   <?php
   namespace Modules\<Name>\Http\Controllers;

   use App\Http\Controllers\BaseModuleInstallController;

   class InstallController extends BaseModuleInstallController
   {
       protected function moduleName(): string { return '<Name>'; }
       protected function moduleSystemKey(): string { return '<lowercase-name>'; }
       protected function moduleVersion(): string {
           return (string) config('<lowercase-name>.module_version', '0.1.0');
       }
   }
   ```

2. Adicionar **rota Install** em `Modules/<Name>/Routes/web.php`:
   ```php
   Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
       ->prefix('<lowercase-name>')
       ->group(function () {
           Route::get('install', [InstallController::class, 'index']);
           Route::get('install/uninstall', [InstallController::class, 'uninstall']);
           Route::get('install/update', [InstallController::class, 'update']);
       });
   ```

3. Garantir `Modules/<Name>/Config/config.php` tem `'module_version' => '0.1.0'`.

4. Testar: acessar `/manage-modules` como superadmin → clicar Install → confirmar toast verde + System property `<key>_version` persiste.

**Hooks opcionais (sobrescrever em subclasse):**
- `postMigrationSteps()` — comandos artisan extras pós-migrate (ex: Connector chama `passport:install --force`)
- `postInstallCommand()` — comando artisan complementar (ex: Financeiro retorna `'financeiro:install'` pra registrar permissões + seed plano contas)
- `shouldPublishModule()` — default true (chama `module:publish`); falsa em módulos sem assets
- `successMessage()` — texto custom do toast

**How to apply (módulo upstream atualizado pelo Codecanyon):**

1. Verificar diff do `Modules/<X>/Http/Controllers/InstallController.php` upstream
2. **NÃO aplicar a versão upstream** — manter nossa versão extends BaseModuleInstallController
3. Se upstream adicionou feature útil (passport:install novo, seed de dados), portar pra `postMigrationSteps()` ou `postInstallCommand()` da nossa versão

**Trabalho aplicado em 2026-04-25** (commit deste session):
- 5 spec-ready: Financeiro, NfeBrasil, RecurringBilling, LaravelAI, MemCofre
- 15 upstream: Connector, Crm, Cms, AiAssistance, Spreadsheet, Manufacturing, Project, Repair, Accounting, AssetManagement, IProduction (bug fix namespace), ProductCatalogue, Officeimpresso, PontoWr2, Writebot (bug fix namespace), Grow

**NÃO confundir com:**
- `Modules/<Name>/Console/Commands/InstallCommand.php` — comando artisan CLI (`php artisan financeiro:install`). Esse continua existindo separado pra setup avançado (permissões, seeds). InstallController web pode chamá-lo via `postInstallCommand()` hook.
- `App\Services\ModuleManagerService::install()` — UI nova `/modulos` Inertia. Também usa convenção `<alias>:install` mas é caminho independente.

**Conexões:**
- ADR formal: `memory/decisions/0024-instalacao-1-clique-modulos.md`
- Base class: `app/Http/Controllers/BaseModuleInstallController.php`
- Tela simplificada: `resources/views/install/install-module.blade.php`
- ADR relacionada: `memory/decisions/0001-estender-ultimatepos-opcao-c.md` (pattern geral de extensão)
