<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateMetaRequest — atualização parcial de Meta via PATCH /jana/metas/{id}.
 *
 * D8.c (Wave 14 governance v3) — Controller@update fazia `only(['nome',
 * 'unidade', 'tipo_agregacao'])` sem validação alguma. Agora:
 *  - `sometimes` permite payload parcial (qualquer subset dos 3 campos)
 *  - whitelist explícita em unidade/tipo_agregacao (fail-secure)
 *  - PT-BR mensagens (UX)
 *
 * Autorização granular (só dono OU superadmin pode editar meta da plataforma)
 * fica no Controller via abort_unless — request só valida forma.
 */
class UpdateMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:150'],
            'unidade' => ['sometimes', 'required', Rule::in(['R$', 'qtd', '%', 'dias'])],
            'tipo_agregacao' => ['sometimes', 'required', Rule::in(['soma', 'media', 'ultimo', 'contagem'])],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da meta.',
            'unidade.in' => 'Unidade inválida. Use R$, qtd, % ou dias.',
            'tipo_agregacao.in' => 'Tipo de agregação inválido. Use soma, media, ultimo ou contagem.',
        ];
    }
}
