<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Pest — paymentgateway:register-permissions command.
 *
 * ADR 0170 Onda 5 SIMPLIFICADA — espelhado de whatsapp:register-permissions.
 * Garante que rodar o command popula tabela `permissions` Spatie pra que
 * Wagner possa marcar paymentgateway.* no UI /roles depois.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL com Spatie permissions table.');
    }
    if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
        $this->markTestSkipped('Schema Spatie ausente — rode migrations primeiro.');
    }
});

it('--dry-run não persiste permissions', function () {
    Permission::where('name', 'like', 'paymentgateway.%')->delete();

    $this->artisan('paymentgateway:register-permissions', ['--business' => '1', '--dry-run' => true])
        ->assertExitCode(0);

    $count = Permission::where('name', 'like', 'paymentgateway.%')->count();
    expect($count)->toBe(0);
});

it('apply registra 10 permissions paymentgateway.*', function () {
    Permission::where('name', 'like', 'paymentgateway.%')->delete();

    $this->artisan('paymentgateway:register-permissions', ['--business' => '1'])
        ->assertExitCode(0);

    $names = Permission::where('name', 'like', 'paymentgateway.%')->pluck('name')->all();
    expect($names)->toContain('paymentgateway.access');
    expect($names)->toContain('paymentgateway.cobranca.emit');
    expect($names)->toContain('paymentgateway.webhook.replay');
    expect(count($names))->toBeGreaterThanOrEqual(10);

    Permission::where('name', 'like', 'paymentgateway.%')->delete();
});

it('apply é idempotente — rodar 2x não duplica', function () {
    Permission::where('name', 'like', 'paymentgateway.%')->delete();

    $this->artisan('paymentgateway:register-permissions', ['--business' => '1'])->assertExitCode(0);
    $countFirst = Permission::where('name', 'like', 'paymentgateway.%')->count();

    $this->artisan('paymentgateway:register-permissions', ['--business' => '1'])->assertExitCode(0);
    $countSecond = Permission::where('name', 'like', 'paymentgateway.%')->count();

    expect($countSecond)->toBe($countFirst);

    Permission::where('name', 'like', 'paymentgateway.%')->delete();
});

it('business inválido retorna FAILURE', function () {
    $this->artisan('paymentgateway:register-permissions', ['--business' => 'abc'])
        ->assertExitCode(1);
});
