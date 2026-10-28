<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services\Banking;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\ExtratoLancamento;
use Modules\RecurringBilling\Dto\StatementLineDto;

/**
 * Grava o extrato de QUALQUER driver (`BankStatementDriverContract`) em
 * `fin_extrato_lancamentos` — a tabela que a Conciliação lê (ADR 0236).
 *
 * Extraído do `SyncBankStatementsJob` (US-RB-046) para o Inter, o OFX e os próximos
 * bancos gravarem pelo mesmo caminho: valor positivo + tipo C/D, `business_id` da
 * própria conta, idempotente pelo UNIQUE (conta_bancaria_id, idempotency_key).
 *
 * `origem`/`external_id` seguem a convenção do backfill da ADR 0236
 * (`api:<key>` / `ofx:<FITID>`) quando as colunas existem.
 *
 * @see US-FIN-084
 */
class StatementPersister
{
    /**
     * @param  Collection<int, StatementLineDto>  $linhas
     * @return array{novas:int, existentes:int}
     */
    public function gravar(ContaBancaria $conta, Collection $linhas, string $origem = 'api', ?string $sourceFile = null): array
    {
        $temUnificacao = Schema::hasColumn('fin_extrato_lancamentos', 'external_id');
        $novas = 0;

        foreach ($linhas as $linha) {
            $extra = [];
            if ($temUnificacao) {
                $extra = [
                    'origem'      => $origem,
                    'external_id' => $origem === 'ofx'
                        ? 'ofx:'.($linha->raw['fitid'] ?? $linha->idempotencyKey)
                        : 'api:'.$linha->idempotencyKey,
                    'source_file' => $sourceFile,
                ];
            }

            // SUPERADMIN: o upsert precisa enxergar a linha já existente da conta que está
            // sendo gravada — de qualquer contexto de sessão (fila, CLI, request) — para
            // respeitar o UNIQUE e continuar idempotente. O `business_id` gravado é sempre
            // o da PRÓPRIA conta.
            $registro = ExtratoLancamento::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->updateOrCreate(
                    [
                        'conta_bancaria_id' => $conta->id,
                        'idempotency_key'   => $linha->idempotencyKey,
                    ],
                    [
                        'business_id'           => $conta->business_id,
                        'data'                  => $linha->data->toDateString(),
                        'valor'                 => $linha->valor,
                        'tipo'                  => $linha->tipo,
                        'descricao'             => $linha->descricao,
                        'contraparte_documento' => $linha->contraparteDocumento,
                        'contraparte_nome'      => $linha->contraparteNome,
                        'raw_payload'           => $linha->raw,
                    ] + $extra
                );

            if ($registro->wasRecentlyCreated) {
                $novas++;
            }
        }

        return ['novas' => $novas, 'existentes' => $linhas->count() - $novas];
    }
}
