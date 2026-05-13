<?php

namespace Modules\OficinaAuto\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Wrapper canônico de seeders do módulo OficinaAuto.
 *
 * Roda na ordem:
 *   1. RepairSettingsSeeder       — repair_settings business JSON (vocabulário automotivo)
 *   2. OficinaAutoFsmSeeder       — 2 processos FSM (cacamba_locacao + cacamba_manutencao) per-business
 *
 * Pattern espelha demais Modules/<X>/Database/Seeders/<X>DatabaseSeeder.php
 * (Accounting, Cms, Crm, etc).
 *
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
class OficinaAutoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Model::unguard();

        $this->call(RepairSettingsSeeder::class);
        $this->call(OficinaAutoFsmSeeder::class);

        Model::reguard();
    }
}
