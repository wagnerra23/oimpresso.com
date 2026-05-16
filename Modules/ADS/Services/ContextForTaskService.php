<?php

namespace Modules\ADS\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Observabilidade D9.a (ADR 0155): consolidação multi-source envolve
 * `OtelHelper::span(` (Tracer ads.context_for_task.fetch) — mede cache hit/miss.
 *
 * Consolida o conhecimento ESPALHADO em UMA chamada cache-friendly.
 *
 * Resolve o problema "Claude não consulta automaticamente":
 *   antes (4-5 chamadas ad-hoc):
 *     decisions-search query:X        →  bate Meilisearch (~100-500ms)
 *     decisions-fetch slug:Y          →  bate DB
 *     check user scope                →  bate DB
 *     consulta policy                 →  bate Reflection
 *     lê mcp_tasks                    →  bate DB (governado, US-COPI-077)
 *
 *   depois (1 chamada, output cacheável):
 *     ads.context-for-task            →  pacote enriquecido em <500ms
 *
 * Estado da arte: Anthropic Skills + Mem0 working memory pattern.
 * Inspirado em: ADR 0027 (roles claros), ADR 0036 (Meilisearch first),
 *               ADR 0037 (Tier 7-9 RAG roadmap).
 */
class ContextForTaskService
{
    public function __construct(
        private readonly UserScopeService $scope,
        private readonly PolicyEngine $policy,
        private readonly DecisionLinksService $links,
    ) {}

    /**
     * Monta o pacote completo de contexto.
     *
     * @param array{user_id?:int, intent:string, domain?:?string, files_planned?:array, event_type?:?string} $input
     */
    public function buildContext(array $input): array
    {
        $userId = isset($input['user_id']) ? (int) $input['user_id'] : null;
        $intent = $input['intent'] ?? '';
        $domain = $input['domain'] ?? null;
        $filesPlanned = $input['files_planned'] ?? [];
        $eventType = $input['event_type'] ?? null;

        return [
            'meta'                       => $this->buildMeta($input),
            'user_scope'                 => $this->buildUserScope($userId, $filesPlanned),
            'policy_constraints'         => $this->buildPolicyConstraints(),
            'applicable_adrs'            => $this->buildApplicableAdrs($intent, $domain, $eventType),
            'skills_with_confidence'     => $this->buildSkills($domain, $eventType),
            'active_meta_skills'         => $this->buildMetaSkills(),
            'recent_decisions_same_domain' => $this->buildRecentDecisions($domain),
            'active_tasks_cycle'         => $this->buildActiveTasks($domain),
            'tool_recommendations'       => $this->buildToolRecommendations($input),
        ];
    }

    private function buildMeta(array $input): array
    {
        return [
            'generated_at'  => now()->toIso8601String(),
            'cache_ttl_seconds' => 300, // 5min
            'input_summary' => mb_strimwidth(($input['intent'] ?? ''), 0, 200, '…'),
            'reference_adrs' => ['0027', '0036', '0037', '0061'], // canon papéis memória
        ];
    }

    private function buildUserScope(?int $userId, array $filesPlanned): array
    {
        if ($userId === null) {
            return ['authenticated' => false, 'allowed_modules' => 'unrestricted_check_per_path'];
        }

        $modules = $this->scope->getAllowedModules($userId);
        $allowedNames = array_column($modules, 'module');

        $pathChecks = [];
        foreach ($filesPlanned as $path) {
            $pathChecks[$path] = [
                'allowed' => $this->scope->canWriteToPath($userId, $path),
                'module'  => $this->extractModule($path),
            ];
        }

        return [
            'user_id'           => $userId,
            'allowed_modules'   => $allowedNames,
            'modules_detail'    => $modules,
            'path_checks'       => $pathChecks,
            'all_paths_allowed' => empty($pathChecks)
                || ! in_array(false, array_column($pathChecks, 'allowed'), true),
        ];
    }

