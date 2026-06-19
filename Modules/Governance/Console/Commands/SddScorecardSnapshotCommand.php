<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * governance:sdd-scorecard-snapshot — GT-G7 (plano SDD 2026-06-12 §2 GARANTIDA).
 *
 * Roda `node scripts/governance/sdd-scorecard.mjs --json`, cruza com o baseline
 * versionado (armed por métrica — ADR 0275 §3), computa composta v1 (média simples
 * das ARMADAS normalizadas — ADR 0275 §4) + alertas (armada regrediu / fonte
 * vermelha) e persiste 1 row/dia em `mcp_sdd_scorecard_history` (re-run substitui).
 * `--input`/`--baseline` aceitam fixtures em testes/CI sem node.
 *
 * @see memory/decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md
 */
class SddScorecardSnapshotCommand extends Command
{
    protected $signature = 'governance:sdd-scorecard-snapshot
                            {--input= : Path de JSON pré-gerado do scorecard (testes/CI sem node)}
                            {--baseline= : Path do baseline armed (default governance/sdd-scorecard-baseline.json)}
                            {--date= : Data do snapshot Y-m-d (default hoje)}';

    protected $description = 'Snapshot diário do scorecard SDD (composta v1 + alertas) em mcp_sdd_scorecard_history — GT-G7, ADR 0275';

    public function handle(): int
    {
        if (! Schema::hasTable('mcp_sdd_scorecard_history')) {
            $this->error('Tabela mcp_sdd_scorecard_history não existe — rode `php artisan migrate` primeiro.');

            return self::FAILURE;
        }

        $date = (string) ($this->option('date') ?: now()->format('Y-m-d'));

        try {
            $scorecard = $this->loadScorecard();
        } catch (\Throwable $e) {
            $this->error('Falha ao medir scorecard SDD: '.$e->getMessage());

            return self::FAILURE;
        }

        $baselinePath = (string) ($this->option('baseline') ?: base_path('governance/sdd-scorecard-baseline.json'));
        $baseline = is_file($baselinePath)
            ? (json_decode((string) file_get_contents($baselinePath), true) ?: [])
            : [];

        $resumo = $this->summarize($scorecard, $baseline['metrics'] ?? []);

        // Idempotência por dia: re-run substitui (delete + insert atômicos na
        // mesma snapshot_date — transaction evita dia sem row se insert falhar).
        DB::transaction(function () use ($date, $resumo, $scorecard): void {
            DB::table('mcp_sdd_scorecard_history')->where('snapshot_date', $date)->delete();
            DB::table('mcp_sdd_scorecard_history')->insert([
                'snapshot_date' => $date,
                'composta'      => $resumo['composta'],
                'payload'       => json_encode([
                    'composta'      => $resumo['composta'],
                    'composta_k'    => $resumo['k'],
                    'vivas'         => $resumo['vivas'],
                    'metrics_total' => $resumo['total'],
                    'alerts'        => $resumo['alerts'],
                    'scorecard'     => $scorecard,
                ], JSON_UNESCAPED_UNICODE),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        });

        $this->info(sprintf(
            'Snapshot SDD OK — %s · composta %s (k=%d) · %d/%d vivas · %d alertas',
            $date,
            $resumo['composta'] ?? '—',
            $resumo['k'],
            $resumo['vivas'],
            $resumo['total'],
            count($resumo['alerts']),
        ));

        return self::SUCCESS;
    }

    /** @return array{metrics: array<string, array<string, mixed>>} */
    private function loadScorecard(): array
    {
        if ($input = $this->option('input')) {
            $raw = (string) file_get_contents((string) $input);
        } else {
            $process = new Process(['node', 'scripts/governance/sdd-scorecard.mjs', '--json'], base_path(), null, null, 120);
            $process->mustRun();
            $raw = $process->getOutput();
        }

        $json = json_decode($raw, true);
        if (! is_array($json) || ! isset($json['metrics']) || ! is_array($json['metrics'])) {
            throw new RuntimeException('output do sdd-scorecard.mjs não é JSON válido com chave `metrics`');
        }

        return $json;
    }

    /**
     * Composta v1 (ADR 0275 §4): média simples das métricas ARMADAS normalizadas
     * (score = clamp((value-baseline)/(target-baseline))×100; invertida pra "down").
     * Alertas: armada que regrediu vs baseline + fonte vermelha (media e parou).
     *
     * @return array{vivas: int, total: int, k: int, composta: float|null, alerts: list<string>}
     */
    private function summarize(array $scorecard, array $baseMetrics): array
    {
        $vivas = 0;
        $scores = [];
        $alerts = [];

        foreach (($scorecard['metrics'] ?? []) as $name => $m) {
            $measured = ($m['status'] ?? null) === 'measured' && is_numeric($m['value'] ?? null);
            if ($measured) {
                $vivas++;
            }
            $b = $baseMetrics[$name] ?? null;
            if ($b === null) {
                continue;
            }
            $bMeasured = ($b['status'] ?? null) === 'measured' && is_numeric($b['value'] ?? null);
            if ($bMeasured && ! $measured) {
                $alerts[] = "{$name}: fonte vermelha (media no baseline, não mediu agora)";
                continue;
            }
            if (! $bMeasured || ! $measured) {
                continue;
            }

            $down = ($m['direction'] ?? 'up') === 'down';
            $value = (float) $m['value'];
            $base = (float) $b['value'];
            $armed = ($b['armed'] ?? false) === true;

            if ($armed && ($down ? $value > $base : $value < $base)) {
                $alerts[] = sprintf('%s: %s → %s (armada — só pode %s)', $name, $b['value'], $m['value'], $down ? 'descer' : 'subir');
            }
            if ($armed && is_numeric($m['target'] ?? null)) {
                $scores[] = $this->normalize($value, $base, (float) $m['target'], $down);
            }
        }

        $k = count($scores);

        return [
            'vivas'    => $vivas,
            'total'    => count($scorecard['metrics'] ?? []),
            'k'        => $k,
            'composta' => $k > 0 ? round(array_sum($scores) / $k, 1) : null,
            'alerts'   => $alerts,
        ];
    }

    private function normalize(float $value, float $baseline, float $target, bool $down): float
    {
        $den = $down ? $baseline - $target : $target - $baseline;
        if ($den == 0.0) { // baseline já no alvo — binário
            return ($down ? $value <= $target : $value >= $target) ? 100.0 : 0.0;
        }
        $num = $down ? $baseline - $value : $value - $baseline;

        return max(0.0, min(1.0, $num / $den)) * 100;
    }
}
