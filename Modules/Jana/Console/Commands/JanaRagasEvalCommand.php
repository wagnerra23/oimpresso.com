<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * jana:ragas:eval — roda Pest suite RAGAS-style e emite JSON dashboard.
 *
 * Uso:
 *   php artisan jana:ragas:eval                 # roda local (precisa OPENAI_API_KEY)
 *   php artisan jana:ragas:eval --json          # output só JSON (CI artifact)
 *   php artisan jana:ragas:eval --suite=brief   # só BriefDiarioFaithfulnessTest
 *   php artisan jana:ragas:eval --suite=kb      # só KbAnswerRelevancyTest
 *
 * Exit code:
 *   0 = todos thresholds passaram
 *   1 = pelo menos uma métrica abaixo do threshold (gate fail → CI alerta)
 *
 * @see config/ragas.php — thresholds canônicos
 * @see ADR 0037 §GAP-2 — RAGAS gate em CI (Brief Diário + recall tools)
 */
class JanaRagasEvalCommand extends Command
{
    protected $signature = 'jana:ragas:eval
                            {--json : Output só JSON (sem tabela humana)}
                            {--suite=all : Suite (all|brief|kb)}';

    protected $description = 'Roda RAGAS gate (faithfulness/relevancy/precision/recall) sobre Brief Diário + kb-answer';

    public function handle(): int
    {
        if (! config('ragas.enabled', false) && ! $this->option('json')) {
            $this->warn('RAGAS gate desabilitado (config/ragas.php enabled=false).');
            $this->warn('Pra rodar local: .env RAGAS_ENABLED=true + OPENAI_API_KEY=sk-...');
        }

        $suite = $this->option('suite');
        $filter = match ($suite) {
            'brief' => '--filter=BriefDiarioFaithfulness',
            'kb'    => '--filter=KbAnswerRelevancy',
            default => '',
        };

        $cmd = [
            'vendor/bin/pest',
            'tests/Feature/Ragas',
            '--group=ragas',
            '--no-coverage',
            '--colors=never',
        ];

        if ($filter !== '') {
            $cmd[] = $filter;
        }

        if (! $this->option('json')) {
            $this->info('Rodando RAGAS suite: ' . implode(' ', $cmd));
        }

        $process = new Process($cmd, base_path());
        $process->setTimeout(600); // 10min — 4 métricas × 5 perguntas × 2 suites
        $process->run();

        $output     = $process->getOutput();
        $errOutput  = $process->getErrorOutput();
        $exitCode   = $process->getExitCode();

        $outputDir = config('ragas.output_dir', storage_path('app/ragas'));

        if (! File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $jsonPath  = "{$outputDir}/run-{$timestamp}.json";

        $report = [
            'ran_at'     => now()->toIso8601String(),
            'suite'      => $suite,
            'exit_code'  => $exitCode,
            'passed'     => $exitCode === 0,
            'thresholds' => config('ragas.thresholds'),
            'stdout'     => $output,
            'stderr'     => $errOutput,
        ];

        File::put($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line($output);
            if (! empty($errOutput)) {
                $this->error($errOutput);
            }
            $this->info("Report salvo: {$jsonPath}");
            $this->line('Thresholds: ' . json_encode(config('ragas.thresholds')));
        }

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
