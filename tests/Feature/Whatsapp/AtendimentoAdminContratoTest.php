<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\DeferProp;
use Modules\Whatsapp\Http\Controllers\Admin\CsatController;
use Modules\Whatsapp\Http\Controllers\Admin\MetricsController;
use Modules\Whatsapp\Http\Controllers\Admin\SettingsController;
use Modules\Whatsapp\Http\Controllers\Admin\TemplatesController;

uses(Tests\TestCase::class);

/**
 * Contrato das 4 telas de administração do Atendimento (playbook atendimento/04):
 * Csat · Metricas · Whatsapp/Settings · Whatsapp/Templates. Cada `it()` cita o UC do
 * `.casos.md` ao lado da Page. UCs derivados do charter, não do `.tsx`.
 *
 * DB-less por desenho (lane sqlite `.github/ci-sqlite-pest.list`): as consultas rodam
 * dentro de `DB::pretend()`, que registra o SQL e as bindings sem executar. É o que
 * permite provar o filtro de tenant (ADR 0093) sem schema. A exceção é UC-WSET-01, que
 * monta uma tabela mínima em sqlite :memory: para provar com DADO que o token não sai.
 * Tenant fictício 98 (ADR 0358) — biz=4 nunca.
 */
if (! defined('ATD04_BIZ')) {
    define('ATD04_BIZ', 98);
}

if (! function_exists('atd04Request')) {
    function atd04Request(string $uri, array $params = [], string $method = 'GET'): Request
    {
        $request = Request::create($uri, $method, $params);
        $store = app('session.store');
        $store->put('user', ['business_id' => ATD04_BIZ]);
        $request->setLaravelSession($store);

        return $request;
    }

    /** @return array<string, mixed> props da Inertia\Response (eager + DeferProp) */
    function atd04Props(object $response): array
    {
        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty('props');
        $prop->setAccessible(true);

        return $prop->getValue($response);
    }

    /** Resolve as props adiadas dentro de pretend e devolve as queries registradas. */
    function atd04DeferredQueries(array $props, array $keys): array
    {
        return DB::pretend(function () use ($props, $keys) {
            foreach ($keys as $k) {
                expect($props[$k])->toBeInstanceOf(DeferProp::class);
                ($props[$k])();
            }
        });
    }
}

beforeEach(function () {
    session(['user' => ['business_id' => ATD04_BIZ]]);
});

// ─── CSAT ──────────────────────────────────────────────────────────────────

it('UC-ACSAT-01 · KPIs, distribuição e últimas respostas filtram o business da sessão', function () {
    $props = atd04Props((new CsatController)->index(atd04Request('/atendimento/csat')));
    $queries = atd04DeferredQueries($props, ['kpis', 'distribution', 'recent']);

    $csat = array_filter($queries, fn ($q) => str_contains($q['query'], 'whatsapp_csat_responses'));
    expect(count($csat))->toBeGreaterThan(0); // não-vácuo
    foreach ($csat as $q) {
        expect($q['query'])->toContain('business_id');
        expect(in_array(ATD04_BIZ, $q['bindings'], true))->toBeTrue();
    }
});

it('UC-ACSAT-02 · range fora da whitelist cai no default 30', function () {
    $c = new CsatController;
    expect(atd04Props($c->index(atd04Request('/atendimento/csat', ['range' => '7'])))['range'])->toBe(7);
    expect(atd04Props($c->index(atd04Request('/atendimento/csat', ['range' => '999'])))['range'])->toBe(30);
    expect(atd04Props($c->index(atd04Request('/atendimento/csat')))['range'])->toBe(30);
});

it('UC-ACSAT-03 · render inicial não consulta e as props pesadas são adiadas e só leitura', function () {
    $response = null;
    $render = DB::pretend(function () use (&$response) {
        $response = (new CsatController)->index(atd04Request('/atendimento/csat'));
    });
    expect($render)->toBe([]);

    $queries = atd04DeferredQueries(atd04Props($response), ['kpis', 'distribution', 'recent']);
    expect(count($queries))->toBeGreaterThan(0);
    foreach ($queries as $q) {
        expect(strtolower(ltrim($q['query'])))->toStartWith('select');
    }
});

