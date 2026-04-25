<?php

namespace Modules\Financeiro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\PlanoConta;

/**
 * Validação para CRUD de fin_categorias.
 *
 * Categorias são tags livres complementares ao plano de contas
 * (ex: "Marketing Digital Q4", "Comissão Vendedor Z"). Cada business
 * tem suas próprias categorias — nome único POR business.
 *
 * Pattern: ADR 0024 (FormRequest + Inertia).
 */
class UpsertCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $businessId = (int) $this->session()->get('user.business_id');
        $categoriaId = $this->route('id');

        return [
            'nome' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fin_categorias', 'nome')
                    ->where(fn ($q) => $q->where('business_id', $businessId)
                        ->whereNull('deleted_at'))
                    ->ignore($categoriaId),
            ],
            'cor' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'plano_conta_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($businessId) {
                    if ($value === null) {
                        return;
                    }
                    $exists = PlanoConta::query()
                        ->withoutGlobalScope(BusinessScopeImpl::class)
                        ->where('id', $value)
                        ->where('business_id', $businessId)
                        ->exists();

                    if (! $exists) {
                        $fail('Plano de contas inválido para este business.');
                    }
                },
            ],
            'tipo' => ['required', Rule::in(['receita', 'despesa', 'ambos'])],
            'ativo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'cor.regex' => 'A cor deve estar no formato hexadecimal #RRGGBB.',
            'nome.unique' => 'Já existe uma categoria com este nome.',
        ];
    }
}
