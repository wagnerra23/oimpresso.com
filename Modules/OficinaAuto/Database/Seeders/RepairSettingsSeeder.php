<?php

namespace Modules\OficinaAuto\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder business.repair_settings — vertical OficinaAuto (oficinas automotivas).
 *
 * US-REPA-002 amendment 2026-05-10. OficinaAuto é o caso de uso ORIGINAL do
 * Modules/Repair (assistência-técnica/automotiva). Default Modules/Repair
 * (Box B1-B4 + Elevador E1-E2 + Mecânico + Placa+Veículo+KM) já bate.
 *
 * Esse seeder existe pra **reaffirm** vocabulário automotivo NO BUSINESS
 * específico OficinaAuto (Martinho Caçambas piloto), preservando defaults
 * sem deixar dependendo de fallback.
 *
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md §P8
 * @see memory/requisitos/OficinaAuto/SPEC.md
 */
class RepairSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $businessId = env('OFICINA_AUTO_SEEDER_BUSINESS_ID');

        if (! $businessId) {
            $this->command?->warn('OFICINA_AUTO_SEEDER_BUSINESS_ID não setado — skip (feature-wish, candidato Martinho Caçambas a confirmar).');
            return;
        }

        $settings = [
            'slots' => [
                ['key' => 'slot', 'label' => 'Box',      'options' => ['B1', 'B2', 'B3', 'B4']],
                ['key' => 'area', 'label' => 'Elevador', 'options' => ['E1', 'E2']],
            ],
            'labels' => [
                'code'             => 'Placa',
                'item'             => 'Veículo',
                'usage_meter'      => 'KM',
                'usage_unit'       => 'km',
                'executor'         => 'Mecânico',
                'pending_approval' => 'Aguardando aprovação cliente',
                'quote_total'      => 'Orçamento total',
                'quote_items'      => 'Peças',
                'quote_status'     => 'Status orçamento',
            ],
        ];

        DB::table('business')
            ->where('id', $businessId)
            ->update(['repair_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE)]);

        $this->command?->info("OficinaAuto repair_settings aplicados pra business_id={$businessId}.");
    }
}
