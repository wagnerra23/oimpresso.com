<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PontoWr2\Entities\Rep;

class ConfiguracaoController extends Controller
{
    public function index(): View
    {
        return view('pontowr2::configuracoes.index', [
            'config' => config('pontowr2'),
        ]);
    }

    public function reps(Request $request): View
    {
        $businessId = session('business.id') ?? $request->user()->business_id;
        $reps = Rep::where('business_id', $businessId)->paginate(20);
        return view('pontowr2::configuracoes.reps', compact('reps'));
    }

    public function storeRep(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tipo'          => 'required|in:REP_P,REP_C,REP_A',
            'identificador' => 'required|string|size:17|unique:ponto_reps,identificador',
            'descricao'     => 'required|string|max:120',
            'local'         => 'nullable|string|max:120',
            'cnpj'          => 'nullable|string|size:14',
        ]);

        $validated['business_id'] = session('business.id') ?? $request->user()->business_id;
        Rep::create($validated);

        return back()->with('success', 'REP cadastrado.');
    }
}
