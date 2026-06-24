<?php

declare(strict_types=1);

// @covers-us US-NFE-001 — configurar certificado A1: validação OpenSSL, storage encrypted at rest, rotação, isolamento multi-tenant (ADR 0303 covers-check).

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Services\CertificadoService;

uses(Tests\TestCase::class);

/**
 * US-NFE-041 · CertificadoService — tests cobrindo o pattern encryption +
 * validação CNPJ + isolamento multi-tenant.
 *
 * Tests usam Closure injection (`pkcs12Reader`) pra evitar precisar de .pfx
 * real — fixtures cobrem casos de borda sem dependência de openssl.
 *
 * Pattern dual-mode (PR #486 reference):
 *   - SQLite (CI sanity): drop+create isolado em :memory:
 *   - MySQL (Pest local — gate Wagner): preserva schema real;
 *     limpa rows biz=1/2 com FK_CHECKS=0 (cascateia em nfse_provider_configs.cert_id)
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('nfe_certificados');
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
    } elseif (Schema::hasTable('nfe_certificados')) {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('nfse_provider_configs')) {
            DB::table('nfse_provider_configs')->whereIn('business_id', [1, 2])->delete();
        }
        DB::table('nfe_certificados')->whereIn('business_id', [1, 2])->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    Storage::fake('local');
    Storage::fake('nfe_certs'); // CertificadoService usa disk nfe_certs (config/filesystems.php)
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('nfe_certificados');
    } elseif (Schema::hasTable('nfe_certificados')) {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('nfse_provider_configs')) {
            DB::table('nfse_provider_configs')->whereIn('business_id', [1, 2])->delete();
        }
        DB::table('nfe_certificados')->whereIn('business_id', [1, 2])->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
});

/**
 * Helper: gera "fake X.509 cert" + reader pra Closure injection.
 * Evita precisar de openssl_pkcs12_read real nos tests.
 */
function fakePfxReader(string $cnpj, string $validoAte = '+1 year'): Closure
{
    return function (string $content, string $senha) use ($cnpj, $validoAte) {
        if ($senha === 'wrong') {
            throw new InvalidArgumentException('Senha errada simulada');
        }
        // Mock do retorno de openssl_pkcs12_read
        $cert = sprintf(
            "-----BEGIN CERTIFICATE-----\nFAKE-CERT-FOR-%s\n-----END CERTIFICATE-----",
            $cnpj
        );
        return [
            'cert' => $cert,
            'pkey' => '-----BEGIN PRIVATE KEY-----\nFAKE\n-----END PRIVATE KEY-----',
        ];
    };
}

/**
 * Cria um service com leitor mocked + intercepta openssl_x509_parse via stub real.
 * Como openssl_x509_parse é função nativa, nos tests usamos um service derivado
 * que sobrescreve o parsing.
 */
function fakeService(string $cnpj, string $validoAteStr = '+1 year'): CertificadoService
{
    $reader = function (string $content, string $senha) use ($cnpj, $validoAteStr) {
        if ($senha === 'wrong') {
            throw new InvalidArgumentException('Senha errada simulada');
        }
        // Retornamos um array que faz openssl_x509_parse retornar dados consistentes.
        // Truque: usamos um cert auto-gerado em runtime pra que openssl_x509_parse funcione.
        return [
            'cert' => generateFakeX509($cnpj, $validoAteStr),
            'pkey' => '',
        ];
    };
    return new CertificadoService($reader);
}

/**
 * Gera um cert X.509 self-signed real em runtime — assim openssl_x509_parse
 * funciona. Cnpj entra no Subject CN no formato "EMPRESA TESTE:CNPJ".
 */
function generateFakeX509(string $cnpj, string $validoAteStr): string
{
    $config = [
        'private_key_bits' => 1024,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];
    $key = openssl_pkey_new($config);
    $dn = ['CN' => "EMPRESA TESTE:{$cnpj}"];
    $csr = openssl_csr_new($dn, $key);

    $days = (int) ((new DateTime())->diff(new DateTime($validoAteStr))->days
        * ((new DateTime($validoAteStr) > new DateTime()) ? 1 : -1));
    $cert = openssl_csr_sign($csr, null, $key, max($days, 1));

    openssl_x509_export($cert, $pem);
    return $pem;
}

it('valida cert válido + extrai CNPJ do CN', function () {
    $svc = fakeService('12345678000199', '+1 year');

    $meta = $svc->validar(base64_encode('any-pfx-content'), 'senha-correta');

    expect($meta['cnpj_titular'])->toBe('12345678000199')
        ->and($meta['subject_cn'])->toContain('12345678000199')
        ->and($meta['valido_ate'])->toBeInstanceOf(\DateTimeInterface::class)
        ->and($meta['valido_ate'] > new DateTime())->toBeTrue();
});

it('rejeita base64 inválido', function () {
    $svc = new CertificadoService(); // sem reader: vai chamar openssl_pkcs12_read real

    expect(fn () => $svc->validar('', 'senha'))
        ->toThrow(\InvalidArgumentException::class, 'base64 inválido');
});

it('rejeita senha errada (propaga InvalidArgumentException do reader)', function () {
    $svc = fakeService('12345678000199');

    expect(fn () => $svc->validar(base64_encode('any'), 'wrong'))
        ->toThrow(\InvalidArgumentException::class, 'Senha errada');
});

