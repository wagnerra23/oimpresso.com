<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'PontoWr2';
    }

    protected function moduleSystemKey(): string
    {
        return 'pontowr2';
    }

    protected function moduleVersion(): string
    {
        return (string) config('pontowr2.module_version', '0.1');
    }
}
