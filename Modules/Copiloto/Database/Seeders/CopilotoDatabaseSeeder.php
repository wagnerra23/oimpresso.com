<?php

namespace Modules\Copiloto\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Entities\MetaPeriodo;
use Modules\Copiloto\Entities\MetaFonte;
use Modules\Copiloto\Scopes\ScopeByBusiness;

/**
 * Seeder inicial — materializa a meta R$ 5mi/ano da plataforma
 * (ADR 0022 + memory/11-metas-negocio.md).
 *
 * Rodar: `php artisan module:seed Copiloto`
 */
class CopilotoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $slug = (string) config('copiloto.meta_plataforma.slug', 'faturamento_oimpresso_anual');

        $existente = Meta::withoutGlobalScope(ScopeByBusiness::class)
            ->whereNull('business_id')
            ->where('slug', $slug)
            ->first();

        if ($existente) {
            return;
        }

        $meta = Meta::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id'       => null,
            'slug'              => $slug,
            'nome'              => (string) config('copiloto.meta_plataforma.nome', 'Faturamento anual oimpresso'),
            'unidade'           => (string) config('copiloto.meta_plataforma.unidade', 'R$'),
            'tipo_agregacao'    => 'soma',
            'ativo'             => true,
            'criada_por_user_id' => null,
            'origem'            => 'seed',
        ]);

        MetaPeriodo::create([
            'meta_id'      => $meta->id,
            'tipo_periodo' => 'ano',
            'data_ini'     => Carbon::now()->startOfYear()->toDateString(),
            'data_fim'     => Carbon::now()->endOfYear()->toDateString(),
            'valor_alvo'   => (float) config('copiloto.meta_plataforma.valor_alvo', 5000000),
            'trajetoria'   => 'linear',
        ]);

        MetaFonte::create([
            'meta_id'     => $meta->id,
            'driver'      => 'sql',
            'config_json' => [
                // Agregação cross-business — válido pra meta da plataforma (business_id IS NULL).
                'query' => "SELECT COALESCE(SUM(final_total), 0) FROM transactions WHERE type = 'sell' AND status = 'final' AND transaction_date BETWEEN :data_ini AND :data_fim",
                'binds_extra' => [],
            ],
            'cadencia' => 'diaria',
        ]);
    }
}
