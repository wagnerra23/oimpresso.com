<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PontoWr2\Entities\Escala;

class EscalaController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = session('business.id') ?? $request->user()->business_id;
        $paginated = Escala::where('business_id', $businessId)
            ->withCount('turnos')
            ->paginate(20)
            ->withQueryString();

        $paginated->getCollection()->transform(fn ($e) => [
            'id'                    => $e->id,
            'nome'                  => $e->nome,
            'codigo'                => $e->codigo,
            'tipo'                  => $e->tipo,
            'carga_diaria_minutos'  => (int) $e->carga_diaria_minutos,
            'carga_semanal_minutos' => (int) $e->carga_semanal_minutos,
            'permite_banco_horas'   => (bool) $e->permite_banco_horas,
            'turnos_count'          => (int) $e->turnos_count,
        ]);

        return Inertia::render('Ponto/Escalas/Index', ['escalas' => $paginated]);
    }

    public function create(): Response
    {
        return Inertia::render('Ponto/Escalas/Form', ['escala' => null]);
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

    public function edit(int $id): Response
    {
        $escala = Escala::with('turnos')->findOrFail($id);
        return Inertia::render('Ponto/Escalas/Form', [
            'escala' => [
                'id'                    => $escala->id,
                'nome'                  => $escala->nome,
                'codigo'                => $escala->codigo,
                'tipo'                  => $escala->tipo,
                'carga_diaria_minutos'  => (int) $escala->carga_diaria_minutos,
                'carga_semanal_minutos' => (int) $escala->carga_semanal_minutos,
                'permite_banco_horas'   => (bool) $escala->permite_banco_horas,
                'turnos'                => $escala->turnos->map(fn ($t) => [
                    'id'                 => $t->id,
                    'dia_semana'         => $t->dia_semana,
                    'entrada'            => $t->entrada,
                    'saida'              => $t->saida,
                    'almoco_inicio'      => $t->almoco_inicio,
                    'almoco_fim'         => $t->almoco_fim,
                ])->toArray(),
            ],
        ]);
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
