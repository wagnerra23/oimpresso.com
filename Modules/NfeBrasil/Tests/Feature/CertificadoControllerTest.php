<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Http\Controllers\CertificadoController;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Services\CertificadoService;

uses(Tests\TestCase::class);

/**
 * US-NFE-041 fase 2 — controller render Inertia.
 *
 * Garante o contrato pós-refactor JSON→Inertia (ADR 0029):
 *  - GET sem cert → component "NfeBrasil/Configuracao/Certificado" + tem_certificado=false
 *  - GET com cert ativo → tem_certificado=true + props completas
 *  - alerta = "ok" / "proximo_vencimento" / "vencido" conforme dias até vencer
 *
 * Pattern: insert direto na tabela (model::create) com data de vencimento
 * controlada — evita custo de gerar X509 real em runtime e isola a leitura
 * do controller. CertificadoServiceTest cobre o caminho via openssl.
 */

beforeEach(function () {
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

    Storage::fake('local');
});

afterEach(function () {
    Schema::dropIfExists('nfe_certificados');
});

/**
 * Helper: monta um Request com session de business pre-populada.
 */
function makeStatusRequest(int $businessId, ?string $cnpj = null): Request
{
    $request = Request::create('/nfe-brasil/configuracao/certificado', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('business.id', $businessId);
    $request->session()->put('business.tax_number_1', $cnpj);
    return $request;
}

/**
 * Insere uma row na nfe_certificados sem passar por openssl_csr_sign — útil
 * pra controlar a data de vencimento exata sem custo de gerar X509 real.
 */
function insertCertRow(int $businessId, string $cnpj, string $validoAte): NfeCertificado
{
    return NfeCertificado::create([
        'business_id'        => $businessId,
        'uuid'               => (string) \Illuminate\Support\Str::uuid(),
        'cnpj_titular'       => $cnpj,
        'valido_ate'         => $validoAte,
        'encrypted_password' => Crypt::encryptString('test-pass'),
        'ativo'              => true,
    ]);
}

/**
 * Extrai props da Inertia\Response. A API pública só monta quando o response
 * vai pra HTTP — pra teste unitário usamos reflection no `props` interno.
 */
function inertiaProps(\Inertia\Response $r): array
{
    $ref = new ReflectionClass($r);
    $prop = $ref->getProperty('props');
    $prop->setAccessible(true);
    return $prop->getValue($r);
}

function inertiaComponent(\Inertia\Response $r): string
{
    $ref = new ReflectionClass($r);
    $prop = $ref->getProperty('component');
    $prop->setAccessible(true);
    return $prop->getValue($r);
}

it('GET sem cert ativo: renderiza Inertia component com tem_certificado=false', function () {
    $controller = new CertificadoController(new CertificadoService());

    $response = $controller->status(makeStatusRequest(1,'12345678000199'));

    expect($response)->toBeInstanceOf(\Inertia\Response::class);
    expect(inertiaComponent($response))->toBe('NfeBrasil/Configuracao/Certificado');

    $props = inertiaProps($response);
    expect($props)->toHaveKey('tem_certificado')
        ->and($props['tem_certificado'])->toBeFalse()
        ->and($props['cnpj_business'])->toBe('12345678000199');
});

it('GET com cert ativo OK (>30d): tem_certificado=true + alerta=ok', function () {
    $validoAte = (new DateTimeImmutable('+90 days'))->format('Y-m-d');
    insertCertRow(1, '12345678000199', $validoAte);

    $controller = new CertificadoController(new CertificadoService());
    $response = $controller->status(makeStatusRequest(1,'12345678000199'));

    $props = inertiaProps($response);
    expect($props['tem_certificado'])->toBeTrue()
        ->and($props['cnpj_titular'])->toBe('12345678000199')
        ->and($props['alerta'])->toBe('ok')
        ->and($props['dias_ate_vencimento'])->toBeGreaterThan(30)
        ->and($props['valido_ate'])->toBe($validoAte);
});

it('GET com cert próximo do vencimento (≤30d): alerta=proximo_vencimento', function () {
    $validoAte = (new DateTimeImmutable('+15 days'))->format('Y-m-d');
    insertCertRow(1, '12345678000199', $validoAte);

    $controller = new CertificadoController(new CertificadoService());
    $response = $controller->status(makeStatusRequest(1,'12345678000199'));

    $props = inertiaProps($response);
    expect($props['tem_certificado'])->toBeTrue()
        ->and($props['alerta'])->toBe('proximo_vencimento')
        ->and($props['dias_ate_vencimento'])->toBeLessThanOrEqual(30)
        ->and($props['dias_ate_vencimento'])->toBeGreaterThanOrEqual(14);
});

it('GET com cert vencido: alerta=vencido + dias negativos', function () {
    $validoAte = (new DateTimeImmutable('-5 days'))->format('Y-m-d');
    insertCertRow(1, '12345678000199', $validoAte);

    $controller = new CertificadoController(new CertificadoService());
    $response = $controller->status(makeStatusRequest(1,'12345678000199'));

    $props = inertiaProps($response);
    expect($props['tem_certificado'])->toBeTrue()
        ->and($props['alerta'])->toBe('vencido')
        ->and($props['dias_ate_vencimento'])->toBeLessThan(0);
});

it('multi-tenant: cert do business 1 não vaza pra business 99', function () {
    insertCertRow(1, '11111111000111', (new DateTimeImmutable('+90 days'))->format('Y-m-d'));

    $controller = new CertificadoController(new CertificadoService());
    $response = $controller->status(makeStatusRequest(99, '99999999000199'));

    $props = inertiaProps($response);
    expect($props['tem_certificado'])->toBeFalse();
});

// ── testar() endpoint — botão "Testar conexão SEFAZ" (US-NFE-041 fase 2) ──

use Modules\NfeBrasil\Services\NfeService;

function makeTestarRequest(int $businessId): Request
{
    $request = Request::create('/nfe-brasil/configuracao/certificado/testar', 'POST');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('business.id', $businessId);
    return $request;
}

it('POST testar sem business.id na session → 400', function () {
    $controller = new CertificadoController(new CertificadoService());

    $request = Request::create('/nfe-brasil/configuracao/certificado/testar', 'POST');
    $request->setLaravelSession(app('session.store'));
    // session SEM business.id

    $nfeServiceMock = Mockery::mock(NfeService::class);
    $nfeServiceMock->shouldNotReceive('consultarStatusSefaz');

    $response = $controller->testar($request, $nfeServiceMock);

    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true))->toMatchArray([
        'ok'    => false,
        'error' => 'no_business_context',
    ]);
});

