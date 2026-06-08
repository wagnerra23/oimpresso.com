<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar Titulo (transação financeira).
 *
 * Wave 17 D8 — saturação governance Financeiro (66→81). Type-hint
 * obrigatório em Controllers que recebem POST de criação de título.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` NUNCA aceito do request
 * — vem de session() no Controller via BusinessScope `creating` event.
 *
 * @see Modules\Financeiro\Models\Titulo
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permissão fina via middleware can:financeiro.{contas_pagar|contas_receber}.create
        // no roteador. Aqui basta usuário autenticado.
        return $this->user() !== null;
    }

    /**
     * Regras de validação pra criação de Titulo.
     *
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            // Identificação básica
            'numero'              => ['nullable', 'string', 'max:50'],
            'tipo'                => ['required', 'in:receber,pagar'],
            'cliente_id'          => ['nullable', 'integer'],
            'cliente_descricao'   => ['required_without:cliente_id', 'nullable', 'string', 'max:150'],

            // Valores monetários
            'valor_total'         => ['required', 'numeric', 'min:0.01', 'max:99999999.9999'],
            'moeda'               => ['nullable', 'string', 'size:3'],

            // Datas
            'emissao'             => ['nullable', 'date'],
            'vencimento'          => ['required', 'date'],
            'competencia_mes'     => ['nullable', 'date_format:Y-m'],

            // Origem (relação inversa com Sells/Repair/manual)
            'origem'              => ['required', 'in:manual,sells,repair,assinatura,boleto'],
            'origem_id'           => ['nullable', 'integer'],

            // Parcelamento (defaults aplicados no Service se ausente)
            'parcela_numero'      => ['nullable', 'integer', 'min:1'],
            'parcela_total'       => ['nullable', 'integer', 'min:1', 'max:60'],
            'titulo_pai_id'       => ['nullable', 'integer'],

            // Classificação
            'plano_conta_id'      => ['nullable', 'integer'],
            'categoria_id'        => ['nullable', 'integer'],

            // Metadados livres
            'observacoes'         => ['nullable', 'string', 'max:1000'],
            'metadata'            => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required'             => 'Tipo do titulo e obrigatorio (receber ou pagar).',
            'valor_total.min'           => 'Valor total deve ser maior que zero.',
            'cliente_descricao.required_without' => 'Informe cliente_id OU descricao livre do cliente.',
            'origem.in'                 => 'Origem deve ser uma das: manual, sells, repair, assinatura, boleto.',
        ];
    }
}
