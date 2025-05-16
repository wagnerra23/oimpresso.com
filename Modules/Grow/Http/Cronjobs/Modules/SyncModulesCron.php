<?php

namespace App\Cronjobs\Modules;

use Modules\Grow\Models\Module;
use Modules\Grow\Models\Role;
use Illuminate\Support\Facades\Log;
use Modules\Grow\Repositories\Modules\ModuleRolesRespository;

/**
 * Class SyncModulesCron
 *
 * This class handles the periodic synchronization of permissions for active modules across all roles.
 * The cron job fetches active modules and assigns appropriate permissions to each user role.
 */
class SyncModulesCron {
    /**
     * Invoke method that is called by the scheduler.
     * Triggers the module permission synchronization process.
     */
    public function __invoke(ModuleRolesRespository $modulerepo) {

        //sync user role permissions
        $modulerepo->syncModulePermissions();
    }


}
