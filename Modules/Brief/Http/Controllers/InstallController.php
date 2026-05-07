<?php

namespace Modules\Brief\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * InstallController — Brief.
 *
 * Estende BaseModuleInstallController (ADR 0024).
 * Acesso: /brief/install (superadmin → Manage Modules → botão Install)
 *
 * Brief não tem migrations próprias (consome `mcp_briefs` table que vive
 * no schema MCP — ver memory/sprints/s1-daily-brief/02-schema-aggregator.sql).
 * Install só seeda permissions + version.
 *
 * @see memory/decisions/0091-daily-brief.md
 * @see app/Http/Controllers/BaseModuleInstallController.php
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Brief';
    }

    protected function moduleSystemKey(): string
    {
        return 'brief';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Módulo Brief instalado. Tool MCP brief-fetch disponível em mcp.oimpresso.com (CT 100). Schedule cron 6x/dia já ativo via brief:generate.';
    }
}
