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

/**
 * Cria a linha de cert direto no banco, com `valido_ate` a N dias de hoje
 * (N negativo = já vencido).
 *
 * NÃO usa `salvar()` de propósito: o `generateFakeX509` faz `max($days, 1)`,
 * então é incapaz de produzir um cert já vencido. E o cenário real é
 * exatamente este — o cert subiu VÁLIDO e venceu com a passagem do tempo,
 * sem ninguém tocar na linha (foi o que aconteceu em prod: biz=1, cert
 * cadastrado em 03/05, `valido_ate` 06/08).
 *
 * `valido_ate` é cast `date` e `diasAteVencimento()` compara contra
 * `now()->startOfDay()` — os dois zeram a hora, então o resultado é inteiro
 * exato, sem depender da hora em que a suíte roda.
 */
function certComVencimentoEmDias(int $businessId, int $dias): NfeCertificado
{
    return NfeCertificado::create([
        'business_id'        => $businessId,
        'uuid'               => (string) \Illuminate\Support\Str::uuid(),
        'cnpj_titular'       => '12345678000199',
        'valido_ate'         => now()->startOfDay()->addDays($dias)->format('Y-m-d'),
        'encrypted_password' => Crypt::encryptString('p'),
        'ativo'              => true,
    ]);
}

/**
 * GUARD US-NFE-001 — o aviso de cert VENCIDO na Sidebar.
 *
 * Os 4 testes abaixo travam o SINAL de `diasAteVencimento()`, que é o que
 * transforma um cert expirado no aviso "Certificado vencido — há N dias"
 * (`NfeCertBadge`). A cadeia é:
 *
 *   NfeCertificado::diasAteVencimento()  ← negativo quando vencido
 *     → CertificadoService::verificarVencimento()
 *       → HandleInertiaRequests::nfeCertStatus()  ← `$dias < 0` ⇒ 'vencido'
 *         → shell.nfe_cert_status → <NfeCertBadge/>
 *
 * O `nfeCertStatus()` só classifica como `vencido` quando `$dias < 0`. Perdido
 * o sinal, um cert expirado é lido como `ok`/`vencendo` e o badge some CALADO
 * — o pior desfecho possível, porque o aviso desaparece justamente quando o
 * problema existe, e ninguém fica sabendo.
 *
 * Vetores de regressão MEDIDOS (Carbon 3.11.4, a versão fixada no
 * composer.lock — sonda com cert vencido há 5 dias):
 *
 *   hoje->diffInDays(venc, false)  =  -5   ← canon, o que o model faz hoje
 *   hoje->diffInDays(venc, true)   =  +5   ← QUEBRA: absolute mata o sinal
 *   venc->diffInDays(hoje, false)  =  +5   ← QUEBRA: operandos invertidos
 *   hoje->diffInDays(venc)         =  -5   ← NÃO quebra (ver abaixo)
 *
 * ⚠️ Omitir o `false` NÃO é vetor no Carbon 3: ali o `$absolute` já é `false`
 * por default. Era `true` no Carbon 2, e é fácil (eu mesmo caí nisso ao
 * escrever este teste) supor a semântica antiga e "consertar" o model tirando
 * ou trocando o argumento. Os dois números que importam estão medidos acima —
 * confira contra a versão instalada antes de mexer, não contra a memória.
 *
 * Estes testes NÃO mudam comportamento — apenas fixam o que já funciona
 * corretamente em produção (Wagner confirmou o aviso em 11/08/2026).
 */
it('GUARD: cert vencido devolve dias NEGATIVOS (sinal preservado)', function () {
    certComVencimentoEmDias(1, -5);

    // Direto no model — sem service no meio, isola a aritmética.
    $cert = NfeCertificado::where('business_id', 1)->where('ativo', true)->first();

    expect($cert->diasAteVencimento())
        ->toBe(-5, 'cert vencido há 5 dias tem que devolver -5; +5 significa que o sinal se perdeu (diffInDays sem o `false`) e o badge vai silenciar');
});

it('GUARD: verificarVencimento() propaga o negativo até o service', function () {
    certComVencimentoEmDias(1, -5);

    $svc = new CertificadoService();

    // É este valor que o `nfeCertStatus()` compara com `< 0` pra decidir 'vencido'.
    expect($svc->verificarVencimento(1))->toBe(-5);
});

it('GUARD: fronteira — cert que vence HOJE é 0, ainda não é vencido', function () {
    certComVencimentoEmDias(1, 0);

    $svc = new CertificadoService();

    // 0 cai no ramo `<= 30` ⇒ 'vencendo' (avisa com antecedência), não em
    // `< 0` ⇒ 'vencido'. Trava a fronteira exata entre os dois avisos.
    expect($svc->verificarVencimento(1))->toBe(0);
});

it('GUARD: controle negativo — cert válido segue POSITIVO (sinal não invertido)', function () {
    certComVencimentoEmDias(1, 10);

    $svc = new CertificadoService();

    // Sem este controle, "negar o resultado" passaria nos 3 testes acima.
    expect($svc->verificarVencimento(1))->toBe(10);
});

/**
 * GUARD — o resto da cadeia até a Sidebar.
 *
 * Os 4 testes acima travam a aritmética. Estes 2 travam os dois elos que
 * faltavam, e que hoje NÃO são exercidos por lane nenhuma de CI:
 *
 *   - `tests/Unit/NfeCertStatusSharedPropTest.php` cobre o `nfeCertStatus()`
 *     com Service MOCKADA, mas não aparece no `.github/ci-sqlite-pest.list`,
 *     nem no `tier0-guards-advisory.yml`, nem na allowlist desta lane —
 *     ou seja, os 6 testes dele nunca rodam. Aqui roda, e sem mock.
 *   - a FIAÇÃO da shared prop não tinha teste algum: apagar a linha
 *     `'nfe_cert_status' => ...` do `HandleInertiaRequests::share()` derruba
 *     o badge em produção e deixa TODOS os outros testes verdes.
 */
it('GUARD: share() publica a chave nfe_cert_status no shell (fiação do badge)', function () {
    $request = \Illuminate\Http\Request::create('/');
    $request->setLaravelSession(app('session.store'));

    $shared = (new \App\Http\Middleware\HandleInertiaRequests())->share($request);

    // `toHaveKey` é o assert CERTO aqui: o mecanismo sob teste É a presença
    // da chave no contrato do shell (o `<NfeCertBadge/>` lê exatamente
    // `usePage().props.shell.nfe_cert_status`). Sem a chave, o badge nunca
    // renderiza — e é isso que este teste impede de acontecer calado.
    expect($shared)->toHaveKey('shell')
        ->and($shared['shell'])->toHaveKey('nfe_cert_status');
});

it('GUARD: nfeCertStatus() classifica cert REAL vencido como "vencido" (sem mock)', function () {
    certComVencimentoEmDias(1, -5);

    // Service REAL resolvida do container + linha REAL no banco — prova a
    // cadeia inteira do backend, não a concordância entre dois mocks.
    $middleware = new \App\Http\Middleware\HandleInertiaRequests();
    $metodo = (new ReflectionClass($middleware))->getMethod('nfeCertStatus');
    $metodo->setAccessible(true);

    expect($metodo->invoke($middleware, 1))->toBe([
        'status'         => 'vencido',
        'dias_restantes' => -5,
    ]);
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
