<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\RecurringBilling\Events\InvoicePaid;
use Modules\RecurringBilling\Services\Centavos;

/**
 * Processa um PIX recebido (webhook Inter, US-RB-047).
 *
 * Acha a fatura pelo `txid` do Pix em `rb_invoices.gateway_ref`. Quem grava esse `txid` é o
 * `CobradorPixAutomatico`, logo depois de o PaymentGateway emitir a cobrança do ciclo (auditoria
 * RecurringBilling 2026-11-19, achado a4: antes nenhum código de produção gravava o campo, e o
 * webhook nunca achava fatura nenhuma).
 *
 * VALOR (regra mestre) — o mesmo contrato do `ProcessAsaasWebhookJob` (#11223):
 * - lê a fatura com lock e só aceita status `open`/`overdue`;
 * - valor pago menor que a fatura não quita: compara em centavos inteiros, sem float;
 * - o UPDATE é condicional (`status` em aberto) e conta as linhas: só com 1 linha afetada é que
 *   entram o crédito em `account_transactions` e o `saldo_cached` da conta Inter, na MESMA
 *   transação;
 * - o `InvoicePaid` sai depois do commit, só quando houve a transição, e leva o
 *   `numero_documento` da fatura — é por ele que a NF-e (`EmitirNFeAoReceberPagamento`) procura;
 * - `created_by` do crédito é o dono da empresa, não o usuário 1 fixo.
 *
 * Pix sem `txid`, ou cujo `txid` não é de fatura do RB, não quita nem credita nada: só loga.
 * O retry (`tries=3`) e o Pix reentregue viram `ja_paga` e não mexem em valor.
 *
 * A entrada é idempotente por (`provider='inter'`, `event_id=endToEndId`): o controller grava
 * em `pg_webhook_events` antes do dispatch.
 */
class ProcessInterWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly int    $businessId,
        private readonly string $endToEndId,
        private readonly array  $pix,
    ) {}

    public function handle(): void
    {
        $this->quitar();

        DB::table('pg_webhook_events')
            ->where('business_id', $this->businessId)
            ->where('provider', 'inter')
            ->where('event_id', $this->endToEndId)
            ->update(['processed' => true, 'updated_at' => now()]);
    }

    /**
     * @return string paga | ja_paga | valor_insuficiente | status_nao_cobravel | nao_encontrada | sem_txid
     */
    public function quitar(): string
    {
        $txid = trim((string) ($this->pix['txid'] ?? ''));
        // O Inter manda `valor` como texto ("150.00"); texto inválido lança e o job falha visível.
        $pagoCentavos = Centavos::de((string) ($this->pix['valor'] ?? '0'));
        $paidAt = substr((string) ($this->pix['horario'] ?? now()->toIso8601String()), 0, 10);

        if ($txid === '') {
            Log::info('recurringbilling.inter: Pix sem txid, nada a quitar', [
                'business_id' => $this->businessId, 'end_to_end_id' => $this->endToEndId,
            ]);

            return 'sem_txid';
        }

        [$resultado, $fatura] = DB::transaction(function () use ($txid, $pagoCentavos, $paidAt) {
            $fatura = DB::table('rb_invoices')
                ->where('business_id', $this->businessId)
                ->where('gateway_ref', $txid)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($fatura === null) {
                return ['nao_encontrada', null];
            }
            if ($fatura->status === 'paid') {
                return ['ja_paga', $fatura];
            }
            if (! in_array($fatura->status, ['open', 'overdue'], true)) {
                return ['status_nao_cobravel', $fatura];
            }
            if ($pagoCentavos < Centavos::de((string) $fatura->valor)) {
                return ['valor_insuficiente', $fatura];
            }

            $afetadas = DB::table('rb_invoices')
                ->where('id', $fatura->id)
                ->where('business_id', $this->businessId)
                ->whereIn('status', ['open', 'overdue'])
                ->update(['status' => 'paid', 'pago_em' => $paidAt, 'updated_at' => now()]);

            if ($afetadas !== 1) {
                return ['ja_paga', $fatura];
            }

            $this->creditarContaInter($pagoCentavos, $paidAt);

            return ['paga', $fatura];
        });

        if ($resultado === 'paga') {
            event(new InvoicePaid(
                businessId: $this->businessId,
                invoiceRef: (string) $fatura->numero_documento,
                valor: $pagoCentavos / 100,
                paidAt: $paidAt,
            ));
        } elseif ($resultado !== 'ja_paga') {
            Log::warning('recurringbilling.inter: Pix não quitou a fatura', [
                'business_id' => $this->businessId, 'end_to_end_id' => $this->endToEndId,
                'fatura_id' => $fatura?->id, 'motivo' => $resultado,
            ]);
        }

        return $resultado;
    }

    /** Crédito na conta Inter da empresa + saldo. Roda dentro da transação da quitação. */
    private function creditarContaInter(int $pagoCentavos, string $paidAt): void
    {
        $conta = DB::table('fin_contas_bancarias')
            ->where('business_id', $this->businessId)
            ->where('banco_codigo', '077') // Inter
            ->whereNull('deleted_at')
            ->first(['id', 'account_id']);

        if (! $conta?->account_id) {
            return;
        }

        $donoDaEmpresa = DB::table('business')->where('id', $this->businessId)->value('owner_id');
        if (! $donoDaEmpresa) {
            // Desfaz a quitação: o retry tenta de novo em vez de quitar sem o crédito.
            throw new \RuntimeException("Empresa {$this->businessId} sem owner_id para o crédito Inter.");
        }

        $valor = number_format($pagoCentavos / 100, 2, '.', '');

        DB::table('account_transactions')->insert([
            'account_id'       => $conta->account_id,
            'amount'           => $valor,
            'type'             => 'credit',
            'sub_type'         => 'deposit',
            'note'             => 'PIX recebido Inter — '.$this->endToEndId,
            'transaction_date' => $paidAt,
            'created_by'       => (int) $donoDaEmpresa,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        DB::table('fin_contas_bancarias')
            ->where('id', $conta->id)
            ->increment('saldo_cached', (float) $valor, ['saldo_atualizado_em' => now()]);
    }
}
