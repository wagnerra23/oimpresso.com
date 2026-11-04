<?php

declare(strict_types=1);

namespace Modules\Fisio\Observers;

use App\Transaction;
use Illuminate\Support\Facades\Schema;
use Modules\Fisio\Entities\Pacote;
use Modules\Fisio\Services\Pacotes\SaldoDoPacote;

/**
 * Venda do núcleo cancelada ou excluída → o pacote ligado a ela perde as sessões que sobraram
 * (US-FISIO-022). Só mexe em SESSÕES; título e devolução de dinheiro são do Financeiro.
 *
 * Nunca propaga erro: falha aqui não pode travar o cancelamento da venda. Vai para `report()`.
 */
final class VendaDoPacoteObserver
{
    public function updated(Transaction $tx): void
    {
        // `getAttribute`: o Larastan tipa `status` pelo enum da migration original, sem 'cancelled'
        // (mesmo aviso da CancelamentoDaVenda), e acusaria a comparação como sempre falsa.
        if ($tx->type === 'sell' && $tx->wasChanged('status') && (string) $tx->getAttribute('status') === 'cancelled') {
            $this->encerrar($tx);
        }
    }

    public function deleted(Transaction $tx): void
    {
        if ($tx->type === 'sell') {
            $this->encerrar($tx);
        }
    }

    private function encerrar(Transaction $tx): void
    {
        try {
            if (! Schema::hasTable('fisio_packages')) {
                return;
            }
            // SUPERADMIN: o cancelamento pode vir de job sem sessão; a empresa é a da própria venda.
            $pacotes = Pacote::withoutGlobalScopes()->where('business_id', (int) $tx->business_id)
                ->where('transaction_id', (int) $tx->id)->where('status', Pacote::STATUS_ATIVO)->get();
            foreach ($pacotes as $p) {
                app(SaldoDoPacote::class)->encerrarPorVendaCancelada($p, (int) $tx->id, (int) (auth()->id() ?? $tx->created_by));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
