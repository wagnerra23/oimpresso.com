<?php

declare(strict_types=1);

namespace Modules\Vestuario\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * DevolucaoService — Wave 28 G2 W22 (CAPTERRA Vestuario).
 *
 * Implementa fluxo CDC Art. 26 + 50 + padrão setor "Vale-Trocas":
 *
 * - registrarDevolucao(): cria linha em vestuario_devolucoes (append-only)
 *   + se tipo=credito_ficha, credita saldo do cliente com expiração 6m
 *   + se tipo=estorno_dinheiro, exige flag aprovacao_supervisor=true
 * - consultarCreditoCliente(): saldo atual (ignora expirados)
 * - debitarCredito(): UPDATE atômico (transação DB) ao usar em nova venda
 * - expirarCreditosVencidos(): job mensal zera saldos pós expira_em
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]): todo método recebe
 * $businessId explicito (session() não funciona em fila/job/cron).
 *
 * Append-only Tier 0: nunca UPDATE de devolução existente. Correção é nova
 * linha referenciando original (analógico a ponto_marcacoes Portaria 671).
 *
 * Custo: zero IA (regra de negócio pura). Latência: 1-3 queries DB
 * (~10-30ms tipico). Sem dependência externa.
 *
 * @see Modules/Vestuario/Database/Migrations/2026_05_17_000001_*.php
 * @see Modules/Vestuario/Database/Migrations/2026_05_17_000002_*.php
 * @see memory/requisitos/Vestuario/CAPTERRA-FICHA.md (W22 G2)
 */
final class DevolucaoService
{
    /**
     * Tipos canônicos (espelha enum migration).
     */
    public const TIPOS_VALIDOS = [
        'troca_mesmo_produto',
        'troca_outro_produto',
        'credito_ficha',
        'estorno_dinheiro',
    ];

    /**
     * Validade default crédito ficha: 6 meses (CDC Art. 50 + padrão setor).
     */
    public const VALIDADE_CREDITO_MESES = 6;

