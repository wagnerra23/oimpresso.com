<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * arquivos:recalcular-metadata — Pest tests Sprint 6 ADR 0123.
 *
 * Cobertura:
 * - Command registrado em artisan list
 * - --dry-run não escreve no DB
 * - --tag filtra por classified_by
 * - File ausente conta em missing_file (não erro fatal)
 * - File presente recalcula md5+size_bytes corretos
 * - Idempotente: rodar 2x não corrompe
 *
 * @see Modules/Arquivos/Console/Commands/RecalcularMetadataCommand.php
 */

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode Modules/Arquivos migrate primeiro');
    }
});

it('command arquivos:recalcular-metadata está registrado', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('arquivos:recalcular-metadata');
});

it('retorna 0 e mensagem "Nada pra processar" quando sem placeholders', function () {
    DB::table('arquivos')
        ->where('classified_by', 'like', 'backfill-test-pr11-%')
        ->delete();

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag' => ['backfill-test-pr11-empty'],
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Nada pra processar');
});

it('--dry-run não escreve no DB', function () {
    Storage::fake('local');
    Storage::disk('local')->put('test-pr11/file.txt', 'hello world content');

    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'    => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'  => 999,
        'disk'           => 'local',
        'storage_path'   => 'test-pr11/file.txt',
        'filename'       => 'file.txt',
        'mime_type'      => 'text/plain',
        'size_bytes'     => 0,
        'md5'            => 'placeholder-md5',
        'classified_by'  => 'backfill-test-pr11-dryrun',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag'     => ['backfill-test-pr11-dryrun'],
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0);

    $row = DB::table('arquivos')->where('id', $arquivoId)->first();
    expect($row->size_bytes)->toBe(0);
    expect($row->md5)->toBe('placeholder-md5');

    DB::table('arquivos')->where('id', $arquivoId)->delete();
});

it('recalcula md5 + size_bytes quando file presente', function () {
    Storage::fake('local');
    $content = 'pr11-test-content-' . uniqid();
    Storage::disk('local')->put('test-pr11/real.txt', $content);

    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'    => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'  => 998,
        'disk'           => 'local',
        'storage_path'   => 'test-pr11/real.txt',
        'filename'       => 'real.txt',
        'mime_type'      => 'text/plain',
        'size_bytes'     => 0,
        'md5'            => 'placeholder',
        'classified_by'  => 'backfill-test-pr11-real',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag' => ['backfill-test-pr11-real'],
    ]);

    expect($exitCode)->toBe(0);

    $row = DB::table('arquivos')->where('id', $arquivoId)->first();
    expect($row->size_bytes)->toBe(strlen($content));
    expect($row->md5)->toBe(md5($content));

    DB::table('arquivos')->where('id', $arquivoId)->delete();
});

it('é idempotente — rodar 2x não muda recalculados', function () {
    Storage::fake('local');
    $content = 'idempotent-' . uniqid();
    Storage::disk('local')->put('test-pr11/idem.txt', $content);

    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'    => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'  => 997,
        'disk'           => 'local',
        'storage_path'   => 'test-pr11/idem.txt',
        'filename'       => 'idem.txt',
        'mime_type'      => 'text/plain',
        'size_bytes'     => 0,
        'md5'            => 'placeholder',
        'classified_by'  => 'backfill-test-pr11-idem',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    Artisan::call('arquivos:recalcular-metadata', ['--tag' => ['backfill-test-pr11-idem']]);
    $afterFirst = DB::table('arquivos')->where('id', $arquivoId)->first();

    // Segunda run: query usa size_bytes=0 como filtro, então não pega de novo (já recalculado)
    Artisan::call('arquivos:recalcular-metadata', ['--tag' => ['backfill-test-pr11-idem']]);
    $afterSecond = DB::table('arquivos')->where('id', $arquivoId)->first();

    expect($afterSecond->size_bytes)->toBe($afterFirst->size_bytes);
    expect($afterSecond->md5)->toBe($afterFirst->md5);

    DB::table('arquivos')->where('id', $arquivoId)->delete();
});

it('conta missing_file quando file ausente no disk', function () {
    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'    => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'  => 996,
        'disk'           => 'local',
        'storage_path'   => 'test-pr11/inexistente-' . uniqid() . '.txt',
        'filename'       => 'inexistente.txt',
        'mime_type'      => 'text/plain',
        'size_bytes'     => 0,
        'md5'            => 'placeholder',
        'classified_by'  => 'backfill-test-pr11-missing',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $exitCode = Artisan::call('arquivos:recalcular-metadata', [
        '--tag' => ['backfill-test-pr11-missing'],
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('File ausente');

    $row = DB::table('arquivos')->where('id', $arquivoId)->first();
    expect($row->size_bytes)->toBe(0); // não atualizou

    DB::table('arquivos')->where('id', $arquivoId)->delete();
});
