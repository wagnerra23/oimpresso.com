<?php

namespace Modules\ADS\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * ADR 0076 — 6 permissions Spatie pra UI Skills.
 *
 * V1: Wagner (superadmin) tem todas. Time tem só read+test.
 * V2: granularidade fina via UI de roles existente.
 *
 * read    → ler skills (lista + detalhe + history)
 * edit    → criar version draft via editor
 * test    → rodar test runner
 * approve → aprovar version draft pra status published (label production)
 * publish → cria PR no git via Publish-to-git
 * config  → toggle git_sync_mode + auto_publish_to_git por skill
 */
class AdsAdminSkillsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'ads.admin.skills.read',
            'ads.admin.skills.edit',
            'ads.admin.skills.test',
            'ads.admin.skills.approve',
            'ads.admin.skills.publish',
            'ads.admin.skills.config',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
