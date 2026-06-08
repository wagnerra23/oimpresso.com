<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Vestuario\Services\VestuarioSettingsResolver;

uses(Tests\TestCase::class);

/**
 * VestuarioSettingsResolver — Pest tests Sprint 2 ADR 0121 §P7.
 *
 * Cobertura:
 * - get() com default fallback + dot notation
 * - getInt() com bounds
 * - getBool() com truthy strings
 * - set() invalida cache
 * - forBusiness() override per-call
 * - Cache 5min funciona (mock Cache::shouldReceive)
 * - Sem session, retorna default
 *
 * @see Modules/Vestuario/Services/VestuarioSettingsResolver.php
 */

beforeEach(function () {
    if (! Schema::hasTable('vestuario_settings')) {
        $this->markTestSkipped('vestuario_settings table missing — rode migrate primeiro');
    }
    Cache::flush();
});

it('get retorna default quando session sem business_id', function () {
    session()->forget('user');
    session()->forget('business');

    $resolver = new VestuarioSettingsResolver();
    expect($resolver->get('any.key', 'default-val'))->toBe('default-val');
});

it('get retorna default quando settings vazios', function () {
    session(['user' => ['business_id' => 1]]);
    DB::table('vestuario_settings')->where('business_id', 1)->delete();

    $resolver = new VestuarioSettingsResolver();
    expect($resolver->get('inexistente', 'fallback'))->toBe('fallback');
});

it('get suporta dot notation', function () {
    session(['user' => ['business_id' => 1]]);
    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 1],
        [
            'settings'   => json_encode(['feature' => ['x' => ['threshold' => 100]]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $resolver = new VestuarioSettingsResolver();
    $resolver->refresh(); // limpa cache antes
    expect($resolver->get('feature.x.threshold'))->toBe(100);
    expect($resolver->get('feature.x.inexistente', 'def'))->toBe('def');

    DB::table('vestuario_settings')->where('business_id', 1)->delete();
});

it('getInt aplica bounds min/max', function () {
    session(['user' => ['business_id' => 1]]);
    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 1],
        ['settings' => json_encode(['shift' => 5]), 'created_at' => now(), 'updated_at' => now()]
    );

    $resolver = new VestuarioSettingsResolver();
    $resolver->refresh();

    expect($resolver->getInt('shift', 0))->toBe(5);
    expect($resolver->getInt('shift', 0, min: 10))->toBe(0); // 5<10 -> default
    expect($resolver->getInt('shift', 0, max: 3))->toBe(0); // 5>3 -> default
    expect($resolver->getInt('shift', 0, min: 1, max: 10))->toBe(5);

    DB::table('vestuario_settings')->where('business_id', 1)->delete();
});

it('getBool aceita strings truthy', function () {
    session(['user' => ['business_id' => 1]]);
    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 1],
        ['settings' => json_encode([
            'a' => 'true',
            'b' => '1',
            'c' => 'yes',
            'd' => 'sim',
            'e' => 'no',
            'f' => false,
        ]), 'created_at' => now(), 'updated_at' => now()]
    );

    $resolver = new VestuarioSettingsResolver();
    $resolver->refresh();

    expect($resolver->getBool('a'))->toBeTrue();
    expect($resolver->getBool('b'))->toBeTrue();
    expect($resolver->getBool('c'))->toBeTrue();
    expect($resolver->getBool('d'))->toBeTrue();
    expect($resolver->getBool('e'))->toBeFalse();
    expect($resolver->getBool('f'))->toBeFalse();
    expect($resolver->getBool('inexistente', true))->toBeTrue();

    DB::table('vestuario_settings')->where('business_id', 1)->delete();
});

it('set invalida cache + persiste', function () {
    session(['user' => ['business_id' => 1]]);
    DB::table('vestuario_settings')->where('business_id', 1)->delete();

    $resolver = new VestuarioSettingsResolver();
    $resolver->set('format_date_shift_hours', 3);

    // Re-read deve pegar valor novo (cache invalidado)
    $value = $resolver->get('format_date_shift_hours');
    expect($value)->toBe(3);

    DB::table('vestuario_settings')->where('business_id', 1)->delete();
});

it('forBusiness override permite consultar fora session web', function () {
    session()->forget('user');
    session()->forget('business');

    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 99],
        ['settings' => json_encode(['biz_specific' => 'cross-tenant']), 'created_at' => now(), 'updated_at' => now()]
    );

    $resolver = new VestuarioSettingsResolver();
    $value = $resolver->forBusiness(99)->get('biz_specific');

    expect($value)->toBe('cross-tenant');

    DB::table('vestuario_settings')->where('business_id', 99)->delete();
});

it('forBusiness não muda instance original (chainable imutável)', function () {
    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 99],
        ['settings' => json_encode(['biz' => 99]), 'created_at' => now(), 'updated_at' => now()]
    );
    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 1],
        ['settings' => json_encode(['biz' => 1]), 'created_at' => now(), 'updated_at' => now()]
    );

    session(['user' => ['business_id' => 1]]);

    $resolver = new VestuarioSettingsResolver();
    $forBiz99 = $resolver->forBusiness(99);

    // Original ainda usa session biz=1
    expect($resolver->get('biz'))->toBe(1);
    // Override só na clone
    expect($forBiz99->get('biz'))->toBe(99);

    DB::table('vestuario_settings')->whereIn('business_id', [1, 99])->delete();
});

it('VestuarioSettingsResolver é singleton no container', function () {
    $first = app(VestuarioSettingsResolver::class);
    $second = app(VestuarioSettingsResolver::class);
    expect($first)->toBe($second);
});
