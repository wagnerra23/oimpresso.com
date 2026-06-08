<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Jana';
    }

    protected function moduleSystemKey(): string
    {
        return 'jana';
    }

    protected function moduleVersion(): string
    {
        return (string) config('copiloto.module_version', '0.1');
    }
}
