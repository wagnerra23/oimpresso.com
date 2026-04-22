<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PontoWr2\Entities\Rep;

class ConfiguracaoController extends Controller
{
    public function index(): Response
    {
        $cfg = config('pontowr2');
        return Inertia::render('Ponto/Configuracoes/Index', [
            'config' => $cfg,
        ]);
    }

    public function reps(Request $request): Response
    {
        $businessId = session('business.id') ?? $request->user()->business_id;
        $paginated = Rep::where('business_id', $businessId)
            ->orderBy('identificador')
            ->paginate(20)
            ->withQueryString();

        $paginated->getCollection()->transform(fn ($r) => [
            'id'            => $r->id,
            'tipo'          => $r->tipo,
            'identificador' => $r->identificador,
            'descricao'     => $r->descricao,
            'local'         => $r->local,
            'cnpj'          => $r->cnpj,
            'ativo'         => (bool) ($r->ativo ?? true),
        ]);

        return Inertia::render('Ponto/Configuracoes/Reps', [
            'reps' => $paginated,
        ]);
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
