<?php

namespace Modules\ProductCatalogue\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'ProductCatalogue';
    }

    protected function moduleSystemKey(): string
    {
        return 'productcatalogue';
    }

    protected function moduleVersion(): string
    {
        return (string) config('productcatalogue.module_version', '1.0');
    }
}
