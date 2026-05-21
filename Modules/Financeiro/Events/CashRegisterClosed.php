<?php

declare(strict_types=1);

namespace Modules\Financeiro\Events;

use App\CashRegister;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event disparado quando um cash_register é fechado (status='close').
 *
 * ADR 0183 — Ponte cash_registers → fin_titulos. Disparado pelo
 * CashRegisterObserver::updated quando detecta `wasChanged('status')` e
 * `status === 'close'`.
 *
 * Listener canônico: OnCashRegisterClosedCreateFinanceiroTitulo.
 *
 * Multi-tenant Tier 0 (ADR 0093): CashRegister vem com business_id próprio.
 * Listener herda contexto e propaga pra fin_titulo gerado.
 */
final class CashRegisterClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CashRegister $cashRegister,
    ) {}
}
