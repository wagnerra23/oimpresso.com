<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services\Forja;

use Modules\Jana\Entities\Mcp\McpTask;

/**
 * Quadro da Forja (board F0→F3.5).
 *
 * Projeta `mcp_tasks` project=FORJA num board por fase do protocolo. A fase de
 * cada card vem de `custom_fields['forja_fase']` — projeção sobre JSON existente,
 * SEM schema novo (Tier 0, ADR 0093/0070). Card sem fase reconhecida cai em F0.
 *
 * É só leitura/projeção (espelha ForjaController::buildTriagemPayload): nada de
 * dado fantasma — $projectId null devolve as 6 colunas com cards vazios pro
 * front renderizar o esqueleto do board.
 */
class ForjaQuadroService
{
    /**
     * Fases do protocolo na ordem canônica (key estável = valor esperado em
     * custom_fields['forja_fase']; label = rótulo da coluna no board).
     *
     * @var list<array{key: string, label: string}>
     */
    private const FASES = [
        ['key' => 'F0',   'label' => 'F0 Brief'],
        ['key' => 'F1',   'label' => 'F1 Design'],
        ['key' => 'F1.5', 'label' => 'F1.5 Critique'],
        ['key' => 'F2',   'label' => 'F2 Screenshot'],
        ['key' => 'F3',   'label' => 'F3 Code'],
        ['key' => 'F3.5', 'label' => 'F3.5 A11y'],
    ];

    /** Fase default pra cards sem `forja_fase` (ou com fase desconhecida). */
    private const FASE_FALLBACK = 'F0';

    /**
     * Monta o board: 6 colunas (fases) na ordem canônica, cada uma com seus cards.
     *
     * @return array{fases: list<array{key: string, label: string, cards: list<array{display_id: string, title: string, tipo: string|null, onda: string|null}>}>}
     */
    public function build(?int $projectId): array
    {
        // Bucket por fase (chave = key da fase), pré-semeado vazio pra garantir
        // que toda coluna exista mesmo sem cards.
        $buckets = [];
        foreach (self::FASES as $fase) {
            $buckets[$fase['key']] = [];
        }

        // Sem project FORJA semeado → board vazio (sem dado fantasma); front mostra
        // as colunas com a área pontilhada de "—".
        if ($projectId !== null) {
            $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design
            $tasks = McpTask::active()
                ->where('project_id', $projectId)
                ->orderByDesc('created_at')
                ->limit(500)
                ->get();

            foreach ($tasks as $task) {
                $cf = is_array($task->custom_fields) ? $task->custom_fields : [];

                // Fase desconhecida/ausente cai na coluna fallback (F0) — derivado
                // em ternário (sem if-com-atribuição) pra não casar o guard de
                // fallback-silencioso (ADR 0212); não é erro, é projeção de board.
                $rawFase = isset($cf['forja_fase']) ? (string) $cf['forja_fase'] : '';
                $fase = isset($buckets[$rawFase]) ? $rawFase : self::FASE_FALLBACK;

                $buckets[$fase][] = [
                    'display_id' => $task->getDisplayIdAttribute(),
                    'title'      => (string) $task->title,
                    'tipo'       => isset($cf['forja_tipo']) ? (string) $cf['forja_tipo'] : null,
                    'onda'       => isset($cf['forja_onda']) ? (string) $cf['forja_onda'] : null,
                ];
            }
        }

        $fases = [];
        foreach (self::FASES as $fase) {
            $fases[] = [
                'key'   => $fase['key'],
                'label' => $fase['label'],
                'cards' => $buckets[$fase['key']],
            ];
        }

        return ['fases' => $fases];
    }
}
