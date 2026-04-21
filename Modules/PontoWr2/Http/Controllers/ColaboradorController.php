<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PontoWr2\Entities\Colaborador;

class ColaboradorController extends Controller
{
    public function index(Request $request): View
    {
        $businessId = session('business.id') ?: $request->user()->business_id;
        $search = $request->input('q');

        $colaboradores = Colaborador::where('business_id', $businessId)
            ->with(['user', 'escalaAtual'])
            ->when($search, function ($q) use ($search) {
                $q->whereHas('user', function ($u) use ($search) {
                        return $u->where('first_name', 'like', "%{$search}%");
                    })
                  ->orWhere('matricula', 'like', "%{$search}%")
                  ->orWhere('cpf', 'like', "%{$search}%");
            })
            ->paginate(25);

        return view('pontowr2::colaboradores.index', compact('colaboradores', 'search'));
    }

    public function edit($id): View
    {
        $colaborador = Colaborador::with(['user', 'escalaAtual'])->findOrFail($id);
        return view('pontowr2::colaboradores.edit', compact('colaborador'));
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $colaborador = Colaborador::findOrFail($id);

        $validated = $request->validate([
            'matricula'       => 'nullable|string|max:30',
            'pis'             => 'nullable|string|max:14',
            'cpf'             => 'nullable|string|max:14',
            'escala_atual_id' => 'nullable|exists:ponto_escalas,id',
            'controla_ponto'  => 'boolean',
            'usa_banco_horas' => 'boolean',
            'admissao'        => 'required|date',
            'desligamento'    => 'nullable|date|after:admissao',
        ]);

        $colaborador->update($validated);

        return back()->with('success', 'Configuração de ponto atualizada.');
    }
}
