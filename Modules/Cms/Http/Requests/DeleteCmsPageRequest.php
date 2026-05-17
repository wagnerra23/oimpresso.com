<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DeleteCmsPageRequest — D8.c Security Wave 27 (2026-05-17).
 *
 * FormRequest dedicada pra delete (destroy) de CmsPage. Antes o Controller
 * destruía sem checar payload — agora valida `type` whitelisted (defesa em
 * profundidade) e exige sessão autenticada.
 *
 * Por que validar `type` no delete?
 *  - `CmsPageService::remover($id, $type)` filtra `where('type', $type)` na
 *    findOrFail — type errado vira ModelNotFoundException → 404 silencioso.
 *  - Atacante poderia tentar disparar destroy com type fora do whitelist
 *    pra mapear endpoints; aqui retornamos 422 com mensagem clara.
 *
 * Multi-tenant Tier 0 (ADR 0093): CmsPage usa global scope herdado quando
 * US-CMS-002 entregar; até lá page é GLOBAL (site público — não pode ter
 * `business_id` na tabela). Authorize ainda exige sessão pra evitar delete
 * anônimo via CSRF + endpoint público mal configurado.
 *
 * @see Modules\Cms\Services\CmsPageService::remover
 * @see Modules\Cms\Http\Controllers\CmsPageController
 */
class DeleteCmsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Sessão web autenticada — fina permissão fica em policy/middleware.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        return [
            // type vem como query string ou form input. Whitelist espelha
            // StoreCmsPageRequest pra simetria — qualquer type fora vira 422.
            'type' => ['nullable', 'string', Rule::in(['page', 'post', 'banner'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Tipo inválido pra remoção. Use page, post ou banner.',
        ];
    }
}
