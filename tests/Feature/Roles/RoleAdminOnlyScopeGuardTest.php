<?php

declare(strict_types=1);

// Tests\TestCase ja e aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')).

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Database\Seeders\McpScopesSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * As DUAS metades do contrato de scope MCP no save de papel, medidas por COMPORTAMENTO
 * (POST HTTP real + estado do papel no banco), nao pela forma da lista de checkbox.
 *
 * Os guards irmaos em `Modules/Jana/Tests/Feature/` sao deterministicos e sem DB: eles
 * provam o que o CATALOGO oferta. Este prova o que o CONTROLLER faz com o POST — que e a
 * pergunta que interessa, porque o vetor real nunca foi a tela, foi a requisicao.
 *
 * ── B (Tier 0) — `admin_only` nao e auto-concedivel ─────────────────────────────────
 *
 * `/roles/{id}/edit` exige `roles.update`, permission de admin de BUSINESS (Camada 3 do
 * multi-tenant), nao de superadmin. Ate 2026-09-07 os 5 scopes `admin_only` do catalogo
 * viravam checkbox ali, e `jana.mcp.usage.all` e o UNICO gate de `/governance/qualidade-ia`
 * e das 8 telas do hub de engenharia da Forja. Confirmado em producao: biz=164 (Martinho,
 * OficinaAuto LIVE) tem os 5 concedidos numa role com 5 usuarios — nao era hipotese.
 *
 * O caso B4 forja o POST de proposito. Barrar na tela nunca foi barrar: quem tem
 * `roles.update` monta a requisicao a mao, e ate a correcao ela era aceita, porque
 * `PermissionCatalog::intrusas` so descarta o que esta FORA do catalogo — e o slug estava
 * DENTRO, exatamente por ser ofertado.
 *
 * ── A (anti-regressao 2026-07-29) — o save nao apaga `jana.mcp.*` ────────────────────
 *
 * `syncPermissions()` e destrutivo: apaga toda permission ausente do POST. Em 29/07 um save
 * no papel `Operacional#1` (biz=1) zerou os 17 scopes e derrubou o MCP dos 4 usuarios do
 * time — token valido devolvendo `403 no_permission` no gate `jana.mcp.use`.
 *
 * O contrato inteiro cabe numa frase: O FORM SO PODE REVOGAR O QUE ELE OFERECE. A1 prova
 * o lado geral (permission de origem qualquer que o form nao oferece sobrevive), A2 o caso
 * `admin_only` que biz=164 tem hoje, e A3 a contra-prova — o que E ofertado continua
 * revogavel, senao "preservar" teria virado "congelar" e o papel seria imutavel.
 *
 * ⚠️ CONTROLE POSITIVO OBRIGATORIO (B3). `getModuleData('user_permissions')` so devolve
 * dados de modulo INSTALADO (`System::getProperty('jana_version')`). Sem o Jana instalado,
 * TODO `jana.mcp.*` viraria intruso e seria descartado — e ai B1/B2 passariam pelo motivo
 * errado, medindo "o modulo esta desligado" e chamando isso de seguranca. B3 prova que um
 * scope NAO-`admin_only` E concedido pelo mesmo POST, na mesma requisicao.
 *
 * ⚠️ NAO RODADO LOCAL: Pest e CT 100/CI only (proibicoes.md §Ambiente). Status de teste vem
 * do veredito, nunca da leitura.
 *
 * LANE: `PHP / Pest (Acessos · MySQL)` (advisory) roda `tests/Feature/Roles/` como
 * DIRETORIO, e o `paths:` ja inclui `RoleController.php`; este PR acrescenta o
 * `DataController.php` da Jana, que passou a ser codigo sob teste.
 *
 * @see app/Http/Controllers/RoleController::__preservaNaoOfertadas()
 * @see Modules/Jana/Http/Controllers/DataController::mcpScopePermissions()
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: exige schema MySQL UltimatePOS (FKs business/users/roles).');
    }
    if (! Schema::hasTable('business') || ! Schema::hasTable('roles')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — esta suite roda em MySQL real semeado.');
    }

    // `ModuleUtil::isModuleInstalled('Jana')` le `system.jana_version`, que so o
    // InstallController grava. O CI migra do zero e nunca o roda: sem a property o
    // getModuleData volta vazio e B1/B2 passariam por ausencia de dado. Mesmo idioma do
    // `AcoesContratoTest`.
    if (Schema::hasTable('system') && ! \App\System::getProperty('jana_version')) {
        \App\System::addProperty('jana_version', (string) config('copiloto.module_version', '0.1'));
    }

    // Tenant 98 (ADR 0358) — NUNCA biz=4, que e cliente real. O CI ja o semeia
    // (`pest-mysql-setup`: "TENANT CANONICO DE TESTE, decisao [W] 2026-07-28"); o
    // `forceCreate` cobre o CT 100, cujo staging tem `1, 99, 2` e nao o 98 — a FK violation
    // que o `ClienteVeiculosModuleGateTest` ja documenta.
    $this->biz = Business::find(98) ?: Business::forceCreate([
        'id' => 98,
        'name' => 'Tenant ficticio 98 (ADR 0358)',
        'currency_id' => 1,
        'start_date' => now()->toDateString(),
        'default_profit_percent' => 0,
        'owner_id' => 1,
        'stop_selling_before' => 0,
        'weighing_scale_setting' => '',
        'certificado' => '',
        'officeimpresso_numerodemaquinas' => 0,
    ]);

    foreach (['roles.update', 'roles.create', 'sell.view', 'jana.mcp.use', 'jana.mcp.usage.all'] as $p) {
        Permission::findOrCreate($p, 'web');
    }

    // Quem edita: admin de BUSINESS com `roles.update` — nao superadmin. E o ator do vetor.
    $papelDoAtor = Role::create([
        'name' => 'AdminOnlyAtor'.uniqid().'#'.$this->biz->id,
        'business_id' => $this->biz->id,
        'guard_name' => 'web',
    ]);
    $papelDoAtor->syncPermissions(['roles.update']);

    $this->ator = User::factory()->create([
        'business_id' => $this->biz->id,
        'username' => 'ao_ator_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $this->ator->assignRole($papelDoAtor);

    // O papel EDITADO e outro — senao o teste mediria o ator editando a si mesmo.
    $this->alvoNome = 'AdminOnlyAlvo'.uniqid();
    $this->alvo = Role::create([
        'name' => $this->alvoNome.'#'.$this->biz->id,
        'business_id' => $this->biz->id,
        'guard_name' => 'web',
    ]);

    // Sem isto o `can()` responde com o retrato anterior e o controller aborta 403 — e ai os
    // casos NEGATIVOS passam pelo MOTIVO ERRADO ("nada foi concedido" tambem e verdade
    // quando a requisicao nem chega). Mesma correcao do `RolePermissionCatalogTest`.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->ator = User::findOrFail($this->ator->id);
});

