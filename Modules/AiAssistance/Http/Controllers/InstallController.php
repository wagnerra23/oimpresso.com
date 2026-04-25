<?php

namespace Modules\AiAssistance\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'AiAssistance';
    }

    protected function moduleSystemKey(): string
    {
        return 'aiassistance';
    }

    protected function moduleVersion(): string
    {
        return (string) config('aiassistance.module_version', '1.1');
    }
}
