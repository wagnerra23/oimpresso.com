<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services\Integrations;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\ExtratoLancamento;
use Throwable;

/**
 * Sync de transações Pluggy → ExtratoLancamento oimpresso.
 *
 * Estado-da-arte W27 (2026-05-17): Open Banking BR via Pluggy.ai padrão de
 * mercado. Substitui CSV/OFX manual por sync incremental automático.
 *
 * Idempotência (Tier 0 IRREVOGÁVEL):
 *   - ExtratoLancamento tem UNIQUE `(conta_bancaria_id, idempotency_key)`
 *   - `idempotency_key` = hash determinístico do `transaction.id` Pluggy
 *   - Re-sync mesma janela = UPDATE no mesmo row (não duplica)
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - Recebe `$businessId` no constructor (NUNCA session() — Jobs async não tem)
 *   - `ContaBancaria` tem global scope; query filtra automático
 *   - `ExtratoLancamento::create` auto-preenche `business_id` quando session ok
 *     (em Job, passamos explícito no array)
 *
 * Custo prod estimado: 1 chamada HTTP /transactions por conta por sync. ~300ms
 * Volume: contas Larissa biz=4 = 2-3 contas × 1 sync/dia = ~6 chamadas/dia.
 * Custo Pluggy: ~R$ 0,30-1,00/conta/mês.
 *
 * @see PluggyClient
 * @see ExtratoLancamento  (US-RB-046 schema)
 */
class PluggyBankSyncService
{
    public function __construct(
        private PluggyClient $client,
    ) {}

    /**
     * Sincroniza transações de uma ContaBancaria oimpresso a partir de
     * uma conta Pluggy (`pluggyAccountId`) na janela [$from, $to].
     *
     * @param int    $businessId       Tier 0 — passado explícito (NUNCA session em Job)
     * @param int    $contaBancariaId  PK em fin_contas_bancarias
     * @param string $pluggyAccountId  ID da conta no Pluggy
     * @param Carbon $from             Início janela
     * @param Carbon $to               Fim janela
     *
     * @return array{imported:int, updated:int, skipped:int, errors:int}
     */
    public function syncAccount(
        int $businessId,
        int $contaBancariaId,
        string $pluggyAccountId,
        Carbon $from,
        Carbon $to,
    ): array {
        $conta = ContaBancaria::where('business_id', $businessId)
            ->where('id', $contaBancariaId)
            ->first();

        if (! $conta) {
            Log::warning('[PluggyBankSync] conta_bancaria não encontrada', [
                'business_id'       => $businessId,
                'conta_bancaria_id' => $contaBancariaId,
            ]);

            return ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 1];
        }

        $transactions = $this->client->listTransactions($pluggyAccountId, $from, $to);

        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        DB::transaction(function () use ($transactions, $conta, $businessId, &$stats) {
            foreach ($transactions as $tx) {
                try {
                    $result = $this->upsertTransaction($businessId, $conta->id, $tx);
                    $stats[$result] = ($stats[$result] ?? 0) + 1;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    Log::warning('[PluggyBankSync] upsert falhou', [
                        'business_id'       => $businessId,
                        'conta_bancaria_id' => $conta->id,
                        'tx_id'             => $tx['id'] ?? '[no-id]',
                        'error'             => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('[PluggyBankSync] sync concluído', [
            'business_id'       => $businessId,
            'conta_bancaria_id' => $conta->id,
            'window'            => $from->toDateString() . '..' . $to->toDateString(),
            'stats'             => $stats,
        ]);

        return $stats;
    }

    /**
     * UPSERT idempotente da transaction Pluggy em ExtratoLancamento.
     *
     * Idempotency key = hash determinístico do tx.id Pluggy (sem PII).
     * Update busca por (conta_bancaria_id, idempotency_key) UNIQUE.
     *
     * @param array<string, mixed> $tx  payload Pluggy /transactions
     * @return 'imported'|'updated'|'skipped'
     */
    public function upsertTransaction(int $businessId, int $contaBancariaId, array $tx): string
    {
        $pluggyId = (string) ($tx['id'] ?? '');
        if ($pluggyId === '') {
            return 'skipped';
        }

        $idempotencyKey = $this->buildIdempotencyKey($pluggyId);

        $amount = (float) ($tx['amount'] ?? 0.0);
        $tipo   = $amount >= 0 ? 'credito' : 'debito';
        $valor  = abs($amount);
        $data   = isset($tx['date']) ? Carbon::parse($tx['date'])->toDateString() : Carbon::now()->toDateString();

        $existing = ExtratoLancamento::withoutGlobalScopes()
            ->where('conta_bancaria_id', $contaBancariaId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $payload = [
            'business_id'       => $businessId,
            'conta_bancaria_id' => $contaBancariaId,
            'data'              => $data,
            'valor'             => $valor,
            'tipo'              => $tipo,
            'descricao'         => mb_substr((string) ($tx['description'] ?? ''), 0, 500),
            'idempotency_key'   => $idempotencyKey,
            'raw_payload'       => $tx,
        ];

        if ($existing) {
            // Idempotente: re-sync sobrescreve mesmo row (Pluggy pode revisar amount
            // após settle/clearing — não dupla).
            $existing->fill($payload)->save();

            return 'updated';
        }

        ExtratoLancamento::create($payload);

        return 'imported';
    }

    /**
     * Hash determinístico do transaction.id Pluggy. Não embute PII (só o id
     * canônico da plataforma). 64 chars hex < 191 = índice MySQL OK.
     */
    public function buildIdempotencyKey(string $pluggyTransactionId): string
    {
        return 'pluggy:' . hash('sha256', $pluggyTransactionId);
    }
}
