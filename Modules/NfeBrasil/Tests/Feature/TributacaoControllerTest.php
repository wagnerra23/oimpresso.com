<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — CRUD de regras tributárias (index/create/edit) + ordenação + isolamento multi-tenant 404.

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Http\Controllers\TributacaoController;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeFiscalRule;

uses(Tests\TestCase::class);

/**
 * US-NFE-010 fase 2 · TributacaoController + ConfigDefaultController.
 *
 * Pattern dual-mode (PR #486 reference):
 *   - SQLite (CI sanity): drop+create isolado em :memory:
 *   - MySQL (Pest local — gate Wagner): preserva schema real;
 *     limpa rows biz=1/99 com FK_CHECKS=0 (cascateia em
 *     nfe_fiscal_rule_tax_rate_links.fiscal_rule_id)
 *
 * Event::fake do bridge listener `SyncFiscalRuleToTaxRate` (ADR ARQ-0005)
 * pra isolar tests do controller das tax_rates derivadas — listener tem
 * cobertura própria em SyncFiscalRuleToTaxRateTest.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
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
    } else {
        if (Schema::hasTable('nfe_fiscal_rules')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
                DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('business_id', [1, 99, 999])->delete();
            }
            DB::table('nfe_fiscal_rules')->whereIn('business_id', [1, 99, 999])->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        if (Schema::hasTable('nfe_business_configs')) {
            DB::table('nfe_business_configs')->whereIn('business_id', [1, 99, 999])->delete();
        }
    }

    Event::fake([FiscalRuleCreated::class, FiscalRuleUpdated::class, FiscalRuleDeleted::class]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('nfe_fiscal_rules');
        Schema::dropIfExists('nfe_business_configs');
    } else {
        if (Schema::hasTable('nfe_fiscal_rules')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
                DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('business_id', [1, 99, 999])->delete();
            }
            DB::table('nfe_fiscal_rules')->whereIn('business_id', [1, 99, 999])->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        if (Schema::hasTable('nfe_business_configs')) {
            DB::table('nfe_business_configs')->whereIn('business_id', [1, 99, 999])->delete();
        }
    }
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
    $props = $prop->getValue($r);

    // Wave 25 D3: `regras` + `templates` agora são Inertia::defer(closure).
    // Pra preservar contrato do test (lê valor resolvido), resolve closures
    // que sejam Inertia DeferProp OU LazyProp (fallback) OU Closure direto.
    foreach ($props as $key => $value) {
        if ($value instanceof \Closure) {
            $props[$key] = $value();
        } elseif (is_object($value)) {
            // Inertia\DeferProp + LazyProp armazenam callable em "callback"
            $rv = new ReflectionClass($value);
            if ($rv->hasProperty('callback')) {
                $cb = $rv->getProperty('callback');
                $cb->setAccessible(true);
                $callable = $cb->getValue($value);
                if (is_callable($callable)) {
                    $props[$key] = $callable();
                }
            }
        }
    }

    return $props;
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
    $response = $controller->index(makeIndexRequest(1));

    expect(inertiaComponentTributacao($response))->toBe('NfeBrasil/Tributacao/Index');

    $props = inertiaPropsTributacao($response);
    expect($props['regras'])->toBe([])
        ->and($props['config'])->toBeNull();
});

it('index() lista regras do business + config quando existem', function () {
    NfeFiscalRule::create([
        'business_id' => 1,
        'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => null,
        'cfop' => '5102', 'csosn' => '102',
        'aliquota_icms' => 0.18, 'aliquota_pis' => 0.0065,
        'aliquota_cofins' => 0.03, 'aliquota_ipi' => 0,
    ]);

    NfeBusinessConfig::create([
        'business_id' => 1,
        'regime' => 'simples',
        'tributacao_default' => ['ncm_default' => '49019900', 'cfop' => '5102', 'csosn' => '102'],
    ]);

    $response = (new TributacaoController())->index(makeIndexRequest(1));
    $props = inertiaPropsTributacao($response);

    expect($props['regras'])->toHaveCount(1)
        ->and($props['regras'][0]['ncm'])->toBe('49019900')
        ->and($props['regras'][0]['aliquota_icms'])->toBe(0.18)
        ->and($props['config']['regime'])->toBe('simples');
});

it('index() multi-tenant: regras do business 1 não vazam pro business 99', function () {
    NfeFiscalRule::create([
        'business_id' => 1,
        'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => null,
        'cfop' => '5102', 'csosn' => '102',
        'aliquota_icms' => 0.18, 'aliquota_pis' => 0,
        'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);

    $response = (new TributacaoController())->index(makeIndexRequest(99));
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
        'business_id' => 1,
        'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => 'RJ',
        'cfop' => '6102', 'csosn' => '102',
        'aliquota_icms' => 0.18, 'aliquota_pis' => 0,
        'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);

    $request = Request::create('/nfe-brasil/tributacao/regras/' . $regra->id . '/edit', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('business.id', 1);

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
    $request->session()->put('business.id', 1); // tentando acessar como biz 1 (Wagner)

    expect(fn () => (new TributacaoController())->edit($request, $regra->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('regras retornam ordenadas por NCM, UF origem, UF destino (NULL primeiro)', function () {
    NfeFiscalRule::create([
        'business_id' => 1, 'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => 'RJ',
        'cfop' => '6102', 'csosn' => '102',
        'aliquota_icms' => 0, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);
    NfeFiscalRule::create([
        'business_id' => 1, 'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => null,
        'cfop' => '5102', 'csosn' => '102',
        'aliquota_icms' => 0, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);
    NfeFiscalRule::create([
        'business_id' => 1, 'ncm' => '22021000', 'uf_origem' => 'SP', 'uf_destino' => null,
        'cfop' => '5102', 'csosn' => '102',
        'aliquota_icms' => 0, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);

    $response = (new TributacaoController())->index(makeIndexRequest(1));
    $props = inertiaPropsTributacao($response);

    expect($props['regras'][0]['ncm'])->toBe('22021000')
        ->and($props['regras'][1]['ncm'])->toBe('49019900')
        ->and($props['regras'][1]['uf_destino'])->toBeNull() // Null primeiro
        ->and($props['regras'][2]['uf_destino'])->toBe('RJ');
});
