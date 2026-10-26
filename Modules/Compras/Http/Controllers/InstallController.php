<?php

namespace Modules\Compras\Http\Controllers;

use App\Http\Controllers\Concerns\ConfirmaInstalacaoPorPost;
use App\System;
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
    use ConfirmaInstalacaoPorPost;

    /**
     * `system.compras_version` é o que o core lê para saber se o módulo está instalado
     * (ModuleUtil::isModuleInstalled). Sem ela, getModuleData pula o Compras: os ganchos do
     * formulário de produto e o menu nunca rodam. O install antigo não gravava a chave (medido em
     * produção em 2026-10-21: ausente). É o mesmo que o BaseModuleInstallController faz.
     */
    private const CHAVE_VERSAO = 'compras_version';

    protected Util $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    public function index()
    {
        if ($confirmacao = $this->confirmacaoSeNaoForPost('install')) {
            return $confirmacao;
        }

        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Wave 1: scaffold — sem migrations novas.
            Artisan::call('module:publish-migration');
            Artisan::call('migrate', ['--force' => true]);
            $this->gravarVersao();
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
        if ($confirmacao = $this->confirmacaoSeNaoForPost('uninstall')) {
            return $confirmacao;
        }

        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Sem rollback de migration: só tira a marca de instalado.
            System::removeProperty(self::CHAVE_VERSAO);
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
        if ($confirmacao = $this->confirmacaoSeNaoForPost('update')) {
            return $confirmacao;
        }

        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            Artisan::call('module:publish-migration');
            Artisan::call('migrate', ['--force' => true]);
            $this->gravarVersao();
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

    private function gravarVersao(): void
    {
        $versao = (string) config('compras.module_version', '1.0');
        if (empty(System::getProperty(self::CHAVE_VERSAO))) {
            System::addProperty(self::CHAVE_VERSAO, $versao);
        } else {
            System::setProperty(self::CHAVE_VERSAO, $versao);
        }
    }
}
