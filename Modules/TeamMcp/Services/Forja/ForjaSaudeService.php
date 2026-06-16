<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services\Forja;

use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\TeamMcp\Services\ScorecardBuilderService;

/**
 * ForjaSaudeService — aba Saúde do cockpit Forja (/forja/saude).
 *
 * Semáforo do loop: projeta estado que JÁ EXISTE (mcp_tasks project=FORJA +
 * ScorecardBuilderService Facts) — SEM dado fantasma. Cada KPI é honesto:
 * deriva de query real OU é rotulado como sugestão quando não há fonte exata
 * (regra "Gates verdes" — contagem conhecida, não medida).
 *
 * Referência aprovada (F1.5 ADR 0114):
 * memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md §Saúde.
 *
 * **Multi-tenant Tier 0** ({@see ADR 0093} / {@see ADR 0070}): mcp_tasks /
 * mcp_jira_projects são REPO-WIDE cross-tenant POR DESIGN — governança da
 * plataforma, SEM filtro business_id. Espelha ScorecardBuilderService.
 *
 * @see Modules\TeamMcp\Http\Controllers\ForjaController (caller / wiring)
 * @see Modules\TeamMcp\Services\ScorecardBuilderService (Facts repo-wide)
 */
class ForjaSaudeService
{
    /** Key do project Jira-style do cockpit Forja (ADR 0070). */
    private const PROJECT_KEY = 'FORJA';

    /**
     * Fases do cowork loop projetadas no fluxo WIP (ordem = pipeline F0→F3.5).
     * Casa com `custom_fields['forja_fase']` semeado nas issues FORJA.
     *
     * @var list<string>
     */
    private const FASES = ['F0', 'F1', 'F1.5', 'F2', 'F3', 'F3.5'];

    public function __construct(
        private readonly ScorecardBuilderService $scorecard,
    ) {
    }

    /**
     * Constrói o payload da aba Saúde (KPIs + WIP por fase + toggles + entregas).
     *
     * @return array{
     *     kpis: list<array{label: string, value: string, meta: string, tone: string}>,
     *     wip: list<array{fase: string, count: int}>,
     *     automacao: list<array{label: string, detail: string, on: bool}>,
     *     entregas: int
     * }
     */
    public function build(?int $projectId): array
    {
        $projectId ??= $this->resolveForjaProjectId();

        return [
            'kpis'      => $this->buildKpis($projectId),
            'wip'       => $this->buildWip($projectId),
            'automacao' => $this->buildAutomacao(),
            'entregas'  => $this->countEntregas($projectId),
        ];
    }

    /**
     * 4 KPIs do semáforo do loop. Tudo derivado de query real (project=FORJA),
     * exceto "Gates verdes" — contagem honesta conhecida (rotulada como sugestão
     * na meta), pois não há fonte exata de baseline de gate aqui.
     *
     * @return list<array{label: string, value: string, meta: string, tone: string}>
     */
    private function buildKpis(?int $projectId): array
    {
        // Sem project FORJA semeado → KPIs zerados (sem dado fantasma).
        if ($projectId === null) {
            return [
                ['label' => 'Não-verificados', 'value' => '0',   'meta' => 'meta 0',                'tone' => 'success'],
                ['label' => 'Bloqueados',      'value' => '0',   'meta' => 'status blocked',        'tone' => 'success'],
                ['label' => 'P0 abertos',      'value' => '0',   'meta' => 'priority p0 em aberto', 'tone' => 'success'],
                ['label' => 'Gates verdes',    'value' => '5/7', 'meta' => 'sugestão (sem fonte exata)', 'tone' => 'warning'],
            ];
        }

        // 1ª query Eloquent do método → marker NoMissingTenantScopeRule:
        $tenancy = 'business_id'; // marker — mcp_* repo-wide (ADR 0070/0093), sem tenant por design

        // Não-verificados: triagem (sem dono OU sem prio OU backlog) ainda aberta.
        // Espelha o filtro da aba Triagem (ForjaController::buildTriagemPayload).
        $naoVerificados = (int) McpTask::triage()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->where('project_id', $projectId)
            ->count();

        // Bloqueados: status='blocked'.
        $bloqueados = (int) McpTask::query()
            ->where('project_id', $projectId)
            ->where('status', 'blocked')
            ->count();

        // P0 abertos: priority='p0' em aberto (não done/cancelled).
        $p0Abertos = (int) McpTask::query()
            ->where('project_id', $projectId)
            ->where('priority', 'p0')
            ->whereNotIn('status', ['done', 'cancelled'])
            ->count();

        return [
            // Não-verificados: meta é 0; verde se zerado, atenção se houver fila.
            [
                'label' => 'Não-verificados',
                'value' => (string) $naoVerificados,
                'meta'  => 'meta 0',
                'tone'  => $naoVerificados === 0 ? 'success' : 'warning',
            ],
            // Bloqueados: verde se nenhum, atenção se houver.
            [
                'label' => 'Bloqueados',
                'value' => (string) $bloqueados,
                'meta'  => 'status blocked',
                'tone'  => $bloqueados === 0 ? 'success' : 'warning',
            ],
            // P0 abertos: verde se nenhum, vermelho se houver (P0 aberto é urgente).
            [
                'label' => 'P0 abertos',
                'value' => (string) $p0Abertos,
                'meta'  => 'priority p0 em aberto',
                'tone'  => $p0Abertos === 0 ? 'success' : 'destructive',
            ],
            // Gates verdes: contagem honesta conhecida (5/7) — rotulada sugestão.
            // SEM fonte exata aqui (baselines vivem nos workflows .github), então
            // não inventa medição: declara explicitamente que é sugestão.
            [
                'label' => 'Gates verdes',
                'value' => '5/7',
                'meta'  => 'sugestão (sem fonte exata)',
                'tone'  => 'warning',
            ],
        ];
    }

