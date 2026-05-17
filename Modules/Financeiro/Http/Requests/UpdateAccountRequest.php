<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra editar `App\Account` + complemento `fin_contas_bancarias`.
 *
 * Wave 18 RETRY D8 — 6° FormRequest tipado. Diferente de `StoreAccountRequest`
 * (criação completa), este edita conta existente — campos `name` opcionais
 * (preservar via Eloquent `fill` sem sobrescrever), `rb_gateway_credential_id`
 * permitido pra trocar gateway boleto.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` NUNCA aceito do request —
 * Controller valida via $account->business_id === session('user.business_id').
 *
 * @see Modules\Financeiro\Http\Requests\StoreAccountRequest
 * @see Modules\Financeiro\Http\Controllers\ContaBancariaController
 */
class UpdateAccountRequest extends FormRequest
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
            // Account base — todos sometimes (PATCH semantics)
            'name'             => ['sometimes', 'required', 'string', 'max:191'],
            'account_number'   => ['sometimes', 'nullable', 'string', 'max:191'],
            'account_type_id'  => ['sometimes', 'nullable', 'integer'],
            'note'             => ['sometimes', 'nullable', 'string', 'max:1000'],

            // Complemento
            'banco_codigo'              => ['sometimes', 'nullable', 'string', 'size:3'],
            'agencia'                   => ['sometimes', 'nullable', 'string', 'max:10'],
            'agencia_dv'                => ['sometimes', 'nullable', 'string', 'max:2'],
            'conta_dv'                  => ['sometimes', 'nullable', 'string', 'max:2'],
            'carteira'                  => ['sometimes', 'nullable', 'string', 'max:5'],
            'convenio'                  => ['sometimes', 'nullable', 'string', 'max:20'],
            'codigo_cedente'            => ['sometimes', 'nullable', 'string', 'max:20'],
            'beneficiario_documento'    => ['sometimes', 'nullable', 'string', 'max:18'],
            'beneficiario_razao_social' => ['sometimes', 'nullable', 'string', 'max:191'],
            'tipo_conta'                => ['sometimes', 'nullable', 'in:corrente,poupanca,virtual_pj'],
            'ativo_para_boleto'         => ['sometimes', 'nullable', 'boolean'],

            // Wiring com RecurringBilling gateway
            'rb_gateway_credential_id'  => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'Nome da conta nao pode ser vazio.',
            'banco_codigo.size'    => 'Codigo COMPE deve ter 3 digitos (077 Inter, 336 C6, 274 Asaas).',
            'tipo_conta.in'        => 'Tipo deve ser: corrente, poupanca ou virtual_pj.',
        ];
    }
}
