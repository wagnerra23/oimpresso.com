<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Sells\IcmsStDaVenda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * US-SELL-078 · POST /sells/icms-st — o ICMS-ST do carrinho, para a tela de venda mostrar no total
 * antes de gravar ([W] 2026-10-26: o ST entra no fechamento da venda). A conta é a da NF-e
 * (IcmsStDaVenda); ao gravar, o servidor recalcula e recusa se divergir.
 *
 * Só leitura. Tier 0: produto, cliente e local resolvidos no business da sessão.
 */
class SellIcmsStController extends Controller
{
    public function calcular(Request $request, IcmsStDaVenda $st): JsonResponse
    {
        if (! auth()->user()->can('sell.create') && ! auth()->user()->can('direct_sell.access')) {
            abort(403);
        }
        $dados = $request->validate([
            'location_id' => 'nullable|integer',
            'contact_id' => 'nullable|integer',
            'itens' => 'array|max:500',
            'itens.*.variation_id' => 'required|integer',
            'itens.*.quantidade' => 'required|numeric|min:0',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
            'total_sem_st' => 'required|numeric|min:0',
            'frete' => 'nullable|numeric|min:0',
        ]);
        $businessId = (int) $request->session()->get('user.business_id');
        $valor = $st->doCarrinho(
            $businessId,
            isset($dados['location_id']) ? (int) $dados['location_id'] : null,
            isset($dados['contact_id']) ? (int) $dados['contact_id'] : null,
            $dados['itens'] ?? [],
            (float) $dados['total_sem_st'],
            (float) ($dados['frete'] ?? 0),
        );

        return response()->json(['icms_st' => number_format($valor, 2, '.', '')]);
    }
}