// ─── MÉTRICAS ──────────────────────────────────────────────────────────────

it('UC-AMET-01 · totais, série e breakdown filtram o business da sessão', function () {
    $props = atd04Props((new MetricsController)->index(atd04Request('/atendimento/metricas')));
    $queries = atd04DeferredQueries($props, ['aggregated', 'breakdown']);

    expect(count($queries))->toBeGreaterThan(0);
    foreach ($queries as $q) {
        expect($q['query'])->toContain('business_id');
        expect(in_array(ATD04_BIZ, $q['bindings'], true))->toBeTrue();
    }
});

it('UC-AMET-02 · lê só o snapshot agregado e os rótulos de canal', function () {
    $props = atd04Props((new MetricsController)->index(atd04Request('/atendimento/metricas')));
    $queries = atd04DeferredQueries($props, ['aggregated', 'breakdown']);

    expect(count($queries))->toBeGreaterThan(0);
    foreach ($queries as $q) {
        preg_match('/\bfrom\s+["`]?([a-z_]+)/i', $q['query'], $m);
        expect($m[1] ?? null)->toBeIn(['whatsapp_conversation_metricas', 'channels']);
    }
});

it('UC-AMET-03 · período whitelisted e props pesadas adiadas', function () {
    $c = new MetricsController;
    expect(atd04Props($c->index(atd04Request('/atendimento/metricas', ['range' => '7'])))['range'])->toBe(7);
    expect(atd04Props($c->index(atd04Request('/atendimento/metricas', ['range' => '45'])))['range'])->toBe(30);

    $render = DB::pretend(fn () => $c->index(atd04Request('/atendimento/metricas')));
    expect($render)->toBe([]);
    $props = atd04Props($c->index(atd04Request('/atendimento/metricas')));
    expect($props['aggregated'])->toBeInstanceOf(DeferProp::class);
    expect($props['breakdown'])->toBeInstanceOf(DeferProp::class);
});

// ─── SETTINGS (Meta Embedded Signup) ───────────────────────────────────────

it('UC-WSET-01 · mostra a conexão do business da sessão e nunca o token', function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Monta tabela mínima só em sqlite :memory: (lane ci-sqlite-pest).');
    }
    Schema::dropIfExists('whatsapp_business_configs');
    Schema::create('whatsapp_business_configs', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('business_id');
        foreach (['business_uuid', 'driver', 'display_phone', 'meta_waba_id', 'driver_health'] as $col) {
            $t->string($col)->nullable();
        }
        $t->text('meta_access_token')->nullable();
        $t->timestamp('last_health_check_at')->nullable();
        $t->timestamps();
    });
    foreach ([ATD04_BIZ => '+5548999000098', 99 => '+5548999000099'] as $biz => $phone) {
        DB::table('whatsapp_business_configs')->insert([
            'business_id' => $biz, 'driver' => 'meta_cloud', 'display_phone' => $phone,
            'meta_waba_id' => "WABA_{$biz}", 'driver_health' => 'healthy',
            'meta_access_token' => "EAA_TOKEN_SECRETO_{$biz}",
        ]);
    }

    $props = atd04Props((new SettingsController)->settings(atd04Request('/whatsapp/settings')));
    $json = json_encode($props);

    expect($props['currentConfig']['display_phone'])->toBe('+5548999000098');
    expect(str_contains($json, '+5548999000099'))->toBeFalse();
    expect(str_contains($json, 'EAA_TOKEN_SECRETO'))->toBeFalse();
    expect(str_contains($json, 'access_token'))->toBeFalse();

    Schema::dropIfExists('whatsapp_business_configs');
});

it('UC-WSET-02 · sem Meta App a tela recebe vazio e o init devolve 503', function () {
    config()->set('whatsapp.meta.app_id', '');
    config()->set('whatsapp.meta.business_config_id', '');

    $response = null;
    DB::pretend(function () use (&$response) {
        $response = (new SettingsController)->settings(atd04Request('/whatsapp/settings'));
    });
    expect(atd04Props($response)['metaAppId'])->toBe('');

    $req = atd04Request('/whatsapp/settings/meta-oauth-init');
    $req->session()->forget('whatsapp_oauth_state');
    $init = (new SettingsController)->metaOauthInit($req);
    expect($init->getStatusCode())->toBe(503);
    expect($init->getData(true)['error'])->toBe('meta_app_not_configured');
    expect($req->session()->has('whatsapp_oauth_state'))->toBeFalse();
});