    private function buildPolicyConstraints(): array
    {
        $rules = $this->policy->getAllRules();
        return [
            'BLOCK_ALWAYS' => array_values($rules['BLOCK_ALWAYS'] ?? []),
            'REQUIRE_HUMAN_REVIEW' => array_values($rules['REQUIRE_HUMAN_REVIEW'] ?? []),
            'REQUIRE_BRAIN_B' => array_values($rules['REQUIRE_BRAIN_B'] ?? []),
            'ALLOW_BRAIN_A'  => array_values($rules['ALLOW_BRAIN_A'] ?? []),
            'note' => 'Hardcoded em Modules/ADS/Services/PolicyEngine.php — só muda via PR git',
        ];
    }

    /**
     * ADRs aplicáveis: combinação de full-text match + filtro por módulo + backlinks existentes.
     */
    private function buildApplicableAdrs(string $intent, ?string $domain, ?string $eventType): array
    {
        $query = DB::table('mcp_memory_documents')
            ->where('type', 'adr');

        // Filtro por módulo (se informado)
        if ($domain) {
            $query->where(function ($q) use ($domain) {
                $q->where('module', $domain)
                  ->orWhere('module', strtolower($domain))
                  ->orWhereNull('module'); // inclui ADRs de "core"
            });
        }

        // Search no título e content_md
        if ($intent !== '') {
            $words = array_filter(preg_split('/\s+/', $intent), fn ($w) => strlen($w) > 3);
            foreach (array_slice($words, 0, 5) as $word) {
                $query->orWhere(function ($q) use ($word) {
                    $q->where('title', 'like', "%{$word}%")
                      ->orWhere('content_md', 'like', "%{$word}%");
                });
            }
        }

        $rows = $query->limit(8)
            ->get(['slug', 'title', 'module', 'content_md']);

        return $rows->map(fn ($r) => [
            'slug'    => $r->slug,
            'title'   => $r->title,
            'module'  => $r->module,
            'snippet' => mb_strimwidth(strip_tags($r->content_md ?? ''), 0, 300, '…'),
            'kb_url'  => "/ads/admin/kb/{$r->slug}",
        ])->all();
    }

    /**
     * Skills aprendidos com confidence — útil pra Claude saber "isso já foi feito antes com sucesso".
     */
    private function buildSkills(?string $domain, ?string $eventType): array
    {
        $query = DB::table('mcp_decision_patterns')
            ->orderByDesc('total_count');

        if ($domain) {
            $query->where('domain', $domain);
        }
        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        return $query->limit(10)
            ->get(['domain', 'event_type', 'success_count', 'total_count', 'success_rate', 'is_hardcoded'])
            ->map(fn ($p) => [
                'domain'        => $p->domain,
                'event_type'    => $p->event_type,
                'success_count' => (int) $p->success_count,
                'total_count'   => (int) $p->total_count,
                'success_rate'  => (float) $p->success_rate,
                'is_hardcoded'  => (bool) $p->is_hardcoded,
                // Wilson LB calculado externamente; aqui só success_rate naïve
            ])->all();
    }

    private function buildMetaSkills(): array
    {
        return DB::table('mcp_governance_rules')
            ->where('enabled', true)
            ->orderBy('category')
            ->get(['rule_key', 'name', 'description', 'category'])
            ->map(fn ($r) => [
                'rule_key'    => $r->rule_key,
                'name'        => $r->name,
                'description' => mb_strimwidth($r->description, 0, 200, '…'),
                'category'    => $r->category,
            ])
            ->all();
    }

