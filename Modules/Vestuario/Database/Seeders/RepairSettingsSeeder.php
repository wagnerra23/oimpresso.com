<?php

namespace Modules\Vestuario\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder Modules/Repair `business.repair_settings` per-vertical Vestuario.
 *
 * US-REPA-002 amendment 2026-05-10 — Modules/Repair virou shared infra com
 * vocabulário genérico (code/item/usage_meter/executor/slot/area). Cada vertical
 * customiza labels + slot_config via JSON em business.repair_settings.
 *
 * Vestuario (CNAE 4781) — caso de uso: ajustes/customização/troca-CDC com kanban
 * de fluxo costureira → revisão → finalização.
 *
 * Vocabulário:
 * - code → "SKU" (referência produto)
 * - item → "Peça" (vestido, calça, etc)
 * - executor → "Costureira"
 * - slot → "Arara" (A1-A20)
 * - area → "Mesa de corte" (M1-M3)
 *
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md §P8
 * @see memory/requisitos/Vestuario/SPEC.md
 */
class RepairSettingsSeeder extends Seeder
{
    /**
     * Roda em business específico (default: business_id=4 ROTA LIVRE).
     * Override via env VESTUARIO_SEEDER_BUSINESS_ID se quiser testar outro.
     */
    public function run(): void
    {
        $businessId = env('VESTUARIO_SEEDER_BUSINESS_ID', 4);

        $settings = [
            'slots' => [
                ['key' => 'slot', 'label' => 'Arara', 'options' => array_map(
                    fn ($i) => "A{$i}",
                    range(1, 20)
                )],
                ['key' => 'area', 'label' => 'Mesa de corte', 'options' => ['M1', 'M2', 'M3']],
            ],
            'labels' => [
                'code'             => 'SKU',
                'item'             => 'Peça',
                'usage_meter'      => 'Tamanho',
                'usage_unit'       => '',
                'executor'         => 'Costureira',
                'pending_approval' => 'Aguardando cliente',
                'quote_total'      => 'Valor estimado',
                'quote_items'      => 'Peças',
                'quote_status'     => 'Status orçamento',
            ],
        ];

        DB::table('business')
            ->where('id', $businessId)
            ->update(['repair_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE)]);

        $this->command?->info("Vestuario repair_settings aplicados pra business_id={$businessId}.");
    }
}
