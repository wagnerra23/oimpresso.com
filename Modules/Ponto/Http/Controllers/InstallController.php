<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'Ponto';
    }

    protected function moduleSystemKey(): string
    {
        return 'ponto';
    }

    protected function moduleVersion(): string
    {
        return (string) config('pontowr2.module_version', '0.1');
    }
}
