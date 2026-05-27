<?php

declare(strict_types=1);

namespace App\Http\Requests\Cliente;

use App\Rules\BR\CpfCnpj;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação canon BR pra criação de cliente/fornecedor.
 *
 * Slice 7 — wira App\Rules\BR\CpfCnpj automaticamente em
 * App\Http\Controllers\ContactController@store(StoreContactRequest $request).
 *
 * Antes deste FormRequest:
 *   - `$request->only(['cpf_cnpj', 'indicador_ie', 'regime', ...])` aceitava qualquer string.
 *   - A Rule existia mas era zero-usada (investigação 2026-05-21).
 *
 * Depois:
 *   - cpf_cnpj passa por mod-11 SEFAZ (Eduardokum\LaravelBoleto\Util vendored).
 *   - indicador_ie só aceita 1 (contribuinte) / 2 (isento) / 9 (não contribuinte) per layout SEFAZ.
 *   - regime restrito a simples/presumido/real/mei.
 *
 * Authorize delega às permissions Spatie existentes — manter compat com
 * legacy abort(403) no controller (defensividade dupla).
 *
 * LGPD (ADR 0127): mensagens NÃO ecoam o valor recebido — só descrevem o gabarito.
 *
 * Refs:
 *   - memory/sessions/2026-05-21-investigar-campos-br-cliente.md
 *   - ADR 0093 (multi-tenant Tier 0)
 *   - ADR 0127 (LGPD redact em logs)
 */
class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('supplier.create')
            || $user->can('customer.create')
            || $user->can('customer.view_own')
            || $user->can('supplier.view_own');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Campos BR canon (Slice 1 migration 2026_05_21_140000).
            'cpf_cnpj' => ['nullable', new CpfCnpj],
            'rg' => ['nullable', 'string', 'max:20'],
            'inscricao_estadual' => ['nullable', 'string', 'max:30'],
            'inscricao_municipal' => ['nullable', 'string', 'max:30'],
            'indicador_ie' => ['nullable', 'integer', 'in:1,2,9'],
            'nome_fantasia' => ['nullable', 'string', 'max:150'],
            'consumidor_final' => ['nullable', 'boolean'],
            'contribuinte' => ['nullable', 'boolean'],
            'regime' => ['nullable', 'string', 'in:simples,presumido,real,mei'],
            'suframa' => ['nullable', 'string', 'max:20'],

            // Campos UPOS upstream — manter compat com $request->only() do controller.
            // Validação suave (nullable) — sem regressão pra fluxos legacy.
            'type' => ['nullable', 'string', 'in:customer,supplier,both,lead'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:120'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'landline' => ['nullable', 'string', 'max:50'],
            'alternate_number' => ['nullable', 'string', 'max:50'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'supplier_business_name' => ['nullable', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'contact_id' => ['nullable', 'string', 'max:255'],
            'customer_group_id' => ['nullable', 'integer'],
            'shipping_address' => ['nullable', 'string'],
            'position' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'string', 'max:32'],
            'pay_term_number' => ['nullable', 'integer'],
            'pay_term_type' => ['nullable', 'string', 'in:days,months'],
        ];
    }

    /**
     * Mensagens custom — sem echo do valor recebido (LGPD ADR 0127).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cpf_cnpj' => 'O CPF/CNPJ informado não é válido (verificação mod-11 SEFAZ).',
            'indicador_ie.in' => 'Indicador IE deve ser 1 (contribuinte), 2 (isento) ou 9 (não contribuinte).',
            'regime.in' => 'Regime tributário deve ser: simples, presumido, real ou mei.',
            'email.email' => 'Informe um e-mail válido.',
            'type.in' => 'Tipo de contato deve ser: customer, supplier, both ou lead.',
        ];
    }
}
