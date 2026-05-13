<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ComunicacaoVisual\Entities\Substrato;

/**
 * SubstratoController — CRUD catálogo de substratos (US-COMVIS-002, ROADMAP Fase 2 §2.3).
 *
 * Endpoints REST:
 *   GET    /comunicacao-visual/api/substratos          → index (com filtros categoria/ativo/q)
 *   POST   /comunicacao-visual/api/substratos          → store (cria)
 *   GET    /comunicacao-visual/api/substratos/{id}     → show
 *   PUT    /comunicacao-visual/api/substratos/{id}     → update
 *   DELETE /comunicacao-visual/api/substratos/{id}     → destroy (soft-delete)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id resolvido via session — Model::booted aplica global scope automaticamente.
 *
 * @see Modules\ComunicacaoVisual\Entities\Substrato
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-002 §12.1
 */
class SubstratoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Substrato::query();

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->string('categoria'));
        }
        if ($request->filled('ativo')) {
            $query->where('ativo', $request->boolean('ativo'));
        }
        if ($request->filled('q')) {
            $busca = '%' . trim((string) $request->input('q')) . '%';
            $query->where('nome', 'like', $busca);
        }

        $itens = $query->orderBy('nome')
            ->limit((int) $request->input('limit', 100))
            ->get();

        return response()->json(['data' => $itens], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validate($request);
        $substrato = Substrato::create($validated);
        return response()->json($substrato, 201);
    }

    public function show(int $id): JsonResponse
    {
        $sub = Substrato::findOrFail($id);
        return response()->json($sub, 200);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $sub = Substrato::findOrFail($id);
        $validated = $this->validate($request, isUpdate: true);
        $sub->update($validated);
        return response()->json($sub, 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $sub = Substrato::findOrFail($id);
        $sub->delete(); // soft-delete (Model uses SoftDeletes trait)
        return response()->json(['message' => 'Substrato removido.'], 200);
    }

    /**
     * Validação compartilhada (categorias enum + tributação CNAE 1813).
     */
    private function validate(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'nome'           => [$required, 'string', 'max:150'],
            'categoria'      => [$required, 'string', 'in:lona,vinil,adesivo,acm,tela,mdf,neon,letra_caixa,outro'],
            'gramatura_g_m2' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'preco_custo_m2' => ['nullable', 'numeric', 'min:0'],
            'preco_venda_m2' => [$required, 'numeric', 'gt:0'],
            'minimo_m2'      => ['nullable', 'numeric', 'min:0', 'max:999.999'],
            'ncm'            => ['nullable', 'string', 'size:8'],         // formato 0000.00 (8 chars sem ponto OK também)
            'cfop_padrao'    => ['nullable', 'string', 'size:4'],
            'csosn_padrao'   => ['nullable', 'string', 'max:3'],
            'fornecedor_id'  => ['nullable', 'integer', 'min:1'],
            'ativo'          => ['nullable', 'boolean'],
            'observacoes'    => ['nullable', 'string', 'max:2000'],
        ], [
            'nome.required'        => 'O nome do substrato é obrigatório.',
            'categoria.in'         => 'Categoria inválida (use lona, vinil, adesivo, acm, tela, mdf, neon, letra_caixa ou outro).',
            'preco_venda_m2.required' => 'O preço de venda por m² é obrigatório.',
            'preco_venda_m2.gt'    => 'O preço de venda por m² deve ser maior que zero.',
            'minimo_m2.min'        => 'O mínimo m² não pode ser negativo.',
            'gramatura_g_m2.min'   => 'A gramatura deve ser pelo menos 1 g/m².',
        ]);
    }
}
