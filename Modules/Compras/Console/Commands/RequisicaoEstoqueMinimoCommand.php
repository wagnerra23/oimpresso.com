<?php

declare(strict_types=1);

namespace Modules\Compras\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Jobs\RequisicaoEstoqueMinimoJob;
use Modules\Compras\Services\RequisicaoEstoqueMinimoService;

/**
 * `compras:requisicao-estoque-minimo` — enfileira um `RequisicaoEstoqueMinimoJob` por business
 * com a regra LIGADA (US-COM-027 · UC-FLX-19). Business sem a chave em `common_settings` (o
 * default) não recebe job. Agendado diariamente pelo `ComprasServiceProvider`. Idempotente:
 * variação já numa requisição aberta não é repetida.
 */
class RequisicaoEstoqueMinimoCommand extends Command
{
    protected $signature = 'compras:requisicao-estoque-minimo {--business= : só este business}';

    protected $description = 'Estoque mínimo vira requisição de compra (por business com a regra ligada). Não move estoque.';

    public function handle(RequisicaoEstoqueMinimoService $servico): int
    {
        $ids = DB::table('business')
            ->where('common_settings', 'like', '%'.RequisicaoEstoqueMinimoService::CHAVE.'%')
            ->when($this->option('business'), fn ($q, $b) => $q->where('id', (int) $b))
            ->orderBy('id')
            ->pluck('id')
            ->map('intval');

        $enfileirados = 0;
        foreach ($ids as $biz) {
            if (! $servico->ativo($biz)) {
                continue;
            }
            RequisicaoEstoqueMinimoJob::dispatch($biz);
            $enfileirados++;
        }

        $this->info("Requisição por estoque mínimo enfileirada: {$enfileirados} business(es).");

        return self::SUCCESS;
    }
}
