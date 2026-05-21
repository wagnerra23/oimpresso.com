<?php

declare(strict_types=1);

namespace Modules\Financeiro\Observers;

use App\CashRegister;
use Modules\Financeiro\Events\CashRegisterClosed;

/**
 * Eloquent Observer no CashRegister core UPOS.
 *
 * ADR 0183 — Detecta transição status → 'close' e dispara
 * CashRegisterClosed event que o OnCashRegisterClosedCreateFinanceiroTitulo
 * Listener consome pra criar fin_titulo automaticamente.
 *
 * Registrado em FinanceiroServiceProvider::boot.
 *
 * Pegadinhas mitigadas:
 *  P1 — Observer pode disparar 2x em race (update concorrente) → Listener
 *       trata idempotência via fin_titulos.UNIQUE
 *  P2 — business_id=0 (legacy data) → Listener faz skip + report
 *  P6 — caixa vazio (closing_amount=0 sem transactions) → Listener skip silencioso
 */
final class CashRegisterObserver
{
    /**
     * Disparado em qualquer UPDATE no CashRegister. Filtra pra status='close'.
     *
     * Importante: usar `wasChanged()` em vez de `isDirty()` — `wasChanged` é
     * pós-save (true se valor MUDOU); `isDirty` é pré-save (true se diff
     * ainda não salvo). No hook `updated` queremos pós-save.
     */
    public function updated(CashRegister $cashRegister): void
    {
        if (! $cashRegister->wasChanged('status')) {
            return;
        }

        if ($cashRegister->status !== 'close') {
            return;
        }

        CashRegisterClosed::dispatch($cashRegister);
    }
}