it('UC-WSET-03 · init grava state de 64 hex e o popup carrega o mesmo state', function () {
    config()->set('whatsapp.meta.app_id', 'APP_ATD04');
    config()->set('whatsapp.meta.business_config_id', 'CFG_ATD04');
    config()->set('whatsapp.meta.api_version', 'v21.0');

    $req = atd04Request('/whatsapp/settings/meta-oauth-init');
    $data = (new SettingsController)->metaOauthInit($req)->getData(true);

    expect($data['state'])->toMatch('/^[0-9a-f]{64}$/');
    expect($req->session()->get('whatsapp_oauth_state'))->toBe($data['state']);
    expect($data['url'])->toContain('state='.$data['state']);
    expect($data['url'])->toContain('client_id=APP_ATD04');
});

it('UC-WSET-04 · rotas exigem auth + can:whatsapp.settings.manage e o callback é POST', function () {
    foreach (['whatsapp.settings.show', 'whatsapp.settings.meta.oauth_init', 'whatsapp.settings.meta.embedded_callback'] as $name) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull();
        expect($route->middleware())->toContain('auth');
        expect($route->middleware())->toContain('can:whatsapp.settings.manage');
    }
    expect(Route::getRoutes()->getByName('whatsapp.settings.meta.embedded_callback')->methods())->toBe(['POST']);
});

// ─── TEMPLATES ─────────────────────────────────────────────────────────────

it('UC-WTPL-01 · lista filtra o business da sessão via global scope e a rota exige auth', function () {
    $this->actingAs(new GenericUser(['id' => 9801]));
    $props = atd04Props((new TemplatesController)->index(atd04Request('/whatsapp/templates')));
    $queries = atd04DeferredQueries($props, ['templates']);

    expect(count($queries))->toBe(1);
    expect($queries[0]['query'])->toContain('business_id');
    expect(in_array(ATD04_BIZ, $queries[0]['bindings'], true))->toBeTrue();
    expect(Route::getRoutes()->getByName('whatsapp.templates.index')->middleware())->toContain('auth');
});

it('UC-WTPL-02 · filtro de provedor e status chega na consulta, all não filtra', function () {
    $this->actingAs(new GenericUser(['id' => 9801]));
    $c = new TemplatesController;

    $filtrado = atd04DeferredQueries(
        atd04Props($c->index(atd04Request('/whatsapp/templates', ['provider' => 'meta_cloud', 'status' => 'APPROVED']))),
        ['templates'],
    );
    expect($filtrado[0]['bindings'])->toContain('meta_cloud');
    expect($filtrado[0]['bindings'])->toContain('APPROVED');

    $todos = atd04DeferredQueries(atd04Props($c->index(atd04Request('/whatsapp/templates'))), ['templates']);
    expect(str_contains($todos[0]['query'], 'provider'))->toBeFalse();
    expect(str_contains($todos[0]['query'], 'status'))->toBeFalse();
});

it('UC-WTPL-03 · abrir só lê, sync é POST separado e a rota exige whatsapp.templates.manage', function () {
    $this->actingAs(new GenericUser(['id' => 9801]));
    $queries = atd04DeferredQueries(atd04Props((new TemplatesController)->index(atd04Request('/whatsapp/templates'))), ['templates']);
    foreach ($queries as $q) {
        expect(strtolower(ltrim($q['query'])))->toStartWith('select');
    }

    expect(Route::getRoutes()->getByName('whatsapp.templates.sync_meta')->methods())->toBe(['POST']);
    foreach (['whatsapp.templates.index', 'whatsapp.templates.sync_meta'] as $name) {
        expect(Route::getRoutes()->getByName($name)->middleware())->toContain('can:whatsapp.templates.manage');
    }
});
