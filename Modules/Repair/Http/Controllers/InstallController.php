<?php

namespace Modules\Repair\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Repair';
    }

    protected function moduleSystemKey(): string
    {
        return 'repair';
    }

    protected function moduleVersion(): string
    {
        return (string) config('repair.module_version', '2.0');
    }
}
