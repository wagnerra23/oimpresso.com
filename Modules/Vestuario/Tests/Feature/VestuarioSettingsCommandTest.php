<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Pest tests — vestuario:settings CLI (list/get/set).
 *
 * Sprint 2 ADR 0121 §P7 — command CLI pra inspecionar/editar settings
 * do vertical Vestuario sem ir direto no DB.
 *
 * Convenções:
 * - biz=1 (Wagner WR2) em todos tests, nunca biz=4 (ROTA LIVRE — ADR 0101)
 * - Setup via DB::table()->updateOrInsert(), nunca Storage::fake()
 * - uses(Tests\TestCase::class) SEM ->in(__DIR__)
 *
 * @see Modules/Vestuario/Console/Commands/VestuarioSettingsCommand.php
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md §P7
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

beforeEach(function () {
    if (! Schema::hasTable('vestuario_settings')) {
        $this->markTestSkipped('vestuario_settings table missing — rode php artisan migrate primeiro');
    }
    // Limpa rows biz=1 antes de cada test (isolamento)
    DB::table('vestuario_settings')->where('business_id', 1)->delete();
});

afterEach(function () {
    // afterEach roda mesmo em tests pulados (PHPUnit tearDown). Em SQLite CI
    // sem migrate, DELETE estoura — bail antes.
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }

    // Cleanup pós-test
    DB::table('vestuario_settings')->where('business_id', 1)->delete();
});

it('command vestuario:settings está registrado em artisan list', function () {
    $this->artisan('list')
        ->expectsOutputToContain('vestuario:settings')
        ->assertExitCode(0);
});

it('--business ausente retorna exit 1 com mensagem clara', function () {
    $this->artisan('vestuario:settings', ['action' => 'list'])
        ->expectsOutputToContain('--business é obrigatório')
        ->assertExitCode(1);
});

it('action inválida retorna exit 1', function () {
    $this->artisan('vestuario:settings', [
        'action'     => 'delete',
        '--business' => '1',
    ])
        ->expectsOutputToContain('Action inválida')
        ->assertExitCode(1);
});

it('list exibe tabela com keys e values do business', function () {
    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 1],
        [
            'settings'   => json_encode([
                'format_date_shift_hours' => 3,
                'feature_x'               => true,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $this->artisan('vestuario:settings', [
        'action'     => 'list',
        '--business' => '1',
    ])
        ->expectsOutputToContain('format_date_shift_hours')
        ->expectsOutputToContain('feature_x')
        ->assertExitCode(0);
});

it('list com settings vazio mostra aviso sem erro', function () {
    $this->artisan('vestuario:settings', [
        'action'     => 'list',
        '--business' => '1',
    ])
        ->assertExitCode(0);
});

it('get com dot notation funciona corretamente', function () {
    DB::table('vestuario_settings')->updateOrInsert(
        ['business_id' => 1],
        [
            'settings'   => json_encode(['feature' => ['x' => ['threshold' => 100]]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $this->artisan('vestuario:settings', [
        'action'     => 'get',
        '--business' => '1',
        '--key'      => 'feature.x.threshold',
    ])
        ->expectsOutputToContain('100')
        ->assertExitCode(0);
});

it('get sem --key retorna exit 1 com mensagem clara', function () {
    $this->artisan('vestuario:settings', [
        'action'     => 'get',
        '--business' => '1',
    ])
        ->expectsOutputToContain('--key é obrigatório')
        ->assertExitCode(1);
});

it('set --type=int casteia corretamente e persiste no DB', function () {
    $this->artisan('vestuario:settings', [
        'action'     => 'set',
        '--business' => '1',
        '--key'      => 'format_date_shift_hours',
        '--value'    => '3',
        '--type'     => 'int',
    ])
        ->expectsOutputToContain('format_date_shift_hours')
        ->expectsOutputToContain('3')
        ->assertExitCode(0);

    $row = DB::table('vestuario_settings')->where('business_id', 1)->first();
    expect($row)->not->toBeNull();

    $settings = json_decode($row->settings, true);
    expect($settings['format_date_shift_hours'])->toBe(3);
});

it('set --type=bool aceita "true" e persiste como true', function () {
    $this->artisan('vestuario:settings', [
        'action'     => 'set',
        '--business' => '1',
        '--key'      => 'feature_habilitada',
        '--value'    => 'true',
        '--type'     => 'bool',
    ])
        ->assertExitCode(0);

    $row     = DB::table('vestuario_settings')->where('business_id', 1)->first();
    $settings = json_decode($row->settings, true);
    expect($settings['feature_habilitada'])->toBeTrue();
});

it('set --type=bool aceita "false" e persiste como false', function () {
    $this->artisan('vestuario:settings', [
        'action'     => 'set',
        '--business' => '1',
        '--key'      => 'feature_habilitada',
        '--value'    => 'false',
        '--type'     => 'bool',
    ])
        ->assertExitCode(0);

    $row      = DB::table('vestuario_settings')->where('business_id', 1)->first();
    $settings = json_decode($row->settings, true);
    expect($settings['feature_habilitada'])->toBeFalse();
});

it('set --type=bool aceita "1" como true e "0" como false', function () {
    // "1" → true
    $this->artisan('vestuario:settings', [
        'action'     => 'set',
        '--business' => '1',
        '--key'      => 'flag_a',
        '--value'    => '1',
        '--type'     => 'bool',
    ])->assertExitCode(0);

    $row      = DB::table('vestuario_settings')->where('business_id', 1)->first();
    $settings = json_decode($row->settings, true);
    expect($settings['flag_a'])->toBeTrue();

    // "0" → false
    $this->artisan('vestuario:settings', [
        'action'     => 'set',
        '--business' => '1',
        '--key'      => 'flag_b',
        '--value'    => '0',
        '--type'     => 'bool',
    ])->assertExitCode(0);

    $row      = DB::table('vestuario_settings')->where('business_id', 1)->first();
    $settings = json_decode($row->settings, true);
    expect($settings['flag_b'])->toBeFalse();
});

it('set --type=json inválido retorna exit 1', function () {
    $this->artisan('vestuario:settings', [
        'action'     => 'set',
        '--business' => '1',
        '--key'      => 'config_json',
        '--value'    => 'não é json válido {{{',
        '--type'     => 'json',
    ])
        ->expectsOutputToContain('JSON parse falhou')
        ->assertExitCode(1);
});

it('set sem --value retorna exit 1 com mensagem clara', function () {
    $this->artisan('vestuario:settings', [
        'action'     => 'set',
        '--business' => '1',
        '--key'      => 'alguma_key',
    ])
        ->expectsOutputToContain('--value é obrigatório')
        ->assertExitCode(1);
});

it('set dot notation cria hierarquia aninhada', function () {
    $this->artisan('vestuario:settings', [
        'action'     => 'set',
        '--business' => '1',
        '--key'      => 'feature.x.threshold',
        '--value'    => '250',
        '--type'     => 'int',
    ])->assertExitCode(0);

    $row      = DB::table('vestuario_settings')->where('business_id', 1)->first();
    $settings = json_decode($row->settings, true);
    expect($settings['feature']['x']['threshold'])->toBe(250);
});
