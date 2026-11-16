<?php

declare(strict_types=1);
// @covers-us US-ASSOC-001

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Associacoes\Console\SemearRedeCommand;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do `associacoes:semear-rede` (RUNBOOK-data-readiness-uniafibro): simulação não grava,
 * --aplicar grava só na empresa pedida, segunda rodada não duplica nem sobrescreve, e sem
 * --business o comando recusa (CLI sem sessão = escopo inerte, ADR 0093).
 *
 * ⚠️ SKIP em SQLite: schema UltimatePOS (ADR 0358). Lane verticais-pest (MySQL).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS (ADR 0358).');
    }
    if (! Schema::hasTable('assoc_associacoes')) {
        $this->markTestSkipped('Tabela assoc_associacoes ausente — migrations do Modules/Associacoes não rodaram.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
});

function semearContar(int $biz): int
{
    // SUPERADMIN: contagem explícita por empresa; o teste prova o isolamento entre 98 e 99.
    return DB::table('assoc_associacoes')->where('business_id', $biz)->count();
}

test('sem --business o comando recusa e não grava nada', function () {
    $antes = DB::table('assoc_associacoes')->count();

    $this->artisan('associacoes:semear-rede')->assertFailed();

    expect(DB::table('assoc_associacoes')->count())->toBe($antes);
});

test('empresa inexistente é recusada', function () {
    $this->artisan('associacoes:semear-rede', ['--business' => 999999999, '--aplicar' => true])->assertFailed();
});

test('simulação lista e não grava', function () {
    $antes = semearContar($this->b98);

    $this->artisan('associacoes:semear-rede', ['--business' => $this->b98])->assertSuccessful();

    expect(semearContar($this->b98))->toBe($antes);
});

test('--aplicar grava a rede inicial só na empresa pedida', function () {
    $antes99 = semearContar($this->b99);

    $this->artisan('associacoes:semear-rede', ['--business' => $this->b98, '--aplicar' => true])->assertSuccessful();

    foreach (SemearRedeCommand::REDE_INICIAL as $assoc) {
        expect(DB::table('assoc_associacoes')->where('business_id', $this->b98)->where('nome', $assoc['nome'])->value('cidade'))
            ->toBe($assoc['cidade']);
    }
    expect(semearContar($this->b99))->toBe($antes99);
});

test('segunda rodada não duplica nem sobrescreve o que a tela editou', function () {
    $this->artisan('associacoes:semear-rede', ['--business' => $this->b98, '--aplicar' => true])->assertSuccessful();
    // A diretoria troca o nome e preenche o CNPJ pela tela; a sigla fica.
    DB::table('assoc_associacoes')->where('business_id', $this->b98)->where('sigla', 'AGFESC')
        ->update(['nome' => 'Girassóis Fixture', 'cnpj' => 'CNPJ-FIXTURE']);
    $depoisPrimeira = semearContar($this->b98);

    $this->artisan('associacoes:semear-rede', ['--business' => $this->b98, '--aplicar' => true])->assertSuccessful();

    $agfesc = DB::table('assoc_associacoes')->where('business_id', $this->b98)->where('sigla', 'AGFESC')->first();
    expect(semearContar($this->b98))->toBe($depoisPrimeira)
        ->and($agfesc->nome)->toBe('Girassóis Fixture')
        ->and($agfesc->cnpj)->toBe('CNPJ-FIXTURE');
});

test('a rede inicial tem as 5 associações do site, sem sigla repetida', function () {
    $siglas = array_column(SemearRedeCommand::REDE_INICIAL, 'sigla');

    expect($siglas)->toHaveCount(5)
        ->and(array_unique($siglas))->toHaveCount(5)
        ->and($siglas)->toContain('AGFESC', 'AFIBROMAR', 'AFICOR', 'AREFI', 'ACPFA');
});
