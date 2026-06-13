<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\RecurringBilling\Http\Controllers\ConfiguracoesController;
use Modules\RecurringBilling\Models\BoletoCredential;

uses(Tests\TestCase::class);

/**
 * Wave 8 — Page Inertia Configurações Cobrança Recorrente (Onda 8 v9,75).
 *
 * Cobre o ConfiguracoesController@index isoladamente:
 *   - Props canônicas (regua_dunning, nfe_auto, webhooks, gateways defer)
 *   - Multi-tenant Tier 0 (ADR 0093) — gateways biz=1 NÃO vazam pra biz=99
 *   - URLs webhooks corretas por business_id
 *   - Régua de dunning estrutura canônica (3 retentativas)
 *
 * Padrão: SQLite in-memory + Schema manual (mesmo AtualizarCobrancaAssinaturaTest).
 * Multi-tenant biz=1 (ADR 0101 — NUNCA biz=4 ROTA LIVRE).
 *
 * Resolve o Controller via container (sem HTTP roundtrip) e inspeciona a
 * Inertia\Response — evita falsos negativos do middleware UltimatePOS legacy
 * (authh + SetSessionData + CheckUserLogin) que dependem de UPos schema rico.
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite'
        || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')
    ) {
        $this->markTestSkipped('Wave 8 — Pest rodado apenas em SQLite in-memory.');
    }

    Schema::dropIfExists('rb_boleto_credentials');
    Schema::create('rb_boleto_credentials', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->unsignedBigInteger('conta_bancaria_id')->nullable();
        $table->string('banco', 20);
        $table->string('ambiente', 20)->default('production');
        $table->boolean('ativo')->default(true);
        $table->json('config_json')->nullable();
        $table->string('nome_display')->nullable();
        $table->timestamps();
    });

    // BoletoCredential usa LogsActivity (Spatie) — requer tabela activity_log
    // pra LGPD audit trail (D7). Stub mínimo schema-compatível.
    Schema::dropIfExists('activity_log');
    Schema::create('activity_log', function ($table) {
        $table->bigIncrements('id');
        $table->string('log_name')->nullable()->index();
        $table->text('description');
        $table->nullableMorphs('subject', 'subject');
        $table->nullableMorphs('causer', 'causer');
        $table->json('properties')->nullable();
        $table->uuid('batch_uuid')->nullable();
        $table->string('event')->nullable();
        $table->timestamps();
    });

    // Session: business_id=1 (ADR 0101 — Wagner biz superadmin, NUNCA biz=4 cliente).
    session(['user' => ['business_id' => 1]]);
});

afterEach(function () {
    // rb_boleto_credentials é real-migrada; o afterEach roda mesmo em teste pulado
    // (PHPUnit 12: tearDown gated só por hasMetRequirements), então dropá-la no MySQL
    // persistente corromperia testes irmãos do módulo. DDL só em sqlite :memory:.
    if (config('database.default') === 'sqlite'
        && str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        Schema::dropIfExists('rb_boleto_credentials');
    }
});

/**
 * Helper — resolve closure de prop `Inertia::defer` ou retorna valor eager.
 */
function rbResolveProp(mixed $prop): mixed
{
    if ($prop instanceof \Inertia\DeferProp) {
        // Inertia 2.x: chamar o callback registrado retorna o payload.
        return ($prop)();
    }

    if (is_callable($prop) && ! is_string($prop) && ! is_array($prop)) {
        return $prop();
    }

    return $prop;
}

/**
 * Cenário 1 — Controller render Inertia 'RecurringBilling/Configuracoes/Index'
 * com as 4 props canônicas (regua_dunning + nfe_auto + webhooks eager; gateways defer).
 */
it('renderiza Page Inertia com componente correto e props canônicas', function () {
    $controller = app(ConfiguracoesController::class);
    $response   = $controller->index(request());

    expect($response)->toBeInstanceOf(\Inertia\Response::class);

    $reflection = new ReflectionClass($response);
    $compProp   = $reflection->getProperty('component');
    $compProp->setAccessible(true);
    $propsProp = $reflection->getProperty('props');
    $propsProp->setAccessible(true);

    $component = $compProp->getValue($response);
    $props     = $propsProp->getValue($response);

    expect($component)->toBe('RecurringBilling/Configuracoes/Index');
    expect($props)->toHaveKeys(['regua_dunning', 'nfe_auto', 'webhooks', 'gateways']);
});

/**
 * Cenário 2 — Régua de dunning canônica v1 (3 retentativas: +3d, +7d, +15d).
 */
it('expõe régua de dunning canônica com 3 retentativas estruturadas', function () {
    $controller = app(ConfiguracoesController::class);
    $response   = $controller->index(request());

    $reflection = new ReflectionClass($response);
    $propsProp  = $reflection->getProperty('props');
    $propsProp->setAccessible(true);
    $props = $propsProp->getValue($response);

    $regua = $props['regua_dunning'];

    expect($regua)->toBeArray()
        ->toHaveKeys(['descricao', 'retentativas', 'editavel_em']);

    expect($regua['retentativas'])->toHaveCount(3);

    $dias = array_map(fn ($r) => $r['dias'], $regua['retentativas']);
    expect($dias)->toBe([3, 7, 15]);

    $severidades = array_map(fn ($r) => $r['severidade'], $regua['retentativas']);
    expect($severidades)->toBe(['info', 'warn', 'bad']);

    // Cada retentativa tem rotulo + descricao não vazios.
    foreach ($regua['retentativas'] as $r) {
        expect($r)->toHaveKeys(['ordem', 'dias', 'rotulo', 'descricao', 'severidade']);
        expect($r['rotulo'])->not->toBeEmpty();
        expect($r['descricao'])->not->toBeEmpty();
    }
});

