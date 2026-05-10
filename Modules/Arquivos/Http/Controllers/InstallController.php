<?php

namespace Modules\Arquivos\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

/**
 * InstallController — Modules/Arquivos.
 *
 * Acesso: /arquivos/install (superadmin → Manage Modules → Install).
 * Roda 3 migrations: arquivos + arquivos_audit_log + arquivos_dedupe.
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Arquivos';
    }

    protected function moduleSystemKey(): string
    {
        return 'arquivos';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Módulo Arquivos instalado. Backbone DMS pronto. Outros módulos podem adotar trait HasArquivos opt-in. Storage disks: configure ARQUIVOS_DISK_DEFAULT e ARQUIVOS_DISK_VAULT em config/filesystems.php (Sprint 1 dia 4).';
    }
}
