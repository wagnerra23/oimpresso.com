<?php

declare(strict_types=1);

namespace Modules\SRS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8.c Security Wave 17 — FormRequest extraído de InboxController::triage.
 *
 * Endpoint de triagem: muda status de uma DocEvidence (pending → triaged/applied/rejected).
 *
 * Whitelist de status alinhada com enum de domínio (DocEvidence::STATUS_*).
 *
 * Multi-tenant Tier 0: business_id vem da sessão; evidence é localizada por
 * `where('business_id', $businessId)->findOrFail($evidenceId)` no Controller.
 *
 * @see Modules\SRS\Http\Controllers\InboxController::triage
 */
class TriageEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['pending', 'triaged', 'applied', 'rejected', 'duplicate'])],
            'kind'   => ['nullable', Rule::in(['bug', 'rule', 'flow', 'quote', 'screenshot', 'decision'])],
            'module_target'      => ['nullable', 'string', 'max:64'],
            'suggested_story_id' => ['nullable', 'string', 'max:32'],
            'suggested_rule_id'  => ['nullable', 'string', 'max:32'],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status é obrigatório.',
            'status.in'       => 'Status deve ser: pending, triaged, applied, rejected ou duplicate.',
            'kind.in'         => 'Tipo deve ser: bug, rule, flow, quote, screenshot ou decision.',
            'notes.max'       => 'Notas devem ter no máximo 2000 caracteres.',
        ];
    }
}
