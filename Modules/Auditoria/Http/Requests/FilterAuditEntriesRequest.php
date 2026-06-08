<?php

declare(strict_types=1);

namespace Modules\Auditoria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra GET /auditoria (filtragem listing).
 *
 * Wave 18 D8.d: substitui `$request->all()` cru em `AuditoriaController::index`.
 * Whitelist alinhada a `AuditEntryService::ALLOWED_FILTERS` (colunas indexadas).
 *
 * Filtros aceitos:
 *   - causer_kind: 'user'|'ia'|null (3 valores possíveis no schema)
 *   - subject_type: FQCN da entidade auditada (string até 191 chars)
 *   - event: 'created'|'updated'|'deleted'|'reverted'
 *
 * NUNCA aceitar `business_id` no input (multi-tenant scope obrigatório via
 * session — ADR 0093). Filtros adicionais (date range, user_id) ficam pra
 * próximas waves após validação de UX.
 *
 * @see Modules\Auditoria\Services\AuditEntryService
 * @see memory/decisions/0127-modules-auditoria-ui-undo.md
 */
class FilterAuditEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'causer_kind'  => ['nullable', 'string', 'in:user,ia,system'],
            'subject_type' => ['nullable', 'string', 'max:191'],
            'event'        => ['nullable', 'string', 'in:created,updated,deleted,reverted,restored'],
            'page'         => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'causer_kind.in' => 'Origem da ação deve ser user, ia ou system.',
            'event.in'       => 'Evento deve ser created, updated, deleted, reverted ou restored.',
        ];
    }
}
