<?php

declare(strict_types=1);

// @covers-us US-NFE-001 — endpoint de upload/status do certificado A1 (CertificadoController@upload/status).

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
 * Pattern dual-mode (PR #486 reference):
 *   - SQLite (CI sanity): drop+create isolado em :memory:
 *   - MySQL (Pest local — gate Wagner): preserva schema real;
 *     limpa rows biz=1/99 com FK_CHECKS=0 (cascateia em nfse_provider_configs.cert_id)
 *
 * Insert direto na tabela (model::create) com valido_ate controlado — evita custo
 * de gerar X509 real em runtime. CertificadoServiceTest cobre o caminho via openssl.
 */

beforeEach(function () {
    // SQLite CI: a maioria dos tests dependem de `business` (regime/cfop/csosn/ambiente)
    // que vive no schema UltimatePOS — não recriado em :memory:. Skip defensivo padrão
    // PR #475/#478. Pest local MySQL (gate Wagner) é o canal de cobertura real.
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('CertificadoControllerTest depende de schema UPos `business` — Pest local MySQL é o gate real');
    }

    if (Schema::hasTable('nfe_certificados')) {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('nfse_provider_configs')) {
            DB::table('nfse_provider_configs')->whereIn('business_id', [1, 99])->delete();
        }
        DB::table('nfe_certificados')->whereIn('business_id', [1, 99])->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    Storage::fake('local');
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasTable('nfe_certificados')) {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('nfse_provider_configs')) {
            DB::table('nfse_provider_configs')->whereIn('business_id', [1, 99])->delete();
        }
        DB::table('nfe_certificados')->whereIn('business_id', [1, 99])->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
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

// Wagner 2026-05-27 consolidação — tela GET /nfe-brasil/configuracao/certificado
// foi DEPRECATED. Conteúdo unificado em /fiscal/config (Fiscal/Config.tsx).
// `status()` agora retorna RedirectResponse 302 pra /fiscal/config.
// Tests de props/alerta/dias_ate_vencimento foram movidos pra cobertura indireta
// via Fiscal/ConfigController (que lê NfeCertificado direto) + NfeCertificado
// model tests (`diasAteVencimento()` puro).
it('GET status() retorna redirect 302 pra /fiscal/config (consolidação 2026-05-27)', function () {
    $controller = new CertificadoController(new CertificadoService());

    $response = $controller->status(makeStatusRequest(1, '12345678000199'));

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    expect($response->getStatusCode())->toBe(302);
    expect($response->getTargetUrl())->toContain('/fiscal/config');
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

// ── updateAmbiente() — selector ambiente SEFAZ (US-NFE-041 fase 3) ──

it('POST ambiente atualiza business.ambiente quando muda valor', function () {
    if (! Schema::hasTable('business')) {
        test()->markTestSkipped('business table indisponível');
    }

    $controller = new CertificadoController(new CertificadoService());

    $startAmbiente = (int) (\DB::table('business')->where('id', 1)->value('ambiente') ?? 2);

    $request = Request::create('/nfe-brasil/configuracao/certificado/ambiente', 'POST');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('business.id', 1);
    $request->merge(['ambiente' => $startAmbiente === 1 ? 2 : 1]);

    $response = $controller->updateAmbiente($request);

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);

    $novoAmbiente = (int) \DB::table('business')->where('id', 1)->value('ambiente');
    expect($novoAmbiente)->not->toBe($startAmbiente);

    // Restaura
    \DB::table('business')->where('id', 1)->update(['ambiente' => $startAmbiente]);
});

it('POST ambiente sem business.id → erro de validação back', function () {
    $controller = new CertificadoController(new CertificadoService());

    $request = Request::create('/nfe-brasil/configuracao/certificado/ambiente', 'POST');
    $request->setLaravelSession(app('session.store'));
    $request->merge(['ambiente' => 2]);

    $response = $controller->updateAmbiente($request);

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
});

it('POST ambiente com valor inválido (3) → ValidationException', function () {
    if (! Schema::hasTable('business')) {
        test()->markTestSkipped('business table indisponível');
    }

    $controller = new CertificadoController(new CertificadoService());

    $request = Request::create('/nfe-brasil/configuracao/certificado/ambiente', 'POST');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('business.id', 1);
    $request->merge(['ambiente' => 3]); // inválido

    expect(fn () => $controller->updateAmbiente($request))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

// Tests "GET status retorna painel fiscal completo" e "cnpj_titular vazio →
// fallback" removidos na consolidação Wagner 2026-05-27. A lógica que esses
// tests cobriam (`montarPainelFiscal` + alerta + cnpj_titular_fallback) vive
// em Modules\Fiscal\Http\Controllers\ConfigController::montarPainelFiscal +
// `index()`. Cobertura indireta via Fiscal/SidebarConsolidacaoTest +
// CertificadoServiceTest (chain de cert).

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
