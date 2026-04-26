<?php

declare(strict_types=1);

namespace App\Services\Evolution\Eval;

use App\Services\Evolution\Agents\EvolutionAgent;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

/**
 * Roda golden set + LLM-as-judge em Opus 4.5 (modelo diferente do agente: anti-viés).
 *
 * Score 0-5 por caso. Score esperado >=3.5/5 (US-EVOL-004 SPEC.md).
 *
 * @see memory/requisitos/EvolutionAgent/adr/tech/0002-eval-llm-as-judge-em-ci.md
 */
class GoldenSetRunner
{
    public function __construct(
        private readonly ?string $goldenSetPath = null,
        private readonly ?string $judgeModel = null,
    ) {}

    /**
     * @return array{score_avg:float, count:int, results:array<int, array<string,mixed>>}
     */
    public function run(): array
    {
        $path = $this->goldenSetPath ?? base_path('tests/Evolution/golden_set.json');

        if (! is_file($path)) {
            return ['score_avg' => 0.0, 'count' => 0, 'results' => [], 'error' => 'golden set não encontrado em '.$path];
        }

        $cases = json_decode((string) file_get_contents($path), true);
        if (! is_array($cases) || empty($cases)) {
            return ['score_avg' => 0.0, 'count' => 0, 'results' => [], 'error' => 'golden set vazio ou inválido'];
        }

        $agent = new EvolutionAgent;
        $results = [];
        $sumScore = 0.0;
        $judge = $this->judgeModel ?? (string) config('evolution.judge_model', 'claude-opus-4-5');

        foreach ($cases as $case) {
            $question = (string) ($case['pergunta'] ?? '');
            $rubric = (string) ($case['rubrica_esperada'] ?? '');
            $id = (string) ($case['id'] ?? '?');

            $response = $agent->run($question);
            $score = $this->judge($question, $response->text, $rubric, $judge);

            $sumScore += $score;
            $results[] = [
                'id' => $id,
                'pergunta' => $question,
                'resposta' => $response->text,
                'score' => $score,
                'tokens_in' => $response->tokensIn,
                'tokens_out' => $response->tokensOut,
                'latency_ms' => $response->latencyMs,
            ];
        }

        $count = count($results);
        $avg = $count > 0 ? $sumScore / $count : 0.0;

        return [
            'score_avg' => round($avg, 2),
            'count' => $count,
            'results' => $results,
            'judge_model' => $judge,
        ];
    }

    private function judge(string $question, string $response, string $rubric, string $judgeModel): float
    {
        $apiKey = (string) config('prism.providers.anthropic.api_key', '');

        if ($apiKey === '') {
            return $this->offlineHeuristicScore($response, $rubric);
        }

        $system = <<<PROMPT
Você é um juiz objetivo de respostas de agentes IA.
Pontue a resposta de 0.0 a 5.0 com 1 casa decimal, baseado em:
- accuracy (a resposta cobre os pontos da rubrica?)
- citação (cita arquivos memory/?)
- completude (não inventa, não foge do tema)

Devolva APENAS um número decimal entre 0.0 e 5.0. Nada mais.
PROMPT;

        $user = "Pergunta: {$question}\n\nRubrica esperada (pontos chave que a resposta deve cobrir):\n{$rubric}\n\nResposta do agente:\n{$response}\n\nScore (0.0 a 5.0):";

        try {
            $r = Prism::text()
                ->using(Provider::Anthropic, $judgeModel)
                ->withSystemPrompt($system)
                ->withPrompt($user)
                ->asText();

            $text = trim((string) ($r->text ?? ''));
            if (preg_match('/(\d+(?:[.,]\d+)?)/', $text, $m)) {
                $score = (float) str_replace(',', '.', $m[1]);

                return max(0.0, min(5.0, $score));
            }

            return 0.0;
        } catch (\Throwable $e) {
            return $this->offlineHeuristicScore($response, $rubric);
        }
    }

    private function offlineHeuristicScore(string $response, string $rubric): float
    {
        if ($response === '' || str_contains($response, 'Nenhum trecho')) {
            return 0.0;
        }

        $rubricTerms = array_filter(
            preg_split('/[\s,;.]+/u', mb_strtolower($rubric)) ?: [],
            fn ($t) => mb_strlen($t) >= 4
        );

        if (empty($rubricTerms)) {
            return 2.5;
        }

        $hay = mb_strtolower($response);
        $hits = 0;
        foreach ($rubricTerms as $term) {
            if (str_contains($hay, $term)) {
                $hits++;
            }
        }

        $ratio = $hits / count($rubricTerms);

        return round(min(5.0, $ratio * 5.0), 2);
    }
}
