<?php

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'RecurringBilling';
    }

    protected function moduleSystemKey(): string
    {
        return 'recurringbilling';
    }

    protected function moduleVersion(): string
    {
        return (string) config('recurringbilling.module_version', '0.1.0');
    }

    protected function successMessage(): string
    {
        return 'Módulo RecurringBilling instalado. Setup de adapters de gateway pendente (próxima sub-onda).';
    }
}
