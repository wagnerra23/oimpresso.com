<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * arquivos:recalcular-metadata — contrato (Sprint 6).
 *
 * O que prova: as migrations de backfill (US-ARQ-020 · NFe; US-ARQ-023 · consumers)
 * gravaram linhas com md5/size_bytes de PLACEHOLDER. O comando relê o arquivo físico e
 * corrige os dois campos — sem escrever em --dry-run, contando arquivo ausente como
 * `missing_file` (não erro) e sem mexer de novo no que já recalculou.
 * Contrato: SPEC US-ARQ-020/023 + CHANGELOG 0.1.0 (comando listado entre os 7 artisan).
 *
 * TENANT: 98 canônico (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 * Limpeza no afterEach de arquivo, por marcador de classified_by — roda mesmo quando
 * um assert falha no meio (antes ela ficava depois do assert e vazava a linha).
 * NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente).
 */
const RECALC_V1_BIZ = Tests\TestCase::SEEDED_TENANT_ID;
const RECALC_V1_TAG = 'backfill-test-l1-recalc-v1';

function recalcV1Arquivo(string $tag, string $path, int $sizeBytes, string $md5): int
{
    return DB::table('arquivos')->insertGetId([
        'business_id'     => RECALC_V1_BIZ,
        'arquivable_type' => 'TestModel',
        'arquivable_id'   => 987650100,
        'disk'            => 'local',
        'storage_path'    => $path,
        'original_name'   => basename($path),
        'mime_type'       => 'text/plain',
        'size_bytes'      => $sizeBytes,
        'md5'             => $md5,
        'classified_by'   => $tag,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
}

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode Modules/Arquivos migrate primeiro');
    }
});

afterEach(function () {
    if (Schema::hasTable('arquivos')) {
        DB::table('arquivos')
            ->where('business_id', RECALC_V1_BIZ)
            ->where('classified_by', 'like', RECALC_V1_TAG.'%')
            ->delete();
    }
});

it('command arquivos:recalcular-metadata está registrado', function () {
    expect(Artisan::all())->toHaveKey('arquivos:recalcular-metadata');
});

it('retorna 0 e mensagem "Nada pra processar" quando a tag não tem linhas', function () {
    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag' => [RECALC_V1_TAG.'-vazia'],
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Nada pra processar');
});

it('--dry-run não escreve no DB', function () {
    Storage::fake('local');
    Storage::disk('local')->put('test-l1/file.txt', 'hello world content');

    $id = recalcV1Arquivo(RECALC_V1_TAG.'-dryrun', 'test-l1/file.txt', 0, 'placeholder-md5');

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag'     => [RECALC_V1_TAG.'-dryrun'],
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0);

    $row = DB::table('arquivos')->where('id', $id)->first();
    expect($row->size_bytes)->toBe(0);
    expect($row->md5)->toBe('placeholder-md5');
});

it('recalcula md5 + size_bytes quando o arquivo está no disco', function () {
    Storage::fake('local');
    $content = 'l1-test-content-'.uniqid();
    Storage::disk('local')->put('test-l1/real.txt', $content);

    $id = recalcV1Arquivo(RECALC_V1_TAG.'-real', 'test-l1/real.txt', 0, 'placeholder');

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag' => [RECALC_V1_TAG.'-real'],
    ]);

    expect($exitCode)->toBe(0);

    $row = DB::table('arquivos')->where('id', $id)->first();
    expect($row->size_bytes)->toBe(strlen($content));
    expect($row->md5)->toBe(md5($content));
});

it('é idempotente — rodar 2x não muda o que já foi recalculado', function () {
    Storage::fake('local');
    $content = 'idempotent-'.uniqid();
    Storage::disk('local')->put('test-l1/idem.txt', $content);

    $id = recalcV1Arquivo(RECALC_V1_TAG.'-idem', 'test-l1/idem.txt', 0, 'placeholder');

    Artisan::call('arquivos:recalcular-metadata', ['--tag' => [RECALC_V1_TAG.'-idem']]);
    $afterFirst = DB::table('arquivos')->where('id', $id)->first();

    // Controle: a 1ª run de fato recalculou (senão "não mudou" seria verde por vácuo).
    expect($afterFirst->md5)->toBe(md5($content));

    Artisan::call('arquivos:recalcular-metadata', ['--tag' => [RECALC_V1_TAG.'-idem']]);
    $afterSecond = DB::table('arquivos')->where('id', $id)->first();

    expect($afterSecond->size_bytes)->toBe($afterFirst->size_bytes);
    expect($afterSecond->md5)->toBe($afterFirst->md5);
});

it('conta missing_file quando o arquivo não está no disco', function () {
    Storage::fake('local');

    $id = recalcV1Arquivo(RECALC_V1_TAG.'-missing', 'test-l1/inexistente-'.uniqid().'.txt', 0, 'placeholder');

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag' => [RECALC_V1_TAG.'-missing'],
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('File ausente:  1');

    $row = DB::table('arquivos')->where('id', $id)->first();
    expect($row->size_bytes)->toBe(0);
});
