<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Yaml\Yaml;

/**
 * US-COPI-081 (Sprint 7 ADR 0037) — Gate quantitativo Cycle 01.
 *
 * Pipeline RAGAS-style com 3 metrics calculadas via LLM-as-judge:
 *   - faithfulness:        claims na resposta são suportados pelo contexto?
 *   - answer_relevancy:    a resposta endereça a pergunta?
 *   - context_precision:   o contexto recuperado é útil pra responder?
 *
 * Pode rodar em 2 modos:
 *   --pipeline=adr       Usa só ADRs canônicas como contexto (eval rápido,
 *                        valida qualidade da KB)
 *   --pipeline=copiloto  Chama endpoint real do Copiloto (COPILOTO_EVAL_ENDPOINT
 *                        no .env) pra obter resposta + contexto. Eval do
 *                        produto end-to-end.
 *
 * Output:
 *   - Tabela no terminal com per-question + médias
 *   - JSON em tests/eval/results/ragas-YYYY-MM-DD-HHMMSS.json
 *
 * Custo aprox: 30 perguntas × 4 chamadas Sonnet (1 resposta + 3 metrics) =
 * 120 calls × ~500 tokens = ~$0.60/run.
 *
 * Sem ANTHROPIC_API_KEY → exit 0 graceful (pipeline pode rodar nos slots
 * sem chave em CI).
 *
 * Ver: ADR 0037 (Tier 7-9 RAG roadmap), memory/sessions/2026-04-29-baseline-rag.md
 */
class EvalRagasBaselineCommand extends Command
{
    protected $signature = 'eval:ragas-baseline
                            {--question= : Roda apenas 1 question por id}
                            {--category= : Filtra por categoria (ex: larissa-faturamento)}
                            {--pipeline=adr : Modo (adr | copiloto)}
                            {--judge-model= : Modelo do LLM judge (auto-detect: gpt-4o-mini se OpenAI, claude-sonnet-4-6 se Anthropic)}
                            {--provider= : Forçar provider (openai | anthropic). Auto-detect via env por default.}
                            {--threshold=0.7 : Score médio mínimo pra exit 0}';

    protected $description = 'Sprint 7 RAGAS — baseline com 3 metrics LLM-as-judge sobre golden questions';

    public function handle(): int
    {
        // Auto-detect provider: prefer OpenAI (mais barato), fallback Anthropic
        $provider = $this->option('provider');
        if (! $provider) {
            $provider = env('OPENAI_API_KEY') ? 'openai' : (env('ANTHROPIC_API_KEY') ? 'anthropic' : null);
        }
        if (! $provider) {
            $this->warn('Nem OPENAI_API_KEY nem ANTHROPIC_API_KEY no .env — eval pulado (graceful exit).');
            return 0;
        }

        $apiKey = $provider === 'openai' ? env('OPENAI_API_KEY') : env('ANTHROPIC_API_KEY');
        if (! $apiKey) {
            $this->warn(strtoupper($provider) . '_API_KEY ausente — eval pulado.');
            return 0;
        }

        $this->info("Provider: $provider");

        $yamlPath = base_path('tests/eval/golden-questions.yaml');
        if (! file_exists($yamlPath)) {
            $this->error("Arquivo não encontrado: $yamlPath");
            return 1;
        }

        $questions = Yaml::parseFile($yamlPath) ?: [];
        if ($id = $this->option('question')) {
            $questions = array_values(array_filter($questions, fn($q) => ($q['id'] ?? null) === $id));
        }
        if ($cat = $this->option('category')) {
            $questions = array_values(array_filter($questions, fn($q) => ($q['category'] ?? null) === $cat));
        }
        if (empty($questions)) {
            $this->warn('Nenhuma pergunta após filtros.');
            return 0;
        }

        $pipeline = $this->option('pipeline');
        $judgeModel = $this->option('judge-model')
            ?: ($provider === 'openai' ? 'gpt-4o-mini' : 'claude-sonnet-4-6');
        $this->info(sprintf('Pipeline: %s · Judge: %s · Perguntas: %d',
            $pipeline, $judgeModel, count($questions)));

        $results = [];
        foreach ($questions as $i => $q) {
            $id = $q['id'] ?? "q$i";
            $this->line(sprintf('→ [%d/%d] %s', $i + 1, count($questions), $id));

            // 1. Pega resposta + contexto via pipeline escolhida
            $rag = $this->runPipeline($pipeline, $q, $apiKey, $judgeModel, $provider);
            if ($rag === null) {
                $results[] = ['id' => $id, 'erro' => 'pipeline falhou'];
                continue;
            }

            // 2. Avalia 3 metrics RAGAS
            $metrics = $this->computeRagasMetrics(
                $q['question'],
                $rag['answer'],
                $rag['context'] ?? '',
                $apiKey,
                $judgeModel,
                $provider
            );

            // 3. must_contain fallback (continuidade com eval:adr-discovery)
            $stringMatch = $this->scoreStringMatch($rag['answer'], $q);

            $results[] = array_merge([
                'id'        => $id,
                'question'  => $q['question'],
                'category'  => $q['category'] ?? null,
                'answer'    => $rag['answer'],
                'context'   => mb_substr($rag['context'] ?? '', 0, 500),
            ], $metrics, $stringMatch);
        }

        $this->renderTable($results);
        $this->saveResults($pipeline, $judgeModel, $results);

        $avg = $this->avgRagasScore($results);
        $threshold = (float) $this->option('threshold');
        $this->line('');
        $this->line(sprintf('Score RAGAS médio: <info>%.3f</info> (threshold %.2f)', $avg, $threshold));

        return $avg >= $threshold ? 0 : 1;
    }

