<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Auditoria Arquivos 2026-11-05, lote L2 (D5) — trilha append-only e FK de empresa.
 * Só MySQL (trigger e FK não existem no sqlite): roda na lane arquivos-pest.
 * Tenant fictício 98 (ADR 0358); a empresa inexistente é um id muito alto, nunca criado.
 */
uses(Tests\TestCase::class);

const AAO_BIZ = 98;
const AAO_BIZ_INEXISTENTE = 987654321;
const AAO_MD5 = 'aa0aa0aa0aa0aa0aa0aa0aa0aa0aa0a5';

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('trigger e FK só existem no MySQL — lane arquivos-pest.');
    }
});

afterEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        return;
    }
    $ids = DB::table('arquivos')->where('md5', AAO_MD5)->pluck('id');
    // DELETE na trilha ainda é permitido (o BEFORE DELETE está pendente); UPDATE não.
    DB::table('arquivos_audit_log')->whereIn('arquivo_id', $ids)->delete();
    DB::table('arquivos')->where('md5', AAO_MD5)->delete();
});

function aaoArquivo(int $businessId): int
{
    return (int) DB::table('arquivos')->insertGetId([
        'business_id' => $businessId,
        'disk' => 'arquivos',
        'storage_path' => 'biz-' . $businessId . '/aao.txt',
        'original_name' => 'aao.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 3,
        'md5' => AAO_MD5,
        'bucket' => 'active',
        'encrypted' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('trilha aceita linha nova e recusa UPDATE', function () {
    $arquivoId = aaoArquivo(AAO_BIZ);
    $linha = DB::table('arquivos_audit_log')->insertGetId([
        'arquivo_id' => $arquivoId,
        'business_id' => AAO_BIZ,
        'action' => 'upload',
        'payload' => json_encode(['size' => 3]),
        'created_at' => now(),
    ]);

    expect(DB::table('arquivos_audit_log')->where('id', $linha)->value('action'))->toBe('upload');

    expect(fn () => DB::table('arquivos_audit_log')->where('id', $linha)->update(['action' => 'download']))
        ->toThrow(QueryException::class, 'append-only');

    expect(DB::table('arquivos_audit_log')->where('id', $linha)->value('action'))->toBe('upload');
});

it('arquivo de empresa existente grava (controle positivo da FK)', function () {
    expect(aaoArquivo(AAO_BIZ))->toBeGreaterThan(0);
});

it('arquivo de empresa inexistente é recusado pela FK', function () {
    expect(DB::table('business')->where('id', AAO_BIZ_INEXISTENTE)->exists())->toBeFalse();

    expect(fn () => aaoArquivo(AAO_BIZ_INEXISTENTE))->toThrow(QueryException::class);
});

it('linha da trilha de empresa inexistente é recusada pela FK', function () {
    $arquivoId = aaoArquivo(AAO_BIZ);

    expect(fn () => DB::table('arquivos_audit_log')->insert([
        'arquivo_id' => $arquivoId,
        'business_id' => AAO_BIZ_INEXISTENTE,
        'action' => 'upload',
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});
