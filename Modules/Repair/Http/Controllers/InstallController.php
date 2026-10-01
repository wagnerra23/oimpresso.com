<?php

namespace Modules\Repair\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'Repair';
    }

    protected function moduleSystemKey(): string
    {
        return 'repair';
    }

    protected function moduleVersion(): string
    {
        return (string) config('repair.module_version', '2.0');
    }
}
