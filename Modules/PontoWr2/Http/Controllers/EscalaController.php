<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PontoWr2\Entities\Escala;

class EscalaController extends Controller
{
    public function index(Request $request): View
    {
        $businessId = session('business.id') ?? $request->user()->business_id;
        $escalas = Escala::where('business_id', $businessId)->paginate(20);
        return view('pontowr2::escalas.index', compact('escalas'));
    }

    public function create(): View
    {
        return view('pontowr2::escalas.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:120',
            'codigo' => 'nullable|string|max:30',
            'tipo' => 'required|in:FIXA,FLEXIVEL,ESCALA_12X36,ESCALA_6X1,ESCALA_5X2',
            'carga_diaria_minutos' => 'required|integer|min:60|max:600',
            'carga_semanal_minutos' => 'required|integer|min:0|max:3600',
            'permite_banco_horas' => 'boolean',
        ]);

        $validated['business_id'] = session('business.id') ?? $request->user()->business_id;
        $escala = Escala::create($validated);

        return redirect()
            ->route('ponto.escalas.edit', $escala->id)
            ->with('success', 'Escala criada. Configure os turnos por dia da semana.');
    }

    public function edit(int $id): View
    {
        $escala = Escala::with('turnos')->findOrFail($id);
        return view('pontowr2::escalas.edit', compact('escala'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $escala = Escala::findOrFail($id);
        $escala->update($request->validated());
        return back()->with('success', 'Escala atualizada.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Escala::findOrFail($id)->delete();
        return redirect()->route('ponto.escalas.index')->with('success', 'Escala removida.');
    }
}
