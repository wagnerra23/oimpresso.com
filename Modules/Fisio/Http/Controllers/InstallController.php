<?php

namespace Modules\Fisio\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'Fisio';
    }

    /**
     * Lowercase SEM hífen — `ModuleUtil::isModuleInstalled()` busca
     * `strtolower($moduleName).'_version'` na tabela `system`.
     */
    protected function moduleSystemKey(): string
    {
        return 'fisio';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Modulo Fisio instalado. Habilite o pacote no superadmin e marque "Atualizar inscricoes existentes".';
    }
}