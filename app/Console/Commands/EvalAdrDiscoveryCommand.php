<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Yaml\Yaml;

/**
 * Camada 3 — Eval LLM-as-judge da Eval Suite (Opção C).
 *
 * Roda golden questions de tests/eval/golden-questions.yaml contra o LLM
 * SEM tools (testa o pior caso — modelo sem busca canônica). Score baseado em
 * substring match contra `must_contain` / `must_not_contain`.
 *
 * Provider auto-detect (pós-migração OpenAI): prefere OpenAI (mais barato),
 * fallback Anthropic. Forçável via --provider. Modelo default por provider:
 * gpt-4o-mini (openai) / claude-sonnet-4-6 (anthropic). Custo gpt-4o-mini
 * ~$0.01/run com 8 perguntas.
 *
 * Output:
 *   - Tabela no terminal
 *   - JSON em tests/eval/results/YYYY-MM-DD-HHMMSS.json (histórico)
 *
 * Exit code 1 se score médio < 0.7 (CI gate).
 *
 * Sem OPENAI_API_KEY nem ANTHROPIC_API_KEY no env, sai 0 graceful (não quebra CI).
 *
 * Ver: ADR 0064/0065/0066 (entradas canônicas testadas).
 */
class EvalAdrDiscoveryCommand extends Command
{
    protected $signature = 'eval:adr-discovery
                            {--question= : Roda apenas 1 question por id}
                            {--provider= : Forçar provider (openai | anthropic). Auto-detect via env por default.}
                            {--model= : Modelo do LLM (auto-detect: gpt-4o-mini se OpenAI, claude-sonnet-4-6 se Anthropic)}
                            {--threshold=0.7 : Score médio mínimo pra exit 0}';

    protected $description = 'Eval LLM-as-judge das golden questions sobre ADRs canônicas';