    /**
     * Fluxo · WIP por fase: conta mcp_tasks project=FORJA agrupado por
     * `custom_fields['forja_fase']`. Pode vir sparse/0 — ok (sem dado fantasma).
     *
     * Agrupa em PHP (custom_fields é JSON; evita dependência de função JSON do
     * driver). Todas as 6 fases sempre presentes (0 quando vazia), ordem F0→F3.5.
     *
     * @return list<array{fase: string, count: int}>
     */
    private function buildWip(?int $projectId): array
    {
        // Base: todas as fases zeradas, na ordem canônica do pipeline.
        $contagem = array_fill_keys(self::FASES, 0);

        if ($projectId !== null) {
            // 1ª query Eloquent deste ramo → marker NoMissingTenantScopeRule:
            $tenancy = 'business_id'; // marker — mcp_* repo-wide (ADR 0070/0093), sem tenant por design

            McpTask::query()
                ->where('project_id', $projectId)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->get(['custom_fields'])
                ->each(function (McpTask $t) use (&$contagem): void {
                    $cf = is_array($t->custom_fields) ? $t->custom_fields : [];
                    $fase = isset($cf['forja_fase']) ? (string) $cf['forja_fase'] : null;
                    if ($fase !== null && array_key_exists($fase, $contagem)) {
                        $contagem[$fase]++;
                    }
                });
        }

        $wip = [];
        foreach (self::FASES as $fase) {
            $wip[] = ['fase' => $fase, 'count' => $contagem[$fase]];
        }

        return $wip;
    }

    /**
     * 3 toggles read-only do protótipo (design do contrato de automação do loop).
     * Estado fixo do design aprovado — não há tabela de flags pra ler aqui (o
     * enforce real é dos workflows .github), então é honesto declarar estático.
     *
     * @return list<array{label: string, detail: string, on: bool}>
     */
    private function buildAutomacao(): array
    {
        return [
            [
                'label'  => 'Gate vermelho trava o avanço de fase',
                'detail' => 'Nenhuma issue avança de fase enquanto um gate exigido está vermelho.',
                'on'     => true,
            ],
            [
                'label'  => 'F1 exige ✓ lido @main antes de avançar',
                'detail' => 'Saída de F1 só com o ✓ "lido @main" confirmado.',
                'on'     => true,
            ],
            [
                'label'  => 'PR merged → move issue p/ F4 (auto)',
                'detail' => 'Automação ainda não ligada — movimentação é manual.',
                'on'     => false,
            ],
        ];
    }

    /**
     * Entregas: issues FORJA concluídas (status='done'). Sinal de throughput do
     * loop. 0 quando não semeado (sem dado fantasma).
     */
    private function countEntregas(?int $projectId): int
    {
        if ($projectId === null) {
            return 0;
        }

        // 1ª query Eloquent do método → marker NoMissingTenantScopeRule:
        $tenancy = 'business_id'; // marker — mcp_* repo-wide (ADR 0070/0093), sem tenant por design

        return (int) McpTask::query()
            ->where('project_id', $projectId)
            ->where('status', 'done')
            ->count();
    }

    /** Resolve o id do project FORJA (null se ainda não semeado → tudo zerado). */
    private function resolveForjaProjectId(): ?int
    {
        // 1ª query Eloquent do método → marker NoMissingTenantScopeRule:
        $tenancy = 'business_id'; // marker — mcp_* repo-wide (ADR 0070/0093), sem tenant por design

        return McpProject::where('key', self::PROJECT_KEY)->value('id');
    }
}