    /**
     * Pipeline ADR: contexto = ADRs canônicas grep-ed por keywords da pergunta.
     * Pipeline Copiloto: chama endpoint configurado em COPILOTO_EVAL_ENDPOINT.
     */
    private function runPipeline(string $mode, array $q, string $apiKey, string $model, string $provider): ?array
    {
        return match ($mode) {
            'adr'      => $this->pipelineAdr($q, $apiKey, $model, $provider),
            'copiloto' => $this->pipelineCopiloto($q),
            default    => null,
        };
    }

    private function pipelineAdr(array $q, string $apiKey, string $model, string $provider): ?array
    {
        // Recupera ADRs relevantes via grep simples (proxy de retrieval)
        $keywords = $this->extractKeywords($q['question']);
        $contextChunks = [];
        foreach (glob(base_path('memory/decisions/*.md')) as $f) {
            if (str_starts_with(basename($f), '_')) continue;
            $content = file_get_contents($f);
            $score = 0;
            foreach ($keywords as $kw) {
                if (mb_stripos($content, $kw) !== false) $score++;
            }
            if ($score >= 2) {
                $contextChunks[] = sprintf("# %s\n%s", basename($f), mb_substr($content, 0, 2000));
            }
            if (count($contextChunks) >= 3) break;
        }

        $context = implode("\n\n---\n\n", $contextChunks);
        $system = "Você é Claude no projeto oimpresso. Use apenas o CONTEXTO abaixo " .
            "(extraído de memory/decisions/) pra responder. Se a info não está no contexto, " .
            "diga 'não tenho info canônica'.\n\nCONTEXTO:\n" . ($context ?: '(vazio)');

        $answer = $this->askLlm($provider, $apiKey, $model, $system, $q['question']);
        if ($answer === null) return null;

        return ['answer' => $answer, 'context' => $context];
    }