    public function handle(): int
    {
        // Provider auto-detect: prefere OpenAI (mais barato), fallback Anthropic.
        $provider = $this->option('provider')
            ?: (env('OPENAI_API_KEY') ? 'openai' : (env('ANTHROPIC_API_KEY') ? 'anthropic' : null));

        if ($provider === null) {
            $this->warn('Nem OPENAI_API_KEY nem ANTHROPIC_API_KEY no .env — eval pulado (graceful exit).');
            return 0;
        }

        $apiKey = $provider === 'openai' ? env('OPENAI_API_KEY') : env('ANTHROPIC_API_KEY');
        if (! $apiKey) {
            $this->warn(strtoupper($provider) . '_API_KEY ausente — eval pulado (graceful exit).');
            return 0;
        }

        $yamlPath = base_path('tests/eval/golden-questions.yaml');
        if (! file_exists($yamlPath)) {
            $this->error("Arquivo não encontrado: $yamlPath");
            return 1;
        }

        $questions = Yaml::parseFile($yamlPath);
        if (! is_array($questions) || empty($questions)) {
            $this->error('golden-questions.yaml vazio ou inválido');
            return 1;
        }

        $filter = $this->option('question');
        if ($filter) {
            $questions = array_values(array_filter($questions, fn($q) => ($q['id'] ?? null) === $filter));
            if (empty($questions)) {
                $this->error("Question id=`$filter` não encontrada");
                return 1;
            }
        }

        $model = $this->option('model')
            ?: ($provider === 'openai' ? 'gpt-4o-mini' : 'claude-sonnet-4-6');
        $systemPrompt = "Você é um assistente trabalhando no projeto oimpresso (ERP gráfico Laravel + UltimatePOS). " .
            "Responda usando apenas conhecimento de `memory/decisions/*.md` (ADRs canônicas do projeto). " .
            "Se não souber, diga 'não tenho info canônica'. Seja conciso (3-6 frases).";

        $results = [];
        foreach ($questions as $q) {
            $id = $q['id'] ?? '?';
            $this->line("→ Avaliando: <info>$id</info>");

            $resposta = $this->askLlm($provider, $apiKey, $model, $systemPrompt, $q['question']);
            if ($resposta === null) {
                $results[] = [
                    'id' => $id, 'score' => 0.0, 'erro' => 'API call falhou',
                    'must_contain_hit' => 0, 'must_contain_total' => count($q['must_contain'] ?? []),
                    'must_not_contain_hit' => 0,
                ];
                continue;
            }

            $score = $this->scoreResposta($resposta, $q);
            $results[] = array_merge($score, [
                'id' => $id,
                'question' => $q['question'],
                'expected_adr' => $q['expected_adr'] ?? null,
                'response' => $resposta,
            ]);
        }

        // Tabela
        $rows = [];
        foreach ($results as $r) {
            $rows[] = [
                $r['id'],
                number_format($r['score'], 2),
                "{$r['must_contain_hit']}/{$r['must_contain_total']}",
                $r['must_not_contain_hit'] > 0 ? '⚠️ ' . $r['must_not_contain_hit'] : '0',
            ];
        }
        $this->table(['id', 'score', 'must_contain', 'must_not_contain hits'], $rows);

        $media = empty($results) ? 0 : array_sum(array_column($results, 'score')) / count($results);
        $this->line('');
        $this->line(sprintf('Score médio: <info>%.2f</info> (threshold %.2f)', $media, (float) $this->option('threshold')));

        // JSON history
        $resultsDir = base_path('tests/eval/results');
        if (! is_dir($resultsDir)) mkdir($resultsDir, 0755, true);
        $jsonPath = $resultsDir . '/' . date('Y-m-d-His') . '.json';
        file_put_contents($jsonPath, json_encode([
            'timestamp' => date('c'),
            'provider'  => $provider,
            'model'     => $model,
            'avg_score' => $media,
            'results'   => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line("JSON salvo em: $jsonPath");

        return $media >= (float) $this->option('threshold') ? 0 : 1;
    }

    private function askLlm(string $provider, string $apiKey, string $model, string $system, string $userMsg): ?string
    {
        return $provider === 'openai'
            ? $this->askOpenAi($apiKey, $model, $system, $userMsg)
            : $this->askAnthropic($apiKey, $model, $system, $userMsg);
    }

    private function askOpenAi(string $apiKey, string $model, string $system, string $userMsg): ?string
    {
        try {
            $resp = Http::withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $userMsg],
                ],
                'max_tokens'  => 1024,
                'temperature' => 0,
            ]);

            if (! $resp->successful()) {
                $this->warn("API erro {$resp->status()}: " . substr($resp->body(), 0, 200));
                return null;
            }
            return $resp->json()['choices'][0]['message']['content'] ?? null;
        } catch (\Throwable $e) {
            $this->warn("HTTP exception: {$e->getMessage()}");
            return null;
        }
    }

    private function askAnthropic(string $apiKey, string $model, string $system, string $userMsg): ?string
    {
        try {
            $resp = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $model,
                'max_tokens' => 1024,
                'system'     => $system,
                'messages'   => [['role' => 'user', 'content' => $userMsg]],
            ]);

            if (! $resp->successful()) {
                $this->warn("API erro {$resp->status()}: " . substr($resp->body(), 0, 200));
                return null;
            }
            $data = $resp->json();
            $text = collect($data['content'] ?? [])
                ->filter(fn($b) => ($b['type'] ?? '') === 'text')
                ->pluck('text')
                ->join("\n");
            return $text ?: null;
        } catch (\Throwable $e) {
            $this->warn("HTTP exception: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * @return array{score:float, must_contain_hit:int, must_contain_total:int, must_not_contain_hit:int}
     */
    private function scoreResposta(string $resposta, array $q): array
    {
        $low = mb_strtolower($resposta);
        $must = $q['must_contain'] ?? [];
        $mustNot = $q['must_not_contain'] ?? [];

        $hit = 0;
        foreach ($must as $term) {
            if (str_contains($low, mb_strtolower($term))) $hit++;
        }

        $bad = 0;
        foreach ($mustNot as $term) {
            if (str_contains($low, mb_strtolower($term))) $bad++;
        }

        $total = max(count($must), 1);
        $ratio = $hit / $total;

        $score = match (true) {
            $bad > 0          => 0.0,
            $ratio >= 1.0     => 1.0,
            $ratio > 0.5      => 0.5,
            default           => 0.0,
        };

        return [
            'score'                => $score,
            'must_contain_hit'     => $hit,
            'must_contain_total'   => $total,
            'must_not_contain_hit' => $bad,
        ];
    }
}
