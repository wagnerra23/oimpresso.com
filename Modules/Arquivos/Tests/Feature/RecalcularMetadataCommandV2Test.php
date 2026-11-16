<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * arquivos:recalcular-metadata — contrato da coluna metadata_recalculated_at (Sprint 7).
 *
 * Complementa RecalcularMetadataCommandTest. O que prova:
 * - após a run, a linha recalculada tem metadata_recalculated_at preenchido;
 * - a 2ª run não re-processa linha já marcada (idempotência pelo timestamp);
 * - linha com size_bytes>0 mas sem timestamp É re-processada (recalcular após mudar
 *   o algoritmo);
 * - --dry-run não preenche o timestamp.
 * Contrato: migration 2026_05_10_000030 (coluna de rastreio) + SPEC US-ARQ-020/023
 * (backfills que deixaram placeholder) + CHANGELOG 0.1.0.
 *
 * TENANT: 98 canônico (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 * Limpeza e reset do relógio no afterEach de arquivo: rodam mesmo quando um assert
 * falha no meio (antes ficavam depois do assert e vazavam linha e relógio congelado).
 * NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente).
 */
const RECALC_V2_BIZ = Tests\TestCase::SEEDED_TENANT_ID;
const RECALC_V2_TAG = 'backfill-test-l1-recalc-v2';

function recalcV2Arquivo(string $tag, string $path, int $sizeBytes, string $md5): int
{
    return DB::table('arquivos')->insertGetId([
        'business_id'              => RECALC_V2_BIZ,
        'arquivable_type'          => 'TestModelV2',
        'arquivable_id'            => 987650200,
        'disk'                     => 'local',
        'storage_path'             => $path,
        'original_name'            => basename($path),
        'mime_type'                => 'text/plain',
        'size_bytes'               => $sizeBytes,
        'md5'                      => $md5,
        'classified_by'            => $tag,
        'metadata_recalculated_at' => null,
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);
}

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode Modules/Arquivos migrate primeiro');
    }

    if (! Schema::hasColumn('arquivos', 'metadata_recalculated_at')) {
        $this->markTestSkipped('metadata_recalculated_at column missing — rode migration 000030 primeiro');
    }
});

afterEach(function () {
    Carbon::setTestNow();

    if (Schema::hasTable('arquivos')) {
        DB::table('arquivos')
            ->where('business_id', RECALC_V2_BIZ)
            ->where('classified_by', 'like', RECALC_V2_TAG.'%')
            ->delete();
    }
});

it('após run, rows recalculadas têm metadata_recalculated_at preenchido', function () {
    Storage::fake('local');
    $content = 'v2-test-content-'.uniqid();
    Storage::disk('local')->put('test-l1v2/recalc.txt', $content);

    $id = recalcV2Arquivo(RECALC_V2_TAG.'-recalc', 'test-l1v2/recalc.txt', 0, 'placeholder-v2');

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag' => [RECALC_V2_TAG.'-recalc'],
    ]);

    expect($exitCode)->toBe(0);

    $row = DB::table('arquivos')->where('id', $id)->first();
    expect($row->size_bytes)->toBe(strlen($content));
    expect($row->md5)->toBe(md5($content));
    expect($row->metadata_recalculated_at)->not->toBeNull('metadata_recalculated_at deve ser preenchido após recalc');
});

it('é idempotente via timestamp — 2ª run não re-processa rows já recalculadas', function () {
    Storage::fake('local');
    $content = 'v2-idem-'.uniqid();
    Storage::disk('local')->put('test-l1v2/idem.txt', $content);

    $id = recalcV2Arquivo(RECALC_V2_TAG.'-idem', 'test-l1v2/idem.txt', 0, 'placeholder-v2');

    Artisan::call('arquivos:recalcular-metadata', ['--tag' => [RECALC_V2_TAG.'-idem']]);
    $afterFirst = DB::table('arquivos')->where('id', $id)->first();
    expect($afterFirst->metadata_recalculated_at)->not->toBeNull();

    // Relógio 1s à frente: se a 2ª run re-processasse, o timestamp mudaria.
    Carbon::setTestNow(now()->addSecond());

    Artisan::call('arquivos:recalcular-metadata', ['--tag' => [RECALC_V2_TAG.'-idem']]);
    $afterSecond = DB::table('arquivos')->where('id', $id)->first();

    expect($afterSecond->metadata_recalculated_at)->toBe(
        $afterFirst->metadata_recalculated_at,
        'metadata_recalculated_at não deve mudar em 2ª run — row já marcada'
    );
    expect($afterSecond->md5)->toBe($afterFirst->md5);
    expect($afterSecond->size_bytes)->toBe($afterFirst->size_bytes);
});

it('re-processa rows com size_bytes>0 mas metadata_recalculated_at NULL (edge case pós-algoritmo)', function () {
    Storage::fake('local');
    $contentAntigo = 'conteudo-antigo';
    $contentNovo = 'conteudo-novo-v2-'.uniqid();
    Storage::disk('local')->put('test-l1v2/edge.txt', $contentNovo);

    // Linha que "parecia" ter metadata (size_bytes>0) mas nunca foi marcada.
    $id = recalcV2Arquivo(RECALC_V2_TAG.'-edge', 'test-l1v2/edge.txt', strlen($contentAntigo), md5($contentAntigo));

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag' => [RECALC_V2_TAG.'-edge'],
    ]);

    expect($exitCode)->toBe(0);

    $row = DB::table('arquivos')->where('id', $id)->first();
    expect($row->size_bytes)->toBe(strlen($contentNovo));
    expect($row->md5)->toBe(md5($contentNovo));
    expect($row->metadata_recalculated_at)->not->toBeNull('deve marcar timestamp mesmo no edge case');
});

// Título honesto: o ramo de fallback (coluna ausente) NÃO é exercitado aqui — dropar a
// coluna em runtime quebraria os demais testes da lane. O assert é sobre o TEXTO do
// comando: prova que os dois filtros estão escritos, não que o fallback funciona.
it('o código do comando declara os dois filtros (coluna de rastreio e fallback size_bytes=0)', function () {
    $commandContent = file_get_contents(
        base_path('Modules/Arquivos/Console/Commands/RecalcularMetadataCommand.php')
    );

    expect($commandContent)
        ->toContain("Schema::hasColumn('arquivos', 'metadata_recalculated_at')")
        ->toContain("where('size_bytes', 0)")
        ->toContain("whereNull('metadata_recalculated_at')");
});

it('--dry-run não preenche metadata_recalculated_at', function () {
    Storage::fake('local');
    Storage::disk('local')->put('test-l1v2/dryrun.txt', 'dry run content v2');

    $id = recalcV2Arquivo(RECALC_V2_TAG.'-dryrun', 'test-l1v2/dryrun.txt', 0, 'placeholder-dryrun');

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag'     => [RECALC_V2_TAG.'-dryrun'],
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0);

    $row = DB::table('arquivos')->where('id', $id)->first();
    expect($row->size_bytes)->toBe(0, '--dry-run não deve atualizar size_bytes');
    expect($row->md5)->toBe('placeholder-dryrun', '--dry-run não deve atualizar md5');
    expect($row->metadata_recalculated_at)->toBeNull('--dry-run não deve preencher metadata_recalculated_at');
});
