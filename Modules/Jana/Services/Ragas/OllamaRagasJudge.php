<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Ragas;

use Illuminate\Support\Facades\Http;

/**
 * OllamaRagasJudge — juiz RAGAS LOCAL (Ollama self-host CT 100), ZERO egress.
 *
 * US-COPI-137 — liga a medição de qualidade sobre o tráfego REAL da Jana SEM
 * mandar dado de cliente pra provedor pago (LGPD Tier 0 — ADR 0093). Reusa os
 * MESMOS prompts RAGAS do {@see RagasJudgeService} (herança) — só troca o
 * transporte: OpenAI → Ollama `/api/chat` no CT 100. Assim o eval online (só
 * faithfulness, no Job) e o `jana:ragas-real-eval --judge=local` (4 métricas)
 * compartilham UM juiz e UM conjunto de prompts (SoC — sem duplicar prompt).
 *
 * ⛔ ANTITAUTOLOGIA / HONESTIDADE (proibicoes.md §"Teste que deriva do código" +
 * lápide 2026-07-17 "não fabricar score de alarme"): em falha (Ollama down,
 * HTTP != 2xx, JSON inválido, sem `score`) o juiz LANÇA {@see JudgeUnavailableException}
 * em vez de devolver `0.0`. Um `0.0` fabricaria "totalmente infiel" numa queda
 * de infra (falso alarme) — quem chama DECIDE pular, nunca grava score fantasma.
 * (Difere do pai, que devolve 0.0 em erro — comportamento herdado do gate de CI.)
 *
 * ⚠️ PRÉ-REQ DE INFRA: o Ollama do CT 100 (`ollama-embedder`) hoje só tem
 * embedders (`qwen3-embedding:0.6b`, `nomic-embed-text`) — um modelo de CHAT
 * precisa ser puxado (`ollama pull <model>`) pro juiz funcionar. Sem o modelo,
 * o Ollama devolve erro e este juiz lança (honesto) → o consumidor pula.
 * CPU-only é ok aqui: o Job é ASSÍNCRONO (fila, amostra ~5%) — latência não
 * atinge a resposta ao cliente.
 *
 * URL/modelo/timeout vêm de `copiloto.online_eval.local.*` (config-as-code,
 * SEM env() — o config.php da Jana tem baseline Larastan fixo de env()). Trocar
 * o modelo = editar config.php + `config:clear` (commit auditável).
 *
 * @see RagasJudgeService — prompts + métricas RAGAS herdadas
 * @see \Modules\Jana\Jobs\Telemetry\JudgeTraceOnlineJob — consumidor online (faithfulness)
 * @see \Modules\Jana\Console\Commands\JanaRagasRealEvalCommand — consumidor batch (--judge=local)
 * @see memory/requisitos/Jana/SPEC.md#US-COPI-137
 */
class OllamaRagasJudge extends RagasJudgeService
{
    /**
     * Override do transporte: chama o Ollama LOCAL do CT 100 em vez do OpenAI.
     * Mantém a assinatura do pai (substituível 1:1) — os prompts vêm dele.
     *
     * @throws JudgeUnavailableException quando não há score confiável (nunca 0.0 fabricado).
     */
    protected function callJudge(string $prompt, string $metric): float
    {
        $url = rtrim((string) config('copiloto.online_eval.local.url', 'http://ollama-embedder:11434'), '/');
        $model = (string) config('copiloto.online_eval.local.model', 'qwen2.5:3b');
        $timeout = (int) config('copiloto.online_eval.local.timeout', 120);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post("{$url}/api/chat", [
                    'model' => $model,
                    'stream' => false,
                    // `format: json` força o Ollama a devolver JSON válido (sem prosa em volta).
                    'format' => 'json',
                    'options' => ['temperature' => 0],
                    'messages' => [
                        ['role' => 'system', 'content' => 'Você é avaliador RAGAS imparcial. Retorna sempre JSON válido.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (\Throwable $e) {
            throw new JudgeUnavailableException(
                "Ollama judge indisponível (metric={$metric}, model={$model}): {$e->getMessage()}",
                0,
                $e,
            );
        }

        if (! $response->successful()) {
            throw new JudgeUnavailableException(
                "Ollama judge HTTP {$response->status()} (metric={$metric}, model={$model})",
            );
        }

        // Ollama /api/chat: { "message": { "content": "<json string>" }, ... }
        $content = $response->json('message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new JudgeUnavailableException("Ollama judge sem message.content (metric={$metric})");
        }

        $data = json_decode($content, true);
        if (! is_array($data) || ! array_key_exists('score', $data) || ! is_numeric($data['score'])) {
            throw new JudgeUnavailableException(
                "Ollama judge devolveu JSON sem 'score' numérico (metric={$metric})",
            );
        }

        // Sanitiza pra range 0..1 (mesma regra do pai).
        return max(0.0, min(1.0, (float) $data['score']));
    }
}
