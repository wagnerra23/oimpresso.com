<?php

declare(strict_types=1);

namespace Modules\Repair\Services;

use Illuminate\Support\Collection;

/**
 * KanbanProductionService — thin service de mapping kanban (D4.a SoC brutal).
 *
 * Extrai lógica determinística de mapping `repair_status.id → column_id` do
 * ProducaoOficinaController. Stateless, sem dependência Eloquent — recebe
 * Collection<RepairStatus> e retorna estruturas puras pra Controller renderizar.
 *
 * Multi-tenant: Service NÃO toca DB; Controller passa Collection já scopada
 * por `business_id`. Service é puro mapping.
 *
 * Refs:
 *   - ProducaoOficinaController::mapStatusesToColumns (origem da lógica)
 *   - ProducaoOficinaController::findStatusForColumn (origem da lógica reversa)
 *   - ADR 0094 §5 SoC brutal
 */
class KanbanProductionService
{
    public const COLUMN_ORDER = ['recepcao', 'diagnostico', 'aguardando-pecas', 'em-execucao'];

    public const COLUMN_PRONTO = 'pronto';

    /**
     * Mapeia cada `repair_status.id` pro id da coluna kanban.
     *
     * Heurística:
     *   - `is_completed_status = true` → 'pronto'
     *   - resto, dividido em 4 buckets por posição em `sort_order`
     *
     * @param  Collection  $statuses  Collection<RepairStatus> já scopada por business_id.
     * @return array<int, string>     Map [status_id => column_id]
     */
    public function mapStatusesToColumns(Collection $statuses): array
    {
        $completed = $statuses->where('is_completed_status', true);
        $active = $statuses->where('is_completed_status', false)->values();

        $map = [];
        foreach ($completed as $s) {
            $map[$s->id] = self::COLUMN_PRONTO;
        }

        $count = $active->count();
        if ($count === 0) {
            return $map;
        }

        $bucketSize = max(1, (int) ceil($count / 4));

        foreach ($active as $i => $status) {
            $bucketIdx = min(3, intdiv($i, $bucketSize));
            $map[$status->id] = self::COLUMN_ORDER[$bucketIdx];
        }

        return $map;
    }

    /**
     * Mapping reverso: dado um column id, retorna o `repair_status.id` default
     * pra usar quando user dropa um card lá.
     *
     * @param  Collection  $statuses  Collection<RepairStatus> já scopada por business_id.
     * @param  string      $columnId  recepcao|diagnostico|aguardando-pecas|em-execucao|pronto
     */
    public function findStatusForColumn(Collection $statuses, string $columnId): ?int
    {
        if ($columnId === self::COLUMN_PRONTO) {
            return $statuses->where('is_completed_status', true)->first()?->id;
        }

        $active = $statuses->where('is_completed_status', false)->values();
        if ($active->isEmpty()) {
            return null;
        }

        $bucketIdx = array_search($columnId, self::COLUMN_ORDER, true);
        if ($bucketIdx === false) {
            return null;
        }

        $bucketSize = max(1, (int) ceil($active->count() / 4));
        $startIdx = $bucketIdx * $bucketSize;

        return $active->get($startIdx)?->id ?? $active->first()?->id;
    }

    /**
     * Valida se um column id é válido (whitelist).
     */
    public function isValidColumn(string $columnId): bool
    {
        return $columnId === self::COLUMN_PRONTO || in_array($columnId, self::COLUMN_ORDER, true);
    }
}
