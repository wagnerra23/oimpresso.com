<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Orçamento rápido do app das lojas (tela 04 · P4) — REGRA MESTRE (valor).
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-04-orcamento-rapido.md. NÃO derivado do controller.
 *
 * DUPLA PROVA do caso de referência do contrato (valores fictícios do teste):
 *   material a 65,00/m², item 3,00 × 1,20 × 1.
 *   Prova 1 — conta à mão, escrita aqui: 3,00 × 1,20 = 3,60 m² × 65,00 = 234,00.
 *   Prova 2 — o MESMO corpo pela calculadora/gravação da WEB (`/comunicacao-visual/api/*`, sessão)
 *   tem que dar o mesmo área/subtotal/total que o app, e o que ficou gravado no banco também.
 *
 * Tier 0 (ADR 0093): tenant fictício 98 (ADR 0358) × business 2. Material e cliente do 2 são
 * recusados com 422, sem gravar. Transação revertida.
 */
uses(DatabaseTransactions::class);

const APP_OR_BIZ = 98;
const APP_OR_OUTRO = 2;

function appOrUsuario(array $permissoes): User
{
    $user = User::factory()->create(['business_id' => APP_OR_BIZ]);
    $papel = Role::create(['name' => 'AppOr' . uniqid() . '#' . APP_OR_BIZ, 'business_id' => APP_OR_BIZ, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

function appOrMaterial(int $biz, string $nome, string $preco, bool $ativo = true): int
{
    return (int) DB::table('comvis_materiais')->insertGetId([
        'business_id' => $biz, 'nome' => $nome, 'categoria' => 'lona', 'unidade' => 'm2',
        'preco_custo_m2' => 0, 'preco_venda_m2' => $preco, 'ativo' => $ativo,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function appOrAjuste(bool $liga): void
{
    $pos = json_decode((string) DB::table('business')->where('id', APP_OR_BIZ)->value('pos_settings'), true) ?: [];
    $pos['app_orcamento_rapido'] = $liga;
    DB::table('business')->where('id', APP_OR_BIZ)->update(['pos_settings' => json_encode($pos)]);
}

function appOrContagem(): int
{
    return DB::table('comvis_orcamentos')->where('business_id', APP_OR_BIZ)->count();
}

function appOrPost(object $t, array $corpo, ?string $chave = null)
{
    return $t->withHeaders(['Idempotency-Key' => $chave ?? ('k-' . uniqid())])->postJson('/api/app/orcamentos', $corpo);
}

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('comvis_materiais') || ! Schema::hasTable('app_idempotencia')) {
        $this->markTestSkipped('Schema UltimatePOS + ComunicacaoVisual exige MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_OR_BIZ, APP_OR_OUTRO])->count() !== 2) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }
    // Pacote do business sem depender do semeado na lane; o resto do ModuleUtil é o real.
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $mu);

    appOrAjuste(true);
    $pre = 'AppOR ' . uniqid();
    $this->lona = appOrMaterial(APP_OR_BIZ, $pre . ' Lona', '65.00');
    $this->inativo = appOrMaterial(APP_OR_BIZ, $pre . ' Inativo', '40.00', false);
    $this->doOutro = appOrMaterial(APP_OR_OUTRO, $pre . ' Lona do 2', '10.00');
    $this->user = appOrUsuario(['comvis.orcamento.view', 'comvis.orcamento.create']);
    $this->cliente = (int) DB::table('contacts')->insertGetId([
        'business_id' => APP_OR_BIZ, 'type' => 'customer', 'name' => $pre . ' Cliente', 'mobile' => '',
        'created_by' => $this->user->id, 'contact_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->item = ['material_id' => $this->lona, 'largura_m' => '3.00', 'altura_m' => '1.20', 'quantidade' => '1'];
    $this->corpo = ['cliente_id' => $this->cliente, 'itens' => [$this->item + ['total_previsto' => '234.00']]];
    Passport::actingAs($this->user, [], 'api');
});

it('UC-APPOR-01 · materiais lista só o catálogo ATIVO do business do token, com preço por m² como número', function () {
    $r = $this->getJson('/api/app/orcamentos/materiais')->assertOk();

    $ids = collect($r->json('itens'))->pluck('id')->all();
    expect($ids)->toContain($this->lona)
        ->and($ids)->not->toContain($this->inativo)
        ->and($ids)->not->toContain($this->doOutro);
    $lona = collect($r->json('itens'))->firstWhere('id', $this->lona);
    expect(array_keys($lona))->toBe(['id', 'nome', 'categoria', 'unidade', 'preco_m2']);
    // JSON não distingue 65 de 65.0: o valor é o que importa.
    expect($lona['preco_m2'])->toEqual(65.0);
});

it('UC-APPOR-02 · sem permissão de orçamento → 403 sem_permissao; empresa sem material ativo → 503 sem_configuracao', function () {
    Passport::actingAs(appOrUsuario([]), [], 'api');
    $this->getJson('/api/app/orcamentos/materiais')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    $this->postJson('/api/app/orcamentos/calcular', $this->item)->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    Passport::actingAs($this->user, [], 'api');
    DB::table('comvis_materiais')->where('business_id', APP_OR_BIZ)->update(['ativo' => false]);
    $this->getJson('/api/app/orcamentos/materiais')->assertStatus(503)->assertJsonPath('erro', 'sem_configuracao');
    $this->postJson('/api/app/orcamentos/calcular', $this->item)->assertStatus(503);
});

it('UC-APPOR-03 · calcular o caso de referência dá 3,6 m² × 65,00 = 234,00 e bate com a calculadora da web (dupla prova), sem gravar', function () {
    $antes = appOrContagem();
    $r = $this->postJson('/api/app/orcamentos/calcular', $this->item)->assertOk();

    // Prova 1 — conta à mão.
    expect(3.00 * 1.20 * 1)->toEqualWithDelta(3.6, 0.0001);
    expect(3.6 * 65.00)->toEqualWithDelta(234.00, 0.0001);
    expect(array_keys($r->json()))->toBe(['area_m2', 'preco_m2', 'total']);
    expect($r->json('area_m2'))->toEqual(3.6);
    expect($r->json('preco_m2'))->toEqual(65.0);
    expect($r->json('total'))->toEqual(234.0);

    // Prova 2 — o mesmo item pela calculadora da web (sessão do 98).
    $web = $this->actingAs($this->user, 'web')
        ->withSession(['user.business_id' => APP_OR_BIZ, 'business.id' => APP_OR_BIZ, 'user.id' => $this->user->id])
        ->postJson('/comunicacao-visual/api/calcular', [
            'data_emissao' => now()->toDateString(),
            'itens' => [['material_id' => $this->lona, 'descricao' => 'Lona', 'largura_m' => 3.00, 'altura_m' => 1.20, 'quantidade' => 1]],
        ])->assertOk();
    expect((float) $web->json('itens.0.area_m2'))->toBe((float) $r->json('area_m2'));
    expect((float) $web->json('itens.0.preco_unitario_m2'))->toBe((float) $r->json('preco_m2'));
    expect((float) $web->json('itens.0.subtotal'))->toBe((float) $r->json('total'));

    expect(appOrContagem())->toBe($antes);
});

it('UC-APPOR-04 · calcular recusa material de outro business, medida zero e número que não é texto (422 validacao)', function () {
    $this->postJson('/api/app/orcamentos/calcular', ['material_id' => $this->doOutro] + $this->item)
        ->assertStatus(422)->assertJsonPath('erro', 'validacao')->assertJsonPath('campos.material_id', 'Material não encontrado.');
    $this->postJson('/api/app/orcamentos/calcular', ['material_id' => $this->lona, 'largura_m' => '0.00', 'altura_m' => '1.20', 'quantidade' => '1'])
        ->assertStatus(422)->assertJsonStructure(['campos' => ['largura_m']]);
    $this->postJson('/api/app/orcamentos/calcular', ['material_id' => $this->lona, 'largura_m' => 3, 'altura_m' => '1.20', 'quantidade' => '1'])
        ->assertStatus(422)->assertJsonStructure(['campos' => ['largura_m']]);
    $this->postJson('/api/app/orcamentos/calcular', ['material_id' => $this->lona, 'largura_m' => '3,00', 'altura_m' => '1.20', 'quantidade' => '1'])
        ->assertStatus(422)->assertJsonStructure(['campos' => ['largura_m']]);
});

it('UC-APPOR-05 · grava o rascunho de 234,00 do business do token e o banco bate com a gravação da web (dupla prova)', function () {
    $antes = appOrContagem();
    $r = appOrPost($this, $this->corpo)->assertStatus(201);

    expect(array_keys($r->json()))->toBe(['id', 'numero', 'status', 'total']);
    expect($r->json('status'))->toBe('rascunho');
    expect($r->json('total'))->toEqual(234.0);
    expect($r->json('numero'))->toMatch('/^ORC-\d{4}-\d{5}$/');
    expect(appOrContagem())->toBe($antes + 1);

    $orc = DB::table('comvis_orcamentos')->where('id', $r->json('id'))->first();
    expect((int) $orc->business_id)->toBe(APP_OR_BIZ);
    expect((int) $orc->contato_id)->toBe($this->cliente);
    expect((int) $orc->vendedor_id)->toBe((int) $this->user->id);
    expect((float) $orc->total)->toEqualWithDelta(234.00, 0.0001);
    $itens = DB::table('comvis_orcamento_itens')->where('orcamento_id', $orc->id)->get();
    expect($itens)->toHaveCount(1);
    expect((int) $itens[0]->business_id)->toBe(APP_OR_BIZ);
    expect((float) $itens[0]->area_m2)->toEqualWithDelta(3.6, 0.0001);
    expect((float) $itens[0]->preco_unitario_m2)->toEqualWithDelta(65.00, 0.0001);
    expect($itens->sum(fn ($i) => (float) $i->subtotal))->toEqualWithDelta(234.00, 0.0001);

    // Prova 2 — o mesmo item gravado pela web (sessão do 98): mesmos valores no banco.
    $web = $this->actingAs($this->user, 'web')
        ->withSession(['user.business_id' => APP_OR_BIZ, 'business.id' => APP_OR_BIZ, 'user.id' => $this->user->id])
        ->postJson('/comunicacao-visual/api/orcamentos', [
            'data_emissao' => now()->toDateString(), 'contato_id' => $this->cliente,
            'itens' => [['material_id' => $this->lona, 'descricao' => 'Lona', 'largura_m' => 3.00, 'altura_m' => 1.20, 'quantidade' => 1]],
        ])->assertStatus(201);
    $orcWeb = DB::table('comvis_orcamentos')->where('id', $web->json('id'))->first();
    expect((int) $orcWeb->business_id)->toBe(APP_OR_BIZ);
    expect((float) $orcWeb->total)->toBe((float) $orc->total);
    $itemWeb = DB::table('comvis_orcamento_itens')->where('orcamento_id', $orcWeb->id)->first();
    expect((float) $itemWeb->subtotal)->toBe((float) $itens[0]->subtotal);
    expect((float) $itemWeb->area_m2)->toBe((float) $itens[0]->area_m2);
    // O número segue a MESMA sequência por business nos dois caminhos.
    expect((int) substr($orcWeb->numero, -5))->toBe((int) substr($orc->numero, -5) + 1);
});

it('UC-APPOR-06 · dois itens: 234,00 + (0,50 × 0,50 × 4 = 1,00 m² × 65,00 = 65,00) = 299,00', function () {
    $corpo = ['cliente_id' => null, 'itens' => [
        $this->item + ['total_previsto' => '234.00'],
        ['material_id' => $this->lona, 'largura_m' => '0.50', 'altura_m' => '0.50', 'quantidade' => '4', 'total_previsto' => '65.00'],
    ]];
    $r = appOrPost($this, $corpo)->assertStatus(201);

    expect(234.00 + 0.50 * 0.50 * 4 * 65.00)->toEqualWithDelta(299.00, 0.0001);
    expect($r->json('total'))->toEqual(299.0);
    $soma = DB::table('comvis_orcamento_itens')->where('orcamento_id', $r->json('id'))->sum('subtotal');
    expect((float) $soma)->toEqualWithDelta(299.00, 0.0001);
    expect(DB::table('comvis_orcamentos')->where('id', $r->json('id'))->value('contato_id'))->toBeNull();
});

it('UC-APPOR-07 · total_previsto divergente → 422 em itens.N.total_previsto e NADA é gravado (nem a reserva da chave)', function () {
    $antes = appOrContagem();
    $chave = 'div-' . uniqid();
    $corpo = $this->corpo;
    $corpo['itens'][0]['total_previsto'] = '233.99';

    $r = appOrPost($this, $corpo, $chave)->assertStatus(422)->assertJsonPath('erro', 'validacao');
    // A chave do campo é LITERAL `itens.0.total_previsto` (assertJsonPath a partiria nos pontos).
    expect($r->json('campos'))->toBe(['itens.0.total_previsto' => 'O total mudou: o ERP calculou 234.00. Confira e envie de novo.']);

    expect(appOrContagem())->toBe($antes);
    expect(DB::table('app_idempotencia')->where('business_id', APP_OR_BIZ)->where('chave', $chave)->exists())->toBeFalse();
});

it('UC-APPOR-08 · mesma Idempotency-Key + mesmo corpo → 200 com o MESMO orçamento, sem gravar de novo; outro corpo → 422 idempotencia_conflito', function () {
    $chave = 'fixa-' . uniqid();
    $primeira = appOrPost($this, $this->corpo, $chave)->assertStatus(201);
    $depois = appOrContagem();

    $segunda = appOrPost($this, $this->corpo, $chave)->assertStatus(200);
    expect($segunda->json())->toBe($primeira->json());
    expect(appOrContagem())->toBe($depois);

    $outro = $this->corpo;
    $outro['itens'][0]['quantidade'] = '2';
    $outro['itens'][0]['total_previsto'] = '468.00';
    appOrPost($this, $outro, $chave)->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');
    expect(appOrContagem())->toBe($depois);
});

it('UC-APPOR-09 · Tier 0: material ou cliente do business 2 → 422 e nada gravado', function () {
    $antes = appOrContagem();
    $corpo = $this->corpo;
    $corpo['itens'][0]['material_id'] = $this->doOutro;
    $r = appOrPost($this, $corpo)->assertStatus(422);
    expect($r->json('campos'))->toBe(['itens.0.material_id' => 'Material não encontrado.']);

    $clienteDo2 = (int) DB::table('contacts')->insertGetId([
        'business_id' => APP_OR_OUTRO, 'type' => 'customer', 'name' => 'AppOR cliente do 2', 'mobile' => '',
        'created_by' => $this->user->id, 'contact_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    appOrPost($this, ['cliente_id' => $clienteDo2] + $this->corpo)->assertStatus(422)->assertJsonPath('campos.cliente_id', 'Cliente não encontrado.');

    expect(appOrContagem())->toBe($antes);
    expect(DB::table('comvis_orcamentos')->where('business_id', APP_OR_OUTRO)->where('contato_id', $clienteDo2)->exists())->toBeFalse();
});

it('UC-APPOR-10 · ajuste app_orcamento_rapido desligado ou só permissão de ver → 403 sem_permissao e nada gravado; sem Idempotency-Key → 422', function () {
    $antes = appOrContagem();

    appOrAjuste(false);
    appOrPost($this, $this->corpo)->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    // calcular não grava: segue disponível com o ajuste desligado.
    $this->postJson('/api/app/orcamentos/calcular', $this->item)->assertOk();

    appOrAjuste(true);
    Passport::actingAs(appOrUsuario(['comvis.orcamento.view']), [], 'api');
    appOrPost($this, $this->corpo)->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    Passport::actingAs($this->user, [], 'api');
    // withHeaders() de appOrPost persiste no caso de teste: sem limpar, a chave anterior iria junto.
    $this->flushHeaders();
    $this->postJson('/api/app/orcamentos', $this->corpo)->assertStatus(422)->assertJsonStructure(['campos' => ['idempotency_key']]);

    expect(appOrContagem())->toBe($antes);
});
