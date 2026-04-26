<?php

declare(strict_types=1);

namespace App\Console\Commands\Evolution;

use App\Services\Evolution\Tools\RankByRoiTool;
use Illuminate\Console\Command;

/**
 * evolution:rank --escopo=<X> — top N PRs/oportunidades por ROI.
 *
 * @see memory/requisitos/EvolutionAgent/SPEC.md US-EVOL-003
 */
class RankCommand extends Command
{
    protected $signature = 'evolution:rank
                            {--escopo= : Escopo (Financeiro, PontoWr2, Cms, Copiloto, EvolutionAgent...)}
                            {--top=3 : Número de itens a retornar}
                            {--json : Saída JSON}';

    protected $description = 'Ranqueia top-N oportunidades por ROI a partir do SPEC.md do escopo';

    public function handle(): int
    {
        $escopo = $this->option('escopo');
        $top = (int) $this->option('top');
        $json = (bool) $this->option('json');

        $tool = new RankByRoiTool;
        $items = $tool([
            'scope' => is_string($escopo) ? $escopo : null,
            'top' => $top,
        ]);

        if ($json) {
            $this->line(json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if (empty($items)) {
            $this->warn('Nenhuma oportunidade encontrada no escopo: '.($escopo ?? '(geral)'));

            return self::SUCCESS;
        }

        $this->info(sprintf('Top %d oportunidades%s:',
            count($items),
            $escopo ? ' em '.$escopo : ''
        ));
        $this->newLine();

        foreach ($items as $i => $item) {
            $this->line(sprintf(
                '<fg=cyan>%d.</> <options=bold>%s</> — ROI ~%.0f×',
                $i + 1,
                $item['titulo'] ?? '?',
                $item['roi_score'] ?? 0
            ));
            $this->line('   <fg=gray>fonte: '.($item['source'] ?? '?').'</>');
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