/** Salva o papel alvo com a lista de permissions dada, como o form faria. */
function aoSalvar(array $permissions)
{
    return test()
        ->actingAs(test()->ator)
        ->withSession([
            'user.business_id' => test()->biz->id,
            'user' => ['business_id' => test()->biz->id, 'id' => test()->ator->id],
        ])
        ->put('/roles/'.test()->alvo->id, [
            'name' => test()->alvoNome,
            'permissions' => $permissions,
        ]);
}

/** Estado do papel DEPOIS do save, lido do banco (nao do objeto em memoria). */
function aoPermissoes(): array
{
    return Role::findOrFail(test()->alvo->id)
        ->permissions->pluck('name')->sort()->values()->all();
}

/** Os `admin_only` do catalogo — derivado, nao lista fixa: scope novo ja nasce coberto. */
function aoAdminOnlySlugs(): array
{
    return array_values(array_map(
        static fn (array $s): string => $s['slug'],
        array_filter(
            McpScopesSeeder::catalogo(),
            static fn (array $s): bool => ($s['admin_only'] ?? false) === true
        )
    ));
}

// ── B — Tier 0: `admin_only` nao e auto-concedivel ──────────────────────────────────

it('B1: admin de business com roles.update NAO concede scope admin_only pelo save', function () {
    aoSalvar(['sell.view', 'jana.mcp.usage.all']);

    expect(aoPermissoes())->not->toContain('jana.mcp.usage.all');
});

it('B2: NENHUM dos scopes admin_only do catalogo atravessa o save', function () {
    $adminOnly = aoAdminOnlySlugs();

    // Sem este controle, catalogo vazio faria a intersecao vazia por AUSENCIA DE DADO.
    expect($adminOnly)->not->toBeEmpty();

    foreach ($adminOnly as $slug) {
        Permission::findOrCreate($slug, 'web');
    }

    aoSalvar(array_merge(['sell.view'], $adminOnly));

    expect(array_values(array_intersect(aoPermissoes(), $adminOnly)))->toBe([]);
});

it('B3 (CONTROLE POSITIVO): scope MCP nao-admin_only E concedido pelo MESMO save', function () {
    // Separa "o filtro `admin_only` mordeu" de "o modulo Jana inteiro esta fora do catalogo".
    // Se este caso falhar, B1/B2 nao provam nada — estao medindo modulo desligado.
    aoSalvar(['sell.view', 'jana.mcp.use', 'jana.mcp.usage.all']);

    $depois = aoPermissoes();

    expect($depois)->toContain('jana.mcp.use');
    expect($depois)->not->toContain('jana.mcp.usage.all');
});

