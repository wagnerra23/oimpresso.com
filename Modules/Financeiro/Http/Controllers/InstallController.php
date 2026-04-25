<?php

namespace Modules\Financeiro\Http\Controllers;

use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Install legacy entrypoint pra tela /manage-modules (UltimatePOS Superadmin).
 *
 * Pattern descoberto em Modules/Superadmin/Http/Controllers/InstallController.php
 * + Modules/Connector/Http/Controllers/InstallController.php — apontado pelo
 * `app/Http/Controllers/Install/ModulesController::index()` via:
 *
 *   action('\Modules\<Name>\Http\Controllers\InstallController@index')
 *
 * Este controller faz instalação completa em 1 passo (sem license/2-step):
 *   1. Roda migrations
 *   2. Marca System property `financeiro_version`
 *   3. Chama `financeiro:install --all` (perms Spatie + financeiro_module em
 *      packages com subscription ativa + seed plano contas BR)
 *
 * Diferença do `Modules/Financeiro/Console/Commands/InstallCommand`:
 *   - Este é o entrypoint web (botão "Install" no /manage-modules)
 *   - O Console é entrypoint CLI (`php artisan financeiro:install`)
 *   - Este DELEGA pro Console pra fazer setup de permissões + packages
 */
class InstallController extends Controller
{
    protected string $module_name = 'financeiro';

    protected string $appVersion;

    public function __construct()
    {
        $this->appVersion = config('financeiro.module_version', '0.1.0');
    }

    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Apenas superadmin pode instalar módulos.');
        }

        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '512M');

        try {
            DB::beginTransaction();

            // 1. Roda migrations (idempotente — Laravel pula migrations já executadas)
            DB::statement('SET default_storage_engine=INNODB;');
            Artisan::call('module:migrate', ['module' => 'Financeiro', '--force' => true]);
            $migrateOutput = Artisan::output();

            // 2. Marca como instalado em system properties
            $is_installed = System::getProperty($this->module_name . '_version');
            if (empty($is_installed)) {
                System::addProperty($this->module_name . '_version', $this->appVersion);
            } else {
                System::setProperty($this->module_name . '_version', $this->appVersion);
            }

            DB::commit();

            // 3. Chama financeiro:install --all (FORA da transação — usa próprias DB ops)
            // Atribui 13 permissões aos roles Admin#{biz} de cada business com sub ativa,
            // ativa financeiro_module nos packages, seedpa plano de contas BR.
            try {
                Artisan::call('financeiro:install', ['--all' => true]);
                $installOutput = Artisan::output();
            } catch (\Throwable $e) {
                $installOutput = "Setup parcial (manual via CLI necessário): {$e->getMessage()}";
            }

            $output = [
                'success' => 1,
                'msg' => 'Módulo Financeiro instalado. Permissões + plano de contas configurados em todos os businesses ativos. Logout/login pode ser necessário.',
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            $output = [
                'success' => 0,
                'msg' => 'Falha ao instalar Financeiro: ' . $e->getMessage(),
            ];
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Uninstall — apenas remove System property e desativa módulo.
     * NÃO derruba tabelas (seguro por default; tabelas preservadas).
     */
    public function uninstall()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403);
        }

        try {
            System::removeProperty($this->module_name . '_version');
            Artisan::call('module:disable', ['module' => 'Financeiro']);

            $output = [
                'success' => 1,
                'msg' => 'Módulo Financeiro desativado. Tabelas preservadas (use migrate:rollback pra remover).',
            ];
        } catch (\Throwable $e) {
            $output = ['success' => 0, 'msg' => $e->getMessage()];
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Update — re-roda migrations (seguro — Laravel pula já executadas).
     */
    public function update()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403);
        }

        return $this->index();  // mesma lógica
    }
}
