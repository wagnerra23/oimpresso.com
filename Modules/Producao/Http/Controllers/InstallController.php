<?php

namespace Modules\Producao\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'Producao';
    }

    /**
     * Lowercase SEM hífen — `ModuleUtil::isModuleInstalled()` busca
     * `strtolower($moduleName).'_version'` na tabela `system`.
     */
    protected function moduleSystemKey(): string
    {
        return 'producao';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Modulo Producao instalado. Habilite o pacote no superadmin e marque "Atualizar inscricoes existentes".';
    }
}
