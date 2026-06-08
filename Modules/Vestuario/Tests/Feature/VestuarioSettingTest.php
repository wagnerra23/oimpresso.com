<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Vestuario\Entities\VestuarioSetting;

uses(Tests\TestCase::class);

/**
 * Sprint 1 — primeira migration Modules/Vestuario (vestuario_settings).
 *
 * @see Modules/Vestuario/Database/Migrations/2026_05_10_000001_create_vestuario_settings_table.php
 */

beforeEach(function () {
    if (! Schema::hasTable('vestuario_settings')) {
        $this->markTestSkipped('vestuario_settings table missing — rode migrate primeiro');
    }
});

it('vestuario_settings table tem schema correto', function () {
    $columns = Schema::getColumnListing('vestuario_settings');
    expect($columns)->toContain('id');
    expect($columns)->toContain('business_id');
    expect($columns)->toContain('settings');
    expect($columns)->toContain('deleted_at'); // SoftDeletes
});

it('VestuarioSetting Model usa SoftDeletes + global scope business_id', function () {
    $traits = (new ReflectionClass(VestuarioSetting::class))->getTraitNames();
    expect($traits)->toContain('Illuminate\Database\Eloquent\SoftDeletes');

    // Global scope registrado
    $scopes = (new VestuarioSetting())->getGlobalScopes();
    expect($scopes)->toHaveKey('business_id');
});

it('settings JSON helpers get/set funcionam', function () {
    session(['user' => ['business_id' => 1]]);

    $row = VestuarioSetting::create([
        'business_id' => 1,
        'settings'    => ['feature_x' => true],
    ]);

    expect($row->get('feature_x'))->toBeTrue();
    expect($row->get('inexistente', 'default'))->toBe('default');

    $row->set('format_date_shift_hours', 3);
    $row->refresh();
    expect($row->get('format_date_shift_hours'))->toBe(3);

    // Cleanup
    $row->forceDelete();
});

it('global scope filtra biz=1 nao ve biz=99 (cross-tenant)', function () {
    DB::table('vestuario_settings')->insert([
        ['business_id' => 1, 'settings' => json_encode(['biz' => 1]), 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 99, 'settings' => json_encode(['biz' => 99]), 'created_at' => now(), 'updated_at' => now()],
    ]);

    session(['user' => ['business_id' => 1]]);

    $rows = VestuarioSetting::query()->get();
    $businessIds = $rows->pluck('business_id')->unique()->values()->toArray();

    expect($businessIds)->toBe([1]);

    // Cleanup
    DB::table('vestuario_settings')->whereIn('business_id', [1, 99])->delete();
});

it('UNIQUE constraint impede 2 rows pro mesmo business_id', function () {
    DB::table('vestuario_settings')->insert([
        'business_id' => 99, 'settings' => json_encode([]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('vestuario_settings')->insert([
        'business_id' => 99, 'settings' => json_encode([]),
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    // Cleanup
    DB::table('vestuario_settings')->where('business_id', 99)->delete();
});

it('current() helper auto-cria se vazio + retorna existente se já existir', function () {
    session(['user' => ['business_id' => 1]]);

    $first = VestuarioSetting::current();
    expect($first->business_id)->toBe(1);

    $second = VestuarioSetting::current();
    expect($second->id)->toBe($first->id); // mesma row, não duplica

    // Cleanup
    $first->forceDelete();
});
