<?php

namespace App\Http\Controllers;

use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Base padronizada pra Modules/<Name>/Http/Controllers/InstallController.
 *
 * Pattern decidido em ADR memory/decisions/0023-instalacao-1-clique-modulos.md:
 *   1. Click em "Install" no /manage-modules dispara GET <prefix>/install
 *   2. Action: index() roda tudo direto e redireciona com toast — sem tela intermediária
 *   3. Validação de License Code/Envato REMOVIDA (Wagner já comprou; vetor de
 *      supply-chain attack)
 *
 * Subclasses só configuram 3 métodos abstratos. Hooks opcionais permitem
 * casos especiais (Connector → passport:install, Financeiro → financeiro:install --all).
 *
 * Uso típico (90% dos módulos):
 *
 *   class InstallController extends BaseModuleInstallController
 *   {
 *       protected function moduleName(): string { return 'Crm'; }
 *       protected function moduleSystemKey(): string { return 'crm'; }
 *       protected function moduleVersion(): string { return config('crm.module_version', '2.1'); }
 *   }
 *
 * Casos especiais sobrescrevem postMigrationSteps() e/ou postInstallCommand():
 *
 *   protected function postMigrationSteps(): void
 *   {
 *       Artisan::call('passport:install', ['--force' => true]);
 *   }
 *
 *   protected function postInstallCommand(): ?string
 *   {
 *       return 'financeiro:install';  // chamado com --all
 *   }
 */
abstract class BaseModuleInstallController extends Controller
{
    /**
     * Nome do módulo no Laravel Modules (case-sensitive).
     * Ex: 'Connector', 'Financeiro', 'NfeBrasil'.
     */
    abstract protected function moduleName(): string;

    /**
     * Chave usada em System::addProperty('<key>_version').
     * Geralmente lowercase do moduleName().
     * Ex: 'connector', 'financeiro', 'nfebrasil'.
     */
    abstract protected function moduleSystemKey(): string;

    /**
     * Versão do módulo (vem de config/<module>.php).
     */
    abstract protected function moduleVersion(): string;

    /**
     * Hook opcional — passos pós-migração específicos do módulo.
     * Default: nada. Ex: Connector roda passport:install --force.
     */
    protected function postMigrationSteps(): void
    {
        // override em subclass se precisar
    }

    /**
     * Se true, roda `module:publish` após migrate (pattern upstream UltimatePOS).
     * Default: true. Sobrescrever em subclasse pra módulos sem assets/views publicáveis.
     */
    protected function shouldPublishModule(): bool
    {
        return true;
    }

    /**
     * Hook opcional — comando artisan complementar do módulo
     * (ex: 'financeiro:install' que registra permissões + seeds).
     * Será chamado FORA da transação principal, com flag --all.
     * Default: null = não chama nada.
     */
    protected function postInstallCommand(): ?string
    {
        return null;
    }

    /**
     * Mensagem custom de sucesso. Default genérico.
     */
    protected function successMessage(): string
    {
        return 'Módulo ' . $this->moduleName() . ' instalado com sucesso.';
    }

    /**
     * GET <prefix>/install — Install 1-click padronizado.
     *
     * Flow:
     *   1. Verifica superadmin (403 se não for)
     *   2. Bump limites PHP (memória/tempo)
     *   3. Transação:
     *      a. SET InnoDB
     *      b. module:migrate --force (idempotente)
     *      c. postMigrationSteps() hook
     *      d. System property add/set ({key}_version = appVersion)
     *   4. Commit
     *   5. postInstallCommand() opcional (fora da transação)
     *   6. Redirect /manage-modules com toast success/error
     */
    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Apenas superadmin pode instalar módulos.');
        }

        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '512M');

        try {
            DB::beginTransaction();

            DB::statement('SET default_storage_engine=INNODB;');
            Artisan::call('module:migrate', [
                'module' => $this->moduleName(),
                '--force' => true,
            ]);

            // module:publish é pattern upstream UltimatePOS (assets/configs/views).
            // Subclasse pode opt-out sobrescrevendo shouldPublishModule() = false.
            if ($this->shouldPublishModule()) {
                try {
                    Artisan::call('module:publish', ['module' => $this->moduleName()]);
                } catch (\Throwable $e) {
                    // module:publish falha em módulos sem assets — ignora silenciosamente
                }
            }

            $this->postMigrationSteps();

            $key = $this->moduleSystemKey() . '_version';
            $current = System::getProperty($key);

            if (empty($current)) {
                System::addProperty($key, $this->moduleVersion());
            } else {
                System::setProperty($key, $this->moduleVersion());
            }

            DB::commit();

            $extraOutput = null;
            $cmd = $this->postInstallCommand();
            if ($cmd !== null) {
                try {
                    Artisan::call($cmd, ['--all' => true]);
                    $extraOutput = Artisan::output();
                } catch (\Throwable $e) {
                    $extraOutput = "Setup parcial (rode '{$cmd} --all' manualmente): {$e->getMessage()}";
                }
            }

            $output = [
                'success' => 1,
                'msg' => $this->successMessage()
                    . ($extraOutput ? ' Setup pós-instalação executado.' : ''),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            $output = [
                'success' => 0,
                'msg' => 'Falha ao instalar ' . $this->moduleName() . ': ' . $e->getMessage(),
            ];
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * POST <prefix>/install — alias pro index() pra compat com pattern 2-step legacy.
     * Tela install-module.blade.php (refatorada) ainda submita POST com hidden fields,
     * então mantemos esse endpoint funcionando.
     */
    public function install()
    {
        return $this->index();
    }

    /**
     * GET <prefix>/install/uninstall — desativa módulo.
     * NÃO derruba tabelas (preservadas). Apenas limpa System property.
     */
    public function uninstall()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403);
        }

        try {
            System::removeProperty($this->moduleSystemKey() . '_version');

            $output = [
                'success' => 1,
                'msg' => 'Módulo ' . $this->moduleName() . ' desativado. Tabelas preservadas.',
            ];
        } catch (\Throwable $e) {
            $output = [
                'success' => 0,
                'msg' => 'Falha: ' . $e->getMessage(),
            ];
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * GET <prefix>/install/update — re-roda migrations + atualiza versão.
     * Mesmo flow que index() (idempotente).
     */
    public function update()
    {
        return $this->index();
    }
}
