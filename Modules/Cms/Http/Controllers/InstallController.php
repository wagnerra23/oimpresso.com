<?php

namespace Modules\Cms\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'Cms';
    }

    protected function moduleSystemKey(): string
    {
        return 'cms';
    }

    protected function moduleVersion(): string
    {
        return (string) config('cms.module_version', '1.0');
    }
}