it('B4: POST FORJADO tambem nao concede — a defesa e a concessao, nao a tela', function () {
    // O vetor real. Quem tem `roles.update` monta a requisicao a mao; esconder o checkbox
    // nunca barrou nada. Ate 2026-09-07 este POST era ACEITO.
    aoSalvar(['jana.mcp.usage.all', 'jana.mcp.memory.manage', 'jana.cc.curate']);

    $depois = aoPermissoes();

    expect($depois)->not->toContain('jana.mcp.usage.all');
    expect($depois)->not->toContain('jana.mcp.memory.manage');
    expect($depois)->not->toContain('jana.cc.curate');
});

it('B5: papel NOVO tambem nao nasce com admin_only — o create e o outro caminho', function () {
    // `store()` e o segundo ponto de concessao do controller e roda o MESMO
    // `__somenteDoCatalogo`, entao o filtro ja o fecha — mas fechado-por-construcao nao e
    // fechado-por-prova. Sem este caso, alguem que reintroduza o admin_only so no create
    // passaria por todos os outros.
    $nome = 'AdminOnlyNovo'.uniqid();

    test()->ator->givePermissionTo('roles.create');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    test()
        ->actingAs(User::findOrFail(test()->ator->id))
        ->withSession([
            'user.business_id' => test()->biz->id,
            'user' => ['business_id' => test()->biz->id, 'id' => test()->ator->id],
        ])
        ->post('/roles', [
            'name' => $nome,
            'permissions' => ['sell.view', 'jana.mcp.use', 'jana.mcp.usage.all'],
        ]);

    $criado = Role::where('name', $nome.'#'.test()->biz->id)->first();
    expect($criado)->not->toBeNull(); // senao o caso passaria por o papel nem ter nascido

    $perms = $criado->permissions->pluck('name')->all();

    expect($perms)->toContain('jana.mcp.use');          // controle positivo, como o B3
    expect($perms)->not->toContain('jana.mcp.usage.all');
});

// ── A — anti-regressao do incidente 2026-07-29 ──────────────────────────────────────

it('A1: permission de QUALQUER origem que o form nao oferece sobrevive ao save', function () {
    // A generalizacao — e o caso que nem A2 nem A3 cobrem. O preserve nao conhece
    // `jana.mcp.*`: o predicado e "o form nao oferece", entao permission de modulo
    // desativado, ou legada, ou de um modulo que ainda nem existe, e igualmente preservada.
    // Se alguem trocar o predicado por uma lista de slugs, este caso fica vermelho.
    //
    // ⚠️ `jana.mcp.use` NAO serve aqui: desde o conserto de 2026-07-29 ele E ofertado pelo
    // form, logo sumir do POST e revogacao LEGITIMA — e o que A3 prova. A 1a versao deste
    // caso o usava e ficou vermelha no CT 100, contradizendo A3; o teste estava errado, nao
    // o codigo. A anti-regressao da familia OFERTADA e outra e continua onde sempre esteve:
    // a paridade catalogo ⇄ tela do `McpScopesVisiveisNoRoleEditTest`.
    $forano = 'modulo.desativado.fixture.'.uniqid();
    Permission::findOrCreate($forano, 'web');

    test()->alvo->syncPermissions(['sell.view', $forano]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    aoSalvar(['sell.view']);

    expect(aoPermissoes())->toContain($forano);
});

it('A2: scope admin_only JA CONCEDIDO sobrevive ao save (o form nao o oferece, logo nao o revoga)', function () {
    // biz=164 em producao esta neste estado. A correcao nao pode desligar o cliente LIVE:
    // o que ja foi concedido por superadmin continua valendo; so a concessao NOVA e barrada.
    test()->alvo->syncPermissions(['sell.view', 'jana.mcp.usage.all']);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    aoSalvar(['sell.view']);

    expect(aoPermissoes())->toContain('jana.mcp.usage.all');
});

it('A3: o form CONTINUA podendo revogar o que ele oferece — preservar nao e congelar', function () {
    // Contra-prova do preserve: sem este caso, um preserve largo demais passaria em A1/A2 e o
    // teste diria "nada e revogado", chamando de seguranca um papel que virou imutavel.
    test()->alvo->syncPermissions(['sell.view', 'jana.mcp.use']);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    aoSalvar(['jana.mcp.use']); // `sell.view` e ofertado pelo form e saiu do POST

    $depois = aoPermissoes();

    expect($depois)->not->toContain('sell.view');
    expect($depois)->toContain('jana.mcp.use');
});
