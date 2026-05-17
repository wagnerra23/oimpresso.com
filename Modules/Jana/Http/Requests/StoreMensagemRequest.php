<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreMensagemRequest — persistência direta de Mensagem (admin/import).
 *
 * D8.c (Wave 18 SATURATION) — usado por endpoints administrativos que
 * persistem `jana_mensagens` sem passar pelo fluxo de chat normal (ex:
 * importação de histórico legacy, replay de teste, seed canon). Distingue-se
 * de SendChatMessageRequest (que só valida content do user no chat).
 *
 * Multi-tenant Tier 0 (ADR 0093): `conversa_id` é resolvido por route param;
 * Controller deve verificar conversa.business_id matcha session ANTES de
 * dispatch. Request só valida forma do payload.
 *
 * Append-only (config retention.php): jana_mensagens é append-only por design
 * — UPDATE NÃO é suportado (apenas hard_delete via LGPD purge). Por isso
 * existe apenas Store, sem Update.
 *
 * @see Modules/Jana/Config/retention.php (entities.mensagem = 1825d)
 * @see Modules/Jana/Http/Requests/SendChatMessageRequest.php (chat flow)
 */
class StoreMensagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'role'            => ['required', Rule::in(['user', 'assistant', 'system', 'tool'])],
            'content'         => ['required', 'string', 'max:50000'],
            'tokens_in'       => ['sometimes', 'nullable', 'integer', 'min:0'],
            'tokens_out'      => ['sometimes', 'nullable', 'integer', 'min:0'],
            'modelo'          => ['sometimes', 'nullable', 'string', 'max:80'],
            'custo_brl_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'latencia_ms'     => ['sometimes', 'nullable', 'integer', 'min:0'],
            'metadata'        => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in'          => 'Role inválido. Use user, assistant, system ou tool.',
            'content.required' => 'O conteúdo da mensagem é obrigatório.',
            'content.max'      => 'Conteúdo muito longo (máximo 50000 caracteres).',
        ];
    }
}
