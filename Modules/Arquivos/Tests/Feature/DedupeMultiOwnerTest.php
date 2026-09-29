<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Repair\Entities\JobSheet;

uses(Tests\TestCase::class);

/**
 * Dedupe de STORAGE não pode custar o VÍNCULO do dono (ADR 0123 §API · US-ARQ-004).
 *
 * Contrato: `ArquivosService::attach($owner, $file)` anexa o arquivo AO DONO
 * (`HasArquivos::attachArquivo` → `$owner->arquivos()`). A dedupe por MD5 dentro do
 * business (ADR 0123 §174 — "Dedupe via md5 dentro do mesmo business") economiza
 * STORAGE; ela não autoriza devolver a linha de OUTRO dono. Antes do fix, anexar o
 * mesmo conteúdo a um 2º model devolvia a linha do 1º — `arquivable_*` seguia
 * apontando pro dono original e o 2º ficava sem arquivo.
 *
 * O caso "2× upload mesmo MD5 mesmo business retorna mesma row" (ADR 0123:262)
 * continua valendo quando o dono é o MESMO — é o controle do UC-ARQ-DEDUP-03.
 *
 * Tenant fictício 98 (ADR 0358 — nunca biz=4). Donos = JobSheet NÃO persistidos com
 * ids sentinela: `arquivos()` é morphMany por (type, id), não precisa da linha em
 * repair_job_sheets. MySQL-only (padrão da lane arquivos-pest); cleanup por md5.
 */

const DEDUP_MULTI_CONTEUDO = 'conteudo-fixture-dedupe-multi-owner-biz98';
const DEDUP_MULTI_BIZ      = 98;
const DEDUP_MULTI_DONO_A   = 987650098;
const DEDUP_MULTI_DONO_B   = 987650099;

function dedupMultiDono(int $id): JobSheet
{
    $dono = new JobSheet();
    $dono->id = $id;
    $dono->exists = true;

    return $dono;
}

function dedupMultiUpload(string $nome = 'foto-os.txt'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nome, DEDUP_MULTI_CONTEUDO);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only (lane arquivos-pest) — schema Arquivos + JobSheet UltimatePOS.');
    }
    if (! Schema::hasTable('arquivos') || ! Schema::hasTable('arquivos_audit_log') || ! Schema::hasTable('arquivos_dedupe')) {
        $this->markTestSkipped('Tabelas do Arquivos ausentes — rodar Modules/Arquivos migrate primeiro.');
    }

    // Fakear os discos que o SERVIÇO usa, lidos da config — não pelo nome suposto.
    // `arquivos.disk_default` é `local` (raiz public/uploads no UltimatePOS): fakear
    // só 'arquivos' deixou a 1ª rodada no CT 100 gravar em public/uploads real.
    Storage::fake(config('arquivos.disk_default', 'arquivos'));
    Storage::fake(config('arquivos.disk_vault', 'vault'));
    session(['user' => ['business_id' => DEDUP_MULTI_BIZ]]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('arquivos')) {
        return;
    }
    $md5 = md5(DEDUP_MULTI_CONTEUDO);
    $ids = DB::table('arquivos')->where('md5', $md5)->pluck('id');
    if ($ids->isNotEmpty()) {
        DB::table('arquivos_audit_log')->whereIn('arquivo_id', $ids)->delete();
    }
    DB::table('arquivos')->where('md5', $md5)->delete();
    DB::table('arquivos_dedupe')->where('md5', $md5)->delete();
});

