<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\CatalogoDePecas;

/**
 * US-AUTO-008 — peças compatíveis com o veículo da OS (catálogo por marca e modelo em texto).
 *
 *   GET  /oficina-auto/ordens-servico/{order}/pecas-compativeis → lista as peças que servem no veículo
 *   POST /oficina-auto/ordens-servico/{order}/pecas-compativeis → marca uma peça como compatível
 *
 * Atrás de `oficina-auto.catalogo_pecas.enabled` (desligado = 404). Tier 0 (ADR 0093): a empresa vem
 * do usuário logado; a OS chega pelo route-model binding com escopo global da empresa.
 */
class PecaCompativelController extends Controller
{
    public function __construct(private readonly CatalogoDePecas $catalogo) {}

    public function index(Request $request, ServiceOrder $order): JsonResponse
    {
        abort_unless($this->catalogo->ligado(), 404);
        $this->authorize('view', $order);

        $businessId = (int) $request->user()->business_id;
        $veiculo = $order->vehicle_id ? Vehicle::query()->find($order->vehicle_id) : null;
        if (! $veiculo) {
            return response()->json(['veiculo' => null, 'pecas' => []]);
        }

        return response()->json([
            'veiculo' => ['marca' => $veiculo->getAttribute('brand'), 'modelo' => $veiculo->getAttribute('model'), 'ano' => $veiculo->model_year ?: $veiculo->manufacture_year],
            'pecas'   => $this->catalogo->compativeisCom($businessId, $veiculo),
        ]);
    }

    public function store(Request $request, ServiceOrder $order): JsonResponse
    {
        abort_unless($this->catalogo->ligado(), 404);
        $this->authorize('update', $order);

        $dados = $request->validate([
            'product_id' => ['required', 'integer'],
            'marca'      => ['required', 'string', 'max:60'],
            'modelo'     => ['nullable', 'string', 'max:80'],
            'ano_de'     => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'ano_ate'    => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ]);

        try {
            $id = $this->catalogo->marcarCompativel(
                (int) $request->user()->business_id,
                (int) $dados['product_id'],
                $dados['marca'],
                $dados['modelo'] ?? null,
                isset($dados['ano_de']) ? (int) $dados['ano_de'] : null,
                isset($dados['ano_ate']) ? (int) $dados['ano_ate'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $id], 201);
    }
}
