<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Http\Requests\UpsertCategoriaRequest;
use Modules\Financeiro\Models\Categoria;
use Modules\Financeiro\Models\PlanoConta;

/**
 * CRUD livre de fin_categorias.
 *
 * Categorias complementam o plano de contas (que é fixo/contábil) com tags
 * livres e cores customizadas pra UI/relatórios. Ex: "Aluguel Loja A",
 * "Marketing Digital Q4", "Comissão Vendedor Z".
 *
 * Multi-tenant: BusinessScope (Concerns\BusinessScope) filtra por
 * session('user.business_id') automaticamente em todas as queries.
 *
 * Pattern: ADR 0029 (Inertia + React + UPos).
 */
class CategoriaController extends Controller
{
    public function index(Request $request): Response
    {
        $categorias = Categoria::query()
            ->orderBy('ativo', 'desc')
            ->orderBy('nome')
            ->get([
                'id', 'nome', 'cor', 'plano_conta_id', 'tipo', 'ativo',
            ]);

        $planosConta = PlanoConta::query()
            ->where('ativo', true)
            ->where('aceita_lancamento', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome', 'natureza']);

        return Inertia::render('Financeiro/Categorias/Index', [
            'categorias' => $categorias,
            'planos_conta' => $planosConta,
        ]);
    }

    public function store(UpsertCategoriaRequest $request): RedirectResponse
    {
        Categoria::create($request->validated() + [
            'ativo' => $request->boolean('ativo', true),
        ]);

        return back()->with('success', 'Categoria criada com sucesso.');
    }

    public function update(UpsertCategoriaRequest $request, int $id): RedirectResponse
    {
        // BusinessScope garante 404 se categoria for de outro business.
        $categoria = Categoria::findOrFail($id);

        $categoria->update($request->validated());

        return back()->with('success', 'Categoria atualizada com sucesso.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $categoria = Categoria::findOrFail($id);
        $categoria->delete();

        return back()->with('success', 'Categoria removida.');
    }

    public function toggleAtivo(Request $request, int $id): RedirectResponse
    {
        $categoria = Categoria::findOrFail($id);
        $categoria->ativo = ! $categoria->ativo;
        $categoria->save();

        return back()->with('success', $categoria->ativo ? 'Categoria ativada.' : 'Categoria inativada.');
    }
}
