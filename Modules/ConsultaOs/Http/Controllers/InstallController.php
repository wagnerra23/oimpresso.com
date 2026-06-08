<?php

namespace Modules\ConsultaOs\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;

class InstallController extends BaseModuleInstallController
{
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
