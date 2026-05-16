<?php

namespace Modules\ADS\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\ADS\Ai\Agents\ReviewerAgent;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * T11 — orquestra ReviewerAgent com:
 *   - self-consistency n=2 (faz 2 chamadas, média)
 *   - retry automático se score < 70 (T18)
 *   - escala pra Wagner se score < 50 OU confidence < 0.6
 *   - persiste review_score, review_breakdown, review_confidence em mcp_dual_brain_decisions
 */
class ReviewerService
{
    private const SCORE_RETRY_THRESHOLD = 70;
    private const SCORE_HUMAN_THRESHOLD = 50;
    private const CONFIDENCE_HUMAN_THRESHOLD = 0.6;
    private const SELF_CONSISTENCY_N = 2;
    private const MAX_ATTEMPTS = 3;

    /**
     * @return array{score:int, confidence:float, should_retry:bool, breakdown:array}
     */
    public function review(int $decisionId): array
    {
        $decision = DB::table('mcp_dual_brain_decisions')->where('id', $decisionId)->first();
        if (! $decision) {
            return $this->errorResult('decision_not_found');
        }

        // Auto-skip: se já reviewed nas últimas 24h, pula
        if ($decision->review_score !== null) {
            return [
                'score'        => (int) $decision->review_score,
                'confidence'   => (float) $decision->review_confidence,
                'should_retry' => false,
                'breakdown'    => json_decode($decision->review_breakdown ?? '{}', true) ?: [],
            ];
        }

        $reviews = [];
        for ($i = 0; $i < self::SELF_CONSISTENCY_N; $i++) {
            $reviews[] = $this->callReviewer($decision);
        }

        // Filtra reviews válidas
        $valid = array_filter($reviews, fn ($r) => $r !== null && isset($r['scores']['overall']));

        if (empty($valid)) {
            $this->markFailed($decisionId, 'all_review_calls_failed');
            return $this->errorResult('reviewer_failed');
        }

        // Self-consistency: média dos overall
        $overalls   = array_map(fn ($r) => (int) $r['scores']['overall'], $valid);
        $avgOverall = (int) round(array_sum($overalls) / count($overalls));

        $confidences  = array_map(fn ($r) => (float) ($r['confidence'] ?? 0.5), $valid);
        $avgConfidence = round(array_sum($confidences) / count($confidences), 3);

        // Breakdown vem do primeiro válido (suficiente)
        $first = reset($valid);
        $breakdown = $first['scores'];
        $breakdown['issues']    = $first['issues']    ?? [];
        $breakdown['strengths'] = $first['strengths'] ?? [];
        $breakdown['reasoning'] = $first['reasoning'] ?? '';

        // Decisão de retry
        $shouldRetry = $avgOverall < self::SCORE_RETRY_THRESHOLD
            && (int) $decision->attempts < self::MAX_ATTEMPTS;

        // Escala pra humano?
        $needsHuman = $avgOverall < self::SCORE_HUMAN_THRESHOLD
            || $avgConfidence < self::CONFIDENCE_HUMAN_THRESHOLD;

        DB::table('mcp_dual_brain_decisions')->where('id', $decisionId)->update([
            'review_score'      => $avgOverall,
            'review_breakdown'  => json_encode($breakdown, JSON_UNESCAPED_UNICODE),
            'review_confidence' => $avgConfidence,
        ]);

        // Auto-retry: marca pra reprocessamento
        if ($shouldRetry) {
            $retryAdjust = $first['retry_adjustment'] ?? '';
            $this->scheduleRetry($decisionId, $retryAdjust);
        }

        // Escala pra humano
        if ($needsHuman) {
            $this->escalateToHuman($decisionId, $avgOverall, $avgConfidence);
        }

        Log::channel('single')->info('ads.reviewer.completed', [
            'decision_id' => $decisionId,
            'score'       => $avgOverall,
            'confidence'  => $avgConfidence,
            'retry'       => $shouldRetry,
            'human'       => $needsHuman,
        ]);

        return [
            'score'        => $avgOverall,
            'confidence'   => $avgConfidence,
            'should_retry' => $shouldRetry,
            'breakdown'    => $breakdown,
        ];
    }

    private function callReviewer(object $decision): ?array
    {
        try {
            $agent = new ReviewerAgent(
                eventType:            $decision->event_type,
                domain:               $decision->domain,
                brainUsed:            $decision->brain_used,
                instructionGenerated: $decision->instruction_generated,
                expectedOutcome:      null,
                actualOutcome:        $decision->outcome,
                context: [
                    'risk_score'       => (float) $decision->risk_score,
                    'confidence_score' => (float) $decision->confidence_score,
                    'policy_applied'   => $decision->policy_applied,
                    'execution_ms'     => $decision->execution_ms,
                ],
            );

            $response = $agent->prompt($agent->montarPrompt());
            return $this->parseJson(trim((string) $response));
        } catch (\Throwable $e) {
            report($e);
            // D7.a — PiiRedactor wrap (exception message + class apenas, sem dump completo)
            $safeMessage = app(PiiRedactor::class)->redact($e->getMessage());
            Log::channel('single')->error('ads.reviewer.call_failed', [
                'decision_id' => $decision->id,
                'exception_class' => get_class($e),
                'message'         => $safeMessage,
            ]);
            return null;
        }
    }

    private function parseJson(string $raw): ?array
    {
        if (preg_match('/```(?:json)?\s*(\{.+\})\s*```/s', $raw, $m)) {
            $raw = $m[1];
        }
        $data = json_decode($raw, true);
        return is_array($data) && isset($data['scores']['overall']) ? $data : null;
    }

    private function scheduleRetry(int $decisionId, string $adjustment): void
    {
        DB::table('mcp_dual_brain_decisions')->where('id', $decisionId)->update([
            'attempts'      => DB::raw('attempts + 1'),
            'next_retry_at' => now()->addMinutes(5),
            'outcome'       => 'cancelled', // volta pra fila
            'brain_used'    => 'none',
            'wagner_modified_to' => $adjustment !== '' ? "RETRY: {$adjustment}" : 'RETRY automático',
        ]);
    }

    private function escalateToHuman(int $decisionId, int $score, float $confidence): void
    {
        DB::table('mcp_dual_brain_decisions')->where('id', $decisionId)->update([
            'destination' => 'pending_wagner',
            'hitl_level'  => 3,
        ]);
    }

    private function markFailed(int $decisionId, string $reason): void
    {
        DB::table('mcp_dual_brain_decisions')->where('id', $decisionId)->update([
            'outcome' => 'fail',
            'review_breakdown' => json_encode(['error' => $reason]),
        ]);
    }

    private function errorResult(string $reason): array
    {
        return ['score' => 0, 'confidence' => 0.0, 'should_retry' => false, 'breakdown' => ['error' => $reason]];
    }
}
