<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8 Security — Wave 17 saturação.
 *
 * FormRequest pra PATCH/PUT /ads/admin/projects/{id} (Admin\ProjectsController@update).
 * Todos os campos opcionais (partial update); whitelist alinhada com
 * ProjectService::doUpdate $allowed (defense-in-depth — service também filtra).
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id NÃO vem do payload — Service
 * re-scope na query DB com $this->businessId injetado no constructor.
 *
 * @see Modules\ProjectMgmt\Services\ProjectService::update
 */
class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission upstream
    }

    public function rules(): array
    {
        return [
            'nome'                => ['sometimes', 'string', 'max:200'],
            'objetivo_macro'      => ['sometimes', 'string', 'max:2000'],
            'status'              => ['sometimes', Rule::in(['draft', 'active', 'archived', 'completed', 'cancelled'])],
            'decision'            => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'on_hold'])],
            'owner'               => ['sometimes', 'string', 'max:50'],
            'viability_score'     => ['sometimes', 'integer', 'min:0', 'max:100'],
            'custo_estimado_brl'  => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'prazo_estimado_dias' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'          => 'Status inválido (allowed: draft, active, archived, completed, cancelled).',
            'decision.in'        => 'Decision inválido (allowed: pending, approved, rejected, on_hold).',
            'viability_score.between' => 'Viability score deve estar entre 0 e 100.',
        ];
    }
}
