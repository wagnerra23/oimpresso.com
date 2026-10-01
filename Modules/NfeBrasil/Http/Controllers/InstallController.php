<?php

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'NfeBrasil';
    }

    protected function moduleSystemKey(): string
    {
        return 'nfebrasil';
    }

    protected function moduleVersion(): string
    {
        return (string) config('nfebrasil.module_version', '0.1.0');
    }

    protected function successMessage(): string
    {
        return 'Módulo NfeBrasil instalado. Setup de cert A1 + permissões fica para próxima sub-onda.';
    }
}
