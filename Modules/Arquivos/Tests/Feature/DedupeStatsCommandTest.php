<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * arquivos:dedupe-stats — Pest tests Sprint 2 ADR 0123.
 *
 * Cobertura:
 * - Command registrado em artisan list
 * - Sem rows duplicadas → mensagem "nenhum duplicado encontrado"
 * - MD5 com occurrences=5 aparece no output
 * - --business=99 filtra correto (não vaza biz=1)
 *
 * Setup: inserts diretos via DB::table() com classified_by = 'test-pr16-dedupe-stats'
 * pra cleanup isolado em afterEach.
 *
 * @see Modules/Arquivos/Console/Commands/DedupeStatsCommand.php
 */

beforeEach(function () {
    if (! Schema::hasTable('arquivos_dedupe') || ! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos_dedupe ou arquivos table missing — rode Modules/Arquivos migrate primeiro');
    }
});

afterEach(function () {
    // afterEach roda mesmo em tests pulados (PHPUnit tearDown). Em SQLite CI
    // sem migrate, DELETE estoura — bail antes.
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }

    // Limpa apenas rows de teste pra não afetar dados de outros suites.
    DB::table('arquivos')->where('classified_by', 'test-pr16-dedupe-stats')->delete();
    DB::table('arquivos_dedupe')->whereIn('md5', function ($q) {
        // MD5s usados nos testes são sempre prefixados com 'pr16test'
        $q->select('md5')->from('arquivos')->where('classified_by', 'test-pr16-dedupe-stats');
    })->delete();

    // Cleanup direto por md5 fixos usados nos testes.
    DB::table('arquivos_dedupe')->where('md5', 'pr16testmd500000000000000000001a')->delete();
    DB::table('arquivos_dedupe')->where('md5', 'pr16testmd500000000000000000002b')->delete();
    DB::table('arquivos_dedupe')->where('md5', 'pr16testmd500000000000000000003c')->delete();
});

// ---------------------------------------------------------------------------
// 1. Command registrado
// ---------------------------------------------------------------------------

it('command arquivos:dedupe-stats está registrado no artisan', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('arquivos:dedupe-stats');
});

// ---------------------------------------------------------------------------
// 2. Sem duplicatas → mensagem amigável
// ---------------------------------------------------------------------------

it('retorna 0 e mensagem "Nenhum duplicado encontrado" quando sem rows acima do threshold', function () {
    // Garante que não existe nada com occurrences >= 2 pra um md5 que só inserimos
    // com occurrences=1 (abaixo do threshold padrão).
    $md5 = 'pr16testmd500000000000000000001a';

    DB::table('arquivos_dedupe')->insertOrIgnore([
        'md5'           => $md5,
        'occurrences'   => 1,
        'first_seen_at' => now(),
    ]);

    $exitCode = Artisan::call('arquivos:dedupe-stats', [
        '--min-occurrences' => 2,
        '--top'             => 10,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Nenhum duplicado encontrado');
});

// ---------------------------------------------------------------------------
// 3. MD5 com occurrences=5 aparece no output
// ---------------------------------------------------------------------------

it('exibe MD5 com occurrences=5 na tabela de output', function () {
    $md5 = 'pr16testmd500000000000000000002b';

    // Insert em arquivos_dedupe com 5 occurrences.
    DB::table('arquivos_dedupe')->insertOrIgnore([
        'md5'           => $md5,
        'occurrences'   => 5,
        'first_seen_at' => now()->subDays(3),
    ]);

    // Insert correspondente em arquivos (business=1 — biz de teste, nunca cliente).
    DB::table('arquivos')->insert([
        'business_id'     => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'   => 9901,
        'disk'            => 'arquivos',
        'storage_path'    => 'biz-1/2026/05/' . $md5 . '.pdf',
        'original_name'   => 'relatorio-pr16.pdf',
        'mime_type'       => 'application/pdf',
        'size_bytes'      => 102400, // 100 KB
        'md5'             => $md5,
        'bucket'          => 'active',
        'classified_by'   => 'test-pr16-dedupe-stats',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $exitCode = Artisan::call('arquivos:dedupe-stats', [
        '--top'             => 10,
        '--min-occurrences' => 2,
    ]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    // Os primeiros 8 chars do md5 devem aparecer na tabela.
    expect($output)->toContain(substr($md5, 0, 8));
    // Deve conter o count de occurrences.
    expect($output)->toContain('5');
    // Deve mencionar economia total.
    expect($output)->toContain('Total economia estimada');
});

// ---------------------------------------------------------------------------
// 4. --business filtra correto (não vaza cross-business)
// ---------------------------------------------------------------------------

it('--business=99 não vaza md5 que pertence só ao biz=1', function () {
    $md5 = 'pr16testmd500000000000000000003c';

    // arquivos_dedupe — cross-business (sem business_id por design ADR 0123 §3).
    DB::table('arquivos_dedupe')->insertOrIgnore([
        'md5'           => $md5,
        'occurrences'   => 3,
        'first_seen_at' => now(),
    ]);

    // O arquivo físico pertence APENAS ao business_id=1.
    DB::table('arquivos')->insert([
        'business_id'     => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'   => 9902,
        'disk'            => 'arquivos',
        'storage_path'    => 'biz-1/2026/05/' . $md5 . '.txt',
        'original_name'   => 'cross-biz-test.txt',
        'mime_type'       => 'text/plain',
        'size_bytes'      => 51200,
        'md5'             => $md5,
        'bucket'          => 'active',
        'classified_by'   => 'test-pr16-dedupe-stats',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    // Consulta com --business=99 (business inexistente / diferente de 1).
    $exitCode = Artisan::call('arquivos:dedupe-stats', [
        '--business'        => 99,
        '--min-occurrences' => 1,
        '--top'             => 10,
    ]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    // md5 pertence só ao biz=1 — não deve aparecer no filtro de biz=99.
    expect($output)->not->toContain(substr($md5, 0, 8));
    expect($output)->toContain('Nenhum duplicado encontrado');
});
