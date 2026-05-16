<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ProjectService — D4 SoC brutal (Wave 16 Governance).
 *
 * Encapsula lógica de domínio de mcp_projects/mcp_project_parts originalmente
 * inline em `Admin\ProjectsController`. Pattern alinhado a:
 *   - `Modules\Repair\Services\KanbanProductionService` (thin service de mapping)
 *   - `Modules\Crm\Services\CampaignService` (Service por Controller)
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]):
 * `businessId` é injetado no constructor (NÃO lido de session() — Service
 * pode rodar em fila/job onde session não existe). Toda query DB carrega
 * `where('business_id', $this->businessId)` explícito.
 *
 * Stateless (apenas $businessId imutável + readonly DB facade) — pode ser
 * resolvido por request via container singleton de instância nova.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §5 SoC brutal
 * @see Modules\ProjectMgmt\Http\Controllers\Admin\ProjectsController
 */
class ProjectService
{
    public function __construct(
        private readonly int $businessId,
    ) {
        if ($this->businessId <= 0) {
            throw new \InvalidArgumentException(
                'ProjectService exige business_id > 0 (multi-tenant Tier 0 — ADR 0093)'
            );
        }
    }

    /**
     * Lista projects scoped por business_id com KPIs de progress (parts done/total).
     *
     * D9 observabilidade (Wave 17): wrapped em OtelHelper::spanBiz pra trace
     * com business_id auto-injetado (Tier 0 audit) + count de projects.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function list(): Collection
    {
        return OtelHelper::spanBiz('project_mgmt.project.list', fn () => $this->doList(), [
            'business_id' => $this->businessId,
        ]);
    }

    private function doList(): Collection
    {
        return DB::table('mcp_projects')
            ->where('business_id', $this->businessId)
            ->orderByDesc('id')
            ->get()
            ->map(function ($p) {
                $partsCount = DB::table('mcp_project_parts')->where('project_id', $p->id)->count();
                $partsDone  = DB::table('mcp_project_parts')->where('project_id', $p->id)->where('status', 'done')->count();

                return [
                    'id'                  => $p->id,
                    'codigo'              => $p->codigo,
                    'nome'                => $p->nome,
                    'objetivo_macro'      => $p->objetivo_macro,
                    'status'              => $p->status,
                    'decision'            => $p->decision,
                    'viability_score'     => $p->viability_score !== null ? (int) $p->viability_score : null,
                    'custo_estimado_brl'  => $p->custo_estimado_brl !== null ? (float) $p->custo_estimado_brl : null,
                    'prazo_estimado_dias' => $p->prazo_estimado_dias !== null ? (int) $p->prazo_estimado_dias : null,
                    'parts_total'         => $partsCount,
                    'parts_done'          => $partsDone,
                    'progress_pct'        => $partsCount > 0 ? round(($partsDone / $partsCount) * 100, 1) : 0,
                    'created_at'          => $p->created_at,
                ];
            });
    }

    /**
     * Calcula KPIs agregados da lista de projects.
     *
     * D9 (Wave 17): span — KPI compute é puro (sem DB), trace mede só CPU.
     *
     * @param  Collection<int, array<string,mixed>>  $projects  Resultado de list()
     * @return array{total:int,active:int,draft:int,completed:int}
     */
    public function calculateKpis(Collection $projects): array
    {
        return OtelHelper::spanBiz('project_mgmt.project.calculate_kpis', fn () => [
            'total'     => $projects->count(),
            'active'    => $projects->where('status', 'active')->count(),
            'draft'     => $projects->where('status', 'draft')->count(),
            'completed' => $projects->where('status', 'completed')->count(),
        ], [
            'business_id'   => $this->businessId,
            'projects_count' => $projects->count(),
        ]);
    }

    /**
     * Busca project + parts + decisions linkadas (página de detalhe).
     *
     * D9 (Wave 17): span dedicado pra medir custo da query composta
     * (3 joins lógicos: project + parts + decisions).
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException quando ID
     *         não existe ou não pertence ao $businessId atual (cross-tenant defense)
     *
     * @return array{project: array<string,mixed>, parts: list<array<string,mixed>>, decisions: list<array<string,mixed>>}
     */
    public function findDetail(int $projectId): array
    {
        return OtelHelper::spanBiz('project_mgmt.project.find_detail', fn () => $this->doFindDetail($projectId), [
            'business_id' => $this->businessId,
            'project_id'  => $projectId,
        ]);
    }

