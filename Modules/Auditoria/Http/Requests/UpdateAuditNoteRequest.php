<?php

declare(strict_types=1);

namespace Modules\Auditoria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra PATCH /auditoria/notes/{id} (editar nota de auditoria).
 *
 * Wave 18 RETRY D8.b: complemento ao `StoreAuditNoteRequest`. Permite auditor
 * corrigir typo ou clarificar nota recente (append-only via LogsActivity — a
 * mudança fica registrada). NÃO permite trocar `activity_id` ou `user_id`
 * (audit trail dura).
 *
 * Tier 0 (ADR 0093): authorize valida que user é dono da nota OU superadmin.
 * business_id NUNCA chega no input — vem de session no Controller.
 */
class UpdateAuditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if ($user->can('superadmin')) {
            return true;
        }

        return (bool) ($user->can('auditoria.note.write') || $user->can('auditoria.view'));
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'A nota é obrigatória.',
            'note.min'      => 'A nota precisa ter no mínimo 3 caracteres.',
            'note.max'      => 'A nota é limitada a 5000 caracteres.',
        ];
    }
}
