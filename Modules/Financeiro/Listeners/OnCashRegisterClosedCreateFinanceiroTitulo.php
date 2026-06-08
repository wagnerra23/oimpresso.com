<?php

declare(strict_types=1);

namespace Modules\Financeiro\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Events\CashRegisterClosed;
use Modules\Financeiro\Services\TituloAutoService;

/**
 * Listener — quando cash_register fecha, cria 1 fin_titulo consolidado.
 *
 * ADR 0183 PR B — ponte cash_registers → fin_titulos.
 *
 * Delega trabalho pro TituloAutoService::sincronizarDeCashRegister() que
 * implementa toda lógica (idempotência, computeTotals, resolveContaCaixa,
 * detectLocation, skip silencioso).
 *
 * Listener é fino propositalmente — testabilidade + reuso (TituloAutoService
 * pode ser chamado direto via comando artisan pra backfill manual em PR C).
 *
 * Pegadinhas mitigadas (delegadas ao Service):
 *  P1 idempotência · P2 business_id=0 · P5 data=closed_at · P6 caixa vazio
 *  P7 user_id NULL · P13 multi-business cross-pollution
 */
final class OnCashRegisterClosedCreateFinanceiroTitulo
{
    public function __construct(
        private TituloAutoService $service,
    ) {}

    public function handle(CashRegisterClosed $event): void
    {
        try {
            $this->service->sincronizarDeCashRegister($event->cashRegister);
        } catch (\Throwable $e) {
            // Listener NUNCA propaga exception (Eloquent observer estouraria
            // o transaction commit do CashRegister). Log + report pra audit.
            Log::error('[adr_0183][caixa_bridge] Falha ao criar fin_titulo de cash_register', [
                'cash_register_id' => $event->cashRegister->id,
                'business_id'      => $event->cashRegister->business_id,
                'exception'        => $e::class,
                'message'          => $e->getMessage(),
            ]);
            report($e);
        }
    }
}
