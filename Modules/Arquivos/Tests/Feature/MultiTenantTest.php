<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Repair\Entities\JobSheet;

uses(Tests\TestCase::class);

/**
 * Isolamento multi-tenant Tier 0 do Modules/Arquivos.
 *
 * Contrato:
 * - ADR 0093 (business_id global scope; sessão de uma empresa nunca vê outra);
 * - ADR 0123 §174 "Dedupe via md5 DENTRO do mesmo business" — o mesmo conteúdo em duas
 *   empresas não pode reaproveitar linha nem blob da outra;
 * - SPEC US-ARQ-009 (Pest: multi-tenant isolation, dedupe, soft-delete, audit log).
 *
 * TENANT: 98 canônico × 99 vizinho (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 * Era 1×99 até 2026-11-16, com a limpeza DEPOIS do assert (vazava linha quando o assert
 * caía) e por `$arquivo->delete()`, que é soft-delete: deixava linha com deleted_at no
 * CT 100 a cada run. Agora toda fixture carrega md5 marcador e o afterEach de arquivo
 * apaga de verdade (DB::table, não o model).
 * NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente).
 */
const MT_MD5_98   = '98989898989898989898989898989898';
const MT_MD5_99   = '99999999999999999999999999999999';
const MT_MD5_AUTO = 'a98a98a98a98a98a98a98a98a98a98a9';
const MT_MD5_SO99 = 'b99b99b99b99b99b99b99b99b99b99b9';
const MT_CONTEUDO = 'conteudo-fixture-multitenant-dedupe-98x99';

function mtArquivo(int $businessId, string $md5, string $path): int
{
    return DB::table('arquivos')->insertGetId([
        'business_id'   => $businessId,
        'disk'          => 'arquivos',
        'storage_path'  => $path,
        'original_name' => basename($path),
        'mime_type'     => 'application/xml',
        'size_bytes'    => 100,
        'md5'           => $md5,
        'bucket'        => 'active',
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
}

function mtDono(int $id): JobSheet
{
    $dono = new JobSheet();
    $dono->id = $id;
    $dono->exists = true;

    return $dono;
}

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode migrate primeiro');
    }
});

afterEach(function () {
    session()->forget(['user', 'business']);

    if (! Schema::hasTable('arquivos')) {
        return;
    }
    $md5s = [MT_MD5_98, MT_MD5_99, MT_MD5_AUTO, MT_MD5_SO99, md5(MT_CONTEUDO)];
    $ids = DB::table('arquivos')->whereIn('md5', $md5s)->pluck('id');
    if ($ids->isNotEmpty() && Schema::hasTable('arquivos_audit_log')) {
        DB::table('arquivos_audit_log')->whereIn('arquivo_id', $ids)->delete();
    }
    DB::table('arquivos')->whereIn('md5', $md5s)->delete();
    if (Schema::hasTable('arquivos_dedupe')) {
        DB::table('arquivos_dedupe')->where('md5', md5(MT_CONTEUDO))->delete();
    }
});

it('global scope: sessão da 98 vê o arquivo da 98 e não vê o da 99 (e vice-versa)', function () {
    $biz = $this->seededTenant()->id;
    $vizinho = $this->seededSupportClientTenant()->id;
    $id98 = mtArquivo($biz, MT_MD5_98, "biz-{$biz}/mt.xml");
    $id99 = mtArquivo($vizinho, MT_MD5_99, "biz-{$vizinho}/mt.xml");

    session(['user' => ['business_id' => $biz]]);
    $vistos98 = Arquivo::query()->whereIn('md5', [MT_MD5_98, MT_MD5_99])->pluck('id')->all();

    session(['user' => ['business_id' => $vizinho]]);
    $vistos99 = Arquivo::query()->whereIn('md5', [MT_MD5_98, MT_MD5_99])->pluck('id')->all();

    // Controle positivo + negativo nos dois sentidos: cada sessão vê exatamente o seu.
    expect($vistos98)->toBe([$id98]);
    expect($vistos99)->toBe([$id99]);
});

it('find() por id de arquivo da 99 com sessão da 98 devolve null (com controle positivo)', function () {
    $biz = $this->seededTenant()->id;
    $vizinho = $this->seededSupportClientTenant()->id;
    $id98 = mtArquivo($biz, MT_MD5_98, "biz-{$biz}/mt-find.xml");
    $id99 = mtArquivo($vizinho, MT_MD5_99, "biz-{$vizinho}/mt-find.xml");

    session(['user' => ['business_id' => $biz]]);

    expect(Arquivo::find($id98))->not->toBeNull();
    expect(Arquivo::find($id99))->toBeNull();
});

