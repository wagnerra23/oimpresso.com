<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Services\MecanicosDaOs;

/**
 * US-AUTO-006 — mecânicos da OS e as horas de cada um.
 *
 *   GET /oficina-auto/ordens-servico/{order}/mecanicos → lista
 *   PUT /oficina-auto/ordens-servico/{order}/mecanicos → troca a lista inteira ({mecanicos:[{user_id,horas}]})
 *
 * Atrás de `oficina-auto.multi_mecanico.enabled` (desligado = 404). Tier 0: a empresa vem do usuário.
 */
class ServiceOrderMecanicoController extends Controller
{
    public function __construct(private readonly MecanicosDaOs $mecanicos) {}

    public function index(Request $request, ServiceOrder $order): JsonResponse
    {
        abort_unless($this->mecanicos->ligado(), 404);
        $this->authorize('view', $order);

        return response()->json(['mecanicos' => $this->mecanicos->listar((int) $request->user()->business_id, $order->id)]);
    }

    public function update(Request $request, ServiceOrder $order): JsonResponse
    {
        abort_unless($this->mecanicos->ligado(), 404);
        $this->authorize('update', $order);

        $dados = $request->validate([
            'mecanicos'           => ['present', 'array', 'max:20'],
            'mecanicos.*.user_id' => ['required', 'integer'],
            'mecanicos.*.horas'   => ['required', 'numeric'],
        ]);

        try {
            $lista = $this->mecanicos->definir((int) $request->user()->business_id, $order->id, $dados['mecanicos']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['mecanicos' => $lista]);
    }
}
