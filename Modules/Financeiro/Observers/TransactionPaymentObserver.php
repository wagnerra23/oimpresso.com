<?php

namespace Modules\Financeiro\Observers;

use App\TransactionPayment;
use Modules\Financeiro\Services\Conciliacao\RecebiveisCartaoService;
use Modules\Financeiro\Services\TituloAutoService;

/**
 * Observer no TransactionPayment core do UltimatePOS.
 *
 * Onda 2 (2026-04-25): fix BUG-1 — pagamento via TransactionPayment
 * agora cria automaticamente TituloBaixa + CaixaMovimento e atualiza
 * valor_aberto/status do Titulo correspondente.
 *
 * Idempotência via idempotency_key='tp_<id>' (UNIQUE em fin_titulo_baixas).
 *
 * Fluxo:
 *   created → registrarPagamento (cria baixa + movimento)
 *   updated → se mudou amount, estorna anterior e cria nova
 *   deleted → cancelarPagamento (estorno via row negativa, append-only)
 */
class TransactionPaymentObserver
{
    public function __construct(private TituloAutoService $service)
    {
    }

    public function created(TransactionPayment $tp): void
    {
        $this->service->registrarPagamento($tp);
        $this->recebivelCartao(fn (RecebiveisCartaoService $c) => $c->registrarDoPagamento($tp));
    }

    public function updated(TransactionPayment $tp): void
    {
        // Só estorna+recria se algo financeiramente relevante mudou.
        if (! $tp->wasChanged(['amount', 'method', 'paid_on', 'account_id'])) {
            return;
        }

        // Estratégia: estorno da baixa antiga + cria nova com valores atuais.
        $this->service->cancelarPagamento($tp);
        $this->service->registrarPagamento($tp);
        $this->recebivelCartao(fn (RecebiveisCartaoService $c) => $c->registrarDoPagamento($tp));
    }

    public function deleted(TransactionPayment $tp): void
    {
        $this->service->cancelarPagamento($tp);
        $this->recebivelCartao(fn (RecebiveisCartaoService $c) => $c->cancelarDoPagamento($tp));
    }

    /**
     * US-FIN-083: recebível do cartão da maquininha (desligado por padrão). Falha aqui nunca
     * derruba o pagamento da venda (US-FIN-059): reporta e segue.
     */
    private function recebivelCartao(\Closure $acao): void
    {
        if (! RecebiveisCartaoService::ligado()) {
            return;
        }
        try {
            $acao(app(RecebiveisCartaoService::class));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
