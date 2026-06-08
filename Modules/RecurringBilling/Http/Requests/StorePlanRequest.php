<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

/**
 * Wave 6 — FormRequest pra PlanController@store.
 *
 * US-RB-001 Cadastrar plano de assinatura.
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - business_id NUNCA aceito via request — sempre da sessão.
 *   - slug UNIQUE escopo per business_id (não global).
 *
 * Authorize() retorna true — gate (permission/superadmin) fica no Controller
 * pra centralizar lógica num único ponto (skill multi-tenant-patterns).
 */
class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gate (permission/superadmin/multi-tenant) centralizado no PlanController.
        // FormRequest sempre permite quando rota web carrega auth middleware —
        // testes Pest podem instanciar via ::create() sem auth user.
        return true;
    }

    public function rules(): array
    {
        $businessId = (int) session('user.business_id');

        return [
            'name'            => ['required', 'string', 'max:150'],
            'slug'            => [
                'required', 'string', 'max:80',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('rb_plans', 'slug')->where('business_id', $businessId)->whereNull('deleted_at'),
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
            'name.required'           => 'Nome do plano é obrigatório.',
            'slug.required'           => 'Slug é obrigatório (será derivado do nome se vazio).',
            'slug.unique'             => 'Já existe um plano com este slug nesta empresa.',
            'slug.regex'              => 'Slug deve conter apenas letras minúsculas, números e hifens.',
            'valor.required'          => 'Valor é obrigatório.',
            'valor.min'                => 'Valor não pode ser negativo.',
            'ciclo.in'                => 'Ciclo deve ser monthly, quarterly, semiannual, yearly ou custom.',
            'ciclo_dias.required_if'  => 'Ciclo customizado exige número de dias (1 a 365).',
            'trial_days.max'          => 'Trial pode ter no máximo 90 dias.',
            'fiscal_type.in'          => 'Fiscal type deve ser nfe, nfse ou none.',
            'fiscal_cfop.required_if' => 'CFOP é obrigatório quando fiscal_type=nfe.',
            'fiscal_servico.required_if' => 'Código de serviço é obrigatório quando fiscal_type=nfse.',
        ];
    }

    /**
     * Auto-slug a partir do name se não enviado.
     * Normaliza booleano `ativo` (checkbox HTML pode mandar string).
     */
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
