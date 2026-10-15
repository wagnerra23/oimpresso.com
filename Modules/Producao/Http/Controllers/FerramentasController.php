<?php

namespace Modules\Producao\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Producao\Entities\Ferramenta;

/**
 * Ferramentas com código QR — emenda E03 da thread 03 (ADR 0431). A saída e a devolução por QR
 * são da thread 14; aqui só o cadastro. Tudo pelo escopo da empresa.
 */
class FerramentasController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $biz = $this->autorizar($request);
        Ferramenta::create($this->validar($request, $biz) + ['business_id' => $biz]);

        return back()->with('status', ['success' => 1, 'msg' => 'Ferramenta salva.']);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $biz = $this->autorizar($request);
        $ferramenta = Ferramenta::query()->findOrFail($id);
        $ferramenta->update($this->validar($request, $biz, $ferramenta->id));

        return back()->with('status', ['success' => 1, 'msg' => 'Ferramenta salva.']);
    }

    private function autorizar(Request $request): int
    {
        abort_unless($request->user()->can('producao.access'), 403);

        return (int) $request->session()->get('user.business_id');
    }

    private function validar(Request $request, int $biz, ?int $ignorar = null): array
    {
        return $request->validate([
            'codigo_qr' => ['required', 'string', 'max:40', Rule::unique('pcp_ferramentas', 'codigo_qr')->where('business_id', $biz)->ignore($ignorar)],
            'nome' => ['required', 'string', 'max:120'],
            'ativo' => ['boolean'],
        ]);
    }
}
