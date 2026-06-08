<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * InstallController — Admin Center.
 *
 * Estende BaseModuleInstallController (ADR 0024).
 * Acesso: /admin-center/install (superadmin → Manage Modules → Install)
 *
 * Install seeda role `superadmin#1` (se não existir) + permission
 * `admin.access` + version. Não cria tabelas próprias — só `mcp_admin_audit_log`
 * via migration carregada no ServiceProvider.
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 * @see app/Http/Controllers/BaseModuleInstallController.php
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Admin';
    }

    protected function moduleSystemKey(): string
    {
        return 'admin';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Módulo Admin instalado. Centro de Operações disponível em admin.oimpresso.com (CT 100/Tailscale-only). Wagner-only via gate is-wagner + role superadmin#1.';
    }
}
