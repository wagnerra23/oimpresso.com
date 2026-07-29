<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Services\Curador\CuradorStatsReader;

uses(Tests\TestCase::class);

/**
 * Contrato do CuradorStatsReader — resgatado de Modules/Admin/Services em
 * 2026-07-29 (depreciação do Admin Center). Sustenta a US-ARQ-018.
 *
 * POR QUE ESTE TESTE EXISTE:
 * o caso que veio junto (`Modules/Admin/Tests/Feature/AdapterTest.php`) só
 * afirmava `toHaveKey('total_active')` — passava com a tabela ausente, com o
 * reader em stub, e com o `where('business_id')` deletado. Não media o que o
 * reader tem de Tier 0, que é o recorte por business (ADR 0093). Este mede.
 *
 * ONDE ELE RODA DE VERDADE: lane `arquivos-pest.yml` (MySQL real semeado com
 * biz=1 e biz=2). Na lane genérica SQLite os casos Tier 0 pulam com razão
 * explícita — é o mesmo idioma do AuditLogCommandTest vizinho. Skip sai exit 0,
 * então "0 failed" aqui não prova execução: a prova está no JUnit da lane MySQL.
 *
 * Regras da lane (allowlist-catraca): DB::table() + cleanup no afterEach, SEM
 * RefreshDatabase/migrate:fresh — eles dropam o schema e limpam o biz=1 seedado.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/Arquivos/SPEC.md US-ARQ-018
 */
const CURADOR_STATS_MD5_FIXTURE = 'curadorstatsreadertest0000000000';

/** Pula o caso quando a lane não tem o schema MySQL semeado. */
function curadorStatsPrecisaDeMysql(): bool
{
    return DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('arquivos');
}

function curadorStatsSemearArquivo(int $businessId, string $bucket): void
{
    DB::table('arquivos')->insert([
        'business_id'   => $businessId,
        'disk'          => 'local',
        'storage_path'  => 'fixture/curador-stats-reader.bin',
        'original_name' => 'curador-stats-reader.bin',
        'mime_type'     => 'application/octet-stream',
        'size_bytes'    => 1,
        'md5'           => CURADOR_STATS_MD5_FIXTURE,
        'bucket'        => $bucket,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
}

afterEach(function () {
    // afterEach roda mesmo em caso pulado (tearDown do PHPUnit) — bail antes do
    // DELETE, senão estoura na lane sem schema.
    if (curadorStatsPrecisaDeMysql()) {
        return;
    }

    DB::table('arquivos')->where('md5', CURADOR_STATS_MD5_FIXTURE)->delete();
});

it('TIER 0 — conta só os arquivos do business da sessão, nunca os do vizinho', function () {
    curadorStatsSemearArquivo(1, 'active');
    curadorStatsSemearArquivo(1, 'sensitive');
    curadorStatsSemearArquivo(2, 'active');       // vizinho — NÃO pode aparecer
    curadorStatsSemearArquivo(2, 'sensitive');    // vizinho — NÃO pode aparecer

    session(['user.business_id' => 1]);
    $resultado = (new CuradorStatsReader())->fetch();

    // Pré-condição anti-vácuo: se caiu no stub, TODO assert abaixo passaria por
    // ausência de leitura, não por acerto (§5 2026-07-24).
    expect($resultado['available'])->toBeTrue();

    // as 4 linhas semeadas existem de fato...
    expect(DB::table('arquivos')->where('md5', CURADOR_STATS_MD5_FIXTURE)->count())->toBe(4);

    // ...e mesmo assim o reader só pode enxergar as do biz=1.
    // Se o `where('business_id')` sumir, este assert quebra.
    expect($resultado['sensitive_count'])->toBe(
        (int) DB::table('arquivos')
            ->where('business_id', 1)
            ->where('bucket', 'sensitive')
            ->whereNull('deleted_at')
            ->count()
    );
})->skip(
    fn () => curadorStatsPrecisaDeMysql(),
    'Tier 0 cross-tenant precisa do schema MySQL semeado — cobertura real na lane arquivos-pest.yml'
);

it('TIER 0 — trocar o business da sessão troca o recorte', function () {
    curadorStatsSemearArquivo(2, 'sensitive');

    session(['user.business_id' => 1]);
    $comoBiz1 = (new CuradorStatsReader())->fetch();

    session(['user.business_id' => 2]);
    $comoBiz2 = (new CuradorStatsReader())->fetch();

    expect($comoBiz1['available'])->toBeTrue();
    expect($comoBiz2['available'])->toBeTrue();

    // o mesmo arquivo do biz=2 conta pro 2 e não conta pro 1
    expect($comoBiz2['sensitive_count'])->toBeGreaterThan($comoBiz1['sensitive_count']);
})->skip(
    fn () => curadorStatsPrecisaDeMysql(),
    'Tier 0 cross-tenant precisa do schema MySQL semeado — cobertura real na lane arquivos-pest.yml'
);

it('fetch() nunca lança, mesmo sem as tabelas do Curador', function () {
    // roda em QUALQUER lane — é o contrato de degradação graceful que o widget
    // do Admin dependia e que segue valendo pro próximo consumidor.
    $resultado = (new CuradorStatsReader())->fetch();

    expect($resultado)->toHaveKey('available');
    expect($resultado)->toHaveKey('by_bucket');
    expect($resultado)->toHaveKey('sensitive_count');
});