it('POST testar sem cert ativo → 422 sem chamar NfeService', function () {
    $controller = new CertificadoController(new CertificadoService());

    $nfeServiceMock = Mockery::mock(NfeService::class);
    $nfeServiceMock->shouldNotReceive('consultarStatusSefaz');

    $response = $controller->testar(makeTestarRequest(1), $nfeServiceMock);

    expect($response->getStatusCode())->toBe(422);
    expect($response->getData(true))->toMatchArray([
        'ok'    => false,
        'error' => 'sem_certificado',
    ]);
});

it('POST testar com cert + SEFAZ ok → 200 + payload completo', function () {
    insertCertRow(1, '12345678000199', (new DateTimeImmutable('+60 days'))->format('Y-m-d'));

    $controller = new CertificadoController(new CertificadoService());

    $nfeServiceMock = Mockery::mock(NfeService::class);
    $nfeServiceMock->shouldReceive('consultarStatusSefaz')->once()->with(1)->andReturn([
        'ok'            => true,
        'cstat'         => '107',
        'xMotivo'       => 'Servico em Operacao',
        'tempoResposta' => 0.42,
        'ambiente'      => 2,
        'uf'            => 'SC',
        'versao'        => 'SVRS_202604',
    ]);

    $response = $controller->testar(makeTestarRequest(1), $nfeServiceMock);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toMatchArray([
        'ok'      => true,
        'cstat'   => '107',
        'xMotivo' => 'Servico em Operacao',
        'uf'      => 'SC',
    ]);
});

it('POST testar com cert mas SEFAZ paralisado → 200 + ok=false', function () {
    insertCertRow(1, '12345678000199', (new DateTimeImmutable('+60 days'))->format('Y-m-d'));

    $controller = new CertificadoController(new CertificadoService());

    $nfeServiceMock = Mockery::mock(NfeService::class);
    $nfeServiceMock->shouldReceive('consultarStatusSefaz')->once()->andReturn([
        'ok'            => false,
        'cstat'         => '108',
        'xMotivo'       => 'Servico Paralisado Momentaneamente',
        'tempoResposta' => 0.30,
        'ambiente'      => 2,
        'uf'            => 'SC',
        'versao'        => null,
    ]);

    $response = $controller->testar(makeTestarRequest(1), $nfeServiceMock);

    // 200 porque SEFAZ respondeu — só que ok=false. Status HTTP de erro só pra
    // exception (502/500). Frontend usa `payload.ok` pra renderizar visual.
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toMatchArray([
        'ok'      => false,
        'cstat'   => '108',
        'xMotivo' => 'Servico Paralisado Momentaneamente',
    ]);
});

it('POST testar com NfeService lançando RuntimeException → 502 com UF + ambiente preenchidos', function () {
    insertCertRow(1, '12345678000199', (new DateTimeImmutable('+60 days'))->format('Y-m-d'));

    $controller = new CertificadoController(new CertificadoService());

    $nfeServiceMock = Mockery::mock(NfeService::class);
    $nfeServiceMock->shouldReceive('consultarStatusSefaz')
        ->andThrow(new \RuntimeException('cURL connection timeout'));

    $response = $controller->testar(makeTestarRequest(1), $nfeServiceMock);

    expect($response->getStatusCode())->toBe(502);

    $payload = $response->getData(true);
    expect($payload)->toMatchArray([
        'ok'    => false,
        'error' => 'sefaz_failure',
    ]);
    // Garante que payload de erro tem chaves esperadas pelo front (mesmo sem SEFAZ resposta)
    expect($payload)->toHaveKeys(['cstat', 'xMotivo', 'tempoResposta', 'ambiente', 'uf', 'versao']);
    expect($payload['cstat'])->toBe('—');
    expect($payload['versao'])->toBeNull();
});

it('POST testar com Throwable inesperado (TypeError) → 500 com payload completo', function () {
    insertCertRow(1, '12345678000199', (new DateTimeImmutable('+60 days'))->format('Y-m-d'));

    $controller = new CertificadoController(new CertificadoService());

    $nfeServiceMock = Mockery::mock(NfeService::class);
    $nfeServiceMock->shouldReceive('consultarStatusSefaz')
        ->andThrow(new \TypeError('Argument #1 must be int, string given'));

    $response = $controller->testar(makeTestarRequest(1), $nfeServiceMock);

    expect($response->getStatusCode())->toBe(500);

    $payload = $response->getData(true);
    expect($payload)->toMatchArray([
        'ok'    => false,
        'error' => 'unexpected',
    ]);
    expect($payload)->toHaveKeys(['cstat', 'xMotivo', 'tempoResposta', 'ambiente', 'uf', 'versao']);
    expect($payload['xMotivo'])->toContain('Erro inesperado');
});
