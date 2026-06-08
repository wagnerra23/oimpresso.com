<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Whatsapp\Entities\EmployeePerformance;
use Modules\Whatsapp\Jobs\RebuildEmployeePerformanceJob;
use Modules\Whatsapp\Services\EmployeePerformance\EmployeePerformanceRebuilder;

/**
 * US-WA-VOZ-003 — Backfill employee_performance one-shot por business.
 *
 * Detecta automaticamente atendentes a partir de 2 fontes:
 *   1. DISTINCT messages.sender_user_id (UI Inbox oimpresso) — PRIMÁRIO
 *   2. Pattern *Nome:* extraído de messages.body (chip WhatsApp Web direto) — FALLBACK
 *
 * Pra cada atendente detectado, dispara rebuild (sync ou queue).
 *
 * Uso:
 *   php artisan employee-performance:backfill --business=1 --dry-run
 *   php artisan employee-performance:backfill --business=1
 *   php artisan employee-performance:backfill --business=1 --queue
 *   php artisan employee-performance:backfill --business=1 --extra-names=Eliana,Wagner
 *
 * @see Modules/Whatsapp/Services/EmployeePerformance/EmployeePerformanceRebuilder.php
 */
class EmployeePerformanceBackfillCommand extends Command
{
    protected $signature = 'employee-performance:backfill
        {--business= : business_id obrigatório (Tier 0)}
        {--dry-run : só conta + lista detectados, NÃO grava}
        {--queue : dispatcha jobs em vez de rodar síncrono}
        {--extra-names= : CSV de nomes adicionais pra heurística (ex: Eliana,Wagner)}
        {--detail : log linha-a-linha de cada rebuild}';

    protected $description = 'Backfill employee_performance — detecta atendentes via sender_user_id + heurística (US-WA-VOZ-003).';

    /** Heurísticos default — nomes conhecidos do time WR (TEAM.md) */
    public const DEFAULT_HEURISTIC_NAMES = ['Maiara', 'Luiz', 'Felipe', 'Wagner', 'Eliana'];

    public function handle(EmployeePerformanceRebuilder $rebuilder): int
    {
        $businessId = (int) $this->option('business');
        if ($businessId <= 0) {
            $this->error('--business=N obrigatório (Tier 0 multi-tenant ADR 0093).');
            return Command::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $useQueue = (bool) $this->option('queue');
        $detail = (bool) $this->option('detail');

        $extra = array_filter(array_map('trim', explode(',', (string) $this->option('extra-names'))));
        $heuristicNames = array_unique(array_merge(self::DEFAULT_HEURISTIC_NAMES, $extra));

        $this->info("Detectando atendentes biz={$businessId}...");

        // Fonte 1 — sender_user_id distinct
        $userIds = DB::table('messages as m')
            ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
            ->where('c.business_id', $businessId)
            ->where('m.direction', 'outbound')
            ->where('m.is_internal_note', false)
            ->whereNotNull('m.sender_user_id')
            ->distinct()
            ->pluck('m.sender_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Fonte 2 — heurísticos com pelo menos 1 match no business
        $heuristicsAtivos = [];
        foreach ($heuristicNames as $name) {
            $found = DB::table('messages as m')
                ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
                ->where('c.business_id', $businessId)
                ->where('m.direction', 'outbound')
                ->where('m.is_internal_note', false)
                ->where('m.body', 'like', '%' . $name . ':%')
                ->limit(1)
                ->count();
            if ($found > 0) {
                $heuristicsAtivos[] = $name;
            }
        }

        $this->info(sprintf(
            "Detectados: %d via sender_user_id + %d via heurística nome",
            count($userIds),
            count($heuristicsAtivos),
        ));

        if ($dryRun) {
            $this->table(
                ['fonte', 'identidade'],
                array_merge(
                    array_map(fn ($id) => ['sender_user_id', "user_id={$id}"], $userIds),
                    array_map(fn ($n) => ['heurística', "*{$n}:*"], $heuristicsAtivos),
                )
            );
            return Command::SUCCESS;
        }

        $total = count($userIds) + count($heuristicsAtivos);

        if ($total === 0) {
            $this->info('Nenhum atendente detectado.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $ok = 0;
        $err = 0;

        $process = function (string $kind, ?int $userId, ?string $heur) use (&$ok, &$err, $businessId, $useQueue, $rebuilder, $detail, $bar) {
            try {
                if ($useQueue) {
                    RebuildEmployeePerformanceJob::dispatch(
                        $businessId,
                        $userId,
                        $heur,
                        EmployeePerformance::REBUILT_VIA_BACKFILL,
                    );
                } else {
                    $perf = $rebuilder->rebuild(
                        $businessId,
                        $userId,
                        $heur,
                        EmployeePerformance::REBUILT_VIA_BACKFILL,
                    );
                    if ($detail) {
                        $this->line("  ✓ {$kind} {$perf->identidade()} → nota={$perf->nota_geral}/100");
                    }
                }
                $ok++;
            } catch (\Throwable $e) {
                $err++;
                if ($detail) {
                    $this->error("  ✗ {$kind} " . ($userId ?? $heur) . ": " . $e->getMessage());
                }
            }
            $bar->advance();
        };

        foreach ($userIds as $id) {
            $process('user', $id, null);
        }
        foreach ($heuristicsAtivos as $name) {
            $process('heur', null, $name);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Resultado: {$ok} ok · {$err} erros");

        if ($useQueue) {
            $this->info("Rode: php artisan queue:work database --queue=employee-performance --stop-when-empty");
        }

        return Command::SUCCESS;
    }
}
