<?php

namespace Modules\Spreadsheet\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'Spreadsheet';
    }

    protected function moduleSystemKey(): string
    {
        return 'spreadsheet';
    }

    protected function moduleVersion(): string
    {
        return (string) config('spreadsheet.module_version', '1.0');
    }
}
