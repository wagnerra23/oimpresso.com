<?php

namespace Modules\Auditoria\Http\Controllers;

use App\Http\Controllers\Install\BaseModuleInstallController;

/**
 * Install controller 1-click pra Modules/Auditoria (ADR 0024).
 * Estende BaseModuleInstallController que ja tem fluxo padrao
 * (install / uninstall / update + composer require + module:enable).
 */
class InstallController extends BaseModuleInstallController
{
    protected string $moduleName = 'Auditoria';
}
