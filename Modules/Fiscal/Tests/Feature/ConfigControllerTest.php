<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeCertificado;

uses(Tests\TestCase::class);

/**
 * PR #3 Wave Cert/Cfg Fiscal — isolation + senha hidden.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeCertificado requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('nfe_certificados table missing');
    }
});

it('NfeCertificado encrypted_password é hidden — não vaza no payload Inertia', function () {
    $cert = new NfeCertificado([
        'business_id'        => 1,
        'cnpj_titular'       => '00000000000000',
        'encrypted_password' => 'SECRET_NEVER_LEAK',
        'ativo'              => true,
    ]);

    $json = $cert->toArray();
    expect($json)
        ->not->toHaveKey('encrypted_password', 'Senha encriptada DEVE estar em $hidden');
});

it('NfeCertificado HasBusinessScope esconde certs de outros tenants', function () {
    session(['business.id' => 1, 'user.business_id' => 1]);

    $crossTenantCount = NfeCertificado::query()
        ->where('business_id', '!=', 1)
        ->count();

    expect($crossTenantCount)->toBe(0, 'Cross-tenant nunca vaza certs');
});
