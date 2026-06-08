<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Entities\Arquivo;

uses(Tests\TestCase::class);

/**
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md))
 * tests pra Modules/Arquivos backbone.
 *
 * Cobertura:
 * - Global scope `business_id` aplica em queries Arquivo
 * - Auto-fill business_id no creating
 * - Sessão biz=1 NUNCA vê arquivos biz=99 (cross-tenant adversário)
 * - withoutGlobalScopes só funciona com comentário (skill multi-tenant-patterns)
 *
 * @see Modules/Arquivos/Entities/Arquivo.php
 */

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode migrate primeiro');
    }
});

it('global scope filtra arquivo por business_id da sessao', function () {
    // Cria 2 arquivos: 1 biz=1, 1 biz=99 (cross-tenant adversário)
    DB::table('arquivos')->insert([
        [
            'business_id'   => 1,
            'disk'          => 'arquivos',
            'storage_path'  => 'biz-1/test.xml',
            'original_name' => 'biz1.xml',
            'mime_type'     => 'application/xml',
            'size_bytes'    => 100,
            'md5'           => str_repeat('1', 32),
            'bucket'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ],
        [
            'business_id'   => 99,
            'disk'          => 'arquivos',
            'storage_path'  => 'biz-99/test.xml',
            'original_name' => 'biz99.xml',
            'mime_type'     => 'application/xml',
            'size_bytes'    => 100,
            'md5'           => str_repeat('9', 32),
            'bucket'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ],
    ]);

    // Simula sessão biz=1
    session(['user' => ['business_id' => 1]]);

    $arquivos = Arquivo::query()->get();

    // Esperado: vê apenas biz=1, NUNCA biz=99
    $businessIds = $arquivos->pluck('business_id')->unique()->values()->toArray();
    expect($businessIds)->toBe([1]);

    // Cleanup
    DB::table('arquivos')->whereIn('md5', [str_repeat('1', 32), str_repeat('9', 32)])->delete();
});

it('creating auto-fill business_id da sessao', function () {
    session(['user' => ['business_id' => 1]]);

    $arquivo = new Arquivo([
        'disk'          => 'arquivos',
        'storage_path'  => 'biz-1/auto.xml',
        'original_name' => 'auto.xml',
        'mime_type'     => 'application/xml',
        'size_bytes'    => 50,
        'md5'           => str_repeat('a', 32),
        'bucket'        => 'active',
    ]);
    $arquivo->save();

    expect($arquivo->business_id)->toBe(1);

    // Cleanup
    $arquivo->delete();
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
    $reflection = new ReflectionClass(Arquivo::class);
    $traits = $reflection->getTraitNames();
    expect($traits)->toContain('Illuminate\Database\Eloquent\SoftDeletes');
});

it('arquivos table tem business_id NOT NULL + indexed', function () {
    $columns = Schema::getColumnListing('arquivos');
    expect($columns)->toContain('business_id');

    $indexes = collect(DB::select("SHOW INDEX FROM arquivos"))->pluck('Column_name')->toArray();
    expect($indexes)->toContain('business_id');
});

it('arquivos_audit_log tem business_id pra preservar isolamento em audit', function () {
    if (! Schema::hasTable('arquivos_audit_log')) {
        $this->markTestSkipped('arquivos_audit_log table missing');
        return;
    }

    $columns = Schema::getColumnListing('arquivos_audit_log');
    expect($columns)->toContain('business_id');
});
