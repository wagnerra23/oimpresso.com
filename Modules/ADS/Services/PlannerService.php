<?php

namespace Modules\ADS\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\ADS\Ai\Agents\PlannerAgent;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * T9 — Orquestra PlannerAgent: chama, parseia, cria child decisions.
 *
 * Política: child decisions herdam business_id do parent. event_source='scheduler'
 * (não brain_a) pra distinguir. Auto_generated=true pra contar quota.
 */
class PlannerService
{
    public function __construct(
        private readonly DecisionRouter $router,
    ) {}

    /**
     * Decompõe uma decision em subtarefas via PlannerAgent.
     * @return array{success:bool, plan:?array, subtasks_created:int, error:?string}
     */
    public function plan(int $decisionId): array
    {
        $decision = DB::table('mcp_dual_brain_decisions')->where('id', $decisionId)->first();

        if (! $decision) {
            return ['success' => false, 'plan' => null, 'subtasks_created' => 0, 'error' => 'decision_not_found'];
        }

        // Não decompõe se já tem subtasks
        $hasSubtasks = DB::table('mcp_dual_brain_decisions')
            ->where('parent_decision_id', $decisionId)
            ->exists();
        if ($hasSubtasks) {
            return ['success' => false, 'plan' => null, 'subtasks_created' => 0, 'error' => 'already_planned'];
        }

        try {
            $description = $this->extractDescription($decision);

            $agent = new PlannerAgent(
                eventType:           $decision->event_type,
                domain:              $decision->domain,
                originalDescription: $description,
                filesAffected:       json_decode($decision->files_affected ?? '[]', true) ?: [],
                context: [
                    'risk_score'       => (float) $decision->risk_score,
                    'confidence_score' => (float) $decision->confidence_score,
                    'policy_applied'   => $decision->policy_applied,
                ],
            );

            $response = $agent->prompt($agent->montarPrompt());
            $plan = $this->parseJson(trim((string) $response));

            if (! $plan) {
                return ['success' => false, 'plan' => null, 'subtasks_created' => 0, 'error' => 'invalid_json'];
            }

            if (! empty($plan['rejected'])) {
                Log::channel('single')->warning('ads.planner.rejected', [
                    'decision_id' => $decisionId,
                    'reason'      => $plan['rejection_reason'] ?? '',
                ]);
                DB::table('mcp_dual_brain_decisions')->where('id', $decisionId)->update([
                    'wagner_modified_to' => 'PLANNER REJEITOU: ' . ($plan['rejection_reason'] ?? ''),
                ]);
                return ['success' => false, 'plan' => $plan, 'subtasks_created' => 0, 'error' => 'planner_rejected'];
            }

            $subtasks = $plan['subtasks'] ?? [];
            $created = 0;
            foreach ($subtasks as $st) {
                if (empty($st['event_type']) || empty($st['domain'])) continue;

                // Cria child decision via Router (passa pelo Policy Engine)
                $childDecision = $this->router->route(new RoutingInput(
                    businessId:    $decision->business_id,
                    eventType:     $st['event_type'],
                    eventSource:   'scheduler',
                    domain:        $st['domain'],
                    filesAffected: $st['files_affected'] ?? [],
                    metadata: [
                        'parent_decision_id'   => $decisionId,
                        'order'                => $st['order'] ?? 0,
                        'depends_on'           => $st['depends_on'] ?? [],
                        'title'                => $st['title'] ?? '',
                        'acceptance_criteria'  => $st['acceptance_criteria'] ?? '',
                        'estimated_minutes'    => $st['estimated_minutes'] ?? null,
                        'planner_summary'      => $plan['decomposition_summary'] ?? '',
                    ],
                ));

                // Set parent_decision_id + auto_generated diretamente (Router não seta)
                DB::table('mcp_dual_brain_decisions')
                    ->where('id', $childDecision->decisionId)
                    ->update([
                        'parent_decision_id' => $decisionId,
                        'auto_generated'     => true,
                    ]);

                $created++;
            }

            // Marca a decision pai como "planned"
            DB::table('mcp_dual_brain_decisions')->where('id', $decisionId)->update([
                'instruction_generated' => $response instanceof \Stringable ? (string) $response : json_encode($plan),
            ]);

            Log::channel('single')->info('ads.planner.completed', [
                'decision_id'      => $decisionId,
                'subtasks_created' => $created,
                'confidence'       => $plan['confidence'] ?? null,
            ]);

            return [
                'success'          => true,
                'plan'             => $plan,
                'subtasks_created' => $created,
                'error'            => null,
            ];
        } catch (\Throwable $e) {
            // D7.a — PiiRedactor wrap antes de logar mensagem livre
            $safeMessage = app(PiiRedactor::class)->redact($e->getMessage());
            Log::channel('single')->error('ads.planner.failed', [
                'decision_id' => $decisionId,
                'msg'         => $safeMessage,
            ]);
            return ['success' => false, 'plan' => null, 'subtasks_created' => 0, 'error' => $safeMessage];
        }
    }

    private function extractDescription(object $decision): string
    {
        $metadata = json_decode($decision->event_metadata ?? '{}', true) ?: [];
        return $metadata['title']
            ?? $metadata['subject']
            ?? "Tarefa {$decision->event_type} em {$decision->domain}";
    }

    private function parseJson(string $raw): ?array
    {
        if (preg_match('/```(?:json)?\s*(\{.+\})\s*```/s', $raw, $m)) {
            $raw = $m[1];
        }
        $data = json_decode($raw, true);
        return is_array($data) && isset($data['subtasks']) ? $data : null;
    }
}
