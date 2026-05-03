<?php

namespace Modules\ADS\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * InstallController — ADS (Adaptive Decision System).
 *
 * Estende BaseModuleInstallController (ADR 0024).
 * Acesso: /ads/install (superadmin → Manage Modules)
 *
 * @see memory/claude/feedback_pattern_install_modulos.md
 * @see memory/requisitos/ADS/SPEC.md
 * @see app/Http/Controllers/BaseModuleInstallController.php
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'ADS';
    }

    protected function moduleSystemKey(): string
    {
        return 'ads';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Módulo ADS instalado. Configure ADS_API_KEY no .env e suba o Brain A daemon no CT 100 (ver memory/requisitos/ADS/RUNBOOK-deploy-producao.md).';
    }
}
