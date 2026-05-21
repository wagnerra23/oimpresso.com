<?php

namespace Modules\Compras\Http\Controllers;

use App\Utils\Util;
use Artisan;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * InstallController — Wave 1 stub (US-COM-001).
 *
 * Pattern reutilizado de Modules/Connector/Http/Controllers/InstallController.php
 * e Modules/NfeBrasil/Http/Controllers/InstallController.php. Sem migrations
 * novas ainda — Wave 6 adiciona migration `nfe_dfe_recebidos.transaction_id`.
 */
class InstallController extends Controller
{
    protected Util $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Wave 1: scaffold — sem migrations novas.
            Artisan::call('module:publish-migration');
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('cache:clear');

            $output = 'Compras: instalado com sucesso.';
        } catch (Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = $e->getMessage();
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', ['success' => true, 'msg' => $output]);
    }

    public function uninstall()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Wave 1: scaffold — sem rollback de migration ainda.
            $output = 'Compras: desinstalado.';
        } catch (Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = $e->getMessage();
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', ['success' => true, 'msg' => $output]);
    }

    public function update()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            Artisan::call('module:publish-migration');
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('cache:clear');

            $output = 'Compras: atualizado.';
        } catch (Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = $e->getMessage();
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', ['success' => true, 'msg' => $output]);
    }
}
