<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Sells\ComissaoPorCliente;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /reports/comissao-por-cliente — P-156.
 *
 * Por representante: comissão aberta, liberada (parcela recebida) e vencida no período (data da
 * venda), com o detalhe por parcela. Mesma permissão do relatório de comissão de hoje
 * (`sales_representative.view`). Tudo pelo business da sessão; representante de outra empresa = 404.
 */
class ComissaoPorClienteController extends Controller
{
    public function index(Request $request, ComissaoPorCliente $comissao): JsonResponse
    {
        abort_unless(auth()->user()?->can('sales_representative.view'), 403);
        $dados = $request->validate([
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'representante_id' => ['nullable', 'integer'],
        ]);
        $biz = (int) $request->session()->get('user.business_id');
        $rep = isset($dados['representante_id']) ? (int) $dados['representante_id'] : null;
        if ($rep) {
            User::where('business_id', $biz)->findOrFail($rep);
        }

        return response()->json(['representantes' => $comissao->doPeriodo($biz, $dados['inicio'], $dados['fim'], now(), $rep)]);
    }
}
