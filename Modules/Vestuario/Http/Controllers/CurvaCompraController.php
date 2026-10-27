<?php

declare(strict_types=1);

namespace Modules\Vestuario\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Vestuario\Services\CurvaDeCompra;

/**
 * US-VEST-031 — sugestão de compra por tamanho pela curva de venda (decisão [W] 2026-10-26).
 *
 *   GET /vestuario/curva-compra?product_id=&total= → { base, motivo, dias, total, linhas[] }
 *
 * Só lê: a tela da compra mostra a sugestão e quem compra decide. Permissão de criar compra
 * (`purchase.create`) ou superadmin. Tier 0: `business_id` da sessão, explícito no serviço.
 */
class CurvaCompraController extends Controller
{
    public function show(Request $request, CurvaDeCompra $curva): JsonResponse
    {
        $user = auth()->user();
        if (! $user->can('superadmin') && ! $user->can('purchase.create')) {
            abort(403, 'Sem permissão para criar compra (purchase.create).');
        }
        $dados = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'total'      => ['required', 'integer', 'min:1', 'max:100000'],
        ], [
            'product_id.required' => 'Escolha o produto.',
            'total.required'      => 'Informe quantas peças vai comprar.',
            'total.min'           => 'Informe quantas peças vai comprar.',
        ]);
        $businessId = (int) (session('user.business_id') ?? session('business.id'));

        return response()->json($curva->sugerir($businessId, (int) $dados['product_id'], (int) $dados['total']));
    }
}
