<?php

declare(strict_types=1);

namespace App\Console\Commands\Evolution;

use App\Services\Evolution\MemoryQuery;
use Illuminate\Console\Command;

/**
 * evolution:query "<pergunta>" — busca top-K chunks em memory/.
 *
 * Fase 1a: busca textual simples (sem vetor/LLM).
 * Default output: humano. --json pra Claude Code consumir via Bash.
 *
 * @see memory/requisitos/EvolutionAgent/SPEC.md US-EVOL-002
 */
class QueryCommand extends Command
{
    protected $signature = 'evolution:query
                            {question : Pergunta ou termos de busca}
                            {--top=5 : Número de chunks a retornar}
                            {--json : Saída JSON pra consumo programático}';

    protected $description = 'Busca top-K chunks relevantes em memory/ (Fase 1a — sem vetor)';

    public function handle(): int
    {
        $question = (string) $this->argument('question');
        $topK = (int) $this->option('top');
        $json = (bool) $this->option('json');

        $memoryPath = config(
            'evolution.memory_path',
            base_path('memory')
        );

        $service = new MemoryQuery(memoryPath: $memoryPath);
        $results = $service->search($question, $topK);

        if ($json) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if (empty($results)) {
            $this->warn('Nenhum trecho encontrado para: '.$question);

            return self::SUCCESS;
        }

        $this->info(sprintf('Top %d trechos para: "%s"', count($results), $question));
        $this->newLine();

        foreach ($results as $i => $r) {
            $this->line(sprintf(
                '<fg=cyan>%d.</> <options=bold>%s</>%s  <fg=gray>(score: %.1f)</>',
                $i + 1,
                $r['file'],
                $r['heading'] !== '' ? '  ›  '.$r['heading'] : '',
                $r['score']
            ));
            $preview = mb_substr(trim((string) $r['content']), 0, 200);
            $this->line('   '.str_replace("\n", ' ', $preview).'...');
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
