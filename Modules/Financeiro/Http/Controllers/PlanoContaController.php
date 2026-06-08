<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\PlanoConta;

/**
 * Plano de Contas — tela dedicada (Onda 18 #48, 2026-05-19).
 *
 * Antes: botão "Plano de contas" no header de /financeiro/unificado apontava
 * pra /financeiro/categorias (workaround Onda 16). Agora tela real lista o
 * plano contábil BR seedado em árvore hierárquica (47 entries Receita Federal).
 *
 * Persona: Eliana [E] (financeiro escritório) — quer ver estrutura contábil
 * pra classificar lançamentos corretamente.
 */
class PlanoContaController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');

        // Lista hierárquica ordenada por código (canonical BR Receita Federal).
        // Onda 18 — não usa tree DB recursive; ordenação por codigo já reflete
        // hierarquia (1, 1.1, 1.1.01, 1.1.01.001).
        $planos = PlanoConta::query()
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get([
                'id', 'codigo', 'nome', 'tipo', 'nivel', 'parent_id',
                'natureza', 'aceita_lancamento', 'protegido',
            ]);

        // Stats por tipo pra cards canônicos.
        $stats = [
            'total'      => $planos->count(),
            'receita'    => $planos->where('tipo', 'receita')->count(),
            'despesa'    => $planos->where('tipo', 'despesa')->count(),
            'ativo'      => $planos->where('tipo', 'ativo')->count(),
            'passivo'    => $planos->where('tipo', 'passivo')->count(),
            'patrimonio' => $planos->where('tipo', 'patrimonio')->count(),
            'custo'      => $planos->where('tipo', 'custo')->count(),
        ];

        return Inertia::render('Financeiro/PlanoContas/Index', [
            'planos' => $planos,
            'stats' => $stats,
        ]);
    }
}
