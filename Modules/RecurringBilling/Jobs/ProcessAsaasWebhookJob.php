<?php

namespace Modules\RecurringBilling\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;

class ProcessAsaasWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly int    $businessId,
        private readonly string $eventId,
        private readonly string $event,
        private readonly array  $payment,
    ) {}

    public function handle(): void
    {
        match ($this->event) {
            'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => $this->onPaymentReceived(),
            'PAYMENT_OVERDUE'                        => $this->onPaymentOverdue(),
            'BALANCE_UPDATED'                        => $this->onBalanceUpdated(),
            default                                  => null,
        };

        // Marca como processado
        DB::table('pg_webhook_events')
            ->where('provider', 'asaas')
            ->where('event_id', $this->eventId)
            ->update(['processed' => true, 'updated_at' => now()]);
    }

    /**
     * PAYMENT_RECEIVED e PAYMENT_CONFIRMED caem aqui (cartão manda os dois, com `id` diferentes,
     * e o Asaas reentrega). Por isso o efeito de valor só acontece UMA vez, na TRANSIÇÃO da
     * fatura para `paid` (auditoria RecurringBilling 2026-11-19, achado a3):
     *
     * - lê a fatura com lock e só aceita status `open`/`overdue`;
     * - valor pago (`payment.value`) menor que a fatura não quita: compara em centavos inteiros;
     * - o UPDATE é condicional (`status` em aberto) e conta as linhas: só com 1 linha afetada é
     *   que entram o crédito em `account_transactions` e o `saldo_cached`, na MESMA transação;
     * - o `InvoicePaid` sai depois do commit, só quando houve a transição;
     * - `created_by` do crédito é o dono da empresa, não mais o usuário 1 fixo.
     *
     * Sem `externalReference` (pagamento que não é de fatura do RB) não há o que quitar: só loga.
     * O retry (`tries=3`) e o evento repetido viram `ja_paga` e não mexem em valor.
     *
     * @return string paga | ja_paga | valor_insuficiente | status_nao_cobravel | nao_encontrada | sem_referencia
     */
    public function onPaymentReceived(): string
    {
        $externalRef = $this->payment['externalReference'] ?? null;
        $pagoCentavos = self::centavos($this->payment['value'] ?? 0);
        $paidAt = (string) ($this->payment['paymentDate'] ?? now()->toDateString());

        if (! $externalRef) {
            Log::info('recurringbilling.asaas: pagamento sem externalReference, nada a quitar', [
                'business_id' => $this->businessId, 'event_id' => $this->eventId,
            ]);

            return 'sem_referencia';
        }

        [$resultado, $faturaId] = DB::transaction(function () use ($externalRef, $pagoCentavos, $paidAt) {
            $fatura = DB::table('rb_invoices')
                ->where('business_id', $this->businessId)
                ->where('numero_documento', $externalRef)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($fatura === null) {
                return ['nao_encontrada', null];
            }
            if ($fatura->status === 'paid') {
                return ['ja_paga', $fatura->id];
            }
            if (! in_array($fatura->status, ['open', 'overdue'], true)) {
                return ['status_nao_cobravel', $fatura->id];
            }
            if ($pagoCentavos < self::centavos($fatura->valor)) {
                return ['valor_insuficiente', $fatura->id];
            }

            $afetadas = DB::table('rb_invoices')
                ->where('id', $fatura->id)
                ->where('business_id', $this->businessId)
                ->whereIn('status', ['open', 'overdue'])
                ->update(['status' => 'paid', 'pago_em' => $paidAt, 'updated_at' => now()]);

            if ($afetadas !== 1) {
                return ['ja_paga', $fatura->id];
            }

            $this->creditarContaAsaas($externalRef, $pagoCentavos, $paidAt);

            return ['paga', $fatura->id];
        });

        if ($resultado === 'paga') {
            event(new \Modules\RecurringBilling\Events\InvoicePaid(
                businessId: $this->businessId,
                invoiceRef: $externalRef,
                valor: $pagoCentavos / 100,
                paidAt: $paidAt,
            ));
        } elseif ($resultado !== 'ja_paga') {
            Log::warning('recurringbilling.asaas: pagamento não quitou a fatura', [
                'business_id' => $this->businessId, 'event_id' => $this->eventId,
                'fatura_id' => $faturaId, 'motivo' => $resultado,
            ]);
        }

        return $resultado;
    }

    /** Crédito na conta Asaas da empresa + saldo. Roda dentro da transação da quitação. */
    private function creditarContaAsaas(string $externalRef, int $pagoCentavos, string $paidAt): void
    {
        $conta = DB::table('fin_contas_bancarias')
            ->where('business_id', $this->businessId)
            ->where('banco_codigo', '274') // Asaas
            ->whereNull('deleted_at')
            ->first(['id', 'account_id']);

        if (! $conta?->account_id) {
            return;
        }

        $donoDaEmpresa = DB::table('business')->where('id', $this->businessId)->value('owner_id');
        if (! $donoDaEmpresa) {
            // Desfaz a quitação: o retry tenta de novo em vez de quitar sem o crédito.
            throw new \RuntimeException("Empresa {$this->businessId} sem owner_id para o crédito Asaas.");
        }

        $valor = number_format($pagoCentavos / 100, 2, '.', '');

        DB::table('account_transactions')->insert([
            'account_id'       => $conta->account_id,
            'amount'           => $valor,
            'type'             => 'credit',
            'sub_type'         => 'deposit',
            'note'             => 'Boleto recebido via Asaas — ' . $externalRef,
            'transaction_date' => $paidAt,
            'created_by'       => (int) $donoDaEmpresa,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        DB::table('fin_contas_bancarias')
            ->where('id', $conta->id)
            ->increment('saldo_cached', (float) $valor, ['saldo_atualizado_em' => now()]);
    }

    /** "150", 150.5, "123.45" → centavos inteiros (o decimal(15,2) do banco chega como texto). */
    private static function centavos(mixed $valor): int
    {
        if (is_string($valor) && preg_match('/^(\d+)(?:\.(\d{1,}))?$/', $valor, $m)) {
            return (int) $m[1] * 100 + (int) str_pad(substr($m[2] ?? '0', 0, 2), 2, '0');
        }

        return (int) round(((float) $valor) * 100);
    }

    private function onPaymentOverdue(): void
    {
        $externalRef = $this->payment['externalReference'] ?? null;
        if ($externalRef) {
            DB::table('rb_invoices')
                ->where('business_id', $this->businessId)
                ->where('numero_documento', $externalRef)
                ->where('status', 'open')
                ->update(['status' => 'overdue', 'updated_at' => now()]);
        }
    }

    private function onBalanceUpdated(): void
    {
        $saldo = (float) ($this->payment['balance'] ?? 0);
        ContaBancaria::where('business_id', $this->businessId)
            ->where('banco_codigo', '274')
            ->update(['saldo_cached' => $saldo, 'saldo_atualizado_em' => now()]);
    }
}
