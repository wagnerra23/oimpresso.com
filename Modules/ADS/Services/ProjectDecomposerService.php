<?php

namespace Modules\ADS\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\ADS\Ai\Agents\ProjectDecomposerAgent;

/**
 * Decompõe Project em Parts via ProjectDecomposerAgent (Sonnet).
 *
 * Diferente do PlannerService (que decompõe DECISION em subtarefas atômicas):
 *   - PlannerService: decision complexa → N child decisions
 *   - ProjectDecomposerService: project (objetivo macro) → N parts estratégicas
 */
class ProjectDecomposerService
{
    public function __construct(
        private readonly DecisionLinksService $links,
    ) {}

    public function decompose(int $projectId): array
    {
        $project = DB::table('mcp_projects')->where('id', $projectId)->first();
        if (! $project) {
            return ['success' => false, 'error' => 'project_not_found', 'parts_created' => 0];
        }

        // Não re-decompõe se já tem parts
        $existingParts = DB::table('mcp_project_parts')->where('project_id', $projectId)->count();
        if ($existingParts > 0) {
            return ['success' => false, 'error' => 'already_decomposed', 'parts_created' => 0];
        }

        $constraints = json_decode($project->constraints ?? '{}', true) ?: [];

        // Regras canônicas que sempre se aplicam (mapeamento direto das fontes)
        $regrasCanonicas = [
            'CLAUDE.md §1 stack: Laravel 13.6 + PHP 8.4 + Inertia v3 + React 19 + Tailwind 4',
            'ADR 0011: imitar Modules/Jana, Modules/Repair, Modules/NFSe (template canônico)',
            'ADR 0024: BaseModuleInstallController extends padrão para Install/Uninstall/Update',
            'DESIGN.md UI-0006: PageHeader + KpiGrid + StatusBadge + EmptyState',
            'memory/04-conventions.md: PT-BR em copy/labels, business_id em todas queries',
            'reference_criar_modulo_laravel: 8 peças obrigatórias incluindo DataController + topnav',
            'Multi-tenant: business_id global scope obrigatório',
            'Pest v4 obrigatório para regras de negócio',
        ];

        try {
            $agent = new ProjectDecomposerAgent(
                nomeProjeto:        $project->nome,
                objetivoMacro:      $project->objetivo_macro,
                constraints:        $constraints,
                regrasAplicaveis:   $regrasCanonicas,
            );

            $response = $agent->prompt($agent->montarPrompt());
            $plan = $this->parseJson(trim((string) $response));

            if (! $plan) {
                return ['success' => false, 'error' => 'invalid_json', 'parts_created' => 0];
            }

            if (! empty($plan['rejected'])) {
                Log::channel('single')->warning('ads.project_decomposer.rejected', [
                    'project_id' => $projectId,
                    'reason'     => $plan['rejection_reason'] ?? '',
                ]);
                return ['success' => false, 'error' => 'rejected', 'rejection_reason' => $plan['rejection_reason'], 'parts_created' => 0];
            }

            $parts = $plan['parts'] ?? [];
            $created = 0;
            foreach ($parts as $p) {
                if (empty($p['nome']) || empty($p['codigo'])) continue;

                DB::table('mcp_project_parts')->insert([
                    'project_id'         => $projectId,
                    'ordem'              => (int) ($p['ordem'] ?? ($created + 1)),
                    'codigo'             => $p['codigo'],
                    'nome'               => $p['nome'],
                    'objetivo'           => $p['objetivo'] ?? '',
                    'dependencias'       => json_encode($p['dependencias'] ?? []),
                    'arquivos_estimados' => json_encode($p['arquivos_estimados'] ?? []),
                    'viability_score'    => (int) ($p['viability_score'] ?? 50),
                    'risco'              => (int) ($p['risco'] ?? 50),
                    'estimativa_horas'   => (int) ($p['estimativa_horas'] ?? 4),
                    'valor_estimado_brl' => (float) ($p['valor_estimado_brl'] ?? 0),
                    'status'             => 'pending',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
                $created++;
            }

            // Atualiza project com viability + custo + prazo agregados
            DB::table('mcp_projects')->where('id', $projectId)->update([
                'metricas_sucesso'    => json_encode($plan['metricas_sucesso'] ?? []),
                'viability_score'     => (int) ($plan['viability_overall'] ?? 50),
                'custo_estimado_brl'  => (float) ($plan['custo_total_brl'] ?? 0),
                'prazo_estimado_dias' => (int) ($plan['prazo_total_dias'] ?? 0),
                'updated_at'          => now(),
            ]);

            // C — Vincula ADRs consultadas ao project (auditoria reversa)
            $regrasConsultadas = $plan['regras_consultadas'] ?? [];
            $linksGravados = 0;
            if (! empty($regrasConsultadas)) {
                $linksGravados = $this->links->linkFromTexts(
                    DecisionLinksService::TARGET_PROJECT,
                    $projectId,
                    $regrasConsultadas,
                    'referenced',
                );
            }

            Log::channel('single')->info('ads.project_decomposer.completed', [
                'project_id'    => $projectId,
                'parts_created' => $created,
                'viability'     => $plan['viability_overall'] ?? null,
                'adr_links'     => $linksGravados,
            ]);

            return [
                'success'           => true,
                'parts_created'     => $created,
                'viability_overall' => $plan['viability_overall'] ?? null,
                'summary'           => $plan['decomposition_summary'] ?? '',
                'regras_consultadas' => $plan['regras_consultadas'] ?? [],
            ];
        } catch (\Throwable $e) {
            // D7.a — PiiRedactor wrap (exceção pode trazer payload livre do usuário)
            $safeMessage = app(PiiRedactor::class)->redact($e->getMessage());
            Log::channel('single')->error('ads.project_decomposer.failed', [
                'project_id' => $projectId,
                'msg'        => $safeMessage,
            ]);
            return ['success' => false, 'error' => $safeMessage, 'parts_created' => 0];
        }
    }

    private function parseJson(string $raw): ?array
    {
        if (preg_match('/```(?:json)?\s*(\{.+\})\s*```/s', $raw, $m)) {
            $raw = $m[1];
        }
        $data = json_decode($raw, true);
        return is_array($data) && isset($data['parts']) ? $data : null;
    }
}
