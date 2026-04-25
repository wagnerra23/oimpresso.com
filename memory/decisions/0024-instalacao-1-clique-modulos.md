# ADR 0024 — Instalação 1-clique padronizada para todos os módulos

**Data:** 2026-04-25
**Status:** Aceita
**Autor:** Wagner (dono/operador)
**Registrado por:** Claude (sessão 2026-04-25)
**Relacionado:** ADR 0001 (estender UltimatePOS), `auto-memória: feedback_pattern_install_modulos.md`

---

## Contexto

A tela legacy `/manage-modules` do UltimatePOS upstream tinha 2 fluxos diferentes para instalar módulo:

1. **2-step com License (15 módulos)** — botão "Install" levava pra `install-module.blade.php` que pedia:
   - License Code (Envato/Codecanyon ou UltimateFosters)
   - Login Username
   - Email
   - Links externos pra ultimatefosters.com / envato.market

2. **Validação real do código** — `InstallController::install()` apenas checava `request()->validate(['license_code' => 'required'])` (campo não-vazio), sem **NENHUMA call HTTP externa** ao Envato/UltimateFosters.

**Problemas:**
- **Fricção desnecessária** — Wagner já comprou os módulos no Codecanyon, validar em runtime não agrega valor
- **Vetor de supply-chain attack** — links externos a sites de validação podem servir código malicioso (mesmo sem call HTTP, links de "Where Is My Purchase Code?" abrem páginas externas)
- **Copy-paste errors graves** descobertos:
  - `Modules/IProduction/Http/Controllers/InstallController.php` tinha `namespace Modules\Boleto` + `module_name='boleto'`
  - `Modules/Writebot/Http/Controllers/InstallController.php` mesmo bug
  - Resultado: `action('\Modules\IProduction\Http\Controllers\InstallController@index')` retornava `'#'` (rota não existia) → botão Install quebrado
- **Inconsistência** — meus módulos novos (Financeiro/NfeBrasil/etc.) usavam pattern 1-step próprio; upstream usava 2-step. Manutenção dobrada.
- **MemCofre stub** — `Modules/MemCofre/Http/Controllers/InstallController.php` era stub de 14 linhas que retornava plain text sem persistir System property

## Decisão

**1. Criar `App\Http\Controllers\BaseModuleInstallController` abstrata** com pattern padronizado:

```php
public function index()
{
    if (! auth()->user()->can('superadmin')) abort(403);

    DB::beginTransaction();
    DB::statement('SET default_storage_engine=INNODB;');
    Artisan::call('module:migrate', ['module' => $this->moduleName(), '--force' => true]);
    Artisan::call('module:publish', ['module' => $this->moduleName()]);
    $this->postMigrationSteps();           // hook
    System::addProperty(<key>_version, $this->moduleVersion());
    DB::commit();

    if ($cmd = $this->postInstallCommand()) Artisan::call($cmd, ['--all' => true]);

    return redirect()->action(...ModulesController::index)
        ->with('status', ['success' => 1, 'msg' => $this->successMessage()]);
}
```

3 métodos abstratos por subclasse: `moduleName()`, `moduleSystemKey()`, `moduleVersion()`.
4 hooks opcionais: `postMigrationSteps()`, `shouldPublishModule()`, `postInstallCommand()`, `successMessage()`.

**2. Refatorar TODOS os InstallController** (20 módulos):

```php
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string { return 'Crm'; }
    protected function moduleSystemKey(): string { return 'crm'; }
    protected function moduleVersion(): string { return (string) config('crm.module_version', '2.1'); }
}
```

Tipicamente **15-25 linhas vs 100-200** linhas no pattern legacy.

**3. Simplificar `resources/views/install/install-module.blade.php`** — remover campos License/Username/Envato Email/links externos. Apenas botão "Instalar" com hidden defaults para passar `request()->validate()` upstream. Já feito em commit anterior.

**4. Quando módulo novo aparecer (upstream Codecanyon update, ou criação interna):**
- Criar `Modules/<Name>/Http/Controllers/InstallController.php` estendendo `BaseModuleInstallController`
- Configurar 3 abstract methods + opcionalmente hooks
- Adicionar rota em `Routes/web.php`:
  ```php
  Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
      ->prefix('<modulo-lower>')
      ->group(function () {
          Route::get('install', [InstallController::class, 'index']);
          Route::get('install/uninstall', [InstallController::class, 'uninstall']);
          Route::get('install/update', [InstallController::class, 'update']);
      });
  ```

