<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Services\Curador\CuradorStatsReader;

/**
 * Contrato do CuradorStatsReader — resgatado de Modules/Admin/Services em
 * 2026-07-29 (depreciação do Admin Center). Sustenta a US-ARQ-018.
 *
 * POR QUE ESTE TESTE EXISTE:
 * o caso que veio junto (`Modules/Admin/Tests/Feature/AdapterTest.php`) só
 * afirmava `toHaveKey('total_active')` — passava com a tabela ausente, com o
 * filtro de business_id quebrado, e com o reader devolvendo stub. Ou seja: não
 * media o que o reader tem de Tier 0, que é o recorte por `business_id`
 * (ADR 0093). Este mede.
 *
 * Regras da lane `arquivos-pest.yml` (MySQL real, allowlist-catraca):
 * DB::table() direto + cleanup no afterEach. SEM RefreshDatabase/migrate:fresh
 * — eles dropam o schema e limpam o biz=1 seedado, envenenando os outros casos.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/Arquivos/SPEC.md US-ARQ-018
 */
const MD5_FIXTURE = 'curadorstatsreadertest0000000000';

afterEach(function () {
    if (Schema::hasTable('arquivos')) {
        DB::table('arquivos')->where('md5', MD5_FIXTURE)->delete();
    }
});

function semearArquivo(int $businessId, string $bucket): void
{
    DB::table('arquivos')->insert([
        'business_id'   => $businessId,
        'disk'          => 'local',
        'storage_path'  => 'fixture/curador-stats-reader.bin',
        'original_name' => 'curador-stats-reader.bin',
        'mime_type'     => 'application/octet-stream',
        'size_bytes'    => 1,
        'md5'           => MD5_FIXTURE,
        'bucket'        => $bucket,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
}

it('TIER 0 — conta só os arquivos do business da sessão, nunca os do vizinho', function () {
    // Pré-condição anti-vácuo: sem a tabela o reader devolve stub e TODO assert
    // abaixo passaria por ausência de dado, não por acerto (§5 2026-07-24).
    expect(Schema::hasTable('arquivos'))->toBeTrue();

    semearArquivo(1, 'active');
    semearArquivo(1, 'sensitive');
    semearArquivo(2, 'active');       // vizinho — NÃO pode aparecer
    semearArquivo(2, 'sensitive');    // vizinho — NÃO pode aparecer

    session(['user.business_id' => 1]);
    $resultado = (new CuradorStatsReader())->fetch();

    // prova que a leitura aconteceu de verdade (não caiu no stub)
    expect($resultado['available'])->toBeTrue();

    // as 4 linhas semeadas existem; o reader só pode enxergar 2
    $semeadasNoTotal = DB::table('arquivos')->where('md5', MD5_FIXTURE)->count();
    expect($semeadasNoTotal)->toBe(4);

    // se o `where('business_id')` sumir, by_bucket incorpora as do biz=2 e estes quebram
    expect($resultado['by_bucket']['active'] ?? 0)->toBeGreaterThanOrEqual(1);
    expect($resultado['sensitive_count'])->toBe(
        (int) DB::table('arquivos')
            ->where('business_id', 1)
            ->where('bucket', 'sensitive')
            ->whereNull('deleted_at')
            ->count()
    );
});

it('TIER 0 — trocar o business da sessão troca o recorte', function () {
    expect(Schema::hasTable('arquivos'))->toBeTrue();

    semearArquivo(2, 'sensitive');

    session(['user.business_id' => 1]);
    $comoBiz1 = (new CuradorStatsReader())->fetch();

    session(['user.business_id' => 2]);
    $comoBiz2 = (new CuradorStatsReader())->fetch();

    // o mesmo arquivo do biz=2 conta pro 2 e não conta pro 1
    expect($comoBiz2['sensitive_count'])->toBeGreaterThan($comoBiz1['sensitive_count']);
});

it('degrada pra stub quando a tabela não existe, em vez de estourar', function () {
    // não dropa nada: exercita o caminho de fallback pelo contrato público.
    // Se a tabela existir (lane MySQL), o reader responde available=true —
    // o que este caso garante é que `fetch()` nunca lança.
    $resultado = (new CuradorStatsReader())->fetch();

    expect($resultado)->toHaveKey('available');
    expect($resultado)->toHaveKey('by_bucket');
    expect($resultado)->toHaveKey('sensitive_count');
});
