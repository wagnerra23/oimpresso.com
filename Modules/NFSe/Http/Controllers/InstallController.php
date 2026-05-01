<?php

namespace Modules\NFSe\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * InstallController — NFSe.
 *
 * Estende BaseModuleInstallController (ADR 0024).
 * Acesso: /nfse/install (superadmin → Manage Modules)
 *
 * @see memory/claude/feedback_pattern_install_modulos.md
 * @see app/Http/Controllers/BaseModuleInstallController.php
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'NFSe';
    }

    protected function moduleSystemKey(): string
    {
        return 'nfse';
    }

    protected function moduleVersion(): string
    {
        return (string) config('nfse.module_version', '0.1.0');
    }

    protected function successMessage(): string
    {
        return 'Módulo NFSe instalado. Configure cert A1 + NFSE_* no .env antes de emitir.';
    }
}
