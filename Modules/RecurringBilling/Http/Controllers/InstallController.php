<?php

namespace Modules\RecurringBilling\Http\Controllers;

use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    protected string $module_name = 'recurringbilling';

    protected string $appVersion;

    public function __construct()
    {
        $this->appVersion = config('recurringbilling.module_version', '0.1.0');
    }

    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403);
        }

        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '512M');

        try {
            DB::beginTransaction();

            DB::statement('SET default_storage_engine=INNODB;');
            Artisan::call('module:migrate', ['module' => 'RecurringBilling', '--force' => true]);

            $is_installed = System::getProperty($this->module_name . '_version');
            if (empty($is_installed)) {
                System::addProperty($this->module_name . '_version', $this->appVersion);
            } else {
                System::setProperty($this->module_name . '_version', $this->appVersion);
            }

            DB::commit();

            $output = [
                'success' => 1,
                'msg' => 'Módulo RecurringBilling instalado. Migrations rodadas. Setup de adapters de gateway pendente (próxima sub-onda).',
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
            Artisan::call('module:disable', ['module' => 'RecurringBilling']);
            $output = ['success' => 1, 'msg' => 'RecurringBilling desativado.'];
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
