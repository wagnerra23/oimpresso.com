<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\System;
use Composer\Semver\Comparator;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Instalador do módulo PontoWr2.
 *
 * Estrutura clonada de Modules/Jana/Http/Controllers/InstallController.php
 * (referência canônica UltimatePOS — ver ADR 0011). Métodos:
 *   - index()    apresenta a tela de instalação
 *   - install()  roda migrações e registra versão em System
 *   - uninstall() desregistra o módulo
 *   - update()   aplica migrations incrementais quando a versão sobe
 *
 * Apenas superadmin pode executar qualquer ação.
 */
class InstallController extends Controller
{
    /**
     * @var string
     */
    protected $module_name;

    /**
     * @var string
     */
    protected $appVersion;

    public function __construct()
    {
        $this->module_name = 'pontowr2';
        $this->appVersion = config('pontowr2.module_version');
    }

    /**
     * Tela inicial do instalador.
     *
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '512M');

        // clear cache & config file
        config(['app.debug' => true]);
        Artisan::call('config:clear');
        Artisan::call('cache:clear');

        // Se já instalado, 404
        $is_installed = System::getProperty($this->module_name . '_version');
        if (! empty($is_installed)) {
            abort(404);
        }

        $action_url = action('\Modules\PontoWr2\Http\Controllers\InstallController@install');

        return view('install.install-module')
            ->with(compact('action_url'));
    }

    /**
     * Executa a instalação do módulo: migrations + publish + registro da versão.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function install()
    {
        try {
            DB::beginTransaction();

            $is_installed = System::getProperty($this->module_name . '_version');
            if (! empty($is_installed)) {
                abort(404);
            }

            DB::statement('SET default_storage_engine=INNODB;');
            Artisan::call('module:migrate', ['module' => 'PontoWr2']);
            Artisan::call('module:publish', ['module' => 'PontoWr2']);
            System::addProperty($this->module_name . '_version', $this->appVersion);

            DB::commit();

            $output = [
                'success' => 1,
                'msg'     => 'Módulo Ponto WR2 instalado com sucesso',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => false,
                'msg'     => $e->getMessage(),
            ];
        }

        return redirect()
            ->action('\App\Http\Controllers\Install\ModulesController@index')
            ->with('status', $output);
    }

    /**
     * Desinstala o módulo (remove apenas a entrada em System; tabelas ficam).
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uninstall()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            System::removeProperty($this->module_name . '_version');

            $output = [
                'success' => true,
                'msg'     => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            $output = [
                'success' => false,
                'msg'     => $e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Atualiza o módulo quando a versão em config sobe em relação à registrada.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', '512M');

            $current_version = System::getProperty($this->module_name . '_version');

            if (Comparator::greaterThan($this->appVersion, $current_version)) {
                config(['app.debug' => true]);
                Artisan::call('config:clear');
                Artisan::call('cache:clear');

                DB::statement('SET default_storage_engine=INNODB;');
                Artisan::call('module:migrate', ['module' => 'PontoWr2']);
                Artisan::call('module:publish', ['module' => 'PontoWr2']);
                System::setProperty($this->module_name . '_version', $this->appVersion);
            } else {
                abort(404);
            }

            DB::commit();

            $output = [
                'success' => 1,
                'msg'     => 'Módulo Ponto WR2 atualizado com sucesso para ' . $this->appVersion,
            ];

            return redirect()->back()->with(['status' => $output]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            return redirect()->back()->with([
                'status' => [
                    'success' => false,
                    'msg'     => $e->getMessage(),
                ],
            ]);
        }
    }
}
