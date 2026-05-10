<?php

namespace Modules\ComunicacaoVisual\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder business.repair_settings — vertical ComunicacaoVisual (gráfica rápida).
 *
 * US-REPA-002 amendment 2026-05-10. Modules/Repair virou shared infra.
 * Gráfica usa kanban OS pra fluxo: orçamento → arte → impressão → acabamento → entrega.
 *
 * Vocabulário:
 * - code → "OS" (número da Ordem de Serviço)
 * - item → "Trabalho" (lona, fachada, plotter, ACM)
 * - usage_meter+unit → "10 m²" / "5 unidades"
 * - executor → "Operador"
 * - slot → "Máquina" (Plotter1, ACM2, Lona-frente)
 * - area → "Setor" (Arte, Impressão, Acabamento)
 *
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md §P8
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 */
class RepairSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $businessId = env('COMVIS_SEEDER_BUSINESS_ID');

        if (! $businessId) {
            $this->command?->warn('COMVIS_SEEDER_BUSINESS_ID não setado — skip (piloto 2026-Q3 escolhe um dos 6 saudáveis OfficeImpresso).');
            return;
        }

        $settings = [
            'slots' => [
                ['key' => 'slot', 'label' => 'Máquina', 'options' => ['Plotter1', 'Plotter2', 'ACM1', 'ACM2', 'Lona-frente', 'Lona-fundo']],
                ['key' => 'area', 'label' => 'Setor',   'options' => ['Arte', 'Impressão', 'Acabamento', 'Expedição']],
            ],
            'labels' => [
                'code'             => 'OS',
                'item'             => 'Trabalho',
                'usage_meter'      => 'Quantidade',
                'usage_unit'       => 'm²',
                'executor'         => 'Operador',
                'pending_approval' => 'Aguardando arte aprovada',
                'quote_total'      => 'Valor orçamento',
                'quote_items'      => 'Itens',
                'quote_status'     => 'Status orçamento',
            ],
        ];

        DB::table('business')
            ->where('id', $businessId)
            ->update(['repair_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE)]);

        $this->command?->info("ComunicacaoVisual repair_settings aplicados pra business_id={$businessId}.");
    }
}
