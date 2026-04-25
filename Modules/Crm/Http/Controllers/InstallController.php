<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Crm';
    }

    protected function moduleSystemKey(): string
    {
        return 'crm';
    }

    protected function moduleVersion(): string
    {
        return (string) config('crm.module_version', '2.1');
    }
}
