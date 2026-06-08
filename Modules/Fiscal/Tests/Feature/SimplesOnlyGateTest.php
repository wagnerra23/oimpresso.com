<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Onda ESTABILIZAR 2026-05-25 — Feature flag `fiscal.sped_simples_only_lock`
 * bloqueia download SPED enquanto GAP-FISCAL-003 não elimina 6 hardcodes
 * Tier-0 em SpedIcmsIpiGeneratorService.
 *
 * Audit sênior 2026-05-25 §"Surpresa estratégica" — hardcodes (NCM 00000000,
 * CST 102, CFOP 5102, ALIQ 0, COD_MUN, COD_PART) funcionam acidentalmente
 * pra Simples Nacional vestuário sem crédito ICMS, mas quebram em venda
 * interestadual contribuinte. Multa fiscal Larissa biz=4 se não bloqueado.
 *
 * Superadmin bypass: Wagner pode forçar download manualmente via /superadmin.
 */

// Tests HTTP que tocam users table → skipam SQLite (ADR 0101).
// Tests puros de config moveram pra SimplesOnlyGateConfigTest.php — rodam sempre.

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível pra tests HTTP que criam users (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('NfeBrasil tables ausentes — migrate primeiro');
    }
});

it('user comum com fiscal.sped.export é bloqueado por 503 quando flag true', function () {
    config(['fiscal.sped_simples_only_lock' => true]);

    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('fiscal.sped.export');
    $this->actingAs($user);
    session(['business.id' => 1, 'user.business_id' => 1]);

    $r = $this->get('/fiscal/sped/icms-ipi/2026/5');
    $r->assertStatus(503);
    expect($r->getContent())->toContain('temporariamente bloqueado')
        ->and($r->getContent())->toContain('GAP-FISCAL-003');
});

it('superadmin bypassa flag e consegue download mesmo com flag true', function () {
    config(['fiscal.sped_simples_only_lock' => true]);

    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['business.id' => 1, 'user.business_id' => 1]);

    // Superadmin não recebe 503. Pode receber 200 (download ok) OU 500 (gerador
    // sem dados — fixture vazia). O importante é: NÃO recebe 503 do gate.
    $r = $this->get('/fiscal/sped/icms-ipi/2026/5');
    expect($r->getStatusCode())->not->toBe(503, 'superadmin bypassa o gate simples_only');
});

it('flag false libera download pra user comum', function () {
    config(['fiscal.sped_simples_only_lock' => false]);

    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('fiscal.sped.export');
    $this->actingAs($user);
    session(['business.id' => 1, 'user.business_id' => 1]);

    $r = $this->get('/fiscal/sped/icms-ipi/2026/5');
    // Não-503 confirma gate liberado. Pode ser 200 ok ou 500 erro gerador.
    expect($r->getStatusCode())->not->toBe(503, 'flag false libera o gate');
});

it('user sem permissão fiscal.sped.export recebe 403 (gate de perm é anterior)', function () {
    config(['fiscal.sped_simples_only_lock' => true]);

    $user = \App\User::factory()->create(['business_id' => 1]);
    // NÃO dá fiscal.sped.export
    $this->actingAs($user);
    session(['business.id' => 1, 'user.business_id' => 1]);

    $r = $this->get('/fiscal/sped/icms-ipi/2026/5');
    $r->assertStatus(403);
});
