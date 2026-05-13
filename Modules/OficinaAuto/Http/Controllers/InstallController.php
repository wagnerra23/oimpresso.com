<?php

namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * InstallController — Modules/OficinaAuto (CNAEs 4520/2212/4581).
 *
 * Estende BaseModuleInstallController (ADR 0024).
 * Acesso: /oficina-auto/install (superadmin → Manage Modules)
 *
 * V0: scaffold com 2 migrations (vehicles + service_orders). Sem seeder
 * automático — Sprint 2+ traz importer Firebird (US-OFICINA-002).
 *
 * @see app/Http/Controllers/BaseModuleInstallController.php
 * @see memory/requisitos/OficinaAuto/SPEC.md
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'OficinaAuto';
    }

    protected function moduleSystemKey(): string
    {
        return 'oficinaauto';
    }

    protected function moduleVersion(): string
    {
        return (string) config('oficina-auto.module_version', '0.1.0');
    }

    protected function successMessage(): string
    {
        return 'Módulo Oficina Auto instalado (V0). Tabelas vehicles + service_orders criadas. CRUD acessível em /oficina-auto/veiculos e /oficina-auto/ordens-servico.';
    }
}
