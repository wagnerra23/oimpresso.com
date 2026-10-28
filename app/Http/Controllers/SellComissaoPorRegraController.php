<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Sells\ComissaoPorRegra;
use App\Transaction;
use Illuminate\Http\JsonResponse;

/**
 * GET /sells/{id}/comissao-por-regra — US-COMM-015.
 *
 * Devolve a comissão por regra da venda (vendedor e técnico da OS), sem gravar. Desligada
 * (flag `SELLS_COMISSAO_POR_REGRA`) → 404, como se a rota não existisse. Venda de outra
 * empresa → 404. Permissão: `sell.view`.
 */
class SellComissaoPorRegraController extends Controller
{
    public function show(int $id, ComissaoPorRegra $comissao): JsonResponse
    {
        abort_unless(ComissaoPorRegra::ligada(), 404);
        abort_unless(auth()->user()?->can('sell.view'), 403);

        $venda = Transaction::where('business_id', (int) session('user.business_id'))
            ->where('type', 'sell')
            ->findOrFail($id);

        return response()->json($comissao->daVenda($venda));
    }
}
