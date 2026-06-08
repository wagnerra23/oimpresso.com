<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use Modules\Admin\Services\AdrAlertReader;
use Modules\Admin\Services\HealthSnapshotReader;
use Tests\TestCase;

/**
 * AdrAlertReaderTest — Wave 18 D2 SATURATION.
 *
 * Cobre os 4 caminhos do widget W4 (ADRs Tier 0 violados):
 *   1. snapshot indisponível → tier_0_alerts vazio
 *   2. checks all green       → tier_0_alerts vazio
 *   3. 1 check fail Tier 0    → 1 alert com adr correto
 *   4. check unknown não-Tier0 → ignorado
 *
 * Reusa HealthSnapshotReader mas com snapshot mocado via subclasse anônima
 * (mais simples que mock framework — service stateless).
 *
 * @see Modules\Admin\Services\AdrAlertReader
 * @see Modules\Admin\Services\HealthSnapshotReader
 */
class AdrAlertReaderTest extends TestCase
{
    private function makeReader(array $snapshot): AdrAlertReader
    {
        $health = new class($snapshot) extends HealthSnapshotReader {
            public function __construct(private array $forcedSnapshot) {}

            public function fetch(): array
            {
                return $this->forcedSnapshot;
            }
        };

        return new AdrAlertReader($health);
    }

    public function test_snapshot_indisponivel_retorna_alerts_vazio(): void
    {
        $reader = $this->makeReader([
            'available' => false,
            'reason'    => 'snapshot_missing',
        ]);

        $r = $reader->fetch();

        $this->assertFalse($r['available']);
        $this->assertSame('snapshot_missing', $r['reason']);
        $this->assertSame([], $r['tier_0_alerts']);
    }

    public function test_all_checks_green_retorna_alerts_vazio(): void
    {
        $reader = $this->makeReader([
            'available' => true,
            'checks'    => [
                ['name' => 'multi_tenant_isolation', 'status' => 'green', 'message' => 'ok'],
                ['name' => 'pii_leak_in_assistant_responses', 'status' => 'green', 'message' => 'ok'],
                ['name' => 'sql_slow_log', 'status' => 'yellow', 'message' => 'lento mas não-Tier 0'],
            ],
        ]);

        $r = $reader->fetch();

        $this->assertTrue($r['available']);
        $this->assertSame([], $r['tier_0_alerts']);
        $this->assertSame(0, $r['count']);
    }

    public function test_check_tier0_failed_gera_alert_com_adr_correto(): void
    {
        $reader = $this->makeReader([
            'available' => true,
            'checks'    => [
                ['name' => 'multi_tenant_isolation', 'status' => 'red',
                    'message' => 'Vazamento detectado', 'last_run' => '2026-05-16T10:00:00Z'],
                ['name' => 'pii_leak_in_assistant_responses', 'status' => 'green', 'message' => 'ok'],
            ],
        ]);

        $r = $reader->fetch();

        $this->assertTrue($r['available']);
        $this->assertCount(1, $r['tier_0_alerts']);
        $this->assertSame(1, $r['count']);

        $alert = $r['tier_0_alerts'][0];
        $this->assertSame('multi_tenant_isolation', $alert['check']);
        $this->assertSame('0093', $alert['adr']);
        $this->assertSame('red', $alert['status']);
        $this->assertSame('Vazamento detectado', $alert['message']);
    }

    public function test_check_nao_tier0_ignorado_mesmo_red(): void
    {
        $reader = $this->makeReader([
            'available' => true,
            'checks'    => [
                ['name' => 'qualquer_check_nao_canonico', 'status' => 'red', 'message' => 'algo'],
            ],
        ]);

        $r = $reader->fetch();

        $this->assertSame([], $r['tier_0_alerts']);
        $this->assertSame(0, $r['count']);
    }

    public function test_multiplos_tier0_failed_geram_multiplos_alerts(): void
    {
        $reader = $this->makeReader([
            'available' => true,
            'checks'    => [
                ['name' => 'multi_tenant_isolation', 'status' => 'red', 'message' => 'A'],
                ['name' => 'pii_leak_in_assistant_responses', 'status' => 'yellow', 'message' => 'B'],
                ['name' => 'mcp_audit_log_integrity', 'status' => 'red', 'message' => 'C'],
            ],
        ]);

        $r = $reader->fetch();

        $this->assertCount(3, $r['tier_0_alerts']);
        $this->assertSame(3, $r['count']);
    }
}
