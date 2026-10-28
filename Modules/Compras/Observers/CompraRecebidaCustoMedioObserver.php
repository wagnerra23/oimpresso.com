<?php

declare(strict_types=1);

namespace Modules\Compras\Observers;

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Compras\Services\CustoMedioMovel;

/**
 * Compra recebida recalcula o custo médio móvel (US-PROD-024 fase 2).
 *
 * Dispara quando uma compra nasce recebida ou passa a recebida, por qualquer caminho (tela de
 * compra, recebimento parcial, entrada por XML): é um observer do Transaction, não do controller.
 * Roda DEPOIS do commit, porque na hora do `created` as linhas da compra ainda não existem e o
 * estoque ainda não subiu.
 *
 * Desligado pela flag `compras.custo_medio_movel`. Nunca derruba a compra: erro vai para o log.
 * Editar uma compra já recebida (mudar quantidade ou preço) não refaz o médio — limite declarado.
 */
final class CompraRecebidaCustoMedioObserver
{
    public function created(Transaction $tx): void
    {
        $this->talvez($tx);
    }

    public function updated(Transaction $tx): void
    {
        if ($tx->wasChanged('status')) {
            $this->talvez($tx);
        }
    }

    private function talvez(Transaction $tx): void
    {
        if ($tx->type !== 'purchase' || $tx->status !== 'received') {
            return;
        }
        $servico = app(CustoMedioMovel::class);
        if (! $servico->ligado()) {
            return;
        }

        $id = (int) $tx->id;
        DB::afterCommit(function () use ($servico, $id) {
            try {
                $servico->registrarEntrada($id);
            } catch (\Throwable $e) {
                Log::warning('custo médio móvel não recalculado', ['transaction_id' => $id, 'erro' => $e->getMessage()]);
            }
        });
    }
}
