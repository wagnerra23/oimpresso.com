<?php

namespace Modules\SRS\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\System;

/**
 * Histórico de renames: DocVault (2026-04-24) → MemCofre → SRS (2026-05-06).
 * Tabelas docs_* permanecem com prefixo legado. Este controller migra
 * `docvault_version` + `memcofre_version` (legacy) → `srs_version` (atual)
 * automaticamente em postMigrationSteps().
 *
 * @deprecated since 2026-07-29 (ADR 0357) — módulo em deprecação, remoção prevista em E5.
 *             Sem sucessor — hooks de Install saem junto com o módulo.
 *             DEPRECATION-PLAN §Fase 2 item 9.
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'SRS';
    }

    protected function moduleSystemKey(): string
    {
        return 'srs';
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
        if (! empty(System::getProperty('memcofre_version'))) {
            System::removeProperty('memcofre_version');
        }
    }

    protected function successMessage(): string
    {
        return 'SRS instalado. Tabelas docs_* já migradas via module:migrate.';
    }
}
