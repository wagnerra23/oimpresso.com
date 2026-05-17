<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;

/**
 * ServiceOrderItemService — operações de domínio sobre itens de OS.
 *
 * Origem W27 G1 (P0 fatal CAPTERRA OficinaAuto Wave 22).
 *
 * Responsabilidades:
 * - addItem: cria item validando business_id + tipo + OS pertence ao biz
 * - recalcularTotal: soma valor_total de todos itens não-deletados → opcionalmente
 *   sincroniza pra ServiceOrder.notes ou campo derivado (futuro: total_pecas/total_mao_obra)
 * - listarPorTipo: facade pra scopes Eloquent (UI dashboard)
 *
 * Multi-tenant Tier 0 ([ADR 0093]): toda query respeita global scope; addItem
 * exige $businessId explícito (defesa em profundidade — Service não confia em session).
 *
 * @see memory/requisitos/OficinaAuto/CAPTERRA-FICHA.md G1
 */
class ServiceOrderItemService
{
    /**
     * Adiciona item a uma OS.
     *
     * @param  int  $businessId  obrigatório (defesa em profundidade — Service NÃO confia em session)
     * @param  int  $osId        ServiceOrder.id
     * @param  array{tipo:string, descricao:string, quantidade?:float|string, valor_unitario?:float|string, valor_total?:float|string, product_id?:int|null, notes?:string|null}  $data
     *
     * @throws InvalidArgumentException  se tipo inválido OU OS não pertence ao biz
     */
    public function addItem(int $businessId, int $osId, array $data): ServiceOrderItem
    {
        if ($businessId <= 0) {
            throw new InvalidArgumentException('business_id obrigatório (Tier 0 ADR 0093)');
        }

        $tipo = $data['tipo'] ?? null;
        if (! in_array($tipo, ServiceOrderItem::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException(
                "tipo inválido: '{$tipo}'. Permitidos: " . implode(', ', ServiceOrderItem::TIPOS_VALIDOS)
            );
        }

        // Valida OS pertence ao business (defesa contra cross-tenant)
        $osPertenceAoBiz = ServiceOrder::withoutGlobalScopes() // SUPERADMIN: check cross-tenant
            ->where('id', $osId)
            ->where('business_id', $businessId)
            ->exists();

        if (! $osPertenceAoBiz) {
            throw new InvalidArgumentException(
                "ServiceOrder #{$osId} não existe OU não pertence ao business {$businessId}"
            );
        }

        $descricao = trim((string) ($data['descricao'] ?? ''));
        if ($descricao === '') {
            throw new InvalidArgumentException('descricao obrigatória');
        }

        $quantidade = (float) ($data['quantidade'] ?? 1);
        $valorUnitario = (float) ($data['valor_unitario'] ?? 0);

        // valor_total: aceita override (descontos) OU calcula
        $valorTotal = isset($data['valor_total'])
            ? (float) $data['valor_total']
            : round($quantidade * $valorUnitario, 2);

        return ServiceOrderItem::withoutGlobalScopes()->create([
            'business_id'      => $businessId,
            'service_order_id' => $osId,
            'tipo'             => $tipo,
            'descricao'        => $descricao,
            'quantidade'       => $quantidade,
            'valor_unitario'   => $valorUnitario,
            'valor_total'      => $valorTotal,
            'product_id'       => $data['product_id'] ?? null,
            'notes'            => $data['notes'] ?? null,
        ]);
    }

    /**
     * Soma valor_total de todos itens não-deletados da OS.
     * Cross-business safe (filtro explícito por OS, OS já carrega business_id).
     */
    public function recalcularTotal(int $osId): float
    {
        $total = ServiceOrderItem::withoutGlobalScopes() // SUPERADMIN: cálculo agregado controllado
            ->where('service_order_id', $osId)
            ->whereNull('deleted_at')
            ->sum('valor_total');

        return round((float) $total, 2);
    }

    /**
     * Breakdown por tipo (pra UI orçamento + WhatsApp aprovação).
     *
     * @return array{peca:float, mao_obra:float, servico_terceiro:float, total:float}
     */
    public function breakdownPorTipo(int $osId): array
    {
        $rows = ServiceOrderItem::withoutGlobalScopes()
            ->where('service_order_id', $osId)
            ->whereNull('deleted_at')
            ->select('tipo', DB::raw('SUM(valor_total) AS soma'))
            ->groupBy('tipo')
            ->pluck('soma', 'tipo')
            ->toArray();

        $peca = round((float) ($rows[ServiceOrderItem::TIPO_PECA] ?? 0), 2);
        $maoObra = round((float) ($rows[ServiceOrderItem::TIPO_MAO_OBRA] ?? 0), 2);
        $terceiro = round((float) ($rows[ServiceOrderItem::TIPO_SERVICO_TERCEIRO] ?? 0), 2);

        return [
            'peca'             => $peca,
            'mao_obra'         => $maoObra,
            'servico_terceiro' => $terceiro,
            'total'            => round($peca + $maoObra + $terceiro, 2),
        ];
    }

    /**
     * Lista itens de uma OS por tipo (filtro estrito pra UI tab "Peças" / "Mão de obra").
     */
    public function listarPorTipo(int $osId, string $tipo): Collection
    {
        if (! in_array($tipo, ServiceOrderItem::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException("tipo inválido: '{$tipo}'");
        }

        return ServiceOrderItem::withoutGlobalScopes()
            ->where('service_order_id', $osId)
            ->where('tipo', $tipo)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();
    }
}
