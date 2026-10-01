<?php

namespace Modules\AssetManagement\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'AssetManagement';
    }

    protected function moduleSystemKey(): string
    {
        return 'assetmanagement';
    }

    protected function moduleVersion(): string
    {
        return (string) config('assetmanagement.module_version', '2.0');
    }
}
