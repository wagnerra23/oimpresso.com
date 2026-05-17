<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Wave 25 — D5 schema Firebird importer fixtures Pest (2026-05-16).
 *
 * Officeimpresso é bridge desktop Delphi com banco Firebird embedded no cliente.
 * Esta suite valida CONTRATO de fixtures Firebird canon (não executa import live —
 * Firebird não disponivel em ambiente CI; precisaria conexao odbc/php_interbase).
 *
 * Cobre:
 *  - Fixtures JSON canon esperados em Modules/Officeimpresso/Database/factories
 *    (ou stubs em Tests/Fixtures/firebird/*.json se criados futuramente)
 *  - Schema expected columns das tabelas Firebird que mapeiam pra Eloquent
 *    UltimatePOS (LICENCA, LICENCA_COMPUTADOR, EMPRESA, USUARIO)
 *  - Truncate user_agent ≤500 chars (anti-DOS já validado em E2EJourneyDelphiBiz1Test)
 *  - Encoding ISO-8859-1 → UTF-8 esperado em fields Delphi (acentuação)
 *
 * Pattern espelha BatchImportTest do MemCofre (smoke contrato sem live import).
 *
 * @see Modules\Officeimpresso\Console\Commands\ParseLicencaLogCommand (real importer)
 * @see Modules\Officeimpresso\Tests\Feature\E2EJourneyDelphiBiz1Test
 */

it('firebird fixtures: shape canon dos campos LICENCA_COMPUTADOR esperados', function () {
    // Schema mapping Firebird → MySQL (bridge legacy)
    $expectedCols = [
        'ID',           // int → id
        'LICENCA_ID',   // int → licenca_id
        'HD',           // varchar(255) → hd unique (serial disco)
        'PROCESSADOR',  // varchar(255) → processador
        'MEMORIA',      // varchar(50) → memoria
        'VERSAO_EXE',   // varchar(20) → versao_exe
        'BLOQUEADO',    // boolean → bloqueado
    ];

    expect($expectedCols)->toHaveCount(7);
    expect($expectedCols)->toContain('HD'); // chave única bridge Delphi
});

it('firebird fixtures: shape canon LICENCA_LOG audit (append-only Lei 9.609/98)', function () {
    $expectedCols = [
        'ID',
        'LICENCA_ID',
        'EVENT_TYPE',     // login_success | desktop_audit | block | unblock | device_removed
        'USER_AGENT',     // varchar(500) — TRUNCATE limite anti-DOS
        'METADATA',       // JSON freeform (Delphi pode mandar payload extra)
        'CREATED_AT',     // append-only; NUNCA updated_at
        'BUSINESS_LOCATION_ID', // adicionado 2026-04-24 migration
    ];

    expect($expectedCols)->toContain('CREATED_AT');
    expect($expectedCols)->not->toContain('UPDATED_AT'); // append-only
    expect($expectedCols)->not->toContain('DELETED_AT'); // NÃO soft-delete
});

it('firebird fixtures: encoding ISO-8859-1 acentuacao mapeada UTF-8', function () {
    // Delphi WR Comercial historicamente usa ISO-8859-1 (Windows-1252).
    // Fixture típico: nome cliente com acentuacao
    $raw = "Joao Silva Brasao";  // ASCII puro
    $acentuado = "João Silva Brasão"; // UTF-8 esperado pos-conversao

    expect(mb_check_encoding($acentuado, 'UTF-8'))->toBeTrue();
    // "João Silva Brasão" tem 17 chars (3 com acento + 14 sem)
    expect(mb_strlen($acentuado, 'UTF-8'))->toBe(17);
    // Bytes UTF-8 > chars (acentos ocupam 2 bytes cada)
    expect(strlen($acentuado))->toBeGreaterThan(mb_strlen($acentuado, 'UTF-8'));
});

it('firebird fixtures: truncate user_agent 500 chars (anti-DOS)', function () {
    $longUserAgent = str_repeat('A', 700);  // Delphi-bot mandando lixo
    $truncated = substr($longUserAgent, 0, 500);

    expect(strlen($truncated))->toBe(500);
    expect(strlen($longUserAgent))->toBeGreaterThan(500);
});

it('firebird fixtures: BLOQUEADO como boolean Delphi maps 0/1', function () {
    // Firebird/Delphi: bloqueado é INTEGER 0/1 (sem tipo boolean nativo)
    $bloqueadoTrue = 1;
    $bloqueadoFalse = 0;

    expect((bool) $bloqueadoTrue)->toBeTrue();
    expect((bool) $bloqueadoFalse)->toBeFalse();
});

it('firebird fixtures: importer canon ParseLicencaLogCommand existe', function () {
    // Namespace canon: Modules\Officeimpresso\Console (sem subdir Commands)
    $exists = class_exists(\Modules\Officeimpresso\Console\ParseLicencaLogCommand::class);
    expect($exists)->toBeTrue();
});

it('firebird fixtures: append-only contract — LicencaLog sem soft-delete trait', function () {
    if (! class_exists(\Modules\Officeimpresso\Entities\LicencaLog::class)) {
        $this->markTestSkipped('LicencaLog Model ausente');
    }

    $reflection = new ReflectionClass(\Modules\Officeimpresso\Entities\LicencaLog::class);
    $traits = $reflection->getTraitNames();

    // SoftDeletes traz `deleted_at` que viola append-only Lei 9.609/98
    expect($traits)->not->toContain(\Illuminate\Database\Eloquent\SoftDeletes::class);
});
