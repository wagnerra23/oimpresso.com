<?php

namespace Modules\Admin\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * CuradorStatsReader — Widget W5 (Curador / Modules/Arquivos backbone).
 *
 * Lê tabelas do `Modules/Arquivos`:
 * - `arquivos` (count por bucket — sensitive/active/memory/etc)
 * - `arquivos_audit_log` (ações recentes 24h)
 * - `arquivos_dedupe` (dedupe rate global)
 *
 * Sprint 2 W5. Graceful fallback: tabelas ausentes → stub com instruções
 * pra rodar `php artisan migrate` no Modules/Arquivos.
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 * @see memory/decisions/0124-curador-conhecimento-pipeline.md
 */
class CuradorStatsReader
{
    public function fetch(): array
    {
        // D9.a OTel (Wave 17): span envolve 3 queries (by_bucket + audit_24h +
        // dedupe_stats) pra detectar slow DB. Zero-cost se otel.enabled=false.
        return OtelHelper::spanBiz('admin.curador_stats.fetch', function () {
        try {
            if (! Schema::hasTable('arquivos')) {
                return $this->stub('arquivos_table_missing');
            }

            $businessId = session('user.business_id') ?? session('business.id') ?? 1;

            // Count por bucket (multi-tenant Tier 0 preservado)
            $byBucket = DB::table('arquivos')
                ->select('bucket', DB::raw('COUNT(*) as total'))
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->toArray();

            $totalActive = array_sum($byBucket);
            $sensitiveCount = $byBucket['sensitive'] ?? 0;

            // Audit log 24h
            $audit24h = Schema::hasTable('arquivos_audit_log')
                ? DB::table('arquivos_audit_log')
                    ->select('action', DB::raw('COUNT(*) as total'))
                    ->where('business_id', $businessId)
                    ->where('created_at', '>=', now()->subDay())
                    ->groupBy('action')
                    ->pluck('total', 'action')
                    ->toArray()
                : [];

            // Dedupe global stats
            $dedupeStats = Schema::hasTable('arquivos_dedupe')
                ? DB::table('arquivos_dedupe')
                    ->select(
                        DB::raw('COUNT(*) as unique_md5'),
                        DB::raw('SUM(occurrences) as total_occurrences'),
                    )
                    ->first()
                : null;

            $dedupeRate = $dedupeStats && $dedupeStats->total_occurrences > 0
                ? round(
                    (1 - ($dedupeStats->unique_md5 / $dedupeStats->total_occurrences)) * 100,
                    1,
                )
                : 0;

            return [
                'available'         => true,
                'total_active'      => $totalActive,
                'by_bucket'         => $byBucket,
                'sensitive_count'   => $sensitiveCount,
                'audit_24h'         => $audit24h,
                'dedupe_rate_pct'   => $dedupeRate,
                'unique_md5'        => $dedupeStats->unique_md5 ?? 0,
                'total_occurrences' => $dedupeStats->total_occurrences ?? 0,
            ];
        } catch (\Throwable $e) {
            Log::warning('admin.widget.curador.error', ['error' => $e->getMessage()]);
            return $this->stub('exception:' . substr($e->getMessage(), 0, 120));
        }
        }, ['component' => 'admin.widget.w5']);
    }

    private function stub(string $reason): array
    {
        return [
            'available'         => false,
            'reason'            => $reason,
            'total_active'      => 0,
            'by_bucket'         => [],
            'sensitive_count'   => 0,
            'audit_24h'         => [],
            'dedupe_rate_pct'   => 0,
            'instructions'      => 'Rode: `php artisan migrate` em Modules/Arquivos OU instale via /arquivos/install',
        ];
    }
}
