<?php

namespace Modules\Officeimpresso\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Officeimpresso';
    }

    protected function moduleSystemKey(): string
    {
        return 'officeimpresso';
    }

    protected function moduleVersion(): string
    {
        return (string) config('officeimpresso.module_version', '1.0');
    }
}
