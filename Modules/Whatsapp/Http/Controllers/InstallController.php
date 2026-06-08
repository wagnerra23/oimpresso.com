<?php

namespace Modules\Whatsapp\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * InstallController — Whatsapp.
 *
 * Estende BaseModuleInstallController (ADR 0024).
 * Acesso: /whatsapp/install (superadmin → Manage Modules)
 *
 * Pós-install: business precisa cadastrar driver via /whatsapp/settings.
 * Wizard 2 passos obrigatórios (Z-API hoje + Meta Cloud em paralelo) — ver SPEC US-WA-001.
 *
 * @see memory/decisions/0024-receita-criar-modulo.md
 * @see memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md
 * @see memory/requisitos/Whatsapp/SPEC.md
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Whatsapp';
    }

    protected function moduleSystemKey(): string
    {
        return 'whatsapp';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Módulo Whatsapp instalado. Configure driver em /whatsapp/settings (wizard 2 passos: Z-API hoje + Meta Cloud em paralelo).';
    }
}
