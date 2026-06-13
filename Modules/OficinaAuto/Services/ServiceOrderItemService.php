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

        // Tier 0 (ADR 0093): se vier product_id, o produto TEM que pertencer ao mesmo
        // business — senão um item de OS poderia referenciar (e baixar estoque de) catálogo
        // de outro tenant. Rejeita cross-tenant antes de persistir.
        $productId = isset($data['product_id']) ? (int) $data['product_id'] : null;
        if ($productId !== null && $productId > 0) {
            $produtoNoBiz = \App\Product::withoutGlobalScopes() // SUPERADMIN: check cross-tenant
                ->where('id', $productId)
                ->where('business_id', $businessId)
                ->exists();

            if (! $produtoNoBiz) {
                throw new InvalidArgumentException(
                    "product_id #{$productId} não existe OU não pertence ao business {$businessId} (Tier 0 ADR 0093)"
                );
            }
        } else {
            $productId = null;
        }

        $quantidade = (float) ($data['quantidade'] ?? 1);
        $valorUnitario = (float) ($data['valor_unitario'] ?? 0);

        // valor_total: aceita override (descontos) OU calcula
        $valorTotal = isset($data['valor_total'])
            ? (float) $data['valor_total']
            : round($quantidade * $valorUnitario, 2);

        // SUPERADMIN: Service não confia em session (defesa em profundidade) — cria com
        // business_id explícito já validado contra a OS dona (Tier 0, ADR 0093).
        return ServiceOrderItem::withoutGlobalScopes()->create([
            'business_id'      => $businessId,
            'service_order_id' => $osId,
            'tipo'             => $tipo,
            'descricao'        => $descricao,
            'quantidade'       => $quantidade,
            'valor_unitario'   => $valorUnitario,
            'valor_total'      => $valorTotal,
            'product_id'       => $productId,
            'notes'            => $data['notes'] ?? null,
        ]);
    }

    /**
     * Baixa de estoque ao CONCLUIR a OS (P0-2 · análise tela-venda × oficina 2026-06-04).
     *
     * Para cada item `tipo=peca` com `product_id` de produto stock-managed
     * (`enable_stock=1`), decrementa `variation_location_details.qty_available` pela
     * `quantidade` do item — por caminho AUDITÁVEL (modelo Eloquent `save()` dispara o
     * LogsActivity 'inventory.stock' do VariationLocationDetails · INV-1 DOC-RAIZ §7),
     * espelhando o fix R1 de ConsumirEstoque.
     *
     * Itens mão-de-obra / serviço terceiro (sem product_id) NÃO mexem estoque (INV-4).
     * Produto sem controle de estoque (`enable_stock=0`) é ignorado (INV-5).
     *
     * Idempotência: o caller (ServiceOrderObserver) só chama uma vez por OS — o guard
     * `transaction_id !== null` impede re-faturar/re-baixar numa 2ª conclusão.
     *
     * Cross-tenant safe: itera só itens da OS (já business-scoped); VLD resolvido por
     * product_id que addItem garantiu pertencer ao business.
     *
     * @return int  quantidade de itens que efetivamente baixaram estoque
     *
     * @see app/Domain/Fsm/SideEffects/ConsumirEstoque.php (mesmo caminho auditável)
     * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §7 INV-1/4/5
     */
    public function baixarEstoqueConclusao(int $businessId, int $osId): int
    {
        if ($businessId <= 0) {
            throw new InvalidArgumentException('business_id obrigatório (Tier 0 ADR 0093)');
        }

        // SUPERADMIN: Service não confia em session — filtro explícito por business_id
        // + service_order_id antes de mexer estoque (Tier 0, ADR 0093).
        $itens = ServiceOrderItem::withoutGlobalScopes()
            ->where('service_order_id', $osId)
            ->where('business_id', $businessId)
            ->where('tipo', ServiceOrderItem::TIPO_PECA)
            ->whereNotNull('product_id')
            ->whereNull('deleted_at')
            ->get();

        $baixados = 0;

        foreach ($itens as $item) {
            // SUPERADMIN: Service sem session — resolve produto com business_id explícito
            // pra impedir baixar estoque de catálogo de outro tenant (Tier 0, ADR 0093).
            $product = \App\Product::withoutGlobalScopes()
                ->where('id', $item->product_id)
                ->where('business_id', $businessId)
                ->first();

            // INV-5: produto inexistente ou sem controle de estoque não movimenta saldo.
            if ($product === null || (int) $product->enable_stock !== 1) {
                continue;
            }

            $qty = (float) $item->quantidade;
            if ($qty <= 0) {
                continue;
            }

            // Resolve um VLD do produto com saldo pra debitar (prefere o maior saldo).
            $vld = \App\VariationLocationDetails::query()
                ->where('product_id', $item->product_id)
                ->orderByDesc('qty_available')
                ->first();

            if ($vld === null) {
                continue;
            }

            // Caminho auditável (INV-1): save() no modelo dispara LogsActivity 'inventory.stock'.
            // Clamp em 0 (DOC-RAIZ §5) — nunca deixa saldo negativo.
            $vld->qty_available = max(0, (float) $vld->qty_available - $qty);
            $vld->save();

            $baixados++;
        }

        return $baixados;
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
        // SUPERADMIN: agregado controlado por service_order_id (OS já é business-scoped
        // pelo caller) — Service não depende de session (Tier 0, ADR 0093).
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

        // SUPERADMIN: filtro estrito por service_order_id (OS já é business-scoped pelo
        // caller) — Service não depende de session (Tier 0, ADR 0093).
        return ServiceOrderItem::withoutGlobalScopes()
            ->where('service_order_id', $osId)
            ->where('tipo', $tipo)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();
    }
}
