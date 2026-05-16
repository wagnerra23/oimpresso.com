<?php

namespace Modules\Admin\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BriefAdapter — adapter Widget W1 (Brief diário).
 *
 * Lê do canon: tabela `mcp_briefs` (Modules/Brief — ADR 0091).
 * Cache 5 min (TTL alinhado com brief-fetch tool).
 *
 * Graceful fallback: se tabela não existe ou nenhum brief recente,
 * retorna stub com `_unavailable: true` pro widget renderizar empty state.
 *
 * @see Modules/Brief/Mcp/Tools/BriefFetchTool
 */
class BriefAdapter
{
    public function fetch(): array
    {
        // D9.a OTel (Wave 17): span envolve Cache::remember pra detectar
        // cache-miss + latência DB. Zero-cost se otel.enabled=false.
        return OtelHelper::spanBiz('admin.brief_adapter.fetch', function () {
            return Cache::remember('admin.widget.brief', 300, function () {
            try {
                if (! \Schema::hasTable('mcp_briefs')) {
                    return $this->stub('table_missing');
                }

                $row = DB::table('mcp_briefs')
                    ->orderByDesc('created_at')
                    ->first();

                if (! $row) {
                    return $this->stub('no_briefs_yet');
                }

                return [
                    'available'  => true,
                    'brief_id'   => $row->id ?? null,
                    'created_at' => $row->created_at ?? null,
                    'markdown'   => $row->content ?? $row->markdown ?? '',
                    'token_estimate' => $row->token_estimate ?? null,
                ];
            } catch (\Throwable $e) {
                Log::warning('admin.widget.brief.error', ['error' => $e->getMessage()]);
                return $this->stub('exception:' . substr($e->getMessage(), 0, 120));
            }
            });
        }, ['component' => 'admin.widget.w1']);
    }

    private function stub(string $reason): array
    {
        return [
            'available' => false,
            'reason'    => $reason,
            'markdown'  => "# Brief indisponível\n\nMotivo: `{$reason}`.\n\nGerar manualmente: `php artisan brief:generate`",
        ];
    }
}
