<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Services\AssinaturaEntrega;

/**
 * Assinatura do cliente na entrega da OS — US-OFICINA-048 · UC-FLX-22.
 *
 *   POST /oficina-auto/ordens-servico/{order}/assinatura  → store (201)
 *
 * O quadro de assinatura do checklist da entrega (`ServiceOrderStageGate.tsx`) envia o PNG como data
 * URL, no contrato da entrega do pedido no app. Gravar não avança a OS: a entrega continua sendo a
 * ação `entregar` do FSM, que o gate só libera com a assinatura quando a empresa a exige.
 *
 * Tier 0 (ADR 0093): a OS vem pelo Route Model Binding com o escopo de business; a policy `update`
 * confere o mesmo tenant; a assinatura é gravada com o business_id da OS.
 */
class ServiceOrderAssinaturaController extends Controller
{
    public function store(Request $request, ServiceOrder $order, AssinaturaEntrega $assinaturas): JsonResponse
    {
        $this->authorize('update', $order);

        $dados = $request->validate([
            'assinatura_png' => ['required', 'string'],
            'recebido_por' => ['nullable', 'string', 'max:120'],
        ], [
            'assinatura_png.required' => 'Colha a assinatura antes de salvar.',
        ]);

        $png = AssinaturaEntrega::decodificarPng((string) $dados['assinatura_png']);
        if ($png === null) {
            return response()->json([
                'message' => 'A assinatura precisa ser uma imagem PNG de até 200 KB.',
                'errors' => ['assinatura_png' => ['A assinatura precisa ser uma imagem PNG de até 200 KB.']],
            ], 422);
        }

        $nome = trim((string) ($dados['recebido_por'] ?? ''));
        $assinatura = $assinaturas->registrar($order, $png, $nome !== '' ? $nome : null, auth()->id());

        return response()->json([
            'assinatura' => [
                'recebido_por' => $assinatura->recebido_por,
                'colhida_em' => $assinatura->updated_at?->toIso8601String(),
            ],
        ], 201);
    }
}
