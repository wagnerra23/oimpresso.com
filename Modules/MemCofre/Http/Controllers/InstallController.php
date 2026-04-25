<?php

namespace Modules\MemCofre\Http\Controllers;

use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Install legacy entrypoint pra tela /manage-modules.
 *
 * IMPORTANTE: MemCofre foi renomeado de DocVault em 2026-04-24. Tabelas docs_*
 * permanecem com prefixo legado (auto-memória `trigger_guarde_no_cofre.md`).
 * Por isso checamos AMBAS as System properties (`memcofre_version` nova,
 * `docvault_version` legada) e migramos automaticamente quando relevante.
 *
 * Antes deste refactor: o controller era um stub que retornava plain text
 * sem persistir System property nem redirecionar — tela /manage-modules
 * ficava sempre em estado "Install" mesmo após click bem-sucedido.
 */
class InstallController extends Controller
{
    protected string $module_name = 'memcofre';

    protected string $appVersion;

    public function __construct()
    {
        $this->appVersion = (string) config('memcofre.module_version', '0.1.0');
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

            DB::statement('SET default_storage_engine=INNODB;');
            Artisan::call('module:migrate', ['module' => 'MemCofre', '--force' => true]);

            // Migra System property legacy docvault_version → memcofre_version (uma vez só)
            $legacy = System::getProperty('docvault_version');
            $current = System::getProperty($this->module_name . '_version');

            if (empty($current)) {
                System::addProperty($this->module_name . '_version', $this->appVersion);
            } else {
                System::setProperty($this->module_name . '_version', $this->appVersion);
            }

            // Limpa flag legacy
            if (! empty($legacy)) {
                System::removeProperty('docvault_version');
            }

            DB::commit();

            $output = [
                'success' => 1,
                'msg' => 'MemCofre instalado. Tabelas docs_* já migradas via module:migrate.'
                    . ($legacy ? ' Property legacy docvault_version migrada para memcofre_version.' : ''),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            $output = ['success' => 0, 'msg' => 'Falha: ' . $e->getMessage()];
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', $output);
    }

    public function uninstall()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403);
        }

        try {
            System::removeProperty($this->module_name . '_version');
            System::removeProperty('docvault_version');
            Artisan::call('module:disable', ['module' => 'MemCofre']);
            $output = ['success' => 1, 'msg' => 'MemCofre desativado. Tabelas preservadas.'];
        } catch (\Throwable $e) {
            $output = ['success' => 0, 'msg' => $e->getMessage()];
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', $output);
    }

    public function update()
    {
        return $this->index();
    }
}