    /**
     * Registra devolução append-only. Se tipo=credito_ficha, credita saldo.
     * Se tipo=estorno_dinheiro, exige aprovacao_supervisor=true no payload.
     *
     * @param  int  $businessId Tier 0 isolation obrigatório
     * @param  array<string,mixed>  $payload chaves obrigatórias:
     *   transaction_id, transaction_sell_line_id, contact_id (se credito),
     *   quantidade_devolvida, valor_devolvido, tipo, motivo,
     *   processed_by_user_id. Opcional: aprovacao_supervisor (bool).
     * @return array{id:int,saldo_atualizado:float|null}
     *
     * @throws InvalidArgumentException
     */
    public function registrarDevolucao(int $businessId, array $payload): array
    {
        $this->validarPayload($payload);

        if ($payload['tipo'] === 'estorno_dinheiro'
            && empty($payload['aprovacao_supervisor'])
        ) {
            throw new InvalidArgumentException(
                'Estorno em dinheiro requer aprovacao_supervisor=true (Tier 0 RBAC).'
            );
        }

        if ($payload['tipo'] === 'credito_ficha' && empty($payload['contact_id'])) {
            throw new InvalidArgumentException(
                'Crédito ficha requer contact_id (saldo é por cliente).'
            );
        }

        return DB::transaction(function () use ($businessId, $payload): array {
            $now = Carbon::now();

            $devolucaoId = DB::table('vestuario_devolucoes')->insertGetId([
                'business_id' => $businessId,
                'transaction_id' => (int) $payload['transaction_id'],
                'transaction_sell_line_id' => (int) $payload['transaction_sell_line_id'],
                'quantidade_devolvida' => (int) $payload['quantidade_devolvida'],
                'valor_devolvido' => (float) $payload['valor_devolvido'],
                'tipo' => $payload['tipo'],
                'motivo' => (string) $payload['motivo'],
                'processed_by_user_id' => (int) $payload['processed_by_user_id'],
                'processed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Reintegra o item devolvido ao estoque (DOC-RAIZ-ESTOQUE §3 `sell_return` → ENTRA),
            // espelhando o núcleo UltimatePOS (TransactionUtil::addSellReturn linha 6189). Vale pra
            // TODOS os tipos: o item físico volta pro estoque — a reposição de uma troca é uma venda
            // separada (não modelada aqui). Dentro do MESMO DB::transaction (INV-3).
            $this->reintegrarEstoque($businessId, $payload);

            $saldoAtualizado = null;

            if ($payload['tipo'] === 'credito_ficha') {
                $saldoAtualizado = $this->creditarSaldoCliente(
                    $businessId,
                    (int) $payload['contact_id'],
                    (float) $payload['valor_devolvido'],
                );
            }

            Log::info('vestuario.devolucao.registrada', [
                'business_id' => $businessId,
                'devolucao_id' => $devolucaoId,
                'tipo' => $payload['tipo'],
                'valor' => (float) $payload['valor_devolvido'],
            ]);

            return [
                'id' => $devolucaoId,
                'saldo_atualizado' => $saldoAtualizado,
            ];
        });
    }

    /**
     * Consulta saldo atual (sem considerar expirados — quem expira é o cron).
     * Retorna 0.0 se cliente não tem registro.
     */
    public function consultarCreditoCliente(int $businessId, int $contactId): float
    {
        $row = DB::table('vestuario_creditos_cliente')
            ->where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->whereNull('deleted_at')
            ->first();

        if (! $row) {
            return 0.0;
        }

        // Se já expirou, não retorna saldo (cron ainda não rodou).
        if ($row->expira_em !== null && Carbon::parse($row->expira_em)->isPast()) {
            return 0.0;
        }

        return (float) $row->saldo_credito;
    }

    /**
     * Debita saldo de crédito em nova venda. UPDATE atômico via transação DB.
     *
     * @param  int  $transactionId venda nova que está consumindo crédito
     * @return bool true se debitou; false se saldo insuficiente
     */
    public function debitarCredito(
        int $businessId,
        int $contactId,
        float $valor,
        int $transactionId,
    ): bool {
        if ($valor <= 0) {
            throw new InvalidArgumentException('Valor a debitar deve ser >0.');
        }

        return DB::transaction(function () use ($businessId, $contactId, $valor, $transactionId): bool {
            $row = DB::table('vestuario_creditos_cliente')
                ->where('business_id', $businessId)
                ->where('contact_id', $contactId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                return false;
            }

            if ($row->expira_em !== null && Carbon::parse($row->expira_em)->isPast()) {
                return false;
            }

            $saldoAtual = (float) $row->saldo_credito;
            if ($saldoAtual < $valor) {
                return false;
            }

            $novoSaldo = round($saldoAtual - $valor, 2);

            DB::table('vestuario_creditos_cliente')
                ->where('id', $row->id)
                ->update([
                    'saldo_credito' => $novoSaldo,
                    'updated_at' => Carbon::now(),
                ]);

            Log::info('vestuario.credito.debitado', [
                'business_id' => $businessId,
                'contact_id' => $contactId,
                'valor_debitado' => $valor,
                'saldo_anterior' => $saldoAtual,
                'saldo_novo' => $novoSaldo,
                'transaction_id' => $transactionId,
            ]);

            return true;
        });
    }

    /**
     * Job mensal (cron) zera saldos vencidos. Idempotente.
     * Retorna quantidade de registros zerados.
     */
    public function expirarCreditosVencidos(int $businessId): int
    {
        $now = Carbon::now();

        $afetados = DB::table('vestuario_creditos_cliente')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->whereNotNull('expira_em')
            ->where('expira_em', '<', $now)
            ->where('saldo_credito', '>', 0)
            ->update([
                'saldo_credito' => 0,
                'updated_at' => $now,
            ]);

        if ($afetados > 0) {
            Log::info('vestuario.credito.expirados_zerados', [
                'business_id' => $businessId,
                'qtd' => $afetados,
            ]);
        }

        return $afetados;
    }

    /**
     * Helper interno: credita saldo (UPSERT). Cria registro se inexistente.
     */
    private function creditarSaldoCliente(
        int $businessId,
        int $contactId,
        float $valor,
    ): float {
        $now = Carbon::now();
        $expiraEm = $now->copy()->addMonths(self::VALIDADE_CREDITO_MESES);

        $row = DB::table('vestuario_creditos_cliente')
            ->where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if (! $row) {
            DB::table('vestuario_creditos_cliente')->insert([
                'business_id' => $businessId,
                'contact_id' => $contactId,
                'saldo_credito' => $valor,
                'expira_em' => $expiraEm,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return $valor;
        }

        $novoSaldo = round((float) $row->saldo_credito + $valor, 2);

        DB::table('vestuario_creditos_cliente')
            ->where('id', $row->id)
            ->update([
                'saldo_credito' => $novoSaldo,
                // Renova validade a cada crédito (padrão setor — válido por 6m do último uso)
                'expira_em' => $expiraEm,
                'updated_at' => $now,
            ]);

        return $novoSaldo;
    }

    /**
     * Reintegra ao estoque a quantidade devolvida, no LOCAL da venda original.
     *
     * DOC-RAIZ-ESTOQUE §3 (`sell_return` → ENTRA): a devolução de venda volta pro estoque.
     * Usa ProductUtil::updateProductQuantity (caminho AUDITÁVEL — dispara LogsActivity +
     * respeita `enable_stock`: INV-1 e INV-5), NUNCA `DB::table`/`->qty_available =` direto.
     * Roda dentro do DB::transaction de registrarDevolucao (INV-3).
     *
     * Tier 0 ([ADR 0093]): valida que a sell_line/venda pertence ao MESMO business — devolver
     * uma linha de outro tenant aborta a transação (fail-secure).
     */
    private function reintegrarEstoque(int $businessId, array $payload): void
    {
        $quantidade = (float) $payload['quantidade_devolvida'];
        if ($quantidade <= 0) {
            return;
        }

        // Ambiente sem schema UltimatePOS (teste sintético sqlite): sem estoque a reintegrar.
        // Em prod/CI-MySQL a tabela sempre existe → reintegra. Mesmo padrão do fix R1 do
        // ConsumirEstoque (checa Schema antes de tocar caminho legado).
        if (! Schema::hasTable('transaction_sell_lines')) {
            return;
        }

        $sellLine = DB::table('transaction_sell_lines')
            ->where('id', (int) $payload['transaction_sell_line_id'])
            ->first();
        if ($sellLine === null) {
            return;
        }

        $transaction = DB::table('transactions')
            ->where('id', $sellLine->transaction_id)
            ->first();
        if ($transaction === null || $transaction->location_id === null) {
            return;
        }

        // Tier 0 (ADR 0093): a venda devolvida tem que ser do MESMO business.
        if ((int) $transaction->business_id !== $businessId) {
            throw new InvalidArgumentException(
                'Devolução cross-tenant: transaction_sell_line pertence a outro business.'
            );
        }

        // +quantidade no saldo (delta positivo) no local da venda. uf_data=false: número já cru.
        (new \App\Utils\ProductUtil)->updateProductQuantity(
            (int) $transaction->location_id,
            (int) $sellLine->product_id,
            (int) $sellLine->variation_id,
            $quantidade,
            0,
            null,
            false,
        );
    }

    /**
     * Validação básica payload (Tier 0 — early fail).
     */
    private function validarPayload(array $payload): void
    {
        $obrigatorios = [
            'transaction_id',
            'transaction_sell_line_id',
            'quantidade_devolvida',
            'valor_devolvido',
            'tipo',
            'motivo',
            'processed_by_user_id',
        ];

        foreach ($obrigatorios as $campo) {
            if (! array_key_exists($campo, $payload)) {
                throw new InvalidArgumentException("Campo obrigatório ausente: {$campo}");
            }
        }

        if (! in_array($payload['tipo'], self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException(
                "Tipo inválido: {$payload['tipo']}. Válidos: "
                . implode(', ', self::TIPOS_VALIDOS)
            );
        }

        if ((float) $payload['valor_devolvido'] <= 0) {
            throw new InvalidArgumentException('valor_devolvido deve ser >0.');
        }

        if ((int) $payload['quantidade_devolvida'] <= 0) {
            throw new InvalidArgumentException('quantidade_devolvida deve ser >0.');
        }

        if (trim((string) $payload['motivo']) === '') {
            throw new InvalidArgumentException('Motivo obrigatório (CDC Art. 26 audit).');
        }
    }
}
