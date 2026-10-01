<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'Crm';
    }

    protected function moduleSystemKey(): string
    {
        return 'crm';
    }

    protected function moduleVersion(): string
    {
        return (string) config('crm.module_version', '2.1');
    }
}
