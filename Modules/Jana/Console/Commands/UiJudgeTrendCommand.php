<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Entities\UiJudgeRun;

/**
 * jana:ui-judge-trend — responde "a técnica do juiz de UI está sendo medida?".
 *
 * Lê jana_ui_judge_runs (gravado por ui:judge-pr) e mostra, na janela pedida:
 *  - volume de julgamentos + custo estimado total
 *  - score médio + distribuição de verdict (loop fechado por métrica · princípio 4)
 *  - últimos N runs
 *
 *   php artisan jana:ui-judge-trend                 # últimos 30 dias
 *   php artisan jana:ui-judge-trend --days=7        # última semana
 *   php artisan jana:ui-judge-trend --last=20       # mostra 20 runs recentes
 *
 * @see app/Console/Commands/UiJudgePrCommand.php (grava as runs)
 */
class UiJudgeTrendCommand extends Command
{
    protected $signature = 'jana:ui-judge-trend
                            {--days=30 : Janela em dias}
                            {--last=10 : Quantos runs recentes listar}';

    protected $description = 'Trend do PR UI Judge — score médio, verdict, custo estimado por janela';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $last = max(1, (int) $this->option('last'));

        $since = now()->subDays($days);
        $runs = UiJudgeRun::query()->where('judged_at', '>=', $since);

        $total = (clone $runs)->count();

        if ($total === 0) {
            $this->warn("Nenhum julgamento nos últimos {$days} dias.");
            $this->line('  O juiz dispara em PR que toca UI (kill-switch PR_UI_JUDGE_ENABLED=true)');
            $this->line('  ou via: php artisan ui:judge-pr <N> --post-comment');

            return self::SUCCESS;
        }

        $avgScore = round((float) (clone $runs)->avg('score'), 1);
        $custo = round((float) (clone $runs)->sum('custo_usd_estimado'), 3);

        $verdicts = (clone $runs)
            ->selectRaw('verdict, count(*) as n')
            ->groupBy('verdict')
            ->pluck('n', 'verdict');

        $this->newLine();
        $this->info("PR UI Judge · últimos {$days} dias");
        $this->line("  Julgamentos: {$total}");
        $this->line("  Score médio: {$avgScore}/100");
        $this->line('  Verdict: '
            .'approve='.($verdicts['approve'] ?? 0).' · '
            .'comment='.($verdicts['comment'] ?? 0).' · '
            .'request_changes='.($verdicts['request_changes'] ?? 0));
        $this->line("  Custo estimado: ~\${$custo}");
        $this->newLine();

        $recent = (clone $runs)
            ->orderByDesc('judged_at')
            ->limit($last)
            ->get(['pr_number', 'model', 'score', 'verdict', 'violacoes_count', 'judged_at']);

        $this->table(
            ['PR', 'Modelo', 'Score', 'Verdict', 'Violações', 'Quando'],
            $recent->map(fn (UiJudgeRun $r) => [
                "#{$r->pr_number}",
                $r->model,
                "{$r->score}/100",
                $r->verdict,
                (string) $r->violacoes_count,
                $r->judged_at?->format('d/m H:i') ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
