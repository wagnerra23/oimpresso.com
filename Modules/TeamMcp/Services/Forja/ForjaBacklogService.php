<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services\Forja;

use Modules\Jana\Entities\Mcp\McpTask;

/**
 * ForjaBacklogService — projeção do backlog do cockpit Forja (aba Backlog).
 *
 * Projeta `mcp_tasks` project=FORJA em TODOS os status (não só triagem) — o backlog
 * é a visão completa das issues da onda, agrupável client-side por Onda / Fase /
 * Papel / Prioridade / Módulo. Sem dado fantasma: lê só o que já existe no banco.
 *
 * Os campos Forja (tipo/fase/papel/onda) são projeção sobre `custom_fields` (json) —
 * NÃO é schema novo (Tier 0, sem tabela nova). Espelha o idioma de
 * ForjaController::serializeTicket (forja_tipo/forja_papel/forja_onda + forja_fase).
 *
 * Multi-tenant Tier 0 (ADR 0070 + ADR 0093): mcp_tasks é REPO-WIDE cross-tenant
 * POR DESIGN — sem business_id / BusinessScope (governança da plataforma).
 */
class ForjaBacklogService
{
    /**
     * Constrói a lista do backlog (project=FORJA), TODOS os status, mais recentes
     * primeiro, limitada a 200 issues. $projectId null (project FORJA ainda não
     * semeado) → lista vazia (sem dado fantasma; o front mostra empty-state).
     *
     * @return list<array{
     *     display_id: string,
     *     title: string,
     *     tipo: ?string,
     *     fase: ?string,
     *     papel: ?string,
     *     onda: ?string,
     *     modulo: ?string,
     *     prioridade: string,
     *     status: string
     * }>
     */
    public function build(?int $projectId): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design

        if (! $projectId) {
            return [];
        }

        return McpTask::where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (McpTask $t): array => $this->serialize($t))
            ->all();
    }

    /**
     * Serializa 1 issue do backlog. tipo/fase/papel/onda são projeção sobre
     * custom_fields (forja_tipo/forja_fase/forja_papel/forja_onda). prioridade
     * mantém fallback p2 pro badge não quebrar (igual Board/Backlog/Triage).
     *
     * @return array{
     *     display_id: string,
     *     title: string,
     *     tipo: ?string,
     *     fase: ?string,
     *     papel: ?string,
     *     onda: ?string,
     *     modulo: ?string,
     *     prioridade: string,
     *     status: string
     * }
     */
    private function serialize(McpTask $t): array
    {
        $cf = is_array($t->custom_fields) ? $t->custom_fields : [];

        return [
            'display_id' => $t->getDisplayIdAttribute(),
            'title'      => (string) $t->title,
            'tipo'       => isset($cf['forja_tipo']) ? (string) $cf['forja_tipo'] : null,
            'fase'       => isset($cf['forja_fase']) ? (string) $cf['forja_fase'] : null,
            'papel'      => isset($cf['forja_papel']) ? (string) $cf['forja_papel'] : null,
            'onda'       => isset($cf['forja_onda']) ? (string) $cf['forja_onda'] : null,
            'modulo'     => $t->module,
            // priority é enum nullable no banco (default p2); fallback p2 pro badge.
            'prioridade' => $t->priority ?? 'p2',
            'status'     => (string) $t->status,
        ];
    }
}
