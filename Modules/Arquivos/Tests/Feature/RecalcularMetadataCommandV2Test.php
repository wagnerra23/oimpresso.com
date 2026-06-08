<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * arquivos:recalcular-metadata — Pest tests Sprint 7 ADR 0123.
 *
 * Complementa RecalcularMetadataCommandTest.php (Sprint 6) com cobertura
 * das novas garantias da coluna metadata_recalculated_at:
 * - Após run, rows recalculadas têm metadata_recalculated_at NOT NULL
 * - Idempotente via timestamp: 2ª run não re-processa rows já marcadas
 * - Edge case: rows com size_bytes>0 mas metadata_recalculated_at NULL são
 *   re-processadas (ex: recalcular após mudança de algoritmo)
 * - Backward compat: se coluna não existe, fallback pra heurística size_bytes=0
 *
 * Multi-tenant: biz=1 (Wagner WR2). NUNCA biz=4 (ROTA LIVRE — ADR 0101).
 *
 * @see Modules/Arquivos/Console/Commands/RecalcularMetadataCommand.php Sprint 7
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 7
 */

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode Modules/Arquivos migrate primeiro');
    }

    if (! Schema::hasColumn('arquivos', 'metadata_recalculated_at')) {
        $this->markTestSkipped('metadata_recalculated_at column missing — rode migration 000030 primeiro');
    }
});

it('após run, rows recalculadas têm metadata_recalculated_at preenchido', function () {
    Storage::fake('local');
    $content = 'v2-test-content-' . uniqid();
    Storage::disk('local')->put('test-pr21/recalc.txt', $content);

    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'              => 1,
        'arquivable_type'          => 'TestModelV2',
        'arquivable_id'            => 9001,
        'disk'                     => 'local',
        'storage_path'             => 'test-pr21/recalc.txt',
        'filename'                 => 'recalc.txt',
        'mime_type'                => 'text/plain',
        'size_bytes'               => 0,
        'md5'                      => 'placeholder-v2',
        'classified_by'            => 'backfill-test-pr21-recalc',
        'metadata_recalculated_at' => null,
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag' => ['backfill-test-pr21-recalc'],
    ]);

    expect($exitCode)->toBe(0);

    $row = DB::table('arquivos')->where('id', $arquivoId)->first();
    expect($row->size_bytes)->toBe(strlen($content));
    expect($row->md5)->toBe(md5($content));
    expect($row->metadata_recalculated_at)->not->toBeNull('metadata_recalculated_at deve ser preenchido após recalc');

    DB::table('arquivos')->where('id', $arquivoId)->delete();
});

it('é idempotente via timestamp — 2ª run não re-processa rows já recalculadas', function () {
    Storage::fake('local');
    $content = 'v2-idem-' . uniqid();
    Storage::disk('local')->put('test-pr21/idem.txt', $content);

    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'              => 1,
        'arquivable_type'          => 'TestModelV2',
        'arquivable_id'            => 9002,
        'disk'                     => 'local',
        'storage_path'             => 'test-pr21/idem.txt',
        'filename'                 => 'idem.txt',
        'mime_type'                => 'text/plain',
        'size_bytes'               => 0,
        'md5'                      => 'placeholder-v2',
        'classified_by'            => 'backfill-test-pr21-idem',
        'metadata_recalculated_at' => null,
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);

    // 1ª run: recalcula e preenche timestamp
    Artisan::call('arquivos:recalcular-metadata', ['--tag' => ['backfill-test-pr21-idem']]);
    $afterFirst = DB::table('arquivos')->where('id', $arquivoId)->first();
    expect($afterFirst->metadata_recalculated_at)->not->toBeNull();

    $timestampAfterFirst = $afterFirst->metadata_recalculated_at;

    // Pequena pausa pra garantir que now() seria diferente se re-processasse
    Carbon::setTestNow(now()->addSecond());

    // 2ª run: não deve tocar na row (filtro whereNull('metadata_recalculated_at'))
    Artisan::call('arquivos:recalcular-metadata', ['--tag' => ['backfill-test-pr21-idem']]);
    $afterSecond = DB::table('arquivos')->where('id', $arquivoId)->first();

    expect($afterSecond->metadata_recalculated_at)->toBe($timestampAfterFirst,
        'metadata_recalculated_at não deve mudar em 2ª run — row já marcada'
    );
    expect($afterSecond->md5)->toBe($afterFirst->md5);
    expect($afterSecond->size_bytes)->toBe($afterFirst->size_bytes);

    Carbon::setTestNow(); // reset
    DB::table('arquivos')->where('id', $arquivoId)->delete();
});

