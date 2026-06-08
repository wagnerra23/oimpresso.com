<?php

namespace Modules\TeamMcp\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'TeamMcp';
    }

    protected function moduleSystemKey(): string
    {
        return 'teammcp';
    }

    protected function moduleVersion(): string
    {
        return (string) config('teammcp.module_version', '0.1');
    }
}
