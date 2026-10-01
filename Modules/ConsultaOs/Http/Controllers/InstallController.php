<?php

namespace Modules\ConsultaOs\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use App\Http\Controllers\Concerns\InstalacaoSoPorPost;

class InstallController extends BaseModuleInstallController
{
    use InstalacaoSoPorPost;

    protected function moduleName(): string
    {
        return 'ConsultaOs';
    }

    protected function moduleSystemKey(): string
    {
        return 'consultaos';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Modulo ConsultaOs instalado. Portal publico disponivel em /consulta-os.';
    }
}
