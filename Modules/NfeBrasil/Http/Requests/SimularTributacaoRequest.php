<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Simulador read-only da Tributação (playbook Fiscal thread 08 · D-SIM · UC-NFTR-08/09).
 *
 * Mesma permissão de quem mexe nas regras: quem simula está conferindo a configuração fiscal.
 */
class SimularTributacaoRequest extends FormRequest
{
    private const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
        'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('nfe.tributacao.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id'     => ['required', 'integer', 'min:1'],
            'quantidade'     => ['required', 'numeric', 'gt:0', 'max:999999'],
            'valor_unitario' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'uf_destino'     => ['required', 'string', 'in:' . implode(',', self::UFS)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_id.required'     => 'Escolha um produto.',
            'quantidade.gt'           => 'A quantidade precisa ser maior que zero.',
            'valor_unitario.gt'       => 'O valor unitário precisa ser maior que zero.',
            'uf_destino.in'           => 'UF de destino inválida.',
        ];
    }
}
