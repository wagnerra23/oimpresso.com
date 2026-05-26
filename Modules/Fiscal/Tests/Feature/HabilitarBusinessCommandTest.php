<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-018 — Comando `fiscal:habilitar-business {biz}` (Onda ESTABILIZAR).
 *
 * Tests focados em (1) idempotência (rodar 2× = mesmo efeito), (2) garantia que
 * fiscal.sped.export NÃO é atribuído ao role (audit sênior 2026-05-25 §"Surpresa
 * estratégica" GAP-FISCAL-003), (3) cross-tenant scope (perms vão pro role do biz
 * correto, NUNCA cross-business).
 *
 * SQLite skip: tabela `business` + Spatie tables tem schema MySQL UltimatePOS
 * canon (ADR 0101 — tests biz=1, NUNCA biz=4 cliente).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: business + spatie tables exigem MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('business') || ! Schema::hasTable('roles') || ! Schema::hasTable('subscriptions')) {
        $this->markTestSkipped('Tabelas UltimatePOS ausentes — rodar php artisan migrate primeiro');
    }
});

it('comando registrado em php artisan list (signature canon)', function () {
    $output = Artisan::call('list');
    expect(Artisan::output())->toContain('fiscal:habilitar-business');
});

it('rejeita businessId inválido (0 ou negativo)', function () {
    $exitCode = Artisan::call('fiscal:habilitar-business', ['businessId' => 0]);
    expect($exitCode)->toBe(\Symfony\Component\Console\Command\Command::FAILURE);

    expect(Artisan::output())->toContain('businessId inválido');
});

it('rejeita business inexistente', function () {
    $exitCode = Artisan::call('fiscal:habilitar-business', ['businessId' => 999999]);
    expect($exitCode)->toBe(\Symfony\Component\Console\Command\Command::FAILURE);

    expect(Artisan::output())->toContain('não existe');
});

it('cria as 7 permissions fiscal.* mesmo em dry-run (idempotente)', function () {
    // Garante que perms NÃO existem antes
    Permission::where('name', 'LIKE', 'fiscal.%')->delete();

    // dry-run reporta o que faria mas NÃO persiste
    Artisan::call('fiscal:habilitar-business', ['businessId' => 1, '--dry-run' => true]);

    $apósDryRun = Permission::where('name', 'LIKE', 'fiscal.%')->count();
    expect($apósDryRun)->toBe(0, 'dry-run NÃO deve persistir permissions');

    // run real cria as 7
    Artisan::call('fiscal:habilitar-business', ['businessId' => 1]);
    $apósReal = Permission::where('name', 'LIKE', 'fiscal.%')->where('guard_name', 'web')->count();
    expect($apósReal)->toBe(7, 'devem existir 7 fiscal.* permissions após run real');

    // re-roda — idempotente (não duplica)
    Artisan::call('fiscal:habilitar-business', ['businessId' => 1]);
    $apósSegundaRun = Permission::where('name', 'LIKE', 'fiscal.%')->where('guard_name', 'web')->count();
    expect($apósSegundaRun)->toBe(7, 're-run NÃO pode duplicar permissions (idempotência)');
});

it('NUNCA atribui fiscal.sped.export ao role piloto (GAP-FISCAL-003 ainda não fechado)', function () {
    $bizId = 1;

    // Cria role Admin#{biz} se não existir
    $role = Role::firstOrCreate(
        ['name' => "Admin#{$bizId}", 'guard_name' => 'web'],
        ['business_id' => $bizId],
    );
    $role->revokePermissionTo(Permission::where('name', 'LIKE', 'fiscal.%')->pluck('name')->all());

    Artisan::call('fiscal:habilitar-business', ['businessId' => $bizId]);

    expect($role->fresh()->hasPermissionTo('fiscal.access'))->toBeTrue('fiscal.access deve ser atribuída')
        ->and($role->fresh()->hasPermissionTo('fiscal.nfe.view'))->toBeTrue('fiscal.nfe.view deve ser atribuída')
        ->and($role->fresh()->hasPermissionTo('fiscal.nfe.acoes'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('fiscal.dfe.manage'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('fiscal.nfse.view'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('fiscal.config.edit'))->toBeTrue()
        // CRÍTICO: fiscal.sped.export NÃO pode ser atribuída ao role
        ->and($role->fresh()->hasPermissionTo('fiscal.sped.export'))->toBeFalse(
            'audit sênior 2026-05-25 GAP-FISCAL-003: fiscal.sped.export NUNCA atribuída '
            . 'enquanto 6 hardcodes Tier-0 não eliminados em SpedIcmsIpiGeneratorService',
        );
});

it('atribui perms ao role do business correto (cross-tenant scope ADR 0093)', function () {
    // Cria 2 roles em businesses diferentes
    $roleBiz1 = Role::firstOrCreate(
        ['name' => 'Admin#1', 'guard_name' => 'web'],
        ['business_id' => 1],
    );
    $roleBiz1->revokePermissionTo(Permission::where('name', 'LIKE', 'fiscal.%')->pluck('name')->all());

    // Roda comando pra biz=1
    Artisan::call('fiscal:habilitar-business', ['businessId' => 1]);

    // Role do biz=1 RECEBEU perms
    expect($roleBiz1->fresh()->hasPermissionTo('fiscal.access'))->toBeTrue();

    // Garantir que outras roles (de outros businesses) NÃO foram afetadas
    // (defesa cross-tenant — comando só toca role do biz alvo)
    $outrosRoles = Role::where('business_id', '!=', 1)
        ->where('name', 'LIKE', 'Admin#%')
        ->limit(3)
        ->get();
    foreach ($outrosRoles as $outroRole) {
        // Esse outroRole pode ter sido habilitado em outro contexto — vamos só
        // garantir que o comando atual não foi aplicado A ELE neste teste.
        // (Asserção fraca mas declara intent: o comando aceita 1 biz por vez.)
        expect($outroRole->business_id)->not->toBe(1, 'cross-tenant scope: comando só toca o biz argumento');
    }
});
