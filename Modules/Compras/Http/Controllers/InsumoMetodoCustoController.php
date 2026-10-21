<?php

declare(strict_types=1);

namespace Modules\Compras\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Compras\Entities\InsumoMetodoCusto;

/**
 * Grava o método de custo próprio do insumo (US-COM-024 · UC-FLX-38).
 *
 * O método só vale quando a empresa escolheu "Definido em cada insumo" (`por_insumo`) na regra
 * "Atualizar o custo dos insumos" de Fluxos → Compra; quem lê é `CustoDoInsumoService`. Este
 * endpoint grava uma linha por (empresa, variação) na tabela-ponte e não recalcula nada: custos
 * já gravados em orçamento ou recebimento ficam como estão, o método novo vale para as próximas
 * leituras.
 *
 * Tier 0 (ADR 0093): a empresa vem do usuário logado, nunca do corpo; a variação precisa ser de
 * um produto desta empresa, senão 404 (não 403, para não confirmar que o id existe noutra).
 */
class InsumoMetodoCustoController extends Controller
{
    public function update(Request $request, int $variation): RedirectResponse
    {
        abort_unless(auth()->user()->can('purchase.update'), 403);

        $businessId = (int) $request->session()->get('user.business_id');
        abort_if($businessId <= 0, 403);

        $dados = $request->validate([
            'metodo' => ['required', 'string', Rule::in(InsumoMetodoCusto::METODOS)],
        ]);

        $daEmpresa = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('v.id', $variation)
            ->where('p.business_id', $businessId)
            ->whereNull('v.deleted_at')
            ->exists();
        abort_unless($daEmpresa, 404);

        InsumoMetodoCusto::query()
            ->where('business_id', $businessId)
            ->updateOrCreate(
                ['business_id' => $businessId, 'variation_id' => $variation],
                ['metodo' => $dados['metodo']],
            );

        return back();
    }
}
