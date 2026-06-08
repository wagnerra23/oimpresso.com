<?php

/**
 * DRAFT — InstallController Modules/ComunicacaoVisual.
 *
 * Estende BaseModuleInstallController (ADR 0024 — Install 1-clique).
 *
 * Acessivel via /comvis/install (apenas superadmin via /manage-modules).
 *
 * Imitar Modules/ADS/Http/Controllers/InstallController.php (validado 2026-05-03).
 *
 * @see app/Http/Controllers/BaseModuleInstallController.php — implementa index/uninstall/update genericos
 * @see memory/requisitos/Infra/RUNBOOK-criar-modulo.md §5
 */

namespace Modules\ComunicacaoVisual\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'ComunicacaoVisual';
    }

    protected function moduleSystemKey(): string
    {
        // lowercase — bate com `system` table key `<modulesystemkey>_version`.
        return 'comvis';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Modulo Comunicacao Visual instalado. Proximo passo: rodar `php artisan migrate --path=Modules/ComunicacaoVisual/Database/Migrations` e configurar tributaria CNAE 1813 (US-COMVIS-006).';
    }
}
