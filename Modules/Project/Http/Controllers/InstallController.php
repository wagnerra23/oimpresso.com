<?php

namespace Modules\Project\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Project';
    }

    protected function moduleSystemKey(): string
    {
        return 'project';
    }

    protected function moduleVersion(): string
    {
        return (string) config('project.module_version', '2.1');
    }
}
