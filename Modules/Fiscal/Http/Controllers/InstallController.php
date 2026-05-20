<?php

namespace Modules\Fiscal\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * Install entrypoint do módulo Fiscal — pattern padrão (ADR 0023).
 *
 * Sem postInstallCommand — módulo é cockpit thin agregador, sem migrations
 * próprias nem seeders. Permissões + sidebar registration vem via DataController.
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Fiscal';
    }

    protected function moduleSystemKey(): string
    {
        return 'fiscal';
    }

    protected function moduleVersion(): string
    {
        return (string) config('fiscal.module_version', '0.1.0');
    }

    protected function postInstallCommand(): ?string
    {
        return null;
    }

    protected function successMessage(): string
    {
        return 'Módulo Fiscal (Cockpit unificado) instalado. Permissões disponíveis em Roles → Fiscal.';
    }
}
