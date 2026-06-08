<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 RETRY (meta 97 module-grade).
 *
 * FormRequest pra POST /project-mgmt/board/{taskId}/comment
 * (BoardController@addComment). Suporta @mentions inline (PMG-005 ADR 0100).
 *
 * Defense-in-depth: body limitado a 5000 chars (markdown longo permitido,
 * mas evita abuso) + mentions array (até 50 @user) + LGPD (sem PII real
 * em logs — Wagner/Felipe internos só).
 *
 * Multi-tenant Tier 0 (ADR 0093): taskId pertence a business_id session;
 * BoardController valida via McpTask global scope.
 *
 * @see Modules\ProjectMgmt\Http\Controllers\BoardController::addComment
 * @see memory/decisions/0100-projectmgmt-pmg004-007.md
 */
class AddCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission enforced via middleware `auth` + CheckUserLogin upstream.
        return true;
    }

    public function rules(): array
    {
        return [
            'body'        => ['required', 'string', 'min:1', 'max:5000'],
            'mentions'    => ['sometimes', 'array', 'max:50'],
            'mentions.*'  => ['string', 'max:50'],
            'parent_id'   => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Comentário não pode ser vazio.',
            'body.max'      => 'Comentário deve ter no máximo 5000 caracteres.',
            'mentions.max'  => 'Máximo 50 menções por comentário.',
            'parent_id.min' => 'ID do comentário pai inválido.',
        ];
    }
}
