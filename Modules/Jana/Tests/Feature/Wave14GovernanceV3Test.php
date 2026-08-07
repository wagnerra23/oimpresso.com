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
 *   009. (removido 2026-08-06 — PainelController deletado; ver nota no corpo)
 *   010. IndexController: `metas` eager (hotfix 2026-05-25) e defer só no payload seguro
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

// 009. REMOVIDO 2026-08-06 [W] — o PainelController foi apagado na onda 1 da
// fusão das telas da Jana, e este caso lia o arquivo por `file_get_contents`,
// logo passaria a estourar. Registro do que ele afirmava, porque é instrutivo:
// ele exigia `'painel' => Inertia::defer(` num controller que trazia, desde
// 2026-05-25, o comentário "HOTFIX: removido Inertia::defer" e o código
// `'painel' => $this->buildMockPayload()`. O caso afirmava o OPOSTO do código e
// mesmo assim nunca ficou vermelho — este arquivo não está na lista da lane
// `jana-pest.yml`, que executa arquivo-a-arquivo em vez de testsuite.

// 010. CORRIGIDO 2026-08-07 [CL] — o caso afirmava o OPOSTO do código e ficou
// VERMELHO na nightly do CT 100 por 15 runs consecutivos (24/07→07/08), sem
// ninguém agir: ele exigia `'metas' => Inertia::defer(` num controller que traz,
// desde 2026-05-25, o comentário "HOTFIX pós-PR #1547: `metas` SEM Inertia::defer
// porque a Page lê `metas.length` direto" — o defer ali dava TypeError em
// prod, pego por smoke browser. Ou seja: o CÓDIGO está certo e o TESTE estava
// errado, afirmando uma intenção que [W] revogou com evidência.
//
// Reescrito pra DEFENDER a decisão em vez de contradizê-la: `metas` é eager de
// propósito (anti-regressão do hotfix) e o defer segue valendo onde é seguro
// (`coworkAggregates`, que o JanaCockpitV2 trata como opcional).
//
// ⚠️ Asserts separados de propósito: `toContain` é variádico no Pest — passar
// dois argumentos procura AMBOS como needles, não "isto E aquilo" (§5 2026-07-28).
it('010. IndexController: metas eager (hotfix) e defer só no payload seguro', function () {
    $source = file_get_contents(base_path('Modules/Jana/Http/Controllers/IndexController.php'));

    // O padrão Inertia::defer continua em uso no controller...
    expect($source)->toContain('Inertia::defer(');
    // ...mas NÃO no `metas` — é a decisão do hotfix 2026-05-25 que este caso protege.
    expect($source)->not->toContain("'metas' => Inertia::defer(");
    expect($source)->toContain("'metas' => \$this->buildMetasPayload(");
    // E segue aplicado onde é seguro (prop opcional pro cockpit).
    expect($source)->toContain("'coworkAggregates' => Inertia::defer(");
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
