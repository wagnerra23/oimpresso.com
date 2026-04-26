<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Copiloto';
    }

    protected function moduleSystemKey(): string
    {
        return 'copiloto';
    }

    protected function moduleVersion(): string
    {
        return (string) config('copiloto.module_version', '0.1');
    }
}
