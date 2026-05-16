<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreMetaRequest — criação de Meta via POST /jana/metas.
 *
 * D8.c (Wave 14 governance v3) — substitui `$request->validate([...])` inline
 * do MetasController@store, padroniza mensagens PT-BR e endurece regras:
 *  - business_id NULLABLE permite meta da plataforma (superadmin only)
 *  - slug regex `[a-z0-9_-]+` previne injection em rotas
 *  - whitelist explícita em unidade/tipo_agregacao (fail-secure)
 *
 * Multi-tenant Tier 0 (ADR 0093): se business_id veio no payload, controller
 * verifica que matcha session OU user é superadmin antes de persistir.
 *
 * Autorização: any auth user pode submeter; refinements (ex: só perfis com
 * `jana.metas.create`) ficam em policy posterior — request só valida forma.
 */
class StoreMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\-]+$/'],
            'nome' => ['required', 'string', 'max:150'],
            'unidade' => ['required', Rule::in(['R$', 'qtd', '%', 'dias'])],
            'tipo_agregacao' => ['required', Rule::in(['soma', 'media', 'ultimo', 'contagem'])],
            'business_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.required' => 'O slug é obrigatório.',
            'slug.regex' => 'O slug aceita apenas letras minúsculas, números, hífen e underline.',
            'nome.required' => 'Informe o nome da meta.',
            'unidade.in' => 'Unidade inválida. Use R$, qtd, % ou dias.',
            'tipo_agregacao.in' => 'Tipo de agregação inválido. Use soma, media, ultimo ou contagem.',
        ];
    }
}
