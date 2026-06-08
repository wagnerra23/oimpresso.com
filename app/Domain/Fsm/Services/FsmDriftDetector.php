<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * US-SELL-032 v2 — Detector offline de drift do FSM canônico (ADR 0129).
 *
 * Problema: o TransactionFsmObserver (hook Eloquent `updating`) pega
 *   $model->current_stage_id = X; $model->save();
 * MAS NÃO pega bypass via:
 *   - Mass update Eloquent: Model::where(...)->update(['current_stage_id' => X])
 *   - Query builder cru: DB::table('...')->where(...)->update([...])
 *   - Tinker direto, DDL manual, etc.
 *
 * Estratégia: comparar `current_stage_id` atual do subject com o último
 * `to_stage_id` registrado em `sale_stage_history`. Divergência = drift
 * (alguém moveu o stage sem passar pelo ExecuteStageActionService).
 *
 * Severidades:
 *   - `orphan`   : subject tem current_stage_id mas ZERO entries em history
 *                  (nunca passou pelo Service desde sempre)
 *   - `mismatch` : tem history mas o último to_stage_id ≠ current_stage_id
 *                  (mass update fora do gateway depois de transições legítimas)
 *
 * Performance: 1 query SQL com LEFT JOIN na última history row por
 * (business_id, transaction_id) — sem N+1. Aguenta 100k+ rows.
 *
 * Multi-tenant Tier 0 (ADR 0093): scope opcional por $businessId.
 *
 * Whitelist de tabelas vive no FsmScanDriftCommand (anti SQL-injection).
 */
class FsmDriftDetector
{
    /**
     * Escaneia uma tabela FSM-managed em busca de drifts.
     *
     * @param  string    $tableName   Nome da tabela (já validado pelo caller via whitelist)
     * @param  int|null  $businessId  Limita a um business_id (null = todos)
     * @param  int       $limit       Máximo de rows pra inspecionar
     * @return array<int, array{
     *   business_id: int,
     *   transaction_id: int,
     *   current_stage_id: int,
     *   expected_stage_id: int|null,
     *   last_history_at: string|null,
     *   severity: 'orphan'|'mismatch',
     * }>
     */
    public function scan(string $tableName, ?int $businessId = null, int $limit = 1000): array
    {
        // Subquery: pega o último to_stage_id por (business_id, transaction_id)
        // via ROW_NUMBER simulado — usamos GROUP BY no executed_at MAX + JOIN
        // pra recuperar o to_stage_id correspondente. Funciona em MySQL e SQLite
        // (sem window functions).
        //
        // Caminho:
        //   1. last_history := SELECT business_id, transaction_id, MAX(executed_at) AS last_at
        //      FROM sale_stage_history GROUP BY business_id, transaction_id
        //   2. last_row := JOIN sale_stage_history h ON
        //      (h.business_id, h.transaction_id, h.executed_at) = last_history
        //   3. LEFT JOIN subject ON business_id + id
        //   4. Filtra divergência ou orphan.

        $bindings = [];

        $bizFilter = '';
        if ($businessId !== null) {
            $bizFilter = ' AND s.business_id = ?';
            $bindings[] = $businessId;
        }

        // Identificadores entre backticks — $tableName veio da whitelist.
        $sql = "
            SELECT
                s.business_id        AS business_id,
                s.id                 AS transaction_id,
                s.current_stage_id   AS current_stage_id,
                last_h.to_stage_id   AS expected_stage_id,
                last_h.executed_at   AS last_history_at
            FROM `{$tableName}` s
            LEFT JOIN (
                SELECT h.business_id, h.transaction_id, h.to_stage_id, h.executed_at
                FROM sale_stage_history h
                INNER JOIN (
                    SELECT business_id, transaction_id, MAX(executed_at) AS max_at
                    FROM sale_stage_history
                    GROUP BY business_id, transaction_id
                ) latest
                    ON latest.business_id = h.business_id
                   AND latest.transaction_id = h.transaction_id
                   AND latest.max_at = h.executed_at
            ) last_h
                ON last_h.business_id = s.business_id
               AND last_h.transaction_id = s.id
            WHERE s.current_stage_id IS NOT NULL
              {$bizFilter}
            ORDER BY s.business_id ASC, s.id ASC
            LIMIT {$limit}
        ";

        $rows = DB::select($sql, $bindings);

        $drifts = [];

        foreach ($rows as $row) {
            $current = (int) $row->current_stage_id;
            $expected = $row->expected_stage_id !== null ? (int) $row->expected_stage_id : null;
            $lastAt = $row->last_history_at !== null ? (string) $row->last_history_at : null;

            // Caso 1: zero history → orphan
            if ($expected === null && $lastAt === null) {
                $drifts[] = [
                    'business_id' => (int) $row->business_id,
                    'transaction_id' => (int) $row->transaction_id,
                    'current_stage_id' => $current,
                    'expected_stage_id' => null,
                    'last_history_at' => null,
                    'severity' => 'orphan',
                ];

                continue;
            }

            // Caso 2: tem history mas to_stage_id diverge → mismatch
            // Nota: to_stage_id pode ser null legitimamente (ex. action "re-emitir
            // 2ª via" que não transita). Se for null, comparamos com null — se
            // current_stage_id ≠ null, é mismatch ("subject deveria ter ficado no
            // mesmo stage da action anterior mas mudou").
            if ($expected !== $current) {
                $drifts[] = [
                    'business_id' => (int) $row->business_id,
                    'transaction_id' => (int) $row->transaction_id,
                    'current_stage_id' => $current,
                    'expected_stage_id' => $expected,
                    'last_history_at' => $lastAt,
                    'severity' => 'mismatch',
                ];
            }
        }

        // Emite log estruturado pra cada drift (alerting pipeline pode consumir).
        foreach ($drifts as $drift) {
            Log::warning('FsmDriftDetector: drift detected', $drift);
        }

        return $drifts;
    }
}
