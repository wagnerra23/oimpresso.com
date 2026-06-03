<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Console\Commands\HealthCheckCommand;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * `governanca:scorecard` (camada 3) — mecaniza o placar de governança [CC]×Jana.
 *
 * Por quê: o placar (8.2/8.7) era DIGITADO À MÃO — o anti-padrão que o próprio
 * report critica (prosa que envelhece). A meta 9.7 tem métrica contável: a razão
 * de graduação de lições nos dois ledgers. Este comando AGREGA o que já existe
 * (reusa `HealthCheckCommand::parseLessonLedger` + `::ledgerGraduationStats`) e
 * escreve um JSON que o `metricas.html` do Cowork passa a LER no re-sync — frescor
 * por mecanismo, não por memória humana (o ProfileDistiller da governança).
 *
 * NÃO recria motor de score (já há 6 — score-mechanized.mjs, module:grade,
 * screen-grade, ESLint ds/*, etc.). AGREGA (anti G1 do AUDITORIA_ROTINAS_DESIGN).
 *
 * Honestidade de escopo: mecaniza só o contável (graduação + contagem de checks +
 * presença de baselines). Eixos subjetivos ficam marcados `source: "estimativa [CC]"`
 * — não finge objetividade onde não há.
 *
 * Advisory: não numera ADR (soberania [W], ADR 0238) — proposta slug-only até [W].
 *
 * @see Modules/Jana/Console/Commands/HealthCheckCommand.php  (parser + check espelho)
 * @see Modules/Jana/LICOES-OPERACAO.md · memory/LICOES_CC.md  (os dois ledgers)
 */
class GovernancaScorecardCommand extends Command
{
    protected $signature = 'governanca:scorecard
                            {--json : escreve storage/reports/governanca-scorecard.json}
                            {--pipe-unico : marca o pipe único [CC]+Jana como presente (flag manual — condição 9.7)}';

    protected $description = 'Agrega o placar de governança [CC]×Jana (graduação de lições mecanizada) e opcionalmente escreve JSON.';

    private const OUTPUT_PATH = 'reports/governanca-scorecard.json';

    /** Eixos subjetivos do report [CC] — NÃO mecanizados (honestidade de escopo). */
    private const EIXOS_SUBJETIVOS = [
        ['nome' => 'Tiering de risco',         'valor' => 8.5, 'source' => 'estimativa [CC]'],
        ['nome' => 'Clareza de papéis [CC]×[W]', 'valor' => 8.7, 'source' => 'estimativa [CC]'],
        ['nome' => 'Frescor de memória',       'valor' => 8.2, 'source' => 'estimativa [CC]'],
    ];

    public function handle(): int
    {
        $scorecard = $this->buildScorecard();

        $this->renderTable($scorecard);

        if ($this->option('json')) {
            $dir = storage_path('reports');
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $path = storage_path(self::OUTPUT_PATH);
            file_put_contents(
                $path,
                json_encode($scorecard, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
            );
            $this->info("JSON escrito: {$path}");
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildScorecard(): array
    {
        $ledgers = [];
        $ratios = [];
        foreach (HealthCheckCommand::GOVERNANCA_LEDGERS as $key => $cfg) {
            $stats = HealthCheckCommand::ledgerGraduationStats(base_path($cfg['path']), $cfg['header']);
            if ($stats === null) {
                $ledgers[$key] = ['path' => $cfg['path'], 'presente' => false];
                continue;
            }
            $ledgers[$key] = [
                'path'             => $cfg['path'],
                'presente'         => true,
                'total'            => $stats['total'],
                'graduadas'        => $stats['graduadas'],
                'pendentes'        => $stats['pendentes'],
                'pendentes_ids'    => $stats['pendentes_ids'],
                'graduation_ratio' => $stats['graduation_ratio'],
            ];
            $ratios[] = $stats['graduation_ratio'];
        }

        $ratioMedio = $ratios !== [] ? round(array_sum($ratios) / count($ratios), 4) : 1.0;
        // enforcement_score (0-10) DERIVADO da razão de graduação — a fraqueza do
        // lado [CC] (seção 02 do report) se reflete aqui sem número digitado à mão.
        $enforcementScore = round($ratioMedio * 10, 2);

        $ambos100 = $ratios !== [] && count(array_filter($ratios, fn ($r) => $r >= 1.0)) === count($ratios);
        $pipeUnico = (bool) $this->option('pipe-unico');

        return [
            'meta' => [
                'generator'            => 'governanca:scorecard',
                'version'              => '1.0.0',
                'generated_at'         => now()->toIso8601String(),
                'measured_against_sha' => $this->currentSha(),
                'note'                 => 'Mecaniza só o contável (graduação de lições + contagem de checks + baselines). Eixos subjetivos = estimativa [CC] (source marcado).',
            ],
            'ledgers' => $ledgers,
            'mecanizado' => [
                'enforcement_score'      => $enforcementScore,
                'graduation_ratio_medio' => $ratioMedio,
                'health_checks_count'    => $this->healthChecksCount(),
                'baselines_presentes'    => [
                    'module_grades' => file_exists(base_path('governance/module-grades-baseline.json')),
                    'screen_grades' => file_exists(base_path('memory/governance/scorecards/screen-grades-baseline-2026-05-30.json')),
                ],
            ],
            'eixos_subjetivos' => self::EIXOS_SUBJETIVOS,
            'condicao_9_7' => [
                'ambos_ledgers_100'  => $ambos100,
                'pipe_unico_cc_jana' => $pipeUnico,
                'atingido'           => $ambos100 && $pipeUnico,
            ],
        ];
    }

    /**
     * Conta os métodos de check do jana:health-check via reflection (mecanizável,
     * sem bootar os checks SQL). Proxy honesto de "quantos checks a governança roda".
     */
    private function healthChecksCount(): int
    {
        try {
            $rc = new ReflectionClass(HealthCheckCommand::class);
            $count = 0;
            foreach ($rc->getMethods(ReflectionMethod::IS_PROTECTED) as $m) {
                if (preg_match('/^check[A-Z]/', $m->getName())) {
                    $count++;
                }
            }
            return $count;
        } catch (Throwable) {
            return 0;
        }
    }

    private function currentSha(): ?string
    {
        try {
            $out = @shell_exec('git rev-parse --short HEAD 2>&1');
            $sha = is_string($out) ? trim($out) : '';
            return preg_match('/^[0-9a-f]{7,40}$/', $sha) === 1 ? $sha : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $sc
     */
    private function renderTable(array $sc): void
    {
        $this->newLine();
        $this->info('GOVERNANÇA — Scorecard [CC]×Jana (camada 3 · mecanizado)');
        $rows = [];
        foreach ($sc['ledgers'] as $key => $l) {
            $rows[] = ($l['presente'] ?? false)
                ? [$key, "{$l['graduadas']}/{$l['total']}", number_format((float) $l['graduation_ratio'] * 100, 0) . '%', $l['pendentes'] . ' pendente(s)']
                : [$key, '—', '—', 'ausente'];
        }
        $this->table(['Ledger', 'Graduadas', 'Ratio', 'Status'], $rows);

        $m = $sc['mecanizado'];
        $this->line("enforcement_score: {$m['enforcement_score']}/10  ·  ratio médio: {$m['graduation_ratio_medio']}  ·  health checks: {$m['health_checks_count']}");
        $c = $sc['condicao_9_7'];
        $this->line('condição 9.7 → ambos 100%: ' . ($c['ambos_ledgers_100'] ? 'sim' : 'não')
            . '  ·  pipe único: ' . ($c['pipe_unico_cc_jana'] ? 'sim' : 'não')
            . '  ·  atingido: ' . ($c['atingido'] ? 'SIM' : 'não'));
        $this->newLine();
    }
}