## Consequências

### Positivas

- **1-clique funcional** em todos os 20 módulos. Wagner clica "Install" → toast verde "Módulo X instalado".
- **Zero fricção** de license — válido pois Wagner já comprou (não há piracy concern em ambiente owned).
- **Surface de ataque reduzida** — sem links externos no fluxo de install, sem call HTTP que poderia ser hijacked, sem campos formulário pedindo dados que não fazem sentido.
- **Manutenção 5x menor** — InstallController upstream médio passou de ~150 linhas pra ~25. Total economia ~2000 linhas.
- **Bugs corrigidos**:
  - IProduction namespace `Modules\Boleto` → `Modules\IProduction` ✓
  - Writebot namespace `Modules\Boleto` → `Modules\Writebot` ✓
  - System keys `boleto_version` mascarando 2 módulos → `iproduction_version` + `writebot_version` corretos ✓
  - MemCofre stub → InstallController completo + migra `docvault_version` legacy do rename ✓
- **Casos especiais preservados via hooks**:
  - Connector → `postMigrationSteps()` chama `passport:install --force`
  - Financeiro → `postInstallCommand()` chama `financeiro:install --all` (perms + plano contas)
  - MemCofre → `postMigrationSteps()` limpa `docvault_version` legacy
- **Próximos módulos (Codecanyon update, etc.)**: 5 minutos pra adicionar — copia pattern + 3 abstract methods.

### Negativas

- **Quebra de compatibilidade upstream** — quando UltimatePOS Codecanyon enviar update de InstallController de algum módulo, vamos sobrescrever com nossa versão. Risco: perder feature de install nova do upstream.
  - **Mitigação:** auto-memória `feedback_pattern_install_modulos.md` lembra de revisar diff antes de aplicar atualização do upstream.
- **Sem suporte a license validation real** — se algum dia precisar validar (ex: vender módulos), reativar exigirá novo trabalho. Por enquanto não há plano comercial pra módulos.
- **Hard-coded fallback de versões** em cada InstallController — se config esquecer, fallback `'1.0'` nem sempre é correto. Mitigado por config sempre existir + fallback gen.

## Padrão obrigatório (decisão executável)

### Quando módulo NOVO aparecer (upstream ou interno)

1. Criar `InstallController` em `Modules/<Name>/Http/Controllers/`:
   ```php
   class InstallController extends \App\Http\Controllers\BaseModuleInstallController { ... }
   ```
2. Implementar 3 abstract methods.
3. Adicionar rota Install em `Routes/web.php` (com middleware stack admin + prefix do módulo).
4. Verificar se há `Config/<modulo>.php` com `module_version` → criar se não existir.
5. Testar via `/manage-modules` clicando Install.

### Quando upstream atualizar InstallController existente

1. Verificar diff do upstream — provavelmente vai voltar pro pattern 2-step.
2. **NÃO aplicar** — manter nossa versão refatorada (extends BaseModuleInstallController).
3. Se upstream adicionou feature útil (ex: passport:install novo, seed de dados), copiar pra `postMigrationSteps()` ou `postInstallCommand()` do nosso.

## Pendências (futuras)

- [ ] Aplicar mesmo pattern em **Boleto, Chat, Jana, BI, Knowledgebase, Help, Dashboard** quando ressuscitarmos esses módulos legados (auto-memória `preference_modulos_prioridade.md`).
- [ ] Adicionar **teste Pest** validando que cada InstallController concreto retorna 200/302 ao acessar via superadmin (não falha com exception).
- [ ] Considerar mover hooks pra trait pra suportar herança múltipla se algum dia precisar.

## Relação com outras ADRs / memórias

- **ADR 0001** (Estender UltimatePOS via Opção C) — esta ADR é refinamento natural: padronizamos o ponto de extensão `InstallController` ao invés de cada módulo reinventar.
- **`auto-memória: feedback_pattern_install_modulos.md`** — versão operacional desta ADR (instrução curta pra IA aplicar em sessões futuras).
- **`auto-memória: reference_ultimatepos_integracao.md`** — pattern de hooks DataController; este ADR estende com pattern de InstallController.
- **`memory/requisitos/Financeiro/adr/arq/0001-modulo-isolado-via-nwidart.md`** — pattern de módulo isolado; este ADR é peer (instalação 1-click é parte do isolamento).

---

**Última atualização:** 2026-04-25
