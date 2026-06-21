<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Governance\Services\Concerns\PersistsDriftAlert;
use Modules\Governance\Services\DriftFinding;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Escalonamento por persistência (Onda 1 — sentinela transporte) no trait
 * PersistsDriftAlert. Drift novo → severidade base. Drift ABERTO há > N dias →
 * severidade elevada (warn→high / high→critical) + flag escalated no metadata,
 * pra que `governance:audit --notify` dispare alerta ATIVO em vez de só log diário.
 *
 * ADITIVO/retrocompatível: caso comum não muda; reusa created_at — sem migration.
 */

/** Classe-isca que expõe o trait pra testar o método público. */
function persister(): object
{
    return new class
    {
        use PersistsDriftAlert;
    };
}

function findingHigh(): DriftFinding
{
    return new DriftFinding(
        target: 'mcp',
        target_type: 'env',
        severity: 'high',
        message: 'mcp serve commit X != main Y',
        evidence: ['served' => 'xxxxxxx', 'main' => 'yyyyyyy'],
    );
}

beforeEach(function () {
    config()->set('governance.drift_escalation_days', 3);
    // delete() (DML, transaction-safe) em vez de truncate() — TRUNCATE dá implicit
    // commit no MySQL e quebraria a isolação do DatabaseTransactions.
    DB::table('mcp_alertas_eventos')->delete();
});

it('alerta novo → severidade base, escalated=false', function () {
    $id = persister()->persistirDriftAlert('mcp_served_drift', findingHigh());

    $row = DB::table('mcp_alertas_eventos')->find($id);
    $meta = json_decode($row->metadata, true);

    expect($row->severidade)->toBe('high')           // high base → mapeia pra high
        ->and($meta['escalated'])->toBeFalse()
        ->and($row->titulo)->not->toContain('[ESCALADO]');
});

it('mesmo dia 2× → NÃO duplica (idempotência diária)', function () {
    $id1 = persister()->persistirDriftAlert('mcp_served_drift', findingHigh());
    $id2 = persister()->persistirDriftAlert('mcp_served_drift', findingHigh());

    expect($id2)->toBe($id1)
        ->and(DB::table('mcp_alertas_eventos')->count())->toBe(1);
});

it('drift ABERTO há > N dias → severidade elevada high→critical + escalated=true', function () {
    // Semeia uma ocorrência ABERTA de 5 dias atrás com a MESMA assinatura (mesmo
    // prefixo de chave) mas data antiga no sufixo — created_at controla a idade.
    $finding = findingHigh();
    $targetHash = substr(sha1($finding->target), 0, 12);
    $diaAntigo = now()->subDays(5)->format('Y-m-d');
    DB::table('mcp_alertas_eventos')->insert([
        'user_id' => null,
        'business_id' => null,
        'tipo' => 'drift_mcp_served_drift',
        'severidade' => 'high',
        'titulo' => 'Drift [mcp_served_drift] — mcp',
        'descricao' => 'antigo',
        'chave_idempotencia' => "drift_mcp_served_drift:{$finding->target_type}:{$targetHash}:{$diaAntigo}",
        'metadata' => json_encode(['escalated' => false]),
        'status' => 'aberto',
        'criado_em' => now()->subDays(5),
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    // Hoje o mesmo drift reaparece → deve escalar.
    $id = persister()->persistirDriftAlert('mcp_served_drift', $finding);

    $row = DB::table('mcp_alertas_eventos')->find($id);
    $meta = json_decode($row->metadata, true);

    expect($row->severidade)->toBe('critical')        // high escalado → critical
        ->and($meta['escalated'])->toBeTrue()
        ->and($meta['severity_efetiva'])->toBe('critical')
        ->and($meta['dias_aberto'])->toBeGreaterThan(3)
        ->and($row->titulo)->toContain('[ESCALADO]');
});

it('drift ABERTO há < N dias → ainda não escala', function () {
    $finding = findingHigh();
    $targetHash = substr(sha1($finding->target), 0, 12);
    $diaOntem = now()->subDays(1)->format('Y-m-d');
    DB::table('mcp_alertas_eventos')->insert([
        'user_id' => null,
        'business_id' => null,
        'tipo' => 'drift_mcp_served_drift',
        'severidade' => 'high',
        'titulo' => 'Drift [mcp_served_drift] — mcp',
        'descricao' => 'ontem',
        'chave_idempotencia' => "drift_mcp_served_drift:{$finding->target_type}:{$targetHash}:{$diaOntem}",
        'metadata' => json_encode(['escalated' => false]),
        'status' => 'aberto',
        'criado_em' => now()->subDays(1),
        'created_at' => now()->subDays(1),
        'updated_at' => now()->subDays(1),
    ]);

    $id = persister()->persistirDriftAlert('mcp_served_drift', $finding);

    $row = DB::table('mcp_alertas_eventos')->find($id);
    $meta = json_decode($row->metadata, true);

    expect($row->severidade)->toBe('high')
        ->and($meta['escalated'])->toBeFalse();
});
