<?php

namespace Modules\Grow\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Grow';
    }

    protected function moduleSystemKey(): string
    {
        return 'grow';
    }

    protected function moduleVersion(): string
    {
        return (string) config('grow.module_version', '1.0');
    }
}
