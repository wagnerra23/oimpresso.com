<?php

namespace Modules\Admin\Services;

use App\Util\OtelHelper;

/**
 * AdrAlertReader — Widget W4 (ADRs Tier 0 violados).
 *
 * Reusa `HealthSnapshotReader` filtrando checks que mapeiam pra ADRs
 * Tier 0 (irrevogáveis): multi_tenant_isolation (ADR 0093), pii_leak
 * (ADR 0094 §princípio 7), profile_distiller_drift (ADR 0094).
 *
 * Top-bar vermelha quando >0 falhas. Click → drill-down do check.
 *
 * D9.a OTel (Wave 17): span `admin.adr_alert.fetch` rastreia contagem
 * de Tier 0 alerts pra alarme drift. Zero-cost se otel.enabled=false.
 */
class AdrAlertReader
{
    /** Map check_name → ADR canônico violado. */
    private const TIER_0_MAP = [
        'multi_tenant_isolation'           => '0093',
        'pii_leak_in_assistant_responses'  => '0094',
        'profile_distiller_drift'          => '0094',
        'mcp_audit_log_integrity'          => '0053',
        'centrifugo_runtime_separation'    => '0062',
    ];

    public function __construct(
        protected HealthSnapshotReader $health,
    ) {}

    public function fetch(): array
    {
        return OtelHelper::spanBiz('admin.adr_alert.fetch', function () {
            $snapshot = $this->health->fetch();

            if (! ($snapshot['available'] ?? false)) {
                return [
                    'available'      => false,
                    'reason'         => $snapshot['reason'] ?? 'snapshot_unavailable',
                    'tier_0_alerts'  => [],
                ];
            }

            $alerts = [];
            foreach ($snapshot['checks'] ?? [] as $check) {
                $name   = $check['name'] ?? '';
                $status = $check['status'] ?? 'unknown';
                if (isset(self::TIER_0_MAP[$name]) && $status !== 'green') {
                    $alerts[] = [
                        'check'    => $name,
                        'adr'      => self::TIER_0_MAP[$name],
                        'status'   => $status,
                        'message'  => $check['message'] ?? '',
                        'last_run' => $check['last_run'] ?? null,
                    ];
                }
            }

            return [
                'available'      => true,
                'tier_0_alerts'  => $alerts,
                'count'          => count($alerts),
            ];
        }, ['component' => 'admin.widget.w4']);
    }
}
