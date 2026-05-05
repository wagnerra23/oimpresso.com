<?php

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'ProjectMgmt';
    }

    protected function moduleSystemKey(): string
    {
        return 'projectmgmt';
    }

    protected function moduleVersion(): string
    {
        return (string) config('projectmgmt.module_version', '0.1');
    }
}
