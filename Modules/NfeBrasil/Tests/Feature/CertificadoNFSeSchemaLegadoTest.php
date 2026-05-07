<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\CertificadoService;

uses(Tests\TestCase::class);

/**
 * Testa o path de compatibilidade com o schema NFSe legado em nfe_certificados.
 *
 * Contexto: Módulo NFSe (Eliana, 2026-05-01) criou a tabela nfe_certificados
 * com colunas cert_pfx_encrypted/senha_encrypted antes do módulo NfeBrasil
 * (2026-05-06) adotar uuid + arquivo em disco. A migration de NfeBrasil é
 * idempotente (salta se tabela existe), então Hostinger ficou com schema antigo.
 *
 * CertificadoService::carregarParaSefaz() detecta o schema pelo campo uuid:
 *   - uuid presente → caminho novo (arquivo em disco)
 *   - cert_pfx_encrypted presente → caminho legado NFSe (inline DB)
 */

beforeEach(function () {
    Schema::dropIfExists('nfe_certificados');

    // Recria com schema LEGADO (NFSe / Eliana 2026-05-01)
    Schema::create('nfe_certificados', function ($table) {
        $table->increments('id');
        $table->integer('business_id')->unsigned()->index();
        $table->text('cert_pfx_encrypted');
        $table->string('senha_encrypted', 512);
        $table->date('valido_ate');
        $table->string('titular_cnpj', 18)->nullable();
        $table->string('titular_nome', 150)->nullable();
        $table->boolean('ativo')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });
});

afterEach(function () {
    Schema::dropIfExists('nfe_certificados');
});

it('carregarParaSefaz lê cert do schema NFSe legado (cert_pfx_encrypted)', function () {
    $pfxBin = 'FAKE-PFX-BINARY-CONTENT';
    $senha  = 'minha-senha-cert';

    DB::table('nfe_certificados')->insert([
        'business_id'        => 1,
        'cert_pfx_encrypted' => Crypt::encryptString(base64_encode($pfxBin)),
        'senha_encrypted'    => Crypt::encryptString($senha),
        'valido_ate'         => now()->addDays(90)->toDateString(),
        'titular_cnpj'       => null,
        'ativo'              => true,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    $svc    = new CertificadoService();
    $result = $svc->carregarParaSefaz(1);

    expect($result['pfx_binary'])->toBe($pfxBin)
        ->and($result['senha'])->toBe($senha)
        ->and($result['source'])->toBe('nfe_certificados_nfse_legado');
});

it('carregarParaSefaz ignora linha inativa e lança exceção', function () {
    DB::table('nfe_certificados')->insert([
        'business_id'        => 1,
        'cert_pfx_encrypted' => Crypt::encryptString(base64_encode('pfx')),
        'senha_encrypted'    => Crypt::encryptString('pass'),
        'valido_ate'         => now()->addDays(30)->toDateString(),
        'ativo'              => false, // inativo
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    $svc = new CertificadoService();

    expect(fn () => $svc->carregarParaSefaz(1))
        ->toThrow(\RuntimeException::class, 'não tem certificado A1 ativo');
});

it('multi-tenant: cert legado do biz 1 não vaza pro biz 2', function () {
    DB::table('nfe_certificados')->insert([
        [
            'business_id'        => 1,
            'cert_pfx_encrypted' => Crypt::encryptString(base64_encode('pfx-biz-1')),
            'senha_encrypted'    => Crypt::encryptString('senha-1'),
            'valido_ate'         => now()->addDays(90)->toDateString(),
            'ativo'              => true,
            'created_at'         => now(),
            'updated_at'         => now(),
        ],
        [
            'business_id'        => 2,
            'cert_pfx_encrypted' => Crypt::encryptString(base64_encode('pfx-biz-2')),
            'senha_encrypted'    => Crypt::encryptString('senha-2'),
            'valido_ate'         => now()->addDays(90)->toDateString(),
            'ativo'              => true,
            'created_at'         => now(),
            'updated_at'         => now(),
        ],
    ]);

    $svc = new CertificadoService();

    expect($svc->carregarParaSefaz(1)['pfx_binary'])->toBe('pfx-biz-1')
        ->and($svc->carregarParaSefaz(2)['pfx_binary'])->toBe('pfx-biz-2');
});
