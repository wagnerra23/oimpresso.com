<?php

declare(strict_types=1);

namespace Modules\Brief\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * MarkBriefValidRequest — Wave 25 D8 SECURITY.
 *
 * FormRequest pro endpoint admin `POST /brief/admin/{id}/mark-valid`
 * (reverter `InvalidateBriefRequest` quando review humana decide que o brief
 * inválidado por engano deve voltar a ser servível).
 *
 * Cenários:
 *   1. Admin invalidou brief X às 14h por suspeita de PII; review às 16h
 *      confirma que era falso positivo — restaura `valid=1`.
 *   2. Bug em job de purge marcou batch como inválido; admin restaura via
 *      este endpoint sem precisar regenerar.
 *
 * Multi-tenant Tier 0 ({@see ADR 0093}): Brief é repo-wide, permission
 * `brief.purge` (mesma de invalidate — operação simétrica).
 *
 * Audit: motivo gravado em `activity_log` evento `brief.marked_valid`
 * (auditor enxerga par invalidate→revalidate).
 *
 * @see Modules\Brief\Http\Requests\InvalidateBriefRequest (operação inversa)
 * @see memory/decisions/0091-daily-brief.md §schema valid flag
 */
class MarkBriefValidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('brief.purge');
    }

    public function rules(): array
    {
        return [
            // motivo: livre-texto pra audit (pareia com InvalidateBriefRequest).
            'motivo' => ['required', 'string', 'min:5', 'max:500'],

            // unmark_purge: se brief estava `mark_for_purge=true`, limpa flag.
            // Default true (se admin revalidou, normalmente quer manter no histórico).
            'unmark_purge' => ['nullable', 'boolean'],

            // refresh_cache: força `Cache::forget('brief.current')` pra próxima
            // tool MCP servir o brief revalidado se ele for o mais recente.
            'refresh_cache' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required'        => 'Motivo obrigatório (auditoria pareada com invalidate).',
            'motivo.min'             => 'Motivo deve ter ao menos 5 caracteres.',
            'motivo.max'             => 'Motivo deve ter no máximo 500 caracteres.',
            'unmark_purge.boolean'   => 'Campo unmark_purge deve ser booleano.',
            'refresh_cache.boolean'  => 'Campo refresh_cache deve ser booleano.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['unmark_purge', 'refresh_cache'] as $key) {
            if ($this->has($key)) {
                $merge[$key] = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN);
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
