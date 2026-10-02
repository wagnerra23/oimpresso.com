<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Utils\Util;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Gravação de desconto (`POST /discount` e `PUT /discount/{id}` → DiscountController@store|update).
 *
 * O QUE ESTA CLASSE FAZ — e só isso:
 *  1. AUTORIZA pela permissão de escrita `discount.manage` (decisão D1 de [W], 2026-10-02:
 *     ver a lista e criar/editar/excluir viraram permissões separadas). Quem só tem
 *     `discount.view` recebe 403 aqui, antes de o controller rodar.
 *  2. MONTA a linha a gravar no MESMO formato que o controller montava antes desta classe
 *     existir (`dadosParaGravar()`), para os dois métodos (store e update) não divergirem.
 *
 * O QUE ELA NÃO FAZ, DE PROPÓSITO: validar. `rules()` é vazio.
 * Desconto é VALOR (o PDV aplica sozinho). A thread 04 do playbook de Vendas manda que o
 * cadastro aceite e grave exatamente os mesmos valores de antes — PARAR SE o FormRequest
 * mudar o que é aceito. O achado A2 do charter fica para decisão de [W]: prioridade não
 * numérica e datas ausentes continuam passando, e endurecer aqui recusaria
 * entradas que hoje gravam. Correção medida do A2: nome vazio NÃO grava hoje — o
 * ConvertEmptyStringsToNull vira '' em null, `discounts.name` é NOT NULL, o INSERT falha e o
 * catch do controller responde success:false. Continua igual (UC-DSC-05); a tela React recusa
 * nome vazio ANTES de enviar, com mensagem.
 *
 * Números: `discount_amount` e `priority` seguem CRUS, como antes — o controller nunca
 * passou estes campos pelo `num_uf`. Datas seguem pelo `uf_date(..., true)`, no formato
 * de data/hora do negócio, como antes.
 */
class SalvarDescontoRequest extends FormRequest
{
    /** Campos copiados do request sem tratamento — a mesma lista do controller legado. */
    public const CAMPOS = ['name', 'brand_id', 'category_id',
        'location_id', 'priority', 'discount_type', 'discount_amount', 'spg', ];

    /** Checkbox: presente no request = 1, ausente = 0 (semântica de formulário HTML). */
    public const CHECKBOXES = ['is_active', 'applicable_in_cg'];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('discount.manage');
    }

    /**
     * Sem regras: ver o docblock da classe. Aceitar exatamente o que já era aceito.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Linha a gravar em `discounts`, sem `business_id` (quem chama decide se põe:
     * o store põe, o update não — igual a antes).
     *
     * Ordem idêntica à do controller legado: campos crus → (produtos apagam marca e
     * categoria) → datas → checkboxes. O efeito no banco é o mesmo em qualquer ordem;
     * mantida só para a comparação com o código antigo ser linha a linha.
     *
     * @return array<string, mixed>
     */
    public function dadosParaGravar(Util $util): array
    {
        $input = $this->only(self::CAMPOS);

        if (! empty($this->variationIds())) {
            unset($input['brand_id']);
            unset($input['category_id']);
        }

        $input['starts_at'] = $this->has('starts_at') ? $util->uf_date($this->input('starts_at'), true) : null;
        $input['ends_at'] = $this->has('ends_at') ? $util->uf_date($this->input('ends_at'), true) : null;

        foreach (self::CHECKBOXES as $checkbox) {
            $input[$checkbox] = $this->has($checkbox) ? 1 : 0;
        }

        return $input;
    }

    /**
     * Produtos (variações) escolhidos, crus como chegaram — o controller decide o sync.
     *
     * @return mixed
     */
    public function variationIds()
    {
        return $this->input('variation_ids');
    }
}