    private function doFindDetail(int $projectId): array
    {
        $project = DB::table('mcp_projects')
            ->where('id', $projectId)
            ->where('business_id', $this->businessId)
            ->first();

        if (! $project) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "Project id={$projectId} não encontrado pra business_id={$this->businessId}"
            );
        }

        $parts = DB::table('mcp_project_parts')
            ->where('project_id', $projectId)
            ->orderBy('ordem')
            ->get()
            ->map(fn ($p) => [
                'id'                 => $p->id,
                'ordem'              => (int) $p->ordem,
                'codigo'             => $p->codigo,
                'nome'               => $p->nome,
                'objetivo'           => $p->objetivo,
                'dependencias'       => json_decode($p->dependencias ?? '[]', true),
                'arquivos_estimados' => json_decode($p->arquivos_estimados ?? '[]', true),
                'status'             => $p->status,
                'viability_score'    => $p->viability_score !== null ? (int) $p->viability_score : null,
                'risco'              => $p->risco !== null ? (int) $p->risco : null,
                'estimativa_horas'   => $p->estimativa_horas,
                'valor_estimado_brl' => $p->valor_estimado_brl !== null ? (float) $p->valor_estimado_brl : null,
            ])
            ->all();

        $decisions = DB::table('mcp_dual_brain_decisions')
            ->where('project_id', $projectId)
            ->orderBy('id')
            ->get(['id', 'event_type', 'domain', 'destination', 'outcome', 'review_score'])
            ->all();

        return [
            'project' => [
                'id'                  => $project->id,
                'codigo'              => $project->codigo,
                'nome'                => $project->nome,
                'objetivo_macro'      => $project->objetivo_macro,
                'metricas_sucesso'    => json_decode($project->metricas_sucesso ?? '[]', true),
                'constraints'         => json_decode($project->constraints ?? '{}', true),
                'status'              => $project->status,
                'decision'            => $project->decision,
                'viability_score'     => $project->viability_score !== null ? (int) $project->viability_score : null,
                'viability_factors'   => json_decode($project->viability_factors ?? '{}', true),
                'custo_estimado_brl'  => $project->custo_estimado_brl !== null ? (float) $project->custo_estimado_brl : null,
                'valor_estimado_brl'  => $project->valor_estimado_brl !== null ? (float) $project->valor_estimado_brl : null,
                'prazo_estimado_dias' => $project->prazo_estimado_dias,
                'owner'               => $project->owner,
                'created_at'          => $project->created_at,
            ],
            'parts'     => $parts,
            'decisions' => $decisions,
        ];
    }

    /**
     * Cria project novo. Auto-gera código `PROJ-YYYYMM-NNN` se não fornecido.
     *
     * D9 (Wave 17): span dedicado pra trace de mutation (auditável + lat).
     *
     * @param  array{nome:string, objetivo_macro:string, codigo?:string, owner?:string}  $data
     * @return int  ID do project criado
     */
    public function create(array $data): int
    {
        return OtelHelper::spanBiz('project_mgmt.project.create', fn () => $this->doCreate($data), [
            'business_id' => $this->businessId,
        ]);
    }

    private function doCreate(array $data): int
    {
        if (! isset($data['nome']) || ! isset($data['objetivo_macro'])) {
            throw new \InvalidArgumentException('nome + objetivo_macro são obrigatórios');
        }

        $codigo = $data['codigo'] ?? $this->generateCodigo();

        return DB::table('mcp_projects')->insertGetId([
            'business_id'      => $this->businessId,
            'codigo'           => $codigo,
            'nome'             => $data['nome'],
            'objetivo_macro'   => $data['objetivo_macro'],
            'metricas_sucesso' => json_encode([]),
            'constraints'      => json_encode([]),
            'status'           => 'draft',
            'decision'         => 'pending',
            'owner'            => $data['owner'] ?? 'wagner',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);
    }

    /**
     * Atualiza atributos seletivos do project (defense-in-depth: re-scope business_id).
     *
     * D9 (Wave 17): span dedicado pra trace de mutation com project_id.
     *
     * @param  array<string,mixed>  $data
     * @return bool  true se houve update (1+ rows affected)
     */
    public function update(int $projectId, array $data): bool
    {
        return OtelHelper::spanBiz('project_mgmt.project.update', fn () => $this->doUpdate($projectId, $data), [
            'business_id' => $this->businessId,
            'project_id'  => $projectId,
        ]);
    }

    private function doUpdate(int $projectId, array $data): bool
    {
        $allowed = ['nome', 'objetivo_macro', 'status', 'decision', 'owner', 'viability_score', 'custo_estimado_brl', 'prazo_estimado_dias'];
        $payload = array_intersect_key($data, array_flip($allowed));

        if (empty($payload)) {
            return false;
        }

        $payload['updated_at'] = Carbon::now();

        $affected = DB::table('mcp_projects')
            ->where('id', $projectId)
            ->where('business_id', $this->businessId)
            ->update($payload);

        return $affected > 0;
    }

    /**
     * Marca project como `archived` (soft archive — preserva histórico/audit).
     * Não deleta nem altera parts vinculadas.
     */
    public function archive(int $projectId): bool
    {
        return $this->update($projectId, ['status' => 'archived']);
    }

    /**
     * Gera código sequencial PROJ-YYYYMM-NNN baseado em count total.
     * NÃO usa o businessId aqui (códigos globais por mês, contrato legado).
     */
    private function generateCodigo(): string
    {
        $seq = (int) (DB::table('mcp_projects')->count() + 1);
        return 'PROJ-' . date('Ym') . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
