<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\CertificadoService;

uses(Tests\TestCase::class);

/**
 * ADR 0090 · Fallback do CertificadoService::carregarParaSefaz pra `business.*`
 * legado durante coexistência. Tests garantem que emissão atual continua
 * funcionando enquanto Wagner não migra explicitamente cada business.
 *
 * Pattern dual-mode (PR #486 reference):
 *   - SQLite (CI sanity): drop+create schemas sintéticos `business` + `nfe_certificados`
 *   - MySQL (Pest local — gate Wagner): SKIP — o teste cria `business` minimalista
 *     conflitando com schema UPos real (FKs em users, products, transactions etc).
 *     Cobertura genuína do legado fallback ocorre via integration tests E2E.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('CertificadoFallbackLegadoTest requer schema sintético — só roda em SQLite isolado');
    }

    foreach (['business', 'nfe_certificados'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('business', function ($table) {
        $table->increments('id'); // int unsigned (UltimatePOS legado)
        $table->string('name');
        $table->binary('certificado')->nullable(); // BLOB do .pfx legado
        $table->string('senha_certificado', 100)->nullable(); // base64-only legado
        $table->timestamps();
    });

    Schema::create('nfe_certificados', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->uuid('uuid')->unique();
        $table->string('cnpj_titular', 14)->index();
        $table->date('valido_ate')->index();
        $table->text('encrypted_password');
        $table->boolean('ativo')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        return;
    }

    foreach (['business', 'nfe_certificados'] as $t) {
        Schema::dropIfExists($t);
    }
});

it('lerCertLegado retorna null quando business sem cert', function () {
    DB::table('business')->insert([
        'id' => 1, 'name' => 'Sem Cert', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $svc = new CertificadoService();

    expect($svc->lerCertLegado(1))->toBeNull();
});

it('lerCertLegado decodifica senha base64 do legado', function () {
    DB::table('business')->insert([
        'id' => 1, 'name' => 'Com Cert',
        'certificado' => 'BINARY-PFX-CONTENT',
        'senha_certificado' => base64_encode('senha-real-123'),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $svc = new CertificadoService();
    $result = $svc->lerCertLegado(1);

    expect($result)->not()->toBeNull()
        ->and($result['pfx_binary'])->toBe('BINARY-PFX-CONTENT')
        ->and($result['senha'])->toBe('senha-real-123')
        ->and($result['source'])->toBe('business_legado');
});

it('lerCertLegado tolera senha sem base64 (string direta)', function () {
    DB::table('business')->insert([
        'id' => 1, 'name' => 'Cert Plain Pass',
        'certificado' => 'BINARY',
        // Senha NÃO em base64 — caso de configurações antigas mistas
        'senha_certificado' => 'abc123-plain',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $svc = new CertificadoService();
    $result = $svc->lerCertLegado(1);

    // base64_decode('abc123-plain') retorna lixo binário, não false.
    // Decisão: aceitar o que veio (base64_decode silencioso) — em prod,
    // todos os casos reais usam base64. Test documenta o comportamento.
    expect($result)->not()->toBeNull()
        ->and($result['pfx_binary'])->toBe('BINARY');
});

it('carregarParaSefaz cai no fallback quando nfe_certificados vazia', function () {
    DB::table('business')->insert([
        'id' => 1, 'name' => 'X',
        'certificado' => 'PFX-LEGADO',
        'senha_certificado' => base64_encode('senha-legado'),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $svc = new CertificadoService();
    $result = $svc->carregarParaSefaz(1);

    expect($result['source'])->toBe('business_legado')
        ->and($result['pfx_binary'])->toBe('PFX-LEGADO')
        ->and($result['senha'])->toBe('senha-legado');
});

it('carregarParaSefaz lança RuntimeException quando legado E novo ambos vazios', function () {
    DB::table('business')->insert([
        'id' => 4, 'name' => 'Sem Nada', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $svc = new CertificadoService();

    expect(fn () => $svc->carregarParaSefaz(1))
        ->toThrow(\RuntimeException::class, 'não tem certificado A1 ativo');
});

it('isolamento multi-tenant: fallback do biz A não vaza pra biz B', function () {
    DB::table('business')->insert([
        ['id' => 1, 'name' => 'A', 'certificado' => 'PFX-A',
         'senha_certificado' => base64_encode('senha-A'),
         'created_at' => now(), 'updated_at' => now()],
        ['id' => 99, 'name' => 'B', 'certificado' => 'PFX-B',
         'senha_certificado' => base64_encode('senha-B'),
         'created_at' => now(), 'updated_at' => now()],
    ]);

    $svc = new CertificadoService();

    expect($svc->carregarParaSefaz(1)['pfx_binary'])->toBe('PFX-A')
        ->and($svc->carregarParaSefaz(99)['pfx_binary'])->toBe('PFX-B');
});
