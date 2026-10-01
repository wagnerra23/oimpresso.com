<?php

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'Governance';
    }

    protected function moduleSystemKey(): string
    {
        return 'governance';
    }

    protected function moduleVersion(): string
    {
        return (string) config('governance.module_version', '0.1');
    }
}
