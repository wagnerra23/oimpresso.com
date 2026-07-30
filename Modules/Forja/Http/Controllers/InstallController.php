<?php

namespace Modules\Forja\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    /** Nome nWidart — segue o `name` do module.json (renomeado 2026-07-30). */
    protected function moduleName(): string
    {
        return 'Forja';
    }

    /**
     * Chave da row `system.projectmgmt_version` — FACHADA LEGACY (ADR 0088).
     *
     * NÃO renomear pra `forja`: a row já existe em produção com o nome antigo;
     * trocar aqui faria o módulo aparecer como "não instalado".
     */
    protected function moduleSystemKey(): string
    {
        return 'projectmgmt';
    }

    protected function moduleVersion(): string
    {
        return (string) config('projectmgmt.module_version', '0.1');
    }
}