/**
 * Cenário 3 — Webhooks URLs canônicas com business_id correto na URL.
 */
it('expõe webhooks Asaas e Inter PJ com URL scopada por business_id', function () {
    $controller = app(ConfiguracoesController::class);
    $response   = $controller->index(request());

    $reflection = new ReflectionClass($response);
    $propsProp  = $reflection->getProperty('props');
    $propsProp->setAccessible(true);
    $props = $propsProp->getValue($response);

    $webhooks = $props['webhooks'];

    expect($webhooks)->toHaveCount(2);

    $gateways = array_map(fn ($w) => $w['gateway'], $webhooks);
    expect($gateways)->toContain('asaas');
    expect($gateways)->toContain('inter');

    $asaas = collect($webhooks)->firstWhere('gateway', 'asaas');
    $inter = collect($webhooks)->firstWhere('gateway', 'inter');

    expect($asaas['url'])->toContain('/webhooks/asaas/1');
    expect($asaas['metodo'])->toBe('POST');
    expect($asaas['docs_link'])->toContain('asaas.com');

    expect($inter['url'])->toContain('/webhooks/inter/pix/1');
    expect($inter['metodo'])->toBe('POST');
    expect($inter['docs_link'])->toContain('bancointer.com.br');
});

/**
 * Cenário 4 — Multi-tenant Tier 0: gateways biz=1 NÃO vazam pra biz=99.
 * Cross-tenant isolation via HasBusinessScope automático em BoletoCredential.
 */
it('aplica multi-tenant Tier 0 — gateways biz=1 não aparecem pra biz=99', function () {
    // Cria 3 credenciais: 2 biz=1, 1 biz=99 (vazamento se scope falhar).
    BoletoCredential::create([
        'business_id'  => 1,
        'banco'        => 'inter',
        'ambiente'     => 'production',
        'ativo'        => true,
        'config_json'  => ['client_secret' => 'enc'],
        'nome_display' => 'Inter PJ biz1',
    ]);
    BoletoCredential::create([
        'business_id'  => 1,
        'banco'        => 'asaas',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => ['api_key' => 'enc'],
        'nome_display' => 'Asaas biz1 sandbox',
    ]);
    BoletoCredential::create([
        'business_id'  => 99,
        'banco'        => 'c6',
        'ambiente'     => 'production',
        'ativo'        => true,
        'config_json'  => ['api_key' => 'enc'],
        'nome_display' => 'C6 biz99 (NÃO DEVE VAZAR)',
    ]);

    // Session = biz=1 (ADR 0101)
    session(['user' => ['business_id' => 1]]);

    $controller = app(ConfiguracoesController::class);
    $response   = $controller->index(request());

    $reflection = new ReflectionClass($response);
    $propsProp  = $reflection->getProperty('props');
    $propsProp->setAccessible(true);
    $props = $propsProp->getValue($response);

    // Resolve a defer prop pra inspecionar o payload real.
    $gateways = rbResolveProp($props['gateways']);

    expect($gateways)->toBeArray()->toHaveCount(2);

    $bancos = array_map(fn ($g) => $g['banco'], $gateways);
    expect($bancos)->toContain('inter');
    expect($bancos)->toContain('asaas');
    expect($bancos)->not->toContain('c6'); // vazamento bloqueado

    foreach ($gateways as $g) {
        expect($g)->toHaveKeys([
            'id', 'banco', 'banco_label', 'ambiente', 'ambiente_label',
            'ativo', 'nome_display', 'conta_bancaria_id', 'criado_em',
        ]);
        // Garantia explícita — nome_display da biz=99 NÃO está aqui
        expect($g['nome_display'])->not->toContain('NÃO DEVE VAZAR');
    }
});

/**
 * Cenário 5 — Webhooks refletem o business_id da session corrente.
 * (Reforça cenário 3 — protege contra regressão de scope na URL helper.)
 */
it('URLs de webhook refletem o business_id da session ativa', function () {
    session(['user' => ['business_id' => 7]]);

    $controller = app(ConfiguracoesController::class);
    $response   = $controller->index(request());

    $reflection = new ReflectionClass($response);
    $propsProp  = $reflection->getProperty('props');
    $propsProp->setAccessible(true);
    $props = $propsProp->getValue($response);

    $asaas = collect($props['webhooks'])->firstWhere('gateway', 'asaas');
    $inter = collect($props['webhooks'])->firstWhere('gateway', 'inter');

    expect($asaas['url'])->toContain('/webhooks/asaas/7');
    expect($inter['url'])->toContain('/webhooks/inter/pix/7');
});
