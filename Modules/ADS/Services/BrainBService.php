<?php

namespace Modules\ADS\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\ADS\Ai\Agents\BrainBAgent;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * ARQ-0002 — Orquestra a chamada ao Brain B (Claude API).
 *
 * Não executa código. Recebe uma decision com destination=brain_b,
 * chama o agente, parseia a instrução e atualiza o registro.
 *
 * Observabilidade D9.a (ADR 0155): `process()` envolto em `OtelHelper::span(`
 * (Tracer ads.brain_b.process) — mede latência Claude API + tokens.
 */
class BrainBService
{
    /**
     * Processa uma única decision pendente em destination=brain_b.
     *
     * @return array{instruction: ?array, error: ?string}
     */
    public function process(int $decisionId): array
    {
        return OtelHelper::span('ads.brain_b.process', [
            'decision_id' => $decisionId,
        ], fn () => $this->doProcess($decisionId));
    }

    private function doProcess(int $decisionId): array
    {
        $decision = DB::table('mcp_dual_brain_decisions')->where('id', $decisionId)->first();

        if (! $decision) {
            return ['instruction' => null, 'error' => 'decision_not_found'];
        }

        if ($decision->destination !== 'brain_b') {
            return ['instruction' => null, 'error' => 'destination_not_brain_b'];
        }

        if ($decision->brain_used !== 'none') {
            return ['instruction' => null, 'error' => 'already_processed'];
        }

        $startedAt = microtime(true);

        try {
            $agent = new BrainBAgent(
                eventType:       $decision->event_type,
                domain:          $decision->domain,
                filesAffected:   json_decode($decision->files_affected ?? '[]', true) ?: [],
                metadata:        json_decode($decision->event_metadata ?? '{}', true) ?: [],
                riskScore:       (float) $decision->risk_score,
                confidenceScore: (float) $decision->confidence_score,
            );

            $response = $agent->prompt($agent->montarPrompt());
            $rawText  = trim((string) $response);
            $instruction = $this->parseJson($rawText);

            $executionMs = (int) ((microtime(true) - $startedAt) * 1000);
            $tokensUsed  = is_object($response) && isset($response->usage)
                ? ($response->usage->totalTokens ?? null)
                : null;

            DB::table('mcp_dual_brain_decisions')
                ->where('id', $decisionId)
                ->update([
                    'brain_used'            => 'brain_b',
                    'model_used'            => config('ai.providers.anthropic.model', 'claude-sonnet-4-6'),
                    'instruction_generated' => $rawText,
                    'tokens_used'           => $tokensUsed,
                    'execution_ms'          => $executionMs,
                    // Resetar outcome para 'cancelled' (= aguardando Wagner aprovar na UI HiTL-2).
                    // Se rodou anteriormente como 'fail' e agora deu certo, volta ao estado pendente.
                    'outcome'               => 'cancelled',
                    // resolved_at/by só preenchemos quando Wagner aprovar/rejeitar (não aqui).
                ]);

            Log::channel('single')->info('ads.brain_b.processed', [
                'decision_id' => $decisionId,
                'event_type'  => $decision->event_type,
                'tokens'      => $tokensUsed,
                'ms'          => $executionMs,
            ]);

            return ['instruction' => $instruction, 'error' => null];
        } catch (\Throwable $e) {
            // D7.a — PiiRedactor wrap antes de persistir/logar mensagem de erro
            // ($e->getMessage() pode vazar PII de fixtures/payloads reais).
            $redactor = app(PiiRedactor::class);
            $safeMessage = $redactor->redact($e->getMessage());

            DB::table('mcp_dual_brain_decisions')
                ->where('id', $decisionId)
                ->update([
                    'outcome'             => 'fail',
                    'instruction_generated' => 'ERRO: ' . $safeMessage,
                    'resolved_at'         => now(),
                    'resolved_by'         => 'brain_b',
                ]);

            Log::channel('single')->error('ads.brain_b.failed', [
                'decision_id' => $decisionId,
                'message'     => $safeMessage,
            ]);

            return ['instruction' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Tenta extrair JSON limpo da resposta do modelo.
     * Modelos às vezes envolvem em ```json...``` apesar das instruções.
     */
    private function parseJson(string $raw): ?array
    {
        if (preg_match('/```(?:json)?\s*(\{.+\})\s*```/s', $raw, $m)) {
            $raw = $m[1];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
