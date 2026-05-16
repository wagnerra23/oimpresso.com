<?php

namespace Modules\Admin\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Storage;

/**
 * HealthSnapshotReader — Widget W2 (5 SQL health checks).
 *
 * Lê snapshot de `storage/app/jana-health-snapshot.json` (gerado por job
 * scheduled `admin:refresh-snapshot` cada 15 min — Sprint 1 dia 4).
 *
 * Sprint 1 MVP: snapshot file. Migration `jana_health_check_results` é
 * Sprint 2 (US-ADM-021 — Agent D 2026-05-10 propôs opção C como melhor
 * relação custo/benefício pra MVP).
 *
 * Graceful fallback: snapshot ausente → stub vermelho com instruções.
 *
 * **D9.a Wave 14 (2026-05-16):** span `admin.health.snapshot.read`
 * envolve leitura+parse. Zero-cost se `otel.enabled=false` (default
 * Hostinger). Em CT 100 com OTel collector ativo, exporta tracing
 * pra detectar slow I/O do Storage local.
 *
 * @see memory/decisions/0155-module-grade-v3-anti-injustica-na-justified.md D9.a
 * @see app\Util\OtelHelper
 */
class HealthSnapshotReader
{
    private const SNAPSHOT_PATH = 'jana-health-snapshot.json';

    public function fetch(): array
    {
        return OtelHelper::spanBiz('admin.health.snapshot.read', function () {
            return $this->fetchInner();
        }, ['component' => 'admin.widget.w2']);
    }

    private function fetchInner(): array
    {
        if (! Storage::disk('local')->exists(self::SNAPSHOT_PATH)) {
            return $this->stub('snapshot_missing');
        }

        $raw = Storage::disk('local')->get(self::SNAPSHOT_PATH);
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return $this->stub('snapshot_invalid_json');
        }

        $checks = $data['checks'] ?? [];
        $tier0Failures = array_filter($checks, function ($check) {
            $name   = $check['name'] ?? '';
            $status = $check['status'] ?? 'unknown';
            return in_array($name, ['multi_tenant_isolation', 'pii_leak_in_assistant_responses'], true)
                && $status !== 'green';
        });

        return [
            'available'        => true,
            'generated_at'     => $data['generated_at'] ?? null,
            'checks'           => array_values($checks),
            'tier_0_failures'  => array_values($tier0Failures),
            'overall_status'   => count($tier0Failures) > 0 ? 'red' : 'green',
        ];
    }

    private function stub(string $reason): array
    {
        return [
            'available'       => false,
            'reason'          => $reason,
            'overall_status'  => 'unknown',
            'checks'          => [],
            'tier_0_failures' => [],
            'instructions'    => 'Rode: `php artisan jana:health-check --json > storage/app/jana-health-snapshot.json`',
        ];
    }
}
