<?php

declare(strict_types=1);

namespace Modules\AssetManagement\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\AssetManagement\Entities\AssetWarranty;

/**
 * assetmanagement:garantias-orfas — lista (e, com --apply, apaga) garantias sem bem.
 *
 * Por que existe: até 2026-09-30 `AssetService::remover()` apagava o bem e a mídia, mas não as
 * garantias. Como `asset_warranties` pertence inteira ao bem e não tem `business_id`, cada bem
 * excluído com garantia deixava uma linha órfã — medido em produção: 1 garantia de 2026-09-23
 * cujo bem foi criado e excluído 22s depois. O `remover()` foi consertado no mesmo PR; este
 * comando limpa o que já ficou para trás.
 *
 * DRY-RUN por padrão: sem `--apply` só lista, nada é apagado. Exclusão é definitiva, então quem
 * roda com `--apply` em produção é uma pessoa, depois de ver a lista.
 *
 * Tier 0 (ADR 0093): garantia órfã não tem business por construção (o bem que a amarrava a um
 * business sumiu), então este é um comando de manutenção cross-tenant. O critério é só "não
 * existe `assets.id` para este `asset_id`" — nunca apaga garantia de bem que existe.
 * Apaga pelo MODEL, uma a uma, pra o `LogsActivity` registrar cada exclusão.
 *
 * Uso:
 *   php artisan assetmanagement:garantias-orfas            # lista
 *   php artisan assetmanagement:garantias-orfas --apply    # apaga as listadas
 */
class GarantiasOrfasCommand extends Command
{
    protected $signature = 'assetmanagement:garantias-orfas
        {--apply : Apaga as garantias órfãs listadas (sem esta flag, só lista)}';

    protected $description = 'Lista garantias de patrimônio sem bem; com --apply, apaga (dry-run por padrão)';

    public function handle(): int
    {
        $orfas = AssetWarranty::query()
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('assets')
                    ->whereColumn('assets.id', 'asset_warranties.asset_id');
            })
            ->orderBy('id')
            ->get();

        $this->line('garantias órfãs: '.$orfas->count());
        foreach ($orfas as $g) {
            $this->line("  id={$g->id} asset_id={$g->asset_id} {$g->start_date} → {$g->end_date} criada {$g->created_at}");
        }

        if (! $this->option('apply')) {
            if ($orfas->isNotEmpty()) {
                $this->line('dry-run: nada apagado. Rode com --apply para apagar as listadas.');
            }

            return self::SUCCESS;
        }

        $apagadas = 0;
        DB::transaction(function () use ($orfas, &$apagadas): void {
            foreach ($orfas as $g) {
                $g->delete();
                $apagadas++;
            }
        });
        $this->info("apagadas: {$apagadas}");

        return self::SUCCESS;
    }
}
