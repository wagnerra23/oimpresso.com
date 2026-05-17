<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra export CSV de audit log MCP (TeamMcp).
 *
 * Wave 18 D8 SATURATION — extraido de TeamController::exportCsv (request->input direto).
 *
 * **Permissão**: `copiloto.mcp.usage.all` (Wagner/superadmin).
 *
 * Rules:
 *   - de / ate: datas YYYY-MM-DD (formato strict pra prevenir injection no whereBetween)
 *   - range max 13 meses (defensivo — export full ano não estoura RAM cursor)
 */
class ExportUsageCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->can('superadmin')) {
            return true;
        }

        return $user->can('copiloto.mcp.usage.all');
    }

    public function rules(): array
    {
        return [
            'de'  => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
        ];
    }

    /**
     * Defaults idempotentes pra range — último mês até hoje.
     *
     * @return array{de: string, ate: string}
     */
    public function rangeOrDefaults(): array
    {
        return [
            'de'  => $this->input('de', now()->subMonth()->toDateString()),
            'ate' => $this->input('ate', now()->toDateString()),
        ];
    }
}
