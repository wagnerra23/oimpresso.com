<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\OrcamentoItem;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;

/**
 * OrcamentoController — API JSON de orçamentos de comunicação visual.
 *
 * Sprint 1 — US-COMVIS-001: cálculo m² authoritative server-side.
 *
 * Endpoints:
 *   POST /comunicacao-visual/api/calcular       → preview sem persistência
 *   POST /comunicacao-visual/api/orcamentos     → persiste + retorna 201
 *   GET  /comunicacao-visual/api/orcamentos/{id} → retorna com itens
 *
 * Princípio server-side authoritative: valores area_m2 / subtotal / total vindos
 * do frontend são DESCARTADOS. O Service recalcula tudo antes de persistir.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id resolvido via session('user.business_id') — nunca do input.
 *
 * @see Modules\ComunicacaoVisual\Services\OrcamentoCalculator
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 */
class OrcamentoController extends Controller
{
    public function __construct(
        private readonly OrcamentoCalculator $calculator
    ) {}

    // ------------------------------------------------------------------
    // POST /comunicacao-visual/api/calcular
    // Preview server-side — NÃO persiste, apenas retorna cálculo authoritative
    // ------------------------------------------------------------------

    public function calcular(Request $request): JsonResponse
    {
        $validated = $this->validarPayload($request);

        try {
            $resultado = $this->calculator->calcular($validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($resultado, 200);
    }

    // ------------------------------------------------------------------
    // POST /comunicacao-visual/api/orcamentos
    // Persiste orçamento + itens, retorna 201 com relações
    // ------------------------------------------------------------------

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validarPayload($request);

        try {
            $calculado = $this->calculator->calcular($validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $businessId = session('user.business_id') ?? session('business.id');
        $numero     = $this->gerarNumero($businessId);

        $orcamento = DB::transaction(function () use ($calculado, $validated, $businessId, $numero) {
            /** @var Orcamento $orc */
            $orc = Orcamento::create([
                'business_id'      => $businessId,
                'numero'           => $numero,
                'contato_id'       => $calculado['contato_id'],
                'vendedor_id'      => $calculado['vendedor_id'],
                'data_emissao'     => $calculado['data_emissao'],
                'data_validade'    => $calculado['data_validade'],
                'status'           => 'rascunho',
                'subtotal'         => $calculado['subtotal'],
                'desconto'         => $calculado['desconto'],
                'extras'           => $calculado['extras'],
                'custo_instalacao' => $calculado['custo_instalacao'],
                'custo_entrega'    => $calculado['custo_entrega'],
                'total'            => $calculado['total'],
                'observacoes'      => $calculado['observacoes'],
            ]);

            foreach ($calculado['itens'] as $ordem => $itemCalc) {
                OrcamentoItem::create([
                    'orcamento_id'     => $orc->id,
                    'business_id'      => $businessId,
                    'material_id'      => $itemCalc['material_id'],
                    'descricao'        => $itemCalc['descricao'],
                    'largura_m'        => $itemCalc['largura_m'],
                    'altura_m'         => $itemCalc['altura_m'],
                    'quantidade'       => $itemCalc['quantidade'],
                    'area_m2'          => $itemCalc['area_m2'],
                    'preco_unitario_m2' => $itemCalc['preco_unitario_m2'],
                    'subtotal'         => $itemCalc['subtotal'],
                    'observacoes'      => $itemCalc['observacoes'],
                    'ordem'            => $ordem + 1,
                ]);
            }

            return $orc->load('itens');
        });

        return response()->json($orcamento, 201);
    }

    // ------------------------------------------------------------------
    // GET /comunicacao-visual/api/orcamentos/{id}
    // Retorna orçamento com itens (multi-tenant: global scope filtra automaticamente)
    // ------------------------------------------------------------------

    public function show(int $id): JsonResponse
    {
        $orcamento = Orcamento::with('itens')->findOrFail($id);

        return response()->json($orcamento, 200);
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    /**
     * Validação centralizada usada por calcular() e store().
     * Retorna array validado pronto para o Service.
     */
    private function validarPayload(Request $request): array
    {
        return $request->validate([
            // Cabeçalho
            'data_emissao'   => ['required', 'date'],
            'data_validade'  => ['nullable', 'date', 'after_or_equal:data_emissao'],
            'contato_id'     => ['nullable', 'integer', 'min:1'],
            'vendedor_id'    => ['nullable', 'integer', 'min:1'],
            'desconto'       => ['nullable', 'numeric', 'min:0'],
            'extras'         => ['nullable', 'numeric', 'min:0'],
            'custo_instalacao' => ['nullable', 'numeric', 'min:0'],
            'custo_entrega'  => ['nullable', 'numeric', 'min:0'],
            'observacoes'    => ['nullable', 'string', 'max:2000'],

            // Itens (mínimo 1)
            'itens'          => ['required', 'array', 'min:1'],
            'itens.*.material_id'       => ['nullable', 'integer', 'min:1'],
            'itens.*.descricao'         => ['required', 'string', 'max:500'],
            'itens.*.largura_m'         => ['required', 'numeric', 'gt:0'],
            'itens.*.altura_m'          => ['required', 'numeric', 'gt:0'],
            'itens.*.quantidade'        => ['required', 'integer', 'min:1'],
            'itens.*.preco_unitario_m2' => ['nullable', 'numeric', 'gt:0'],
            'itens.*.observacoes'       => ['nullable', 'string', 'max:500'],
        ], [
            // Mensagens de validação em PT-BR
            'data_emissao.required'    => 'A data de emissão é obrigatória.',
            'data_emissao.date'        => 'A data de emissão deve ser uma data válida.',
            'data_validade.after_or_equal' => 'A data de validade deve ser igual ou posterior à data de emissão.',
            'desconto.min'             => 'O desconto não pode ser negativo.',
            'extras.min'               => 'O valor de extras não pode ser negativo.',
            'custo_instalacao.min'     => 'O custo de instalação não pode ser negativo.',
            'custo_entrega.min'        => 'O custo de entrega não pode ser negativo.',
            'itens.required'           => 'O orçamento deve ter pelo menos 1 item.',
            'itens.min'                => 'O orçamento deve ter pelo menos 1 item.',
            'itens.*.descricao.required' => 'A descrição do item é obrigatória.',
            'itens.*.largura_m.required' => 'A largura do item é obrigatória.',
            'itens.*.largura_m.gt'     => 'A largura do item deve ser maior que zero.',
            'itens.*.altura_m.required' => 'A altura do item é obrigatória.',
            'itens.*.altura_m.gt'      => 'A altura do item deve ser maior que zero.',
            'itens.*.quantidade.required' => 'A quantidade do item é obrigatória.',
            'itens.*.quantidade.min'   => 'A quantidade do item deve ser pelo menos 1.',
            'itens.*.preco_unitario_m2.gt' => 'O preço por m² deve ser maior que zero.',
        ]);
    }

    /**
     * Gera número sequencial no formato ORC-YYYY-NNNNN por business.
     *
     * Lookup do MAX(numero) filtrando por business_id e ano atual.
     * Sequência recomeça em 1 a cada ano-civil.
     *
     * Multi-tenant Tier 0: filtra explicitamente por business_id.
     */
    private function gerarNumero(int $businessId): string
    {
        $ano     = now()->year;
        $prefixo = "ORC-{$ano}-";

        // SUPERADMIN: withoutGlobalScopes aqui pois precisamos do MAX real no biz (global scope ativo geraria ambiguidade)
        $ultimo = Orcamento::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('numero', 'LIKE', "{$prefixo}%")
            ->max('numero');

        if ($ultimo) {
            // Extrai parte numérica (últimos 5 chars do formato ORC-YYYY-NNNNN)
            $partes    = explode('-', $ultimo);
            $ultimoSeq = (int) end($partes);
            $proximo   = $ultimoSeq + 1;
        } else {
            $proximo = 1;
        }

        return $prefixo . str_pad((string) $proximo, 5, '0', STR_PAD_LEFT);
    }
}
