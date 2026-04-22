<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PontoWr2\Entities\Colaborador;
use Modules\PontoWr2\Entities\Escala;

class ColaboradorController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = session('business.id') ?: $request->user()->business_id;
        $search = $request->input('q');

        $paginated = Colaborador::where('business_id', $businessId)
            ->with(['user:id,first_name,last_name,email', 'escalaAtual:id,nome'])
            ->when($search, function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u->where('first_name', 'like', "%{$search}%"))
                  ->orWhere('matricula', 'like', "%{$search}%")
                  ->orWhere('cpf', 'like', "%{$search}%");
            })
            ->orderBy('matricula')
            ->paginate(25)
            ->withQueryString();

        $paginated->getCollection()->transform(fn ($c) => [
            'id'              => $c->id,
            'matricula'       => $c->matricula,
            'cpf'             => $c->cpf,
            'pis'             => $c->pis,
            'nome'            => trim(optional($c->user)->first_name . ' ' . optional($c->user)->last_name) ?: '—',
            'email'           => optional($c->user)->email,
            'escala'          => optional($c->escalaAtual)->nome,
            'controla_ponto'  => (bool) $c->controla_ponto,
            'usa_banco_horas' => (bool) $c->usa_banco_horas,
            'admissao'        => optional($c->admissao)->format('Y-m-d'),
            'desligamento'    => optional($c->desligamento)->format('Y-m-d'),
        ]);

        return Inertia::render('Ponto/Colaboradores/Index', [
            'colaboradores' => $paginated,
            'search'        => $search,
        ]);
    }

    public function edit($id): Response
    {
        $colaborador = Colaborador::with(['user', 'escalaAtual'])->findOrFail($id);
        $businessId = session('business.id') ?: request()->user()->business_id;

        return Inertia::render('Ponto/Colaboradores/Edit', [
            'colaborador' => [
                'id'              => $colaborador->id,
                'matricula'       => $colaborador->matricula,
                'cpf'             => $colaborador->cpf,
                'pis'             => $colaborador->pis,
                'nome'            => trim(optional($colaborador->user)->first_name . ' ' . optional($colaborador->user)->last_name) ?: '—',
                'email'           => optional($colaborador->user)->email,
                'controla_ponto'  => (bool) $colaborador->controla_ponto,
                'usa_banco_horas' => (bool) $colaborador->usa_banco_horas,
                'admissao'        => optional($colaborador->admissao)->format('Y-m-d'),
                'desligamento'    => optional($colaborador->desligamento)->format('Y-m-d'),
                'escala_atual_id' => $colaborador->escala_atual_id,
            ],
            'escalas' => Escala::where('business_id', $businessId)
                ->orderBy('nome')
                ->get(['id', 'nome', 'tipo'])
                ->map(fn ($e) => ['id' => $e->id, 'nome' => $e->nome, 'tipo' => $e->tipo]),
        ]);
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
