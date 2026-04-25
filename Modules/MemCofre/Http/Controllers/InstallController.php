<?php

namespace Modules\MemCofre\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\System;

/**
 * MemCofre foi renomeado de DocVault em 2026-04-24. Tabelas docs_* permanecem
 * com prefixo legado. Este controller migra `docvault_version` (legacy) →
 * `memcofre_version` (atual) automaticamente em postMigrationSteps().
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'MemCofre';
    }

    protected function moduleSystemKey(): string
    {
        return 'memcofre';
    }

    protected function moduleVersion(): string
    {
        return (string) config('memcofre.module_version', '0.1');
    }

    protected function postMigrationSteps(): void
    {
        if (! empty(System::getProperty('docvault_version'))) {
            System::removeProperty('docvault_version');
        }
    }

    protected function successMessage(): string
    {
        return 'MemCofre instalado. Tabelas docs_* já migradas via module:migrate.';
    }
}
