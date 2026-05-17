<?php

declare(strict_types=1);

namespace Modules\Auditoria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra POST /auditoria/revert-bulk (undo em batch).
 *
 * Wave 18 RETRY D8.c: prepara endpoint pra revert em lote (auditor seleciona
 * N rows e justifica uma vez). Validação:
 *   - `activity_ids[]` array obrigatório, 1..50 ids (limite hard)
 *   - `revert_reason` obrigatório min:10 max:500 (mesma regra individual)
 *
 * Tier 0 (ADR 0093): RevertService valida que TODOS os IDs pertencem ao
 * business da session. Caller jamais confia no input pra cross-tenant.
 * Whitelist UNREVERTIBLE (5 categorias — ADR 0127) é aplicada por entry
 * dentro do Service, NÃO no FormRequest.
 */
class BulkRevertActivityRequest extends FormRequest
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

        return (bool) $user->can('auditoria.revert');
    }

    public function rules(): array
    {
        return [
            'activity_ids'    => ['required', 'array', 'min:1', 'max:50'],
            'activity_ids.*'  => ['required', 'integer', 'min:1'],
            'revert_reason'   => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'activity_ids.required' => 'Selecione ao menos 1 atividade para reverter.',
            'activity_ids.max'      => 'Máximo de 50 atividades por lote (proteção contra revert massivo).',
            'revert_reason.min'     => 'Justificativa deve ter no mínimo 10 caracteres.',
        ];
    }
}
