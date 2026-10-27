<?php

namespace Modules\Marketplaces\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'Marketplaces';
    }

    /** Lowercase sem hífen — `ModuleUtil::isModuleInstalled()` busca `<key>_version` em `system`. */
    protected function moduleSystemKey(): string
    {
        return 'marketplaces';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Modulo Marketplaces instalado. Habilite o pacote no superadmin e marque "Atualizar inscricoes existentes".';
    }
}