it('UC-ARQ-DEDUP-01: mesmo conteúdo anexado a 2 donos — cada dono enxerga o seu arquivo', function () {
    $a = dedupMultiDono(DEDUP_MULTI_DONO_A);
    $b = dedupMultiDono(DEDUP_MULTI_DONO_B);

    $arqA = $a->attachArquivo(dedupMultiUpload());
    $arqB = $b->attachArquivo(dedupMultiUpload());

    expect($a->arquivos()->count())->toBe(1);
    expect($b->arquivos()->count())->toBe(1);
    expect($arqB->id)->not->toBe($arqA->id);
    expect((int) $arqB->arquivable_id)->toBe(DEDUP_MULTI_DONO_B);
    expect($arqB->arquivable_type)->toBe(JobSheet::class);
    // O 1º dono não foi "roubado" pelo 2º.
    expect((int) $arqA->fresh()->arquivable_id)->toBe(DEDUP_MULTI_DONO_A);
});

it('UC-ARQ-DEDUP-02: a dedupe de STORAGE se preserva — os 2 registros apontam pro mesmo blob, sem cópia em path novo', function () {
    $arqA = dedupMultiDono(DEDUP_MULTI_DONO_A)->attachArquivo(dedupMultiUpload());
    $arqB = dedupMultiDono(DEDUP_MULTI_DONO_B)->attachArquivo(dedupMultiUpload('outro-nome.txt'));

    expect($arqB->storage_path)->toBe($arqA->storage_path);
    expect($arqB->disk)->toBe($arqA->disk);
    expect($arqB->md5)->toBe($arqA->md5);
    expect($arqB->original_name)->toBe('outro-nome.txt');
    // Conta só os blobs DESTE conteúdo — independente do que mais houver no disco.
    $blobs = collect(Storage::disk($arqA->disk)->allFiles('biz-' . DEDUP_MULTI_BIZ))
        ->filter(fn (string $p) => str_contains($p, md5(DEDUP_MULTI_CONTEUDO)));
    expect($blobs)->toHaveCount(1);
});

it('UC-ARQ-DEDUP-03: o MESMO dono subindo o mesmo conteúdo 2× recebe a mesma linha (ADR 0123:262)', function () {
    $a = dedupMultiDono(DEDUP_MULTI_DONO_A);

    $primeiro = $a->attachArquivo(dedupMultiUpload());
    $segundo  = $a->attachArquivo(dedupMultiUpload());

    expect($segundo->id)->toBe($primeiro->id);
    expect($a->arquivos()->count())->toBe(1);
});

it('UC-ARQ-DEDUP-04: o vínculo do 2º dono gera audit upload apontando a linha de origem do blob', function () {
    $arqA = dedupMultiDono(DEDUP_MULTI_DONO_A)->attachArquivo(dedupMultiUpload());
    $arqB = dedupMultiDono(DEDUP_MULTI_DONO_B)->attachArquivo(dedupMultiUpload());

    $audit = DB::table('arquivos_audit_log')
        ->where('arquivo_id', $arqB->id)
        ->where('action', 'upload')
        ->first();

    expect($audit)->not->toBeNull();
    expect((int) $audit->business_id)->toBe(DEDUP_MULTI_BIZ);
    $payload = json_decode((string) $audit->payload, true);
    expect((int) ($payload['dedupe_de'] ?? 0))->toBe((int) $arqA->id);
});

it('UC-ARQ-DEDUP-05: purgar a linha de um dono NÃO apaga o blob que o outro dono ainda usa', function () {
    $arqA = dedupMultiDono(DEDUP_MULTI_DONO_A)->attachArquivo(dedupMultiUpload());
    $arqB = dedupMultiDono(DEDUP_MULTI_DONO_B)->attachArquivo(dedupMultiUpload());

    // Controle da pré-condição: sem o 2º registro não há o que proteger.
    expect($arqB->id)->not->toBe($arqA->id);

    DB::table('arquivos')->where('id', $arqA->id)->update(['deleted_at' => now()->subDays(400)]);

    Artisan::call('arquivos:retention-cleanup', [
        '--business' => DEDUP_MULTI_BIZ,
        '--days'     => 30,
    ]);

    expect(DB::table('arquivos')->where('id', $arqA->id)->exists())->toBeFalse();
    expect(Storage::disk($arqB->disk)->exists($arqB->storage_path))->toBeTrue();
});