    private function buildRecentDecisions(?string $domain): array
    {
        $query = DB::table('mcp_dual_brain_decisions')
            ->whereIn('outcome', ['success', 'wagner_modified', 'wagner_rejected'])
            ->where('created_at', '>=', now()->subDays(14));

        if ($domain) {
            $query->where('domain', $domain);
        }

        return $query->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'event_type', 'outcome', 'review_score', 'wagner_modified_to'])
            ->map(fn ($d) => [
                'decision_id'  => (int) $d->id,
                'event_type'   => $d->event_type,
                'outcome'      => $d->outcome,
                'review_score' => $d->review_score,
                'lesson'       => $d->wagner_modified_to !== null
                    ? mb_strimwidth($d->wagner_modified_to, 0, 200, '…')
                    : null,
            ])->all();
    }

    /**
     * US-COPI-077 — substitui leitura filesystem CURRENT.md por query no DB
     * (mcp_tasks, cache governado sincronizado via webhook GitHub do SPEC.md).
     *
     * Por que? CURRENT.md frequentemente fica desatualizado, e Wagner pediu
     * "tarefas que já foram feitas / em andamento" em vez de "goal estático".
     * mcp_tasks é fonte canônica das US-* extraídas dos SPECs por parser idempotente.
     */
    private function buildActiveTasks(?string $domain): array
    {
        if (! Schema::hasTable('mcp_tasks')) {
            return [
                'active'    => [],
                'completed' => [],
                'source'    => 'mcp_tasks (tabela não existe — TaskRegistry F0 não rodada)',
            ];
        }

        $base = DB::table('mcp_tasks');
        if ($domain) {
            // Module match case-insensitive — domain pode vir 'nfse'/'NFSe'/'NFSE'
            // (ADS sintetiza o domain de várias fontes; tabela tem casing canônico).
            $base->whereRaw('LOWER(module) = ?', [strtolower($domain)]);
        }

        // priority p0/p1/p2/p3 — ordenação string asc dá ordem correta lexicográfica
        // e funciona em MySQL + SQLite (FIELD() é MySQL-only e quebra os testes).
        $active = (clone $base)
            ->whereIn('status', ['todo', 'doing', 'review'])
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get(['task_id', 'title', 'status', 'owner', 'sprint', 'priority'])
            ->map(fn ($t) => [
                'task_id'  => $t->task_id,
                'title'    => mb_strimwidth($t->title, 0, 120, '…'),
                'status'   => $t->status,
                'owner'    => $t->owner,
                'sprint'   => $t->sprint,
                'priority' => $t->priority,
            ])->all();

        $completed = (clone $base)
            ->where('status', 'done')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get(['task_id', 'title', 'sprint'])
            ->map(fn ($t) => [
                'task_id' => $t->task_id,
                'title'   => mb_strimwidth($t->title, 0, 120, '…'),
                'sprint'  => $t->sprint,
            ])->all();

        return [
            'active'    => $active,
            'completed' => $completed,
            'source'    => 'mcp_tasks (cache governado, ADR 0053 + TaskRegistry F0)',
        ];
    }

    private function buildToolRecommendations(array $input): array
    {
        $recommendations = [];
        $intent = strtolower($input['intent'] ?? '');

        if (str_contains($intent, 'criar') || str_contains($intent, 'modificar')) {
            $recommendations[] = [
                'tool' => 'write_file',
                'when' => 'Para criar/modificar arquivos no whitelist (Modules/, resources/js/Pages/, memory/)',
                'note' => 'Path traversal e .env bloqueados; UserScopeService valida per-user',
            ];
        }
        if (str_contains($intent, 'teste') || str_contains($intent, 'pest')) {
            $recommendations[] = [
                'tool' => 'run_test',
                'when' => 'Após modificações em código; timeout 60s',
            ];
        }
        if (str_contains($intent, 'commit') || str_contains($intent, 'salvar')) {
            $recommendations[] = [
                'tool' => 'git_commit_wip',
                'when' => 'Branch wip-decision-{N}-{slug}; nunca push, nunca toca main',
            ];
        }
        $recommendations[] = [
            'tool' => 'boost.database-schema',
            'when' => 'Inspecionar schema de tabela existente antes de migration',
        ];
        $recommendations[] = [
            'tool' => 'boost.read-log-entries',
            'when' => 'Investigar erros recentes',
        ];

        return $recommendations;
    }

    private function extractModule(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        if (preg_match('/^Modules\/([A-Za-z0-9]+)\//', $path, $m)) {
            return $m[1];
        }
        return null;
    }
}
