<?php

namespace Modules\VozDoCliente\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'VozDoCliente';
    }

    /**
     * Lowercase SEM hífen — `ModuleUtil::isModuleInstalled()` busca
     * `strtolower($moduleName).'_version'` na tabela `system`. Chave kebab
     * grava propriedade que ninguém lê → módulo "nunca instalado" pra sempre
     * (bug real: OficinaAuto + ComunicacaoVisual, 2026-05-13).
     */
    protected function moduleSystemKey(): string
    {
        return 'vozdocliente';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Modulo Voz do Cliente instalado. Habilite o pacote no superadmin e marque "Atualizar inscricoes existentes".';
    }
}
