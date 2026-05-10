<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * InstallController — Modules/ComunicacaoVisual (CNAE 1813-0/01).
 *
 * Estende BaseModuleInstallController (ADR 0024).
 * Acesso: /comunicacao-visual/install (superadmin → Manage Modules)
 *
 * Sprint 1: scaffold. Migrations schema entram Sprint 2+ (frente B).
 *
 * @see app/Http/Controllers/BaseModuleInstallController.php
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'ComunicacaoVisual';
    }

    protected function moduleSystemKey(): string
    {
        return 'comunicacao-visual';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Módulo Comunicação Visual instalado. Vertical CNAE 1813 — gráfica/com.visual. Migrations de schema entram Sprint 2 (aguardando sinal qualificado ADR 0105).';
    }
}
