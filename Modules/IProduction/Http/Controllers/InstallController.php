<?php

namespace Modules\IProduction\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * BUG FIX: arquivo upstream tinha namespace `Modules\Boleto\Http\Controllers`
 * (copy-paste error do Modules/Boleto). action('\Modules\IProduction\...')
 * resolvia pra rota inexistente → botao Install no /manage-modules quebrado.
 *
 * Plus: module_name era 'boleto' em vez de 'iproduction', mascarando System
 * properties. Corrigido pra IProduction nativo.
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'IProduction';
    }

    protected function moduleSystemKey(): string
    {
        return 'iproduction';
    }

    protected function moduleVersion(): string
    {
        return (string) config('iproduction.module_version', '1.0');
    }
}
