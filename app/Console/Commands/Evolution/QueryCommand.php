<?php

declare(strict_types=1);

namespace App\Console\Commands\Evolution;

use App\Services\Evolution\Agents\EvolutionAgent;
use App\Services\Evolution\Agents\FinanceiroAgent;
use App\Services\Evolution\MemoryQuery;
use Illuminate\Console\Command;

/**
 * evolution:query "<pergunta>" — busca + (Fase 1b) responde via EvolutionAgent.
 *
 * Default: textual; --agent: roteia via EvolutionAgent (router) ou sub-agent
 * via --escopo=Financeiro. --json pra Claude Code consumir via Bash.
 *
 * @see memory/requisitos/EvolutionAgent/SPEC.md US-EVOL-002
 */
class QueryCommand extends Command
{
    protected $signature = 'evolution:query
                            {question : Pergunta ou termos de busca}
                            {--top=5 : Número de chunks a retornar}
                            {--escopo= : Filtrar/rotear por escopo (Financeiro, PontoWr2, Cms, Copiloto)}
                            {--agent : Rota via EvolutionAgent (Vizra-shaped) em vez de busca textual}
                            {--model= : Override de provider:modelo (ex: deepseek:deepseek-chat, opus, sonnet, grok)}
                            {--json : Saída JSON pra consumo programático}';

    protected $description = 'Busca top-K chunks ou rota via EvolutionAgent (Fase 1b)';

    public function handle(): int
    {
        $question = (string) $this->argument('question');
        $topK = (int) $this->option('top');
        $json = (bool) $this->option('json');
        $useAgent = (bool) $this->option('agent');
        $escopo = $this->option('escopo');

        if ($useAgent) {
            return $this->handleAgent($question, is_string($escopo) ? $escopo : null, $json);
        }

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

    private function handleAgent(string $question, ?string $escopo, bool $json): int
    {
        $agent = match ($escopo) {
            'Financeiro' => new FinanceiroAgent,
            default => new EvolutionAgent,
        };

        $modelOverride = $this->option('model');
        if (is_string($modelOverride) && $modelOverride !== '') {
            $agent->withModel($modelOverride);
        }

        $response = $agent->run($question);

        if ($json) {
            $this->line(json_encode([
                'agent' => get_class($agent),
                'text' => $response->text,
                'tokens_in' => $response->tokensIn,
                'tokens_out' => $response->tokensOut,
                'latency_ms' => $response->latencyMs,
                'traces' => $response->traces,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '[%s] resposta (%d tokens in / %d out, %dms):',
            class_basename(get_class($agent)),
            $response->tokensIn,
            $response->tokensOut,
            $response->latencyMs
        ));
        $this->newLine();
        $this->line($response->text);

        return self::SUCCESS;
    }
}