it('creating preenche business_id da sessão', function () {
    $biz = $this->seededTenant()->id;
    session(['user' => ['business_id' => $biz]]);

    $arquivo = new Arquivo([
        'disk'          => 'arquivos',
        'storage_path'  => "biz-{$biz}/auto.xml",
        'original_name' => 'auto.xml',
        'mime_type'     => 'application/xml',
        'size_bytes'    => 50,
        'md5'           => MT_MD5_AUTO,
        'bucket'        => 'active',
    ]);
    $arquivo->save();

    expect($arquivo->business_id)->toBe($biz);
    expect(DB::table('arquivos')->where('id', $arquivo->id)->value('business_id'))->toBe($biz);
});

it('dedupe(md5, business) com o mesmo md5 nas duas empresas devolve a linha de cada uma', function () {
    $biz = $this->seededTenant()->id;
    $vizinho = $this->seededSupportClientTenant()->id;
    $id98 = mtArquivo($biz, MT_MD5_98, "biz-{$biz}/mesmo.xml");
    $id99 = mtArquivo($vizinho, MT_MD5_98, "biz-{$vizinho}/mesmo.xml");
    $so99 = mtArquivo($vizinho, MT_MD5_SO99, "biz-{$vizinho}/so99.xml");

    // Sem sessão: o global scope não filtra, quem isola é o where explícito do dedupe().
    session()->forget(['user', 'business']);
    $service = app(ArquivosService::class);

    expect($service->dedupe(MT_MD5_98, $biz)?->id)->toBe($id98);
    expect($service->dedupe(MT_MD5_98, $vizinho)?->id)->toBe($id99);
    // md5 que só existe na 99: a 98 não acha (negativo), a 99 acha (positivo).
    expect($service->dedupe(MT_MD5_SO99, $biz))->toBeNull();
    expect($service->dedupe(MT_MD5_SO99, $vizinho)?->id)->toBe($so99);
});

it('attach do mesmo conteúdo na 98 e na 99 gera linha e blob próprios em cada empresa', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only (lane arquivos-pest) — attach grava audit e dedupe.');
    }
    $biz = $this->seededTenant()->id;
    $vizinho = $this->seededSupportClientTenant()->id;

    // Discos que o serviço usa, lidos da config (padrão do DedupeMultiOwnerTest).
    Storage::fake(config('arquivos.disk_default', 'arquivos'));
    Storage::fake(config('arquivos.disk_vault', 'vault'));

    session(['user' => ['business_id' => $biz]]);
    $arq98 = mtDono(987650701)->attachArquivo(UploadedFile::fake()->createWithContent('doc.txt', MT_CONTEUDO));

    session(['user' => ['business_id' => $vizinho]]);
    $arq99 = mtDono(987650702)->attachArquivo(UploadedFile::fake()->createWithContent('doc.txt', MT_CONTEUDO));

    // Controle positivo: o 1º attach gravou na 98.
    expect((int) $arq98->business_id)->toBe($biz);
    expect($arq98->storage_path)->toStartWith("biz-{$biz}/");

    // A 99 não reaproveitou a linha nem o blob da 98.
    expect($arq99->id)->not->toBe($arq98->id);
    expect((int) $arq99->business_id)->toBe($vizinho);
    expect($arq99->storage_path)->toStartWith("biz-{$vizinho}/");
    expect($arq99->md5)->toBe($arq98->md5);
});

it('disks arquivos e vault configurados em config/filesystems', function () {
    expect(config('filesystems.disks.arquivos'))->toBeArray();
    expect(config('filesystems.disks.arquivos.driver'))->toBe('local');

    expect(config('filesystems.disks.vault'))->toBeArray();
    expect(config('filesystems.disks.vault.driver'))->toBe('local');
    expect(config('filesystems.disks.vault.throw'))->toBeTrue();
});

it('rota arquivos.download existe e exige middleware signed', function () {
    $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('arquivos.download');
    expect($route)->not->toBeNull();

    $middlewares = $route->gatherMiddleware();
    expect($middlewares)->toContain('signed');
    expect($middlewares)->toContain('auth');
});

it('Arquivo Model usa SoftDeletes', function () {
    $traits = (new ReflectionClass(Arquivo::class))->getTraitNames();
    expect($traits)->toContain('Illuminate\Database\Eloquent\SoftDeletes');
});

it('arquivos table tem business_id indexado', function () {
    expect(Schema::getColumnListing('arquivos'))->toContain('business_id');

    $indexes = collect(DB::select('SHOW INDEX FROM arquivos'))->pluck('Column_name')->toArray();
    expect($indexes)->toContain('business_id');
});

it('arquivos_audit_log tem business_id pra preservar isolamento em audit', function () {
    if (! Schema::hasTable('arquivos_audit_log')) {
        $this->markTestSkipped('arquivos_audit_log table missing');
    }

    expect(Schema::getColumnListing('arquivos_audit_log'))->toContain('business_id');
});
