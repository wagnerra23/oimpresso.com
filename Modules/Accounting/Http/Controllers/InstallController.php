<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Accounting';
    }

    protected function moduleSystemKey(): string
    {
        return 'accounting';
    }

    protected function moduleVersion(): string
    {
        return (string) config('accounting.module_version', '1.3.1');
    }
}
