<?php

namespace Modules\Auditoria\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * InstallController — Modules/Auditoria (ADR 0127).
 *
 * Estende BaseModuleInstallController (ADR 0024) — fluxo Install 1-clique
 * padrão (migrate + system property + redirect /manage-modules).
 *
 * IMPORTANTE: moduleSystemKey() retorna 'auditoria' (lowercase SEM hífen) —
 * pattern canônico UltimatePOS, casa com strtolower(moduleName) em
 * app/Utils/ModuleUtil.php::isModuleInstalled().
 *
 * @see app/Http/Controllers/BaseModuleInstallController.php
 * @see memory/decisions/0127-auditoria-governanca-transversal.md
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Auditoria';
    }

    protected function moduleSystemKey(): string
    {
        return 'auditoria';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Módulo Auditoria instalado. Camada de governança transversal — UI rica /auditoria + undo sobre activity_log.';
    }
}
