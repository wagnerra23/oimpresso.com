<?php

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

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