it('rejeita cert expirado', function () {
    // openssl_csr_sign nativo não aceita validade negativa (gera cert válido
    // por 1 dia mesmo com -2 days). Override parseCert pra simular cert
    // que SEFAZ rejeitaria. Usa anonymous class subclassing CertificadoService.
    $svc = new class extends CertificadoService {
        public function __construct() { parent::__construct(function ($content, $senha) {
            return ['cert' => '-----BEGIN CERTIFICATE-----\nFAKE\n-----END CERTIFICATE-----'];
        }); }
        protected function parseCert(string $certPem): array
        {
            return [
                'subject' => ['CN' => 'EMPRESA TESTE:12345678000199'],
                'validTo_time_t' => (new DateTimeImmutable('-2 days'))->getTimestamp(),
            ];
        }
    };

    expect(fn () => $svc->validar(base64_encode('any'), 'ok'))
        ->toThrow(\InvalidArgumentException::class, 'expirado');
});

it('salvar() persiste cert encrypted + senha encrypted + cria row em nfe_certificados', function () {
    $svc = fakeService('12345678000199', '+30 days');

    $cert = $svc->salvar(1, base64_encode('binary-pfx'), 'minha-senha');

    expect($cert)->toBeInstanceOf(NfeCertificado::class)
        ->and($cert->business_id)->toBe(1)
        ->and($cert->cnpj_titular)->toBe('12345678000199')
        ->and($cert->ativo)->toBeTrue()
        ->and($cert->uuid)->toBeString();

    // Senha encrypted — verifica roundtrip
    expect(Crypt::decryptString($cert->encrypted_password))->toBe('minha-senha');

    // Arquivo encrypted no storage — service usa disk 'nfe_certs' rooted em storage/app/nfe-certs
    $path = "1/cert/{$cert->uuid}.pfx.enc";
    expect(Storage::disk('nfe_certs')->exists($path))->toBeTrue();

    // Conteúdo do storage NÃO é o binary plain — é encrypted
    $stored = Storage::disk('nfe_certs')->get($path);
    expect($stored)->not()->toBe('binary-pfx');
    expect(Crypt::decrypt($stored))->toBe('binary-pfx');
});

it('salvar() rejeita cert com CNPJ ≠ CNPJ do business', function () {
    $svc = fakeService('11111111000111', '+1 year');

    expect(fn () => $svc->salvar(
        1,
        base64_encode('any'),
        'senha',
        ['cnpj_titular' => '99999999000199'], // business CNPJ diferente
    ))->toThrow(\InvalidArgumentException::class, 'não bate com CNPJ do business');
});

it('salvar() desativa cert anterior do mesmo business (rotação)', function () {
    $svc = fakeService('12345678000199', '+1 year');

    $cert1 = $svc->salvar(1, base64_encode('first'), 'pass1');
    expect($cert1->ativo)->toBeTrue();

    $cert2 = $svc->salvar(1, base64_encode('second'), 'pass2');

    expect($cert2->ativo)->toBeTrue()
        ->and($cert1->fresh()->ativo)->toBeFalse()
        ->and(NfeCertificado::where('business_id', 1)->where('ativo', true)->count())->toBe(1);
});

it('multi-tenant: cert do business A não vaza pro business B', function () {
    $svc = fakeService('11111111000111', '+1 year');

    $svc->salvar(1, base64_encode('biz-1-pfx'), 'pass-1');
    $svcB = fakeService('22222222000199', '+1 year');
    // biz=2 (semeado pelo pest-mysql-setup) em vez de biz=99: nfe_certificados tem FK
    // pra business(id); biz=99 não existe no seed → FK violation no MySQL (sqlite não
    // enforça FK, por isso só estourava no lane MySQL). Isolamento A↔B segue provado.
    $svcB->salvar(2, base64_encode('biz-2-pfx'), 'pass-2');

    $loadedA = $svc->carregarParaSefaz(1);
    $loadedB = $svcB->carregarParaSefaz(2);

    expect($loadedA['pfx_binary'])->toBe('biz-1-pfx')
        ->and($loadedA['senha'])->toBe('pass-1')
        ->and($loadedB['pfx_binary'])->toBe('biz-2-pfx')
        ->and($loadedB['senha'])->toBe('pass-2');
});

it('carregarParaSefaz() lança RuntimeException se business sem cert ativo', function () {
    $svc = new CertificadoService();

    expect(fn () => $svc->carregarParaSefaz(999))
        ->toThrow(\RuntimeException::class, 'não tem certificado A1 ativo');
});

it('verificarVencimento() retorna null quando sem cert', function () {
    $svc = new CertificadoService();
    expect($svc->verificarVencimento(999))->toBeNull();
});

it('verificarVencimento() retorna dias positivos quando válido', function () {
    $svc = fakeService('12345678000199', '+45 days');
    $svc->salvar(1, base64_encode('x'), 'p');

    $dias = $svc->verificarVencimento(1);

    expect($dias)->toBeInt()
        ->and($dias)->toBeGreaterThanOrEqual(44)
        ->and($dias)->toBeLessThanOrEqual(45);
});

it('verificarVencimento() retorna ≤30 quando próximo de vencer', function () {
    $svc = fakeService('12345678000199', '+15 days');
    $svc->salvar(1, base64_encode('x'), 'p');

    expect($svc->verificarVencimento(1))->toBeLessThanOrEqual(30);
});

it('senha NUNCA aparece em toArray() do model (defesa em profundidade)', function () {
    $svc = fakeService('12345678000199', '+1 year');
    $cert = $svc->salvar(1, base64_encode('x'), 'super-secret-password');

    $serialized = $cert->toArray();
    expect($serialized)->not()->toHaveKey('encrypted_password');

    // E claro, plain text também não
    $json = json_encode($cert);
    expect($json)->not()->toContain('super-secret-password');
});
