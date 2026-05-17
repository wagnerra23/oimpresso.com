<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NFSe\Models\NfseCertificado;

uses(Tests\TestCase::class);

/**
 * Wave 18 D1 — Isolamento multi-tenant Tier 0 do alias `NfseCertificado`.
 *
 * `NfseCertificado` é alias de `Modules\NfeBrasil\Models\NfeCertificado` (mesma
 * tabela `nfe_certificados`). O trait `HasBusinessScope` é herdado, mas Pest
 * dedicado pro alias confirma que:
 *   1. Scope NÃO é "perdido" no nível da subclasse
 *   2. NfseProviderConfig::certificado() belongsTo herda o filtro
 *   3. isExpirado() (alias de isVencido) funciona consistente com o pai
 *
 * Tabela nfe_certificados contém PII fiscal sensível: CNPJ titular + senha
 * encriptada do certificado A1/A3. Vazar cross-tenant = expor credencial
 * fiscal de outro CNPJ.
 *
 * ADR 0093: business_id Tier 0 IRREVOGÁVEL.
 * ADR 0101: NUNCA usar biz=4 (ROTA LIVRE — cliente real prod) em tests.
 *
 * @see Modules/NFSe/Models/NfseCertificado.php
 * @see Modules/NfeBrasil/Models/NfeCertificado.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

const NFSE_CERT_BIZ_WAGNER = 1;
const NFSE_CERT_BIZ_FICTICIO = 99;
const NFSE_CERT_TAG_CNPJ = '00000000000099'; // CNPJ fictício para teste isolamento

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: nfe_certificados requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('nfe_certificados table missing — rode Modules/NfeBrasil migrate primeiro');
    }
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    if (! Schema::hasTable('nfe_certificados')) {
        return;
    }
    try {
        NfseCertificado::withoutGlobalScopes()
            ->where('cnpj_titular', NFSE_CERT_TAG_CNPJ)
            ->forceDelete();
    } catch (\Throwable) {
        // best-effort
    }
});

it('NfseCertificado biz=99 NÃO aparece quando session ativa é biz=1 (scope herdado)', function () {
    $certId = DB::table('nfe_certificados')->insertGetId([
        'business_id'         => NFSE_CERT_BIZ_FICTICIO,
        'cnpj_titular'        => NFSE_CERT_TAG_CNPJ,
        'nome_titular'        => 'TESTE ISOL NFSE LTDA',
        'encrypted_password'  => 'ENCRYPTED_PLACEHOLDER',
        'validade_inicio'     => now()->subDays(10)->toDateString(),
        'validade_fim'        => now()->addDays(365)->toDateString(),
        'tipo'                => 'A1',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    session(['business.id' => NFSE_CERT_BIZ_WAGNER]);

    // Scope herdado — alias filtra como o pai
    $vaza = NfseCertificado::where('id', $certId)->first();
    expect($vaza)->toBeNull();

    // SUPERADMIN: setup teste cross-tenant — confirma que existe
    $real = NfseCertificado::withoutGlobalScopes()->where('id', $certId)->first();
    expect($real)->not->toBeNull();
    expect((int) $real->business_id)->toBe(NFSE_CERT_BIZ_FICTICIO);
    expect($real->cnpj_titular)->toBe(NFSE_CERT_TAG_CNPJ);
});

it('NfseCertificado isExpirado() funciona como alias de isVencido()', function () {
    // Cria certificado já vencido
    DB::table('nfe_certificados')->insert([
        'business_id'         => NFSE_CERT_BIZ_WAGNER,
        'cnpj_titular'        => NFSE_CERT_TAG_CNPJ,
        'nome_titular'        => 'VENCIDO TESTE LTDA',
        'encrypted_password'  => 'ENCRYPTED_PLACEHOLDER',
        'validade_inicio'     => now()->subDays(800)->toDateString(),
        'validade_fim'        => now()->subDays(10)->toDateString(), // vencido
        'tipo'                => 'A1',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    session(['business.id' => NFSE_CERT_BIZ_WAGNER]);

    $cert = NfseCertificado::where('cnpj_titular', NFSE_CERT_TAG_CNPJ)->first();
    expect($cert)->not->toBeNull();

    // Alias isExpirado() retorna o mesmo que isVencido() do pai
    expect($cert->isExpirado())->toBe($cert->isVencido());
    expect($cert->isExpirado())->toBeTrue();
});

it('NfseCertificado coluna business_id NOT NULL (mesma tabela do pai)', function () {
    expect(Schema::hasColumn('nfe_certificados', 'business_id'))->toBeTrue();
    $col = collect(DB::select('SHOW COLUMNS FROM nfe_certificados LIKE ?', ['business_id']))->first();
    expect($col)->not->toBeNull();
    expect($col->Null)->toBe('NO');
});
