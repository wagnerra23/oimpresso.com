<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ComunicacaoVisual\Entities\InstalacaoCatalogo;

/**
 * InstalacaoCatalogoController — CRUD catálogo tipos de instalação (US-COMVIS-007).
 *
 * Endpoints REST sob /comunicacao-visual/api/instalacoes-catalogo.
 *
 * Fórmula cálculo:
 *   custo = preco_base + (area_total_m2 × preco_m2) + (distancia_km × preco_km)
 *
 * exige_nr35=true ativa enforcement de docs (ART + treinamento + ASO) quando
 * altura_instalacao_m > 2 — checado em OrcamentoCalculator::calcularInstalacao.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * Model::booted aplica global scope.
 *
 * @see Modules\ComunicacaoVisual\Entities\InstalacaoCatalogo
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1 US-COMVIS-007
 */
class InstalacaoCatalogoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InstalacaoCatalogo::query();

        if ($request->filled('ativo')) {
            $query->where('ativo', $request->boolean('ativo'));
        }
        if ($request->filled('exige_nr35')) {
            $query->where('exige_nr35', $request->boolean('exige_nr35'));
        }
        if ($request->filled('q')) {
            $query->where('nome', 'like', '%' . trim((string) $request->input('q')) . '%');
        }

        $itens = $query->orderBy('nome')->limit((int) $request->input('limit', 100))->get();
        return response()->json(['data' => $itens], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validate($request);
        $cat = InstalacaoCatalogo::create($validated);
        return response()->json($cat, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(InstalacaoCatalogo::findOrFail($id), 200);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $cat = InstalacaoCatalogo::findOrFail($id);
        $cat->update($this->validate($request, isUpdate: true));
        return response()->json($cat, 200);
    }

    public function destroy(int $id): JsonResponse
    {
        InstalacaoCatalogo::findOrFail($id)->delete();
        return response()->json(['message' => 'Tipo de instalação removido.'], 200);
    }

    private function validate(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';
        return $request->validate([
            'nome'                         => [$required, 'string', 'max:150'],
            'preco_base'                   => ['nullable', 'numeric', 'min:0'],
            'preco_m2'                     => ['nullable', 'numeric', 'min:0'],
            'preco_km'                     => ['nullable', 'numeric', 'min:0'],
            'exige_nr35'                   => ['nullable', 'boolean'],
            'ferramentas_necessarias_json' => ['nullable', 'array'],
            'ferramentas_necessarias_json.*' => ['string', 'max:100'],
            'ativo'                        => ['nullable', 'boolean'],
            'observacoes'                  => ['nullable', 'string', 'max:2000'],
        ], [
            'nome.required' => 'O nome do tipo de instalação é obrigatório.',
            'preco_base.min'=> 'preco_base não pode ser negativo.',
            'preco_m2.min'  => 'preco_m2 não pode ser negativo.',
            'preco_km.min'  => 'preco_km não pode ser negativo.',
        ]);
    }
}
