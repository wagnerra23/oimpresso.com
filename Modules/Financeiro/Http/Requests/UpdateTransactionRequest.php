<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra atualizar Titulo existente.
 *
 * Wave 17 D8 — campos imutáveis pós-criação NÃO aparecem nas rules
 * (tipo, valor_total, origem, origem_id). Mudanças nestes => DomainException
 * do Service. Permite somente updates "leves": vencimento, observacoes,
 * categoria_id, plano_conta_id, cliente_descricao.
 *
 * Append-only IRREVOGÁVEL pra `valor_aberto` e `status` — esses
 * só mudam via BaixaService::registrar() / cancelar().
 *
 * @see Modules\Financeiro\Models\Titulo
 * @see memory/proibicoes.md "Append-only contrato"
 */
class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            // Editáveis (low-impact)
            'numero'              => ['sometimes', 'string', 'max:50'],
            'cliente_descricao'   => ['sometimes', 'string', 'max:150'],
            'vencimento'          => ['sometimes', 'date'],
            'competencia_mes'     => ['sometimes', 'date_format:Y-m'],
            'plano_conta_id'      => ['sometimes', 'nullable', 'integer'],
            'categoria_id'        => ['sometimes', 'nullable', 'integer'],
            'observacoes'         => ['sometimes', 'nullable', 'string', 'max:1000'],
            'metadata'            => ['sometimes', 'array'],

            // Mudança de cliente_id permitida (re-atribuição manual)
            'cliente_id'          => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * Wave 17 D8 — bloqueia explicitamente atributos imutáveis vindos do request.
     * Mesmo que appareçam no payload, removemos do validated().
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        $imutaveis = [
            'business_id', 'tipo', 'valor_total', 'valor_aberto', 'status',
            'origem', 'origem_id', 'parcela_numero', 'parcela_total',
            'titulo_pai_id', 'created_by', 'updated_by',
        ];

        foreach ($imutaveis as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    public function messages(): array
    {
        return [
            'vencimento.date' => 'Vencimento deve ser data valida.',
        ];
    }
}
