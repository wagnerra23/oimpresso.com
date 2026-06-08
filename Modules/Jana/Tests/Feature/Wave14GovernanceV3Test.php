<?php

declare(strict_types=1);

use Modules\Jana\Http\Requests\StoreMetaRequest;
use Modules\Jana\Http\Requests\UpdateMetaRequest;

uses(Tests\TestCase::class);

/**
 * Wave 14 — Governance v3 (Jana 66 → 72+).
 *
 * Cobre as adições da Wave 14 sem dependência de Schema/DB:
 *  D8.c FormRequests:
 *   001. StoreMetaRequest aceita payload válido (rules + whitelist)
 *   002. StoreMetaRequest rejeita slug com caracteres inválidos (anti-injection)
 *   003. StoreMetaRequest rejeita unidade fora da whitelist (fail-secure)
 *   004. StoreMetaRequest exige todos os campos required
 *   005. UpdateMetaRequest aceita partial update (sometimes)
 *   006. UpdateMetaRequest valida enum quando campo presente
 *
 *  D9.a OtelHelper:
 *   007. AlertaService importa OtelHelper (instrumentado)
 *   008. GovernancaService importa OtelHelper (instrumentado)
 *
 *  D6.a Inertia::defer:
 *   009. PainelController usa Inertia::defer no payload `painel`
 *   010. DashboardController usa Inertia::defer no payload `metas`
 *
 *  D8.a throttle:
 *   011. routes.php Jana group declara throttle:120,1
 *   012. routes.php mensagens.stream declara throttle:60,1 (custo LLM)
 *
 * Multi-tenant Tier 0 (ADR 0093) — FormRequests não tocam DB; validação pura.
 * OTel zero-cost quando disabled (default test env) — não dispara sampler.
 */

// ---------- D8.c FormRequest tests ----------

it('001. StoreMetaRequest aceita payload válido', function () {
    $request = StoreMetaRequest::create('/ia/metas', 'POST', [
        'slug' => 'receita-mensal',
        'nome' => 'Receita Mensal',
        'unidade' => 'R$',
        'tipo_agregacao' => 'soma',
    ]);

    $validator = validator($request->all(), (new StoreMetaRequest())->rules());

    expect($validator->passes())->toBeTrue();
});

it('002. StoreMetaRequest rejeita slug com caracteres inválidos', function () {
    $validator = validator([
        'slug' => 'receita; DROP TABLE',
        'nome' => 'Teste',
        'unidade' => 'R$',
        'tipo_agregacao' => 'soma',
    ], (new StoreMetaRequest())->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('slug'))->toBeTrue();
});

it('003. StoreMetaRequest rejeita unidade fora da whitelist', function () {
    $validator = validator([
        'slug' => 'meta-x',
        'nome' => 'Teste',
        'unidade' => 'BTC',
        'tipo_agregacao' => 'soma',
    ], (new StoreMetaRequest())->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('unidade'))->toBeTrue();
});

it('004. StoreMetaRequest exige todos os campos required', function () {
    $validator = validator([], (new StoreMetaRequest())->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('slug'))->toBeTrue();
    expect($validator->errors()->has('nome'))->toBeTrue();
    expect($validator->errors()->has('unidade'))->toBeTrue();
    expect($validator->errors()->has('tipo_agregacao'))->toBeTrue();
});

it('005. UpdateMetaRequest aceita partial update (sometimes)', function () {
    $validator = validator(['nome' => 'Novo Nome'], (new UpdateMetaRequest())->rules());

    expect($validator->passes())->toBeTrue();
});

it('006. UpdateMetaRequest valida enum quando campo presente', function () {
    $validator = validator(['tipo_agregacao' => 'inventado'], (new UpdateMetaRequest())->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tipo_agregacao'))->toBeTrue();
});

// ---------- D9.a OtelHelper instrumentation tests ----------

it('007. AlertaService importa OtelHelper (instrumentado D9.a)', function () {
    $source = file_get_contents(base_path('Modules/Jana/Services/AlertaService.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain('OtelHelper::spanBiz(');
    expect($source)->toContain("'jana.alerta.avaliar'");
});

it('008. GovernancaService importa OtelHelper (instrumentado D9.a)', function () {
    $source = file_get_contents(base_path('Modules/Jana/Services/GovernancaService.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain('OtelHelper::span(');
    expect($source)->toContain("'jana.governanca.painel'");
});

// ---------- D6.a Inertia::defer tests ----------

it('009. PainelController usa Inertia::defer no payload painel', function () {
    $source = file_get_contents(base_path('Modules/Jana/Http/Controllers/PainelController.php'));

    expect($source)->toContain('Inertia::defer(');
    expect($source)->toContain("'painel' => Inertia::defer(");
});

it('010. DashboardController usa Inertia::defer no payload metas', function () {
    $source = file_get_contents(base_path('Modules/Jana/Http/Controllers/DashboardController.php'));

    expect($source)->toContain('Inertia::defer(');
    expect($source)->toContain("'metas' => Inertia::defer(");
    expect($source)->toContain('buildMetasPayload(');
});

// ---------- D8.a throttle tests ----------

it('011. routes.php Jana group declara throttle:120,1', function () {
    $source = file_get_contents(base_path('Modules/Jana/Http/routes.php'));

    expect($source)->toContain("'throttle:120,1'");
});

it('012. routes.php mensagens.stream declara throttle:60,1', function () {
    $source = file_get_contents(base_path('Modules/Jana/Http/routes.php'));

    // Throttle agressivo nas rotas que chamam LLM (custo + latência).
    expect($source)->toContain("->middleware('throttle:60,1')");
    expect($source)->toContain('jana.conversas.mensagens.stream');
});
