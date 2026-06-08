<?php

namespace Modules\Connector\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use Illuminate\Support\Facades\Artisan;

/**
 * Connector tem requisito especial: passport:install --force pós-migração
 * (gera client OAuth pra API REST).
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Connector';
    }

    protected function moduleSystemKey(): string
    {
        return 'connector';
    }

    protected function moduleVersion(): string
    {
        return (string) config('connector.module_version', '2.0');
    }

    protected function postMigrationSteps(): void
    {
        Artisan::call('passport:install', ['--force' => true]);
    }
}
