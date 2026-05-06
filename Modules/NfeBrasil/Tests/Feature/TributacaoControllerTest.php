<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Http\Controllers\TributacaoController;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeFiscalRule;

uses(Tests\TestCase::class);

/**
 * US-NFE-010 fase 2 · TributacaoController + ConfigDefaultController.
 *
 * Pattern: chama controllers direto com Request mock (mesmo padrão do
 * CertificadoControllerTest). Schema in-memory pra isolar do UPos DB.
 */

beforeEach(function () {
    Schema::dropIfExists('nfe_fiscal_rules');
    Schema::dropIfExists('nfe_business_configs');

    Schema::create('nfe_fiscal_rules', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->char('ncm', 8);
        $t->char('uf_origem', 2);
        $t->char('uf_destino', 2)->nullable();
        $t->char('cfop', 4);
        $t->char('csosn', 3)->nullable();
        $t->char('cst', 3)->nullable();
        $t->decimal('aliquota_icms', 7, 4)->default(0);
        $t->decimal('aliquota_pis', 7, 4)->default(0);
        $t->decimal('aliquota_cofins', 7, 4)->default(0);
        $t->decimal('aliquota_ipi', 7, 4)->default(0);
        $t->decimal('mva', 7, 4)->nullable();
        $t->decimal('fcp', 7, 4)->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('nfe_business_configs', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->unique();
        $t->enum('regime', ['mei', 'simples', 'lucro_presumido', 'lucro_real'])->default('simples');
        $t->json('tributacao_default');
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('nfe_fiscal_rules');
    Schema::dropIfExists('nfe_business_configs');
});

function makeIndexRequest(int $businessId): Request
{
    $request = Request::create('/nfe-brasil/tributacao', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('business.id', $businessId);
    return $request;
}

function inertiaPropsTributacao(\Inertia\Response $r): array
{
    $ref = new ReflectionClass($r);
    $prop = $ref->getProperty('props');
    $prop->setAccessible(true);
    return $prop->getValue($r);
}

function inertiaComponentTributacao(\Inertia\Response $r): string
{
    $ref = new ReflectionClass($r);
    $prop = $ref->getProperty('component');
    $prop->setAccessible(true);
    return $prop->getValue($r);
}

it('index() renderiza Inertia component com regras vazias e config null', function () {
    $controller = new TributacaoController();
    $response = $controller->index(makeIndexRequest(4));

    expect(inertiaComponentTributacao($response))->toBe('NfeBrasil/Tributacao/Index');

    $props = inertiaPropsTributacao($response);
    expect($props['regras'])->toBe([])
        ->and($props['config'])->toBeNull();
});

it('index() lista regras do business + config quando existem', function () {
    NfeFiscalRule::create([
        'business_id' => 4,
        'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => null,
        'cfop' => '5102', 'csosn' => '102',
        'aliquota_icms' => 0.18, 'aliquota_pis' => 0.0065,
        'aliquota_cofins' => 0.03, 'aliquota_ipi' => 0,
    ]);

    NfeBusinessConfig::create([
        'business_id' => 4,
        'regime' => 'simples',
        'tributacao_default' => ['ncm_default' => '49019900', 'cfop' => '5102', 'csosn' => '102'],
    ]);

    $response = (new TributacaoController())->index(makeIndexRequest(4));
    $props = inertiaPropsTributacao($response);

    expect($props['regras'])->toHaveCount(1)
        ->and($props['regras'][0]['ncm'])->toBe('49019900')
        ->and($props['regras'][0]['aliquota_icms'])->toBe(0.18)
        ->and($props['config']['regime'])->toBe('simples');
});

it('index() multi-tenant: regras do business 4 não vazam pro business 5', function () {
    NfeFiscalRule::create([
        'business_id' => 4,
        'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => null,
        'cfop' => '5102', 'csosn' => '102',
        'aliquota_icms' => 0.18, 'aliquota_pis' => 0,
        'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);

    $response = (new TributacaoController())->index(makeIndexRequest(5));
    $props = inertiaPropsTributacao($response);

    expect($props['regras'])->toBe([]);
});

it('create() renderiza form vazio (regra null)', function () {
    $response = (new TributacaoController())->create();

    expect(inertiaComponentTributacao($response))->toBe('NfeBrasil/Tributacao/RegraForm');
    expect(inertiaPropsTributacao($response)['regra'])->toBeNull();
});

it('edit() renderiza form com regra carregada', function () {
    $regra = NfeFiscalRule::create([
        'business_id' => 4,
        'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => 'RJ',
        'cfop' => '6102', 'csosn' => '102',
        'aliquota_icms' => 0.18, 'aliquota_pis' => 0,
        'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);

    $request = Request::create('/nfe-brasil/tributacao/regras/' . $regra->id . '/edit', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('business.id', 4);

    $response = (new TributacaoController())->edit($request, $regra->id);
    $props = inertiaPropsTributacao($response);

    expect($props['regra']['id'])->toBe($regra->id)
        ->and($props['regra']['ncm'])->toBe('49019900')
        ->and($props['regra']['uf_destino'])->toBe('RJ')
        ->and($props['regra']['aliquota_icms'])->toBe(0.18);
});

it('edit() de regra de outro business retorna 404 (multi-tenant)', function () {
    $regra = NfeFiscalRule::create([
        'business_id' => 999, // business diferente
        'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => null,
        'cfop' => '5102', 'csosn' => '102',
        'aliquota_icms' => 0, 'aliquota_pis' => 0,
        'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);

    $request = Request::create('/nfe-brasil/tributacao/regras/' . $regra->id . '/edit', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('business.id', 4); // tentando acessar como biz 4

    expect(fn () => (new TributacaoController())->edit($request, $regra->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('regras retornam ordenadas por NCM, UF origem, UF destino (NULL primeiro)', function () {
    NfeFiscalRule::create([
        'business_id' => 4, 'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => 'RJ',
        'cfop' => '6102', 'csosn' => '102',
        'aliquota_icms' => 0, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);
    NfeFiscalRule::create([
        'business_id' => 4, 'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => null,
        'cfop' => '5102', 'csosn' => '102',
        'aliquota_icms' => 0, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);
    NfeFiscalRule::create([
        'business_id' => 4, 'ncm' => '22021000', 'uf_origem' => 'SP', 'uf_destino' => null,
        'cfop' => '5102', 'csosn' => '102',
        'aliquota_icms' => 0, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);

    $response = (new TributacaoController())->index(makeIndexRequest(4));
    $props = inertiaPropsTributacao($response);

    expect($props['regras'][0]['ncm'])->toBe('22021000')
        ->and($props['regras'][1]['ncm'])->toBe('49019900')
        ->and($props['regras'][1]['uf_destino'])->toBeNull() // Null primeiro
        ->and($props['regras'][2]['uf_destino'])->toBe('RJ');
});
