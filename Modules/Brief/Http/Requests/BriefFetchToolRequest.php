<?php

namespace Modules\Brief\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra tool MCP `brief-fetch` (BriefFetchController@__invoke).
 *
 * Endpoint POST /api/mcp/tools/brief-fetch — protegido por middleware
 * `mcp.auth` (token Bearer ja validado upstream) + throttle:60,1.
 *
 * Responsabilidade DESTE FormRequest:
 *   - Validar o payload `force_refresh` (unico input do body)
 *   - Validar header X-MCP-Agent-Id (formato sao)
 *
 * Responsabilidade NAO COBERTA AQUI (preservada upstream):
 *   - Validacao do token Bearer — middleware mcp.auth (ADR 0053)
 *   - Cap 8/dia force_refresh — guardForceRefresh() no Controller
 *   - Restricao force_refresh=true a agents Wagner — guardForceRefresh()
 *
 * D8.c Security — Wave S Batch 2.
 *
 * @see Modules\Brief\Http\Controllers\BriefFetchController
 * @see memory/decisions/0091-daily-brief.md
 * @see memory/decisions/0053-mcp-server-governanca-como-produto.md
 */
class BriefFetchToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth (token Bearer + scopes) eh feita pelo middleware mcp.auth — nao
        // duplicar aqui. Esta camada so valida formato do payload.
        return true;
    }

    public function rules(): array
    {
        return [
            'force_refresh' => ['nullable', 'boolean'],

            // Header X-MCP-Agent-Id — Laravel valida header via prefixo 'headers.'
            // mas FormRequest classico usa rules() em body apenas. Header check
            // fica no Controller que ja le ->header('X-MCP-Agent-Id', 'unknown').
        ];
    }

    public function messages(): array
    {
        return [
            'force_refresh.boolean' => 'Campo force_refresh deve ser booleano.',
        ];
    }

    /**
     * Coerce force_refresh string ("true"/"1") pra bool real.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('force_refresh')) {
            $raw = $this->input('force_refresh');
            $this->merge([
                'force_refresh' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
