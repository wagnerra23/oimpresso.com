<?php

namespace Modules\Writebot\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * BUG FIX: arquivo upstream tinha namespace `Modules\Boleto\Http\Controllers`
 * + module_name='boleto' (copy-paste error). Corrigido pra Writebot nativo.
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Writebot';
    }

    protected function moduleSystemKey(): string
    {
        return 'writebot';
    }

    protected function moduleVersion(): string
    {
        return (string) config('writebot.module_version', '1.0');
    }
}
