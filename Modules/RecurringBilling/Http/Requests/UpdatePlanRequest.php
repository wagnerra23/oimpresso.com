<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Wave 6 — FormRequest pra PlanController@update.
 *
 * US-RB-001 Cadastrar plano de assinatura (path de edição).
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - slug UNIQUE escopo per business_id, IGNORANDO o registro atual ({id}).
 *   - business_id NUNCA aceito via request — sempre da sessão.
 */
class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessId = (int) session('user.business_id');
        $planId = (int) $this->route('id');

        return [
            'name'            => ['required', 'string', 'max:150'],
            'slug'            => [
                'required', 'string', 'max:80',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('rb_plans', 'slug')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at')
                    ->ignore($planId),
            ],
            'description'     => ['nullable', 'string', 'max:2000'],
            'descricao_curta' => ['nullable', 'string', 'max:200'],
            'valor'           => ['required', 'numeric', 'min:0'],
            'ciclo'           => ['required', 'string', Rule::in(['monthly', 'quarterly', 'semiannual', 'yearly', 'custom'])],
            'ciclo_dias'      => ['nullable', 'required_if:ciclo,custom', 'integer', 'min:1', 'max:365'],
            'trial_days'      => ['nullable', 'integer', 'min:0', 'max:90'],
            'ativo'           => ['nullable', 'boolean'],
            'fiscal_type'     => ['nullable', 'string', Rule::in(['nfe', 'nfse', 'none'])],
            'fiscal_cfop'     => ['nullable', 'required_if:fiscal_type,nfe', 'string', 'max:8'],
            'fiscal_servico'  => ['nullable', 'required_if:fiscal_type,nfse', 'string', 'max:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique'             => 'Já existe outro plano com este slug nesta empresa.',
            'slug.regex'              => 'Slug deve conter apenas letras minúsculas, números e hifens.',
            'ciclo_dias.required_if'  => 'Ciclo customizado exige número de dias (1 a 365).',
            'fiscal_cfop.required_if' => 'CFOP é obrigatório quando fiscal_type=nfe.',
            'fiscal_servico.required_if' => 'Código de serviço é obrigatório quando fiscal_type=nfse.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = (string) $this->input('name', '');
        $slug = (string) $this->input('slug', '');

        $this->merge([
            'slug'        => $slug !== '' ? Str::slug($slug) : Str::slug($name),
            'ativo'       => $this->boolean('ativo'),
            'fiscal_type' => $this->input('fiscal_type') ?: 'none',
        ]);
    }
}
