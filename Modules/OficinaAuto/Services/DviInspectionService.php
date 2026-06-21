<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;

/**
 * DviInspectionService — operações de domínio sobre itens DVI (Vistoria Digital).
 *
 * Wave 3 OficinaAuto (US-OFICINA-035). Espelha pattern ServiceOrderItemService:
 * Service NÃO confia em session (defesa em profundidade) — business_id é parâmetro
 * obrigatório explícito, conforme princípio multi-tenant Tier 0 ([ADR 0093]).
 *
 * Responsabilidades:
 * - addItem: cria item validando business_id + categoria + severity + OS cross-tenant
 * - breakdownPorSeverity: agrega contadores + total recomendado pra UI card DVI
 * - totalRecomendado: soma valor_recomendado WHERE severity IN (atencao, critico)
 * - listarOrdenado: lista ordenada (critico → atencao → ok, depois sort_order)
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-035
 */
class DviInspectionService
{
    /**
     * Adiciona item DVI a uma OS.
     *
     * @param  int  $businessId  obrigatório (defesa em profundidade — Service NÃO confia em session)
     * @param  int  $serviceOrderId  ServiceOrder.id
     * @param  array{categoria:string, descricao:string, severity:string, recomendacao?:string|null, valor_recomendado?:float|string|null, metadata?:array|null, photo_url?:string|null, sort_order?:int}  $data
     *
     * @throws InvalidArgumentException  se categoria/severity inválida OU OS não pertence ao biz
     */
    public function addItem(int $businessId, int $serviceOrderId, array $data): OaInspectionItem
    {
        if ($businessId <= 0) {
            throw new InvalidArgumentException('business_id obrigatório (Tier 0 ADR 0093)');
        }

        $categoria = $data['categoria'] ?? null;
        if (! in_array($categoria, OaInspectionItem::CATEGORIAS, true)) {
            throw new InvalidArgumentException(
                "categoria inválida: '{$categoria}'. Permitidas: " . implode(', ', OaInspectionItem::CATEGORIAS)
            );
        }

        $severity = $data['severity'] ?? null;
        if (! in_array($severity, OaInspectionItem::SEVERITIES_VALIDAS, true)) {
            throw new InvalidArgumentException(
                "severity inválida: '{$severity}'. Permitidas: " . implode(', ', OaInspectionItem::SEVERITIES_VALIDAS)
            );
        }

        $descricao = trim((string) ($data['descricao'] ?? ''));
        if ($descricao === '') {
            throw new InvalidArgumentException('descricao obrigatória');
        }

        // Valida OS pertence ao business (defesa contra cross-tenant)
        $osPertenceAoBiz = ServiceOrder::withoutGlobalScopes() // SUPERADMIN: check cross-tenant
            ->where('id', $serviceOrderId)
            ->where('business_id', $businessId)
            ->exists();

        if (! $osPertenceAoBiz) {
            throw new InvalidArgumentException(
                "ServiceOrder #{$serviceOrderId} não existe OU não pertence ao business {$businessId}"
            );
        }

        // SUPERADMIN: Service não confia em session (defesa em profundidade) — cria com
        // business_id explícito já validado contra a OS dona (Tier 0, ADR 0093).
        return OaInspectionItem::withoutGlobalScopes()->create([
            'business_id'        => $businessId,
            'service_order_id'   => $serviceOrderId,
            'categoria'          => $categoria,
            'descricao'          => $descricao,
            'severity'           => $severity,
            'recomendacao'       => $data['recomendacao'] ?? null,
            'valor_recomendado'  => isset($data['valor_recomendado']) ? (float) $data['valor_recomendado'] : null,
            'metadata'           => $data['metadata'] ?? null,
            'photo_url'          => $data['photo_url'] ?? null,
            'sort_order'         => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    /**
     * Breakdown agregado pra UI card DVI ("8 ok · 2 atenção · 1 crítico" + total).
     *
     * @return array{ok:int, atencao:int, critico:int, total_recomendado:float}
     */
    public function breakdownPorSeverity(int $serviceOrderId): array
    {
        $rows = OaInspectionItem::withoutGlobalScopes() // SUPERADMIN: agregado controlado por OS
            ->where('service_order_id', $serviceOrderId)
            ->whereNull('deleted_at')
            ->select('severity', DB::raw('COUNT(*) AS total'), DB::raw('SUM(valor_recomendado) AS soma'))
            ->groupBy('severity')
            ->get()
            ->keyBy('severity');

        $ok = (int) ($rows[OaInspectionItem::SEVERITY_OK]->total ?? 0);
        $atencao = (int) ($rows[OaInspectionItem::SEVERITY_ATENCAO]->total ?? 0);
        $critico = (int) ($rows[OaInspectionItem::SEVERITY_CRITICO]->total ?? 0);

        $totalRecomendado = round(
            (float) ($rows[OaInspectionItem::SEVERITY_ATENCAO]->soma ?? 0)
            + (float) ($rows[OaInspectionItem::SEVERITY_CRITICO]->soma ?? 0),
            2
        );

        return [
            'ok'                => $ok,
            'atencao'           => $atencao,
            'critico'           => $critico,
            'total_recomendado' => $totalRecomendado,
        ];
    }

    /**
     * Soma valor_recomendado de itens severity IN (atencao, critico).
     * Cross-business safe (filtro explícito por OS, OS já carrega business_id).
     */
    public function totalRecomendado(int $serviceOrderId): float
    {
        // SUPERADMIN: agregado controlado por service_order_id (OS já é business-scoped
        // pelo caller) — Service não depende de session (Tier 0, ADR 0093).
        $total = OaInspectionItem::withoutGlobalScopes()
            ->where('service_order_id', $serviceOrderId)
            ->whereIn('severity', OaInspectionItem::SEVERIDADES_RECOMENDAVEIS)
            ->whereNull('deleted_at')
            ->sum('valor_recomendado');

        return round((float) $total, 2);
    }

    /**
     * Lista itens ordenados — critico → atencao → ok, depois sort_order ASC.
     *
     * Pra UI card DVI mostrar criticos no topo (lê primeiro o que importa).
     */
    public function listarOrdenado(int $serviceOrderId): Collection
    {
        // SUPERADMIN: filtro estrito por service_order_id (OS já é business-scoped pelo
        // caller) — Service não depende de session (Tier 0, ADR 0093).
        return OaInspectionItem::withoutGlobalScopes()
            ->where('service_order_id', $serviceOrderId)
            ->whereNull('deleted_at')
            // MySQL ORDER BY FIELD: ordem custom (critico=1, atencao=2, ok=3)
            ->orderByRaw("FIELD(severity, 'critico', 'atencao', 'ok')")
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
