<?php

declare(strict_types=1);

/**
 * KbHealthSnapshotTest — régua derivada doc↔código no kb:health-check (2026-07-23).
 *
 * O health-check ganhou 3 checks DERIVADOS do trilho A1–D (code_drift_flagged /
 * code_nodes / code_edges) e a flag --snapshot que persiste 1 row/(business,dia)
 * em kb_health_history — a "evolução gravada" pedida por [W] (precedente
 * mcp_sdd_scorecard_history, ADR 0275 GT-G7). Cobre:
 *   - os 3 checks contam certo (assertados no JSON GRAVADO — testa métrica+sink juntos)
 *   - code_drift_flagged > 0 ⇒ status warn no check
 *   - idempotência: 2 runs no mesmo dia = 1 row (updateOrInsert no UNIQUE)
 *   - sem --snapshot não grava nada
 *   - multi-tenant Tier 0: snapshot biz=1 não cria row de biz=99 (ADR 0093/0101)
 *
 * sqlite-safe: kbBootstrapSchema roda todas migrations 2026_* (incl. kb_health_history).
 */

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
});

afterEach(function () {
    kbTeardownSchema();
});

/** Semeia o cenário doc↔código: 1 nó com drift ativo, 2 code-nodes, 1 code-edge. */
function kbSeedReguaDocCodigo(int $bizId): void
{
    // Nó com drift ativo (doc citando código morto — formato A1)
    DB::table('kb_nodes')->insert([
        'business_id' => $bizId,
        'type' => 'article',
        'slug' => "artigo-driftado-{$bizId}",
        'title' => 'Artigo com drift',
        'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'cita algo']]),
        'code_drift_state' => json_encode([
            'checked_at' => now()->toIso8601String(),
            'refs' => [['path' => 'Modules/Removed/X.php', 'drift_type' => 'reference_deleted_path']],
        ]),
        'status' => 'ok',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 2 code-nodes (kb:code-scan shape: type=reference, slug code-)
    $ids = [];
    foreach (['alpha', 'beta'] as $name) {
        $ids[] = (int) DB::table('kb_nodes')->insertGetId([
            'business_id' => $bizId,
            'type' => 'reference',
            'slug' => "code-app-sample-{$name}-{$bizId}",
            'title' => 'App\\Sample\\'.ucfirst($name),
            'is_editable' => true,
            'body_blocks' => json_encode([['kind' => 'para', 't' => 'Arquivo: x.php']]),
            'status' => 'ok',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // 1 code-edge (kb:code-graph shape)
    DB::table('kb_edges')->insert([
        'business_id' => $bizId,
        'from_node_id' => $ids[0],
        'to_node_id' => $ids[1],
        'edge_type' => 'references-data',
        'weight' => 1.0,
        'generated_by' => 'code_scan',
        'payload' => json_encode(['source' => 'code_scan', 'kind' => 'php-use']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('--snapshot grava a régua doc↔código com os valores derivados certos', function () {
    kbSeedReguaDocCodigo(1);

    $this->artisan('kb:health-check', ['--business-id' => 1, '--snapshot' => true, '--json' => true])
        ->assertExitCode(0);

    $row = DB::table('kb_health_history')
        ->where('business_id', 1)
        ->where('snapshot_date', now()->toDateString())
        ->first();

    expect($row)->not->toBeNull();

    $checks = json_decode((string) $row->checks, true);
    expect($checks['code_drift_flagged']['value'])->toBe(1);
    expect($checks['code_drift_flagged']['status'])->toBe('warn'); // >0 pede olho humano
    expect($checks['code_nodes']['value'])->toBe(2);
    expect($checks['code_edges']['value'])->toBe(1);
});

it('code_drift_flagged BATE com a contagem real do business + status coerente', function () {
    // Robusto a resíduo da lane sqlite compartilhada (mesmo processo :memory,
    // muitos arquivos): prova que o CHECK conta a verdade do banco por-business e
    // que o status segue a regra (>0 → warn, 0 → ok) — não depende de slate limpo.
    $real = (int) DB::table('kb_nodes')
        ->where('business_id', 1)->whereNull('deleted_at')->whereNotNull('code_drift_state')->count();

    $this->artisan('kb:health-check', ['--business-id' => 1, '--snapshot' => true, '--json' => true])
        ->assertExitCode(0);

    $checks = json_decode((string) DB::table('kb_health_history')->where('business_id', 1)->value('checks'), true);
    expect($checks['code_drift_flagged']['value'])->toBe($real);
    expect($checks['code_drift_flagged']['status'])->toBe($real > 0 ? 'warn' : 'ok');
});

it('é idempotente por dia: 2 runs = 1 row (updateOrInsert)', function () {
    kbSeedReguaDocCodigo(1);

    $this->artisan('kb:health-check', ['--business-id' => 1, '--snapshot' => true, '--json' => true])->assertExitCode(0);
    $this->artisan('kb:health-check', ['--business-id' => 1, '--snapshot' => true, '--json' => true])->assertExitCode(0);

    expect(DB::table('kb_health_history')->where('business_id', 1)->count())->toBe(1);
});

it('sem --snapshot não grava história (run só reporta)', function () {
    kbSeedReguaDocCodigo(1);

    $this->artisan('kb:health-check', ['--business-id' => 1, '--json' => true])->assertExitCode(0);

    expect(DB::table('kb_health_history')->count())->toBe(0);
});

it('multi-tenant Tier 0: health-check biz=1 conta SÓ biz=1, ignora o cenário de biz=99', function () {
    kbSeedReguaDocCodigo(99); // cenário (1 drift + 2 code-nodes + 1 edge) vive em biz=99

    // Contagens REAIS por business (robusto a resíduo da lane compartilhada).
    $b1Drift = (int) DB::table('kb_nodes')->where('business_id', 1)->whereNotNull('code_drift_state')->count();
    $b1Edges = (int) DB::table('kb_edges')->where('business_id', 1)->where('generated_by', 'code_scan')->count();

    $this->artisan('kb:health-check', ['--business-id' => 1, '--snapshot' => true, '--json' => true])->assertExitCode(0);

    // Rodar pra biz=1 NÃO cria snapshot de biz=99.
    expect(DB::table('kb_health_history')->where('business_id', 99)->count())->toBe(0);

    // O check de biz=1 = a verdade de biz=1 (NÃO soma o que foi semeado em biz=99).
    $checks = json_decode((string) DB::table('kb_health_history')->where('business_id', 1)->value('checks'), true);
    expect($checks['code_drift_flagged']['value'])->toBe($b1Drift);
    expect($checks['code_edges']['value'])->toBe($b1Edges);

    // Prova positiva do isolamento: o cenário SEMEADO em biz=99 existe (1 drift + 1 edge)
    // e é EXATAMENTE o que o check de biz=1 não contou.
    expect((int) DB::table('kb_nodes')->where('business_id', 99)->whereNotNull('code_drift_state')->count())->toBe(1);
    expect((int) DB::table('kb_edges')->where('business_id', 99)->where('generated_by', 'code_scan')->count())->toBe(1);
});
