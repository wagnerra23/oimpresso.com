<?php

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'KB';
    }

    protected function moduleSystemKey(): string
    {
        return 'kb';
    }

    protected function moduleVersion(): string
    {
        return (string) config('kb.module_version', '0.1');
    }
}
