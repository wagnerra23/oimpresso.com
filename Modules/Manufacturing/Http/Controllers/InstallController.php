<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Manufacturing';
    }

    protected function moduleSystemKey(): string
    {
        return 'manufacturing';
    }

    protected function moduleVersion(): string
    {
        return (string) config('manufacturing.module_version', '3.1');
    }
}
