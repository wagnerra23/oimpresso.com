<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Governance\Services\SddBriefLineService;

/**
 * Tests pra linha SDD do Daily Brief (GT-G8 — ADR 0275 + plano SDD §2 GARANTIDA).
 * Regra: linha SÓ aparece quando (a) composta mudou vs último snapshot OU
 * (b) métrica armada regrediu / fonte vermelha (alerts não-vazio).
 *
 * @see Modules/Governance/Services/SddBriefLineService.php
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */

uses(RefreshDatabase::class);

function sddG8Snapshot(string $date, ?float $composta, array $alerts = [], int $vivas = 2): void
{
    DB::table('mcp_sdd_scorecard_history')->insert([
        'snapshot_date' => $date,
        'composta'      => $composta,
        'payload'       => json_encode([
            'composta'      => $composta,
            'composta_k'    => 2,
            'vivas'         => $vivas,
            'metrics_total' => 10,
            'alerts'        => $alerts,
            'scorecard'     => ['metrics' => []],
        ]),
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
}

function sddG8Brief(): string
{
    return "## ESTADO MACRO\n- x\n\n## EM VOO AGORA\n- x\n\n## DECISÕES RECENTES (24h)\n- x\n\n"
        ."## SKILLS USO 7d\n- x\n\n## CHARTERS APODRECENDO\n—\n\n## FLAGS\n- 🟢 Migration aging: ok\n\n"
        ."## METADATA\n- Gerado: hoje\n---END---";
}

it('sem snapshot nenhum → null (brief intacto)', function () {
    $svc = new SddBriefLineService();

    expect($svc->line())->toBeNull()
        ->and($svc->inject(sddG8Brief()))->toBe(sddG8Brief());
});

it('1º snapshot conta como mudança — linha 🟡 com Δ— e X/10 vivas', function () {
    sddG8Snapshot('2026-06-12', 12.5);

    $line = (new SddBriefLineService())->line();

    expect($line)->toContain('🟡 SDD: composta 12,5 (Δ—) · 2/10 vivas')
        ->and($line)->not->toContain('alerta:');
});

it('composta estável e zero alertas → null (sem ruído diário)', function () {
    sddG8Snapshot('2026-06-11', 12.5);
    sddG8Snapshot('2026-06-12', 12.5);

    expect((new SddBriefLineService())->line())->toBeNull();
});

it('composta mudou → linha 🟡 com Δ assinado', function () {
    sddG8Snapshot('2026-06-11', 12.5);
    sddG8Snapshot('2026-06-12', 14.0);

    expect((new SddBriefLineService())->line())
        ->toContain('🟡 SDD: composta 14,0 (Δ+1,5) · 2/10 vivas');
});

it('alerta com composta estável → linha 🔴 com nome da métrica (+N quando vários)', function () {
    sddG8Snapshot('2026-06-11', 12.5);
    sddG8Snapshot('2026-06-12', 12.5, alerts: [
        'ghost_count: 27 → 30 (armada — só pode descer)',
        'front_door_coverage: fonte vermelha (media no baseline, não mediu agora)',
    ]);

    expect((new SddBriefLineService())->line())
        ->toContain('🔴 SDD: composta 12,5 (Δ0,0) · 2/10 vivas · alerta: ghost_count +1');
});

it('kill-switch OFF → inject vira no-op mesmo com alerta vermelho', function () {
    config(['governance.sdd_brief_line' => false]);
    sddG8Snapshot('2026-06-12', 12.5, alerts: ['ghost_count: 27 → 30 (armada — só pode descer)']);

    expect((new SddBriefLineService())->inject(sddG8Brief()))->toBe(sddG8Brief());
});

it('inject coloca a linha como 1º bullet da seção FLAGS sem quebrar o markdown', function () {
    sddG8Snapshot('2026-06-12', 12.5, alerts: ['ghost_count: 27 → 30 (armada — só pode descer)']);

    $out = (new SddBriefLineService())->inject(sddG8Brief());

    expect($out)->toContain("## FLAGS\n- 🔴 SDD: composta 12,5")
        ->and($out)->toContain('- 🟢 Migration aging: ok') // bullets originais preservados
        ->and(trim($out))->toEndWith('---END---');
});
