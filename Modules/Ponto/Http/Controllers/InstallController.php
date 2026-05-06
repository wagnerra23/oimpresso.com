<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
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