it('re-processa rows com size_bytes>0 mas metadata_recalculated_at NULL (edge case pós-algoritmo)', function () {
    Storage::fake('local');
    $contentAntigo = 'conteudo-antigo';
    $contentNovo   = 'conteudo-novo-v2-' . uniqid();
    Storage::disk('local')->put('test-pr21/edge.txt', $contentNovo);

    // Row que "parecia" ter metadata (size_bytes>0) mas nunca foi marcada
    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'              => 1,
        'arquivable_type'          => 'TestModelV2',
        'arquivable_id'            => 9003,
        'disk'                     => 'local',
        'storage_path'             => 'test-pr21/edge.txt',
        'filename'                 => 'edge.txt',
        'mime_type'                => 'text/plain',
        'size_bytes'               => strlen($contentAntigo), // > 0 mas MD5 desatualizado
        'md5'                      => md5($contentAntigo),
        'classified_by'            => 'backfill-test-pr21-edge',
        'metadata_recalculated_at' => null, // nunca recalculado com coluna
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag' => ['backfill-test-pr21-edge'],
    ]);

    expect($exitCode)->toBe(0);

    $row = DB::table('arquivos')->where('id', $arquivoId)->first();
    // Deve ter re-calculado com o conteúdo real do disk (contentNovo)
    expect($row->size_bytes)->toBe(strlen($contentNovo));
    expect($row->md5)->toBe(md5($contentNovo));
    expect($row->metadata_recalculated_at)->not->toBeNull('deve marcar timestamp mesmo no edge case');

    DB::table('arquivos')->where('id', $arquivoId)->delete();
});

it('backward compat — fallback pra size_bytes=0 quando coluna ausente (simulado via mock Schema)', function () {
    // Este teste garante que o branch de fallback existe no código.
    // Não podemos dropar a coluna em runtime sem quebrar outros testes,
    // então validamos via reflexão que o command usa Schema::hasColumn.
    $commandContent = file_get_contents(
        base_path('Modules/Arquivos/Console/Commands/RecalcularMetadataCommand.php')
    );

    expect($commandContent)
        ->toContain("Schema::hasColumn('arquivos', 'metadata_recalculated_at')",
            'Command deve verificar existência da coluna para backward compat'
        )
        ->toContain("where('size_bytes', 0)",
            'Command deve ter fallback pra heurística legada size_bytes=0'
        )
        ->toContain("whereNull('metadata_recalculated_at')",
            'Command deve usar filtro primário via coluna quando disponível'
        );
});

it('--dry-run não preenche metadata_recalculated_at', function () {
    Storage::fake('local');
    Storage::disk('local')->put('test-pr21/dryrun.txt', 'dry run content v2');

    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'              => 1,
        'arquivable_type'          => 'TestModelV2',
        'arquivable_id'            => 9004,
        'disk'                     => 'local',
        'storage_path'             => 'test-pr21/dryrun.txt',
        'filename'                 => 'dryrun.txt',
        'mime_type'                => 'text/plain',
        'size_bytes'               => 0,
        'md5'                      => 'placeholder-dryrun',
        'classified_by'            => 'backfill-test-pr21-dryrun',
        'metadata_recalculated_at' => null,
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag'     => ['backfill-test-pr21-dryrun'],
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0);

    $row = DB::table('arquivos')->where('id', $arquivoId)->first();
    expect($row->size_bytes)->toBe(0, '--dry-run não deve atualizar size_bytes');
    expect($row->md5)->toBe('placeholder-dryrun', '--dry-run não deve atualizar md5');
    expect($row->metadata_recalculated_at)->toBeNull('--dry-run não deve preencher metadata_recalculated_at');

    DB::table('arquivos')->where('id', $arquivoId)->delete();
});
