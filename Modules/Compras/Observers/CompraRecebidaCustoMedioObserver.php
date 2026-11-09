<?php

declare(strict_types=1);

namespace Modules\Compras\Observers;

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Compras\Services\CustoMedioMovel;

/**
 * Compra recebida recalcula o custo médio móvel; compra que deixa de ser recebida estorna
 * (US-PROD-024 fase 2 · D5 da auditoria de 2026-11-05).
 *
 * Dispara quando uma compra nasce recebida, passa a recebida, sai de recebida ou é excluída, por
 * qualquer caminho (tela de compra, recebimento parcial, entrada por XML): é um observer do
 * Transaction, não do controller. Roda DEPOIS do commit, porque na hora do `created` as linhas da
 * compra ainda não existem e o estoque ainda não subiu.
 *
 * Desligado pela flag `compras.custo_medio_movel`: cancelamento com a flag desligada não estorna.
 * Nunca derruba a compra: erro vai para o log. Editar uma compra já recebida (mudar quantidade ou
 * preço) não refaz o médio — limite declarado.
 */
final class CompraRecebidaCustoMedioObserver
{
    public function created(Transaction $tx): void
    {
        $this->talvezRegistrar($tx);
    }

    public function updated(Transaction $tx): void
    {
        if (! $tx->wasChanged('status')) {
            return;
        }
        if ($tx->getOriginal('status') === 'received') {
            $this->talvezEstornar($tx);

            return;
        }
        $this->talvezRegistrar($tx);
    }

    public function deleted(Transaction $tx): void
    {
        $this->talvezEstornar($tx);
    }

    private function talvezRegistrar(Transaction $tx): void
    {
        if ($tx->type !== 'purchase' || $tx->status !== 'received') {
            return;
        }
        $id = (int) $tx->id;
        $this->depoisDoCommit($id, fn (CustoMedioMovel $s) => $s->registrarEntrada($id));
    }

    private function talvezEstornar(Transaction $tx): void
    {
        if ($tx->type !== 'purchase') {
            return;
        }
        $id = (int) $tx->id;
        $biz = (int) $tx->business_id;
        $this->depoisDoCommit($id, fn (CustoMedioMovel $s) => $s->estornarEntrada($biz, $id));
    }

    private function depoisDoCommit(int $id, callable $acao): void
    {
        $servico = app(CustoMedioMovel::class);
        if (! $servico->ligado()) {
            return;
        }

        DB::afterCommit(function () use ($servico, $id, $acao) {
            try {
                $acao($servico);
            } catch (\Throwable $e) {
                Log::warning('custo médio móvel não recalculado', ['transaction_id' => $id, 'erro' => $e->getMessage()]);
            }
        });
    }
}
