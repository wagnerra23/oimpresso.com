<?php

namespace Modules\LaravelAI\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'LaravelAI';
    }

    protected function moduleSystemKey(): string
    {
        return 'laravelai';
    }

    protected function moduleVersion(): string
    {
        return (string) config('laravelai.module_version', '0.1.0');
    }

    protected function successMessage(): string
    {
        return 'Módulo LaravelAI instalado. Setup de embeddings + knowledge graph pendente (próxima sub-onda).';
    }
}
