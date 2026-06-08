<?php

declare(strict_types=1);

namespace Modules\Auditoria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra POST /auditoria/{activityId}/notes (anotação interna).
 *
 * Wave 18 D8.e: prepara endpoint pra `Modules\Auditoria\Entities\AuditNote`
 * (já criada em US-AUDIT-007). Permite ao auditor escrever nota livre PT-BR
 * (max 5000 chars) sobre uma entry de `activity_log` (contexto offline tipo
 * "cliente pediu por email", "auditor externo solicitou clarificação").
 *
 * Diferença de `revert_reason`:
 *   - `revert_reason` é single-line obrigatório no momento do undo
 *   - `audit_note` é multi-line opcional, append-only via LogsActivity
 *
 * Tier 0 (ADR 0093): business_id NUNCA vem do input — vem de session no
 * Controller que persiste. PII redaction acontece no Service antes de save
 * (campo `note` pode ter dados sensíveis residuais — `LogOptions` evita logar
 * `note` no activity_log secundário).
 *
 * @see Modules\Auditoria\Entities\AuditNote
 */
class StoreAuditNoteRequest extends FormRequest
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

        // Permission Spatie — `auditoria.view` mínimo + `auditoria.note.write`
        // específica (será cadastrada via seeder quando endpoint formalizar).
        return (bool) ($user->can('auditoria.view') || $user->can('auditoria.note.write'));
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
            'note.required' => 'A nota de auditoria é obrigatória.',
            'note.min'      => 'A nota precisa ter no mínimo 3 caracteres.',
            'note.max'      => 'A nota é limitada a 5000 caracteres.',
        ];
    }
}
