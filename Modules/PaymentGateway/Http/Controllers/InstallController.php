<?php

namespace Modules\PaymentGateway\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'PaymentGateway';
    }

    protected function moduleSystemKey(): string
    {
        return 'paymentgateway';
    }

    protected function moduleVersion(): string
    {
        return (string) config('paymentgateway.module_version', '0.1.0');
    }

    protected function successMessage(): string
    {
        return 'Módulo PaymentGateway (esqueleto) instalado. Drivers/credenciais/webhooks chegam em ondas futuras (ADR 0170).';
    }
}
