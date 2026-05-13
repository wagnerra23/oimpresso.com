<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ComunicacaoVisual\Entities\Acabamento;

/**
 * AcabamentoController — CRUD catálogo de acabamentos (ROADMAP Fase 2 §2.3).
 *
 * Endpoints REST sob /comunicacao-visual/api/acabamentos.
 *
 * Tipo enum:
 *   - m_linear (bainha, costura, reforço borda)         — preço × perímetro
 *   - unitario (ilhós, perfuração)                       — preço × qtd unidades
 *   - m2       (laminação, verniz)                       — preço × área
 *   - fixo     (taxa setup arte)                         — preço one-shot
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * Model::booted aplica global scope.
 *
 * @see Modules\ComunicacaoVisual\Entities\Acabamento
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1
 */
class AcabamentoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Acabamento::query();

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->string('tipo'));
        }
        if ($request->filled('ativo')) {
            $query->where('ativo', $request->boolean('ativo'));
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
        $acab = Acabamento::create($validated);
        return response()->json($acab, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Acabamento::findOrFail($id), 200);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $acab = Acabamento::findOrFail($id);
        $acab->update($this->validate($request, isUpdate: true));
        return response()->json($acab, 200);
    }

    public function destroy(int $id): JsonResponse
    {
        Acabamento::findOrFail($id)->delete();
        return response()->json(['message' => 'Acabamento removido.'], 200);
    }

    private function validate(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';
        return $request->validate([
            'nome'        => [$required, 'string', 'max:150'],
            'tipo'        => [$required, 'string', 'in:m_linear,unitario,m2,fixo'],
            'preco'       => [$required, 'numeric', 'gt:0'],
            'ativo'       => ['nullable', 'boolean'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], [
            'nome.required'  => 'O nome do acabamento é obrigatório.',
            'tipo.in'        => 'Tipo deve ser m_linear, unitario, m2 ou fixo.',
            'preco.required' => 'O preço é obrigatório.',
            'preco.gt'       => 'O preço deve ser maior que zero.',
        ]);
    }
}
