<?php

namespace Modules\Vestuario\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * InstallController — Modules/Vestuario (CNAE 4781-4/00).
 *
 * Estende BaseModuleInstallController (ADR 0024).
 * Acesso: /vestuario/install (superadmin → Manage Modules).
 *
 * Cliente piloto: ROTA LIVRE biz=4 (Larissa Termas do Gravatal/SC) em prod
 * desde 2024-Q1. ADR 0121 §P7 — vertical lojas de vestuário/moda BR.
 *
 * IMPORTANTE: moduleSystemKey() retorna 'vestuario' (lowercase SEM hífen) —
 * pattern canônico UltimatePOS, casa com strtolower(moduleName) em
 * app/Utils/ModuleUtil.php::isModuleInstalled().
 *
 * @see app/Http/Controllers/BaseModuleInstallController.php
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Vestuario';
    }

    protected function moduleSystemKey(): string
    {
        return 'vestuario';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Módulo Vestuário instalado. Vertical CNAE 4781 — lojas de vestuário/moda BR. Cliente piloto: ROTA LIVRE em prod desde 2024-Q1.';
    }
}
