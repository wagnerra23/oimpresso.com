<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * Install entrypoint do Financeiro — pattern padrão (ADR 0023).
 *
 * Hook postInstallCommand → 'financeiro:install --all' que registra 13
 * permissões Spatie no role Admin#{biz}, ativa financeiro_module nos packages
 * com sub ativa, e seedpa plano de contas BR (49 entries) por business.
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Financeiro';
    }

    protected function moduleSystemKey(): string
    {
        return 'financeiro';
    }

    protected function moduleVersion(): string
    {
        return (string) config('financeiro.module_version', '0.1.0');
    }

    protected function postInstallCommand(): ?string
    {
        return 'financeiro:install';
    }

    protected function successMessage(): string
    {
        return 'Módulo Financeiro instalado. Permissões + plano de contas BR configurados em todos os businesses ativos.';
    }
}
