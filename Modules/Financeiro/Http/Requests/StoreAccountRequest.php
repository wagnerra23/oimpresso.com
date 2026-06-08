<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar nova `App\Account` + complemento `fin_contas_bancarias`.
 *
 * Wave 17 D8 — diferente de `UpsertContaBancariaRequest` (que só edita
 * complemento). Este request é pra criação completa: name + número + tipo +
 * complemento opcional.
 *
 * NÃO modifica tabela core `accounts` diretamente sem bridge (proibições.md):
 *  - Controller usa Account::create() (camada UltimatePOS suporta extensão
 *    desde que `business_id` seja preenchido — sem alterar SCHEMA).
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` vem de session, NUNCA do request.
 *
 * @see Modules\Financeiro\Http\Controllers\ContaBancariaController
 * @see Modules\Financeiro\Http\Requests\UpsertContaBancariaRequest
 * @see memory/proibicoes.md "Não modificar tabelas core UltimatePOS"
 */
class StoreAccountRequest extends FormRequest
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
            // Account base (UltimatePOS core)
            'name'             => ['required', 'string', 'max:191'],
            'account_number'   => ['nullable', 'string', 'max:191'],
            'account_type_id'  => ['nullable', 'integer'],
            'note'             => ['nullable', 'string', 'max:1000'],

            // Complemento fin_contas_bancarias (opcional na criação)
            'banco_codigo'     => ['nullable', 'string', 'size:3'],
            'agencia'          => ['nullable', 'string', 'max:10'],
            'agencia_dv'       => ['nullable', 'string', 'max:2'],
            'conta_dv'         => ['nullable', 'string', 'max:2'],
            'tipo_conta'       => ['nullable', 'in:corrente,poupanca,virtual_pj'],
            'ativo_para_boleto' => ['nullable', 'boolean'],

            // Sinalização explícita: se true, cria complemento mesmo com banco_codigo null
            'criar_complemento' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'Nome da conta e obrigatorio.',
            'banco_codigo.size'   => 'Codigo COMPE deve ter exatamente 3 digitos (ex 077 Inter).',
            'tipo_conta.in'       => 'Tipo deve ser: corrente, poupanca ou virtual_pj.',
        ];
    }
}