    private function pipelineCopiloto(array $q): ?array
    {
        $endpoint = env('COPILOTO_EVAL_ENDPOINT');
        if (! $endpoint) {
            $this->warn('COPILOTO_EVAL_ENDPOINT não setado — pipeline copiloto indisponível');
            return null;
        }
        try {
            $resp = Http::timeout(60)->post($endpoint, [
                'question'    => $q['question'],
                'business_id' => env('COPILOTO_EVAL_BUSINESS_ID', 4),
            ]);
            if (! $resp->successful()) {
                $this->warn("Copiloto endpoint erro {$resp->status()}");
                return null;
            }
            $data = $resp->json();
            return [
                'answer'  => $data['answer'] ?? '',
                'context' => is_string($data['context'] ?? null)
                    ? $data['context']
                    : json_encode($data['context'] ?? []),
            ];
        } catch (\Throwable $e) {
            $this->warn("Copiloto pipeline exception: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * 3 metrics RAGAS via LLM judge. Cada uma pede ao Sonnet score 0-1
     * com critério específico. Avg dos 3 = score RAGAS final.
     */
    private function computeRagasMetrics(
        string $question,
        string $answer,
        string $context,
        string $apiKey,
        string $model,
        string $provider
    ): array {
        $faithfulness = $this->judge(
            $provider, $apiKey, $model,
            'Você avalia FIDELIDADE: a resposta tem afirmações que NÃO estão no contexto? ' .
            'Retorne APENAS um número 0.0-1.0 onde 1.0 = todas afirmações têm suporte no contexto, ' .
            '0.0 = resposta inventou tudo. Sem explicação, só o número.',
            "PERGUNTA: $question\n\nCONTEXTO:\n$context\n\nRESPOSTA:\n$answer"
        );

        $relevancy = $this->judge(
            $provider, $apiKey, $model,
            'Você avalia RELEVÂNCIA: a resposta endereça a pergunta? Retorne APENAS um número ' .
            '0.0-1.0 onde 1.0 = resposta direta e completa, 0.5 = parcial, 0.0 = irrelevante. Só o número.',
            "PERGUNTA: $question\n\nRESPOSTA:\n$answer"
        );

        $contextPrec = $this->judge(
            $provider, $apiKey, $model,
            'Você avalia PRECISÃO DO CONTEXTO: o contexto recuperado contém info útil pra a pergunta? ' .
            'Retorne APENAS um número 0.0-1.0 onde 1.0 = contexto totalmente relevante, ' .
            '0.0 = contexto inútil/sem relação. Só o número.',
            "PERGUNTA: $question\n\nCONTEXTO:\n" . ($context ?: '(vazio — nada recuperado)')
        );

        $ragasScore = ($faithfulness + $relevancy + $contextPrec) / 3.0;

        return [
            'faithfulness'      => $faithfulness,
            'answer_relevancy'  => $relevancy,
            'context_precision' => $contextPrec,
            'ragas_score'       => $ragasScore,
        ];
    }

    private function judge(string $provider, string $apiKey, string $model, string $criterio, string $payload): float
    {
        $resp = $this->askLlm($provider, $apiKey, $model, $criterio, $payload);
        if ($resp === null) return 0.0;
        // Extrai primeiro número decimal da resposta
        if (preg_match('/(\d+(?:\.\d+)?)/', $resp, $m)) {
            $n = (float) $m[1];
            return max(0.0, min(1.0, $n)); // clamp [0,1]
        }
        return 0.0;
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
            if (! $resp->successful()) return null;
            $data = $resp->json();
            return $data['choices'][0]['message']['content'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function scoreStringMatch(string $answer, array $q): array
    {
        $low = mb_strtolower($answer);
        $must = $q['must_contain'] ?? [];
        $mustNot = $q['must_not_contain'] ?? [];

        $hit = 0;
        foreach ($must as $term) {
            if (str_contains($low, mb_strtolower($term))) $hit++;
        }
        $notHit = 0;
        foreach ($mustNot as $term) {
            if (str_contains($low, mb_strtolower($term))) $notHit++;
        }

        return [
            'must_contain_hit'   => $hit,
            'must_contain_total' => count($must),
            'must_not_contain_hit' => $notHit,
        ];
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
            if (! $resp->successful()) return null;
            $data = $resp->json();
            return collect($data['content'] ?? [])
                ->filter(fn($b) => ($b['type'] ?? '') === 'text')
                ->pluck('text')
                ->join("\n") ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractKeywords(string $question): array
    {
        // Tokens >3 chars, sem stopwords PT
        $stop = ['quero', 'como', 'qual', 'onde', 'quanto', 'quais', 'esse', 'esta', 'isso',
                 'para', 'pelo', 'pela', 'sobre', 'tenho', 'tive', 'sou', 'estou', 'estão',
                 'mais', 'menos', 'foi', 'são', 'pode', 'devo', 'usar', 'fiz', 'faz', 'que'];
        $tokens = preg_split('/\s+|[?,.!:;]/', mb_strtolower($question));
        return array_values(array_unique(array_filter($tokens, fn($t) => mb_strlen($t) > 3 && ! in_array($t, $stop))));
    }

    private function renderTable(array $results): void
    {
        $rows = [];
        foreach ($results as $r) {
            if (isset($r['erro'])) {
                $rows[] = [$r['id'], '⚠️', '', '', '', '—'];
                continue;
            }
            $rows[] = [
                $r['id'],
                number_format($r['faithfulness'] ?? 0, 2),
                number_format($r['answer_relevancy'] ?? 0, 2),
                number_format($r['context_precision'] ?? 0, 2),
                number_format($r['ragas_score'] ?? 0, 2),
                "{$r['must_contain_hit']}/{$r['must_contain_total']}",
            ];
        }
        $this->table([
            'id', 'faith', 'relev', 'ctx_prec', 'RAGAS', 'must_contain'
        ], $rows);
    }

    private function saveResults(string $pipeline, string $model, array $results): void
    {
        $dir = base_path('tests/eval/results');
        if (! is_dir($dir)) mkdir($dir, 0755, true);
        $path = $dir . '/ragas-' . date('Y-m-d-His') . '.json';
        file_put_contents($path, json_encode([
            'timestamp'       => date('c'),
            'pipeline'        => $pipeline,
            'judge_model'     => $model,
            'avg_ragas_score' => $this->avgRagasScore($results),
            'avg_faithfulness'=> $this->avgMetric($results, 'faithfulness'),
            'avg_relevancy'   => $this->avgMetric($results, 'answer_relevancy'),
            'avg_ctx_precision' => $this->avgMetric($results, 'context_precision'),
            'count'           => count($results),
            'results'         => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line("JSON salvo: $path");
    }

    private function avgRagasScore(array $results): float
    {
        $valid = array_filter($results, fn($r) => isset($r['ragas_score']));
        if (empty($valid)) return 0.0;
        return array_sum(array_column($valid, 'ragas_score')) / count($valid);
    }

    private function avgMetric(array $results, string $key): float
    {
        $valid = array_filter($results, fn($r) => isset($r[$key]));
        if (empty($valid)) return 0.0;
        return array_sum(array_column($valid, $key)) / count($valid);
    }
}
