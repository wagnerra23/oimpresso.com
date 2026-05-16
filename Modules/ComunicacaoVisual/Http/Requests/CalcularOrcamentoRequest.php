<?php

namespace Modules\ComunicacaoVisual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CalcularOrcamentoRequest — validação payload de cálculo/persistência de orçamentos.
 *
 * Wave 10 D8 Security — Sprint 1 US-COMVIS-001.
 *
 * Cobre tanto POST /comunicacao-visual/api/calcular (preview) quanto
 * POST /comunicacao-visual/api/orcamentos (persiste). Mesmas regras de payload —
 * o que muda é o uso server-side (Service recalcula sempre, valores do client
 * são DESCARTADOS).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id NUNCA aceito do input — resolvido via session no Controller.
 *
 * Authorization gate: usuário precisa estar autenticado (middleware 'auth' já garante).
 * Permissão fina pode ser adicionada quando ACL ComunicacaoVisual for definida (Sprint 2+).
 */
class CalcularOrcamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth garantido por middleware 'auth' no group da rota.
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // contato_id e vendedor_id são opcionais no payload — Service resolve fallback
            'contato_id'  => ['nullable', 'integer', 'min:1'],
            'vendedor_id' => ['nullable', 'integer', 'min:1'],

            // Itens — pelo menos 1
            'itens'                       => ['required', 'array', 'min:1', 'max:200'],
            'itens.*.descricao'           => ['required', 'string', 'max:255'],
            'itens.*.largura_mm'          => ['required', 'numeric', 'min:1', 'max:100000'],
            'itens.*.altura_mm'           => ['required', 'numeric', 'min:1', 'max:100000'],
            'itens.*.quantidade'          => ['required', 'integer', 'min:1', 'max:10000'],
            'itens.*.preco_m2'            => ['required', 'numeric', 'min:0', 'max:1000000'],
            'itens.*.acabamento_id'       => ['nullable', 'integer', 'min:1'],
            'itens.*.material_id'         => ['nullable', 'integer', 'min:1'],
            'itens.*.observacoes'         => ['nullable', 'string', 'max:1000'],

            // Desconto/acréscimo geral
            'desconto_tipo'   => ['nullable', 'in:percentual,valor'],
            'desconto_valor'  => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'observacoes'     => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'itens.required'         => 'Pelo menos um item é obrigatório no orçamento.',
            'itens.min'              => 'Pelo menos um item é obrigatório no orçamento.',
            'itens.max'              => 'Máximo 200 itens por orçamento.',
            'itens.*.largura_mm.min' => 'Largura mínima 1mm.',
            'itens.*.altura_mm.min'  => 'Altura mínima 1mm.',
            'itens.*.quantidade.min' => 'Quantidade mínima 1.',
        ];
    }
}
