<?php

namespace Modules\Admin\Services;

use App\Util\OtelHelper;

/**
 * BrainBCostReader — Widget W10 (Custos Brain B 24h).
 *
 * Reusa HealthSnapshotReader (W2) filtrando check `custo_brain_b_24h`.
 * Sprint 2 MVP: snapshot file. Sprint 3+: tabela `jana_health_check_results`
 * com histórico (US-ADM-021 — Agent D 2026-05-10).
 *
 * Threshold alarme: R$ [redacted Tier 0]/dia (Wagner ajusta conforme uso ROTA LIVRE).
 * Status pintado server-side: green < 70%, yellow 70-100%, red > 100%.
 *
 * **D9.a Wave 14 (2026-05-16):** span `admin.brain_b.cost.read` envolve
 * fetch+parse pra correlacionar custo IA com latência de leitura. Zero-cost
 * se `otel.enabled=false`. Em CT 100 OTel ativo, dashboard mostra series
 * de custo Brain B por business_id (Tier 0 multi-tenant attribute auto-set).
 *
 * @see Modules/Admin/Services/HealthSnapshotReader.php
 * @see memory/decisions/0155-module-grade-v3-anti-injustica-na-justified.md D9.a
 */
class BrainBCostReader
{
    private const THRESHOLD_BRL_DAY = 500.0;

    public function __construct(
        protected HealthSnapshotReader $health,
    ) {}

    public function fetch(): array
    {
        return OtelHelper::spanBiz('admin.brain_b.cost.read', function () {
            return $this->fetchInner();
        }, ['component' => 'admin.widget.w10']);
    }

    private function fetchInner(): array
    {
        $snapshot = $this->health->fetch();
        if (! ($snapshot['available'] ?? false)) {
            return [
                'available'    => false,
                'reason'       => $snapshot['reason'] ?? 'snapshot_unavailable',
                'cost_brl_24h' => 0,
                'instructions' => 'Rode `php artisan jana:health-check --json > storage/app/jana-health-snapshot.json` ou aguarde scheduler.',
            ];
        }

        $check = collect($snapshot['checks'] ?? [])
            ->firstWhere('name', 'custo_brain_b_24h');

        if (! $check) {
            return [
                'available'    => false,
                'reason'       => 'check_not_in_snapshot',
                'cost_brl_24h' => 0,
                'instructions' => 'Health check `custo_brain_b_24h` não rodou. Verifique jana:health-check ativo.',
            ];
        }

        // Parse "R$ [redacted Tier 0]" ou "123.45" — tolerante
        $rawValue = $check['value'] ?? $check['message'] ?? '0';
        $cost = $this->parseBrl($rawValue);
        $threshold = (float) config('admin.brain_b_threshold_brl', self::THRESHOLD_BRL_DAY);
        $pct = $threshold > 0 ? ($cost / $threshold) * 100 : 0;

        $derivedStatus = $cost > $threshold ? 'red' : ($pct >= 70 ? 'yellow' : 'green');

        return [
            'available'    => true,
            'cost_brl_24h' => round($cost, 2),
            'threshold_brl' => $threshold,
            'pct_threshold' => round($pct, 1),
            'status'       => $check['status'] ?? $derivedStatus,
            'last_run'     => $check['last_run'] ?? $snapshot['generated_at'] ?? null,
        ];
    }

    private function parseBrl(string|float|int $raw): float
    {
        if (is_numeric($raw)) return (float) $raw;
        // "R$ [redacted Tier 0]" -> 123.45
        $clean = preg_replace('/[^\d,.\-]/', '', (string) $raw);
        $clean = str_replace(['.', ','], ['', '.'], $clean);
        return (float) $clean;
    }
}
