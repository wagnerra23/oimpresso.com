<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreSugestaoRequest — criação manual de Sugestao via Jana ou admin.
 *
 * D8.c (Wave 18 SATURATION) — Sugestao normalmente nasce automaticamente via
 * SuggestionEngine (quando user pergunta meta inexistente, Jana sugere
 * configuração). Este request valida criação MANUAL via POST direto pelo
 * admin (ex: cliente B2B importa sugestões pré-cadastradas).
 *
 * Multi-tenant Tier 0 (ADR 0093): Sugestao herda business_id via Conversa
 * (BelongsToBusinessViaParent). Controller deve resolver conversa_id e
 * verificar matching.
 *
 * @see Modules/Jana/Services/SuggestionEngine.php (caminho automático)
 * @see Modules/Jana/Entities/Sugestao.php
 */
class StoreSugestaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'conversa_id'   => ['required', 'integer', 'exists:jana_conversas,id'],
            'tipo'          => ['sometimes', Rule::in(['meta', 'alerta', 'periodo', 'fonte', 'outro'])],
            'payload_json'  => ['required', 'array'],
            'payload_json.nome'         => ['required', 'string', 'max:150'],
            'payload_json.descricao'    => ['sometimes', 'nullable', 'string', 'max:1000'],
            'payload_json.unidade'      => ['sometimes', 'nullable', Rule::in(['R$', 'qtd', '%', 'dias'])],
            'status'        => ['sometimes', Rule::in(['pendente', 'aprovada', 'rejeitada', 'expirada'])],
            'metadata'      => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'conversa_id.exists' => 'A conversa especificada não existe.',
            'payload_json.required' => 'O payload da sugestão é obrigatório.',
            'payload_json.nome.required' => 'O nome da sugestão é obrigatório.',
            'payload_json.unidade.in' => 'Unidade inválida. Use R$, qtd, % ou dias.',
        ];
    }
}
