<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 RETRY (meta 97 module-grade).
 *
 * FormRequest pra POST /project-mgmt/board/{taskId}/watch
 * (BoardController@watch — PMG-006 ADR 0100). Adiciona watcher à task
 * pra receber notifications no Inbox.
 *
 * Pattern Jira watchers: default = user da session. Opcional `user_ids`
 * pra watch em nome de outros (admin only — Controller valida role).
 *
 * @see Modules\ProjectMgmt\Http\Controllers\BoardController::watch
 */
class WatchTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids'      => ['sometimes', 'array', 'max:20'],
            'user_ids.*'    => ['integer', 'min:1'],
            'notify_method' => ['sometimes', 'string', 'in:inbox,email,whatsapp,all'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.array'    => 'user_ids deve ser array de IDs.',
            'user_ids.max'      => 'Máximo 20 watchers por operação.',
            'notify_method.in'  => 'Método de notificação inválido (inbox, email, whatsapp, all).',
        ];
    }
}
