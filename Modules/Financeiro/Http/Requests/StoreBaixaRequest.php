<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest pra criar TituloBaixa (pagamento parcial/total de titulo).
 *
 * Wave 18 RETRY D8 — 5° FormRequest tipado do Financeiro. Antes baixas eram
 * criadas via Service direto sem Request canon — agora controllers que aceitam
 * baixa manual (não via TransactionPayment Observer) injetam este Request.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` vem de session, NUNCA aceito
 * do request. Service força where('business_id', $bizFromSession).
 *
 * Append-only (memory/proibicoes.md): baixa NUNCA permite delete — estorno
 * cria nova linha com estorno_de_id apontando.
 *
 * @see Modules\Financeiro\Models\TituloBaixa
 * @see Modules\Financeiro\Services\TituloAutoService::registrarPagamento
 */
class StoreBaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permissão fina via middleware can:financeiro.{contas_pagar|contas_receber}.baixar
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titulo_id'         => ['required', 'integer', 'min:1'],
            'valor_baixa'       => ['required', 'numeric', 'min:0.01', 'max:99999999.9999'],
            'data_baixa'        => ['required', 'date'],
            'conta_bancaria_id' => ['required', 'integer', 'min:1'],
            'meio_pagamento'    => ['required', Rule::in([
                'dinheiro', 'pix', 'cartao_credito', 'cartao_debito',
                'cheque', 'transferencia', 'boleto', 'outro',
            ])],

            // Adicionais opcionais
            'juros'       => ['nullable', 'numeric', 'min:0'],
            'multa'       => ['nullable', 'numeric', 'min:0'],
            'desconto'    => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:500'],

            // Idempotency explícita (caller pode fornecer pra evitar dupla baixa em retry HTTP)
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo_id.required'         => 'Titulo de origem e obrigatorio.',
            'valor_baixa.min'            => 'Valor baixa deve ser maior que zero.',
            'data_baixa.required'        => 'Data da baixa e obrigatoria.',
            'conta_bancaria_id.required' => 'Conta bancaria de destino e obrigatoria.',
            'meio_pagamento.in'          => 'Meio pagamento invalido. Use dinheiro, pix, cartao_credito, cartao_debito, cheque, transferencia, boleto ou outro.',
        ];
    }

    /**
     * Helper tipado.
     */
    public function meioPagamento(): string
    {
        return (string) $this->input('meio_pagamento', 'outro');
    }

    /**
     * Helper tipado — soma valor + juros + multa - desconto.
     */
    public function valorEfetivo(): float
    {
        $valor = (float) $this->input('valor_baixa', 0);
        $juros = (float) $this->input('juros', 0);
        $multa = (float) $this->input('multa', 0);
        $desconto = (float) $this->input('desconto', 0);

        return round($valor + $juros + $multa - $desconto, 2);
    }
}
