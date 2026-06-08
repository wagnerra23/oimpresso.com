<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Fsm\Services\FsmDriftDetector;
use Illuminate\Console\Command;

/**
 * US-SELL-032 v2 — Comando offline `fsm:scan-drift` (ADR 0129).
 *
 * Roda o {@see FsmDriftDetector} contra uma tabela FSM-managed e reporta
 * cada drift (current_stage_id que bypassou o TransactionFsmObserver via
 * mass-update Eloquent, DB::table writes, tinker, etc.).
 *
 * Exit codes:
 *   0  zero drifts (CI/cron OK)
 *   1  >= 1 drift detectado (CI/cron alerta)
 *   2  argumento inválido (tabela fora da whitelist)
 *
 * Schedule: daily 03:00 BRT (ver `App\Console\Kernel::schedule`).
 *
 * Whitelist anti SQL-injection: $tableName entra na query crua direto.
 * Toda tabela FSM-managed nova precisa ser adicionada aqui.
 */
class FsmScanDriftCommand extends Command
{
    protected $signature = 'fsm:scan-drift
                            {table : Tabela FSM-managed pra escanear (ex: transactions)}
                            {--business= : Limita a um business_id}
                            {--limit=1000 : Máximo de rows a inspecionar}';

    protected $description = 'Detecta drift FSM: rows com current_stage_id que bypassou o TransactionFsmObserver';

    /**
     * Tabelas FSM-managed permitidas. Atualizar quando US futuras adicionarem
     * `current_stage_id` a outras entidades (job_sheets, mcp_tasks, etc.).
     *
     * `fsm_test_subjects` está aqui SÓ pros testes Pest — em prod a tabela
     * não existe (drop em afterEach), portanto o comando retorna "tabela
     * vazia" inofensivamente se alguém rodar em prod com esse arg.
     *
     * @var list<string>
     */
    private const WHITELIST = [
        'transactions',
        'fsm_test_subjects',
        // TODO: adicionar `job_sheets` quando US-REP-NN incluir FSM
        // TODO: adicionar `mcp_tasks` quando US-COPI-NN incluir FSM
    ];

    public function handle(FsmDriftDetector $detector): int
    {
        $table = (string) $this->argument('table');

        if (! in_array($table, self::WHITELIST, true)) {
            $this->error("Tabela '{$table}' não está na whitelist FSM-managed.");
            $this->line('Whitelist atual: ' . implode(', ', self::WHITELIST));
            $this->line('Adicione em FsmScanDriftCommand::WHITELIST se for FSM-managed legítima.');

            return 2;
        }

        $businessIdRaw = $this->option('business');
        $businessId = $businessIdRaw !== null ? (int) $businessIdRaw : null;
        $limit = (int) $this->option('limit');

        if ($limit < 1) {
            $limit = 1000;
        }

        $this->line('Scanning table: ' . $table);
        $this->line('Business filter: ' . ($businessId !== null ? (string) $businessId : '(all)'));
        $this->line('Limit: ' . $limit);
        $this->line('');

        $drifts = $detector->scan($table, $businessId, $limit);

        if (empty($drifts)) {
            $this->info('No drift detected. OK.');

            return 0;
        }

        $this->warn('Found ' . count($drifts) . ' drift(s):');

        foreach ($drifts as $drift) {
            $line = $this->formatDrift($drift);
            $this->line('  ' . $line);
        }

        $this->line('');
        $this->error('Exit code: 1 (drift found)');

        return 1;
    }

    /**
     * @param  array{
     *   business_id: int,
     *   transaction_id: int,
     *   current_stage_id: int,
     *   expected_stage_id: int|null,
     *   last_history_at: string|null,
     *   severity: string,
     * }  $drift
     */
    private function formatDrift(array $drift): string
    {
        $biz = $drift['business_id'];
        $tx = $drift['transaction_id'];
        $current = $drift['current_stage_id'];
        $severity = $drift['severity'];

        if ($severity === 'orphan') {
            return sprintf(
                'business=%d, transaction=%d: current=%d, expected=NULL (orphan)',
                $biz,
                $tx,
                $current
            );
        }

        $expected = $drift['expected_stage_id'] !== null
            ? (string) $drift['expected_stage_id']
            : 'NULL';
        $lastAt = $drift['last_history_at'] ?? '?';

        return sprintf(
            'business=%d, transaction=%d: current=%d, expected=%s (mismatch, last %s)',
            $biz,
            $tx,
            $current,
            $expected,
            $lastAt
        );
    }
}
