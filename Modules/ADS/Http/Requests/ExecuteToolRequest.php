<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 RETRY (meta 97 module-grade).
 *
 * FormRequest pra POST /ads/admin/tools/{name}/execute
 * (Admin\ToolsController@execute do TeamMcp — agregado em ADS pro Panel
 * Cognitive Control). Executa tool MCP server-side com payload arbitrário.
 *
 * Defense-in-depth crítico: tools MCP podem disparar LLMs (custo) ou
 * mutações DB. `params` é JSON arbitrário (validado pela tool downstream),
 * mas limitamos tamanho + timeout aqui.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` extraído da session pelo
 * Controller — tool recebe `$businessId` no constructor.
 *
 * @see Modules\TeamMcp\Http\Controllers\Admin\ToolsController::execute
 * @see memory/decisions/0053-mcp-server-governanca-como-produto.md
 */
class ExecuteToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'params'  => ['sometimes', 'array'],
            'params.*' => ['nullable'],
            'timeout' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'params.array'    => 'Campo `params` deve ser objeto JSON.',
            'timeout.integer' => 'Timeout deve ser inteiro.',
            'timeout.min'     => 'Timeout mínimo é 1 segundo.',
            'timeout.max'     => 'Timeout máximo é 120 segundos.',
            'dry_run.boolean' => 'Campo `dry_run` deve ser booleano (true=simulate, false=execute).',
        ];
    }
}
