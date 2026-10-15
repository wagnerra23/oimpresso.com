<?php

namespace Modules\Producao\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Producao\Entities\ColaboradorCusto;

/**
 * Custo-hora da pessoa no colaborador — emenda E03 da thread 03 (ADR 0431). É dado de
 * desempenho: ler e gravar exigem `producao.desempenho.ver` (D-PERM). Só é um valor
 * cadastrado; a conta do custo real é do `CustoRealService` (thread 02).
 */
class ColaboradorCustoController extends Controller
{
    public function update(Request $request, int $userId): RedirectResponse
    {
        abort_unless($request->user()->can('producao.access') && $request->user()->can('producao.desempenho.ver'), 403);
        $biz = (int) $request->session()->get('user.business_id');
        // O colaborador tem de ser da empresa: id de outra empresa é recusado na validação, nunca gravado.
        $request->merge(['user_id' => $userId]);
        $dados = $request->validate([
            'user_id' => [Rule::exists('users', 'id')->where('business_id', $biz)],
            'custo_hora' => ['required', 'numeric', 'min:0', 'max:99999'],
        ]);
        ColaboradorCusto::query()->updateOrCreate(
            ['business_id' => $biz, 'user_id' => $userId],
            ['custo_hora' => round((float) $dados['custo_hora'], 2)],
        );

        return back()->with('status', ['success' => 1, 'msg' => 'Custo-hora salvo.']);
    }
}
