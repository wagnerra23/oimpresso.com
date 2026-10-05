<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EstoqueFixture;

/**
 * Venda rápida do app das lojas (tela 11) — POST /api/app/vendas. REGRA MESTRE (valor + estoque).
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §2.2. NÃO derivado do controller.
 *
 * DUPLA PROVA do caso de referência (combinado com a sessão do app, oimpresso-app#39):
 * 2 × Banner a 89,90 + 3 × Adesivo a 12,50 + 1 × Caneca a 26,60 (valores fictícios do teste).
 *   Prova 1 — conta à mão, escrita aqui: 179,80 + 37,50 + 26,60 = 243,90.
 *   Prova 2 — o que o ERP GRAVOU, somado de forma independente das linhas (quantity ×
 *   unit_price_inc_tax) e do pagamento, tem que bater com a prova 1 e com o total do 201.
 * Estoque: Banner 5 → 3, Adesivo 10 → 7, Caneca (sem controle) não muda.
 *
 * Tier 0 (ADR 0093): tenant fictício 98 (ADR 0358); o produto do business 2 é recusado mesmo
 * ligado ao local do 98. Transação revertida.
 */
uses(DatabaseTransactions::class);

const APP_VC_BIZ = 98;
const APP_VC_OUTRO = 2;

function appVcUsuario(array $permissoes): User
{
    $user = User::factory()->create(['business_id' => APP_VC_BIZ]);
    $papel = Role::create(['name' => 'AppVc' . uniqid() . '#' . APP_VC_BIZ, 'business_id' => APP_VC_BIZ, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    // `location.<id>` vai DIRETO no usuário: User::permitted_locations lê só as permissões diretas.
    $locais = array_values(array_filter($permissoes, fn ($p) => str_starts_with($p, 'location.')));
    $papel->syncPermissions(array_values(array_diff($permissoes, $locais)));
    $user->assignRole($papel);
    if ($locais !== []) {
        $user->givePermissionTo($locais);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/** Produto `single` no local, com preço; com estoque, ganha lastro de compra (o mapPurchaseSell exige purchase_lines). */
function appVcProduto(object $t, int $biz, string $nome, float $preco, ?float $estoque): int
{
    $p = EstoqueFixture::singleProduct($biz, $estoque !== null);
    DB::table('products')->where('id', $p->productId)->update(['name' => $nome]);
    DB::table('variations')->where('id', $p->variationId())->update(['sell_price_inc_tax' => $preco]);
    DB::table('product_locations')->insert(['product_id' => $p->productId, 'location_id' => $t->local]);
    if ($estoque !== null) {
        EstoqueFixture::setStock($p, 0, $t->local, $estoque);
        $compra = DB::table('transactions')->insertGetId([
            'business_id' => $biz, 'type' => 'purchase', 'status' => 'received', 'location_id' => $t->local,
            'payment_status' => 'paid', 'transaction_date' => now()->subDay(), 'total_before_tax' => 0,
            'final_total' => 0, 'created_by' => $t->user->id, 'essentials_duration' => 0,
            'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $compra, 'product_id' => $p->productId, 'variation_id' => $p->variationId(),
            'quantity' => $estoque, 'quantity_sold' => 0, 'quantity_adjusted' => 0, 'quantity_returned' => 0,
            'purchase_price' => 0, 'purchase_price_inc_tax' => 0, 'item_tax' => 0,
            'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);
    }

    return $p->variationId();
}

function appVcEstoque(object $t, int $variacao): float
{
    return (float) DB::table('variation_location_details')->where('variation_id', $variacao)->where('location_id', $t->local)->value('qty_available');
}

function appVcVendasNoLocal(object $t): int
{
    return DB::table('transactions')->where('business_id', APP_VC_BIZ)->where('type', 'sell')->where('location_id', $t->local)->count();
}

/** Ajuste "Bloquear venda de produto com preço zero no app" (pos_settings) do 98. */
function appVcBloqueiaPrecoZero(bool $liga): void
{
    $pos = json_decode((string) DB::table('business')->where('id', APP_VC_BIZ)->value('pos_settings'), true) ?: [];
    $pos['bloquear_venda_preco_zero_app'] = $liga ? 1 : 0;
    DB::table('business')->where('id', APP_VC_BIZ)->update(['pos_settings' => json_encode($pos)]);
}

function appVcVenderSemEstoque(bool $permite): void
{
    $pos = json_decode((string) DB::table('business')->where('id', APP_VC_BIZ)->value('pos_settings'), true) ?: [];
    $pos['allow_overselling'] = $permite ? 1 : 0;
    DB::table('business')->where('id', APP_VC_BIZ)->update(['pos_settings' => json_encode($pos)]);
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite' || ! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS exige MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_VC_BIZ, APP_VC_OUTRO])->count() !== 2) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }
    // Assinatura e cota sem depender do pacote semeado na lane; o resto do ModuleUtil é o real.
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('isSubscribed')->andReturn(true);
    $mu->shouldReceive('isQuotaAvailable')->andReturn(true);
    app()->instance(ModuleUtil::class, $mu);

    appVcVenderSemEstoque(false);
    appVcBloqueiaPrecoZero(false);
    $this->local = EstoqueFixture::locationId(APP_VC_BIZ, '-APPVC');
    $this->user = appVcUsuario(['direct_sell.access', 'location.' . $this->local]);
    // Consumidor final do 98 (cliente_id null usa ele): o seed pode não ter.
    $this->consumidorFinal = DB::table('contacts')->where('business_id', APP_VC_BIZ)->where('is_default', 1)
        ->whereIn('type', ['customer', 'both'])->value('id')
        ?? DB::table('contacts')->insertGetId([
            'business_id' => APP_VC_BIZ, 'type' => 'customer', 'name' => 'Consumidor final (teste)', 'mobile' => '',
            'is_default' => 1, 'created_by' => $this->user->id, 'contact_status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

    $pre = 'AppVC ' . uniqid();
    $this->banner = appVcProduto($this, APP_VC_BIZ, $pre . ' Banner', 89.90, 5);
    $this->adesivo = appVcProduto($this, APP_VC_BIZ, $pre . ' Adesivo', 12.50, 10);
    $this->caneca = appVcProduto($this, APP_VC_BIZ, $pre . ' Caneca', 26.60, null);
    $this->corpo = [
        'cliente_id' => null,
        'metodo' => 'pix',
        'itens' => [
            ['variacao_id' => $this->banner, 'quantidade' => '2.00', 'preco_unitario' => '89.90'],
            ['variacao_id' => $this->adesivo, 'quantidade' => '3.00', 'preco_unitario' => '12.50'],
            ['variacao_id' => $this->caneca, 'quantidade' => '1.00', 'preco_unitario' => '26.60'],
        ],
        'total_previsto' => '243.90',
    ];
    Passport::actingAs($this->user, [], 'api');
});

function appVcPost(object $t, array $corpo, ?string $chave = null)
{
    return $t->withHeaders(['Idempotency-Key' => $chave ?? ('k-' . uniqid())])->postJson('/api/app/vendas', $corpo);
}

it('UC-APPVR-10 · caso de referência: grava a venda final de 243,90 no consumidor final, com PIX, e baixa o estoque (dupla prova)', function () {
    $r = appVcPost($this, $this->corpo)->assertStatus(201);

    // Prova 1 — conta à mão.
    $esperado = 2 * 89.90 + 3 * 12.50 + 1 * 26.60;
    expect($esperado)->toEqualWithDelta(243.90, 0.0001);
    expect((float) $r->json('total'))->toEqualWithDelta(243.90, 0.0001);
    expect($r->json('metodo'))->toBe('PIX');
    expect(collect($r->json('itens'))->sum('subtotal'))->toEqualWithDelta(243.90, 0.0001);

    // Prova 2 — o que o ERP gravou, somado à parte.
    $tx = DB::table('transactions')->where('id', $r->json('id'))->first();
    expect((int) $tx->business_id)->toBe(APP_VC_BIZ);
    expect((int) $tx->location_id)->toBe($this->local);
    expect($tx->status)->toBe('final');
    expect((int) $tx->contact_id)->toBe((int) $this->consumidorFinal);
    expect($tx->current_stage_id)->toBeNull();
    expect((float) $tx->final_total)->toEqualWithDelta(243.90, 0.0001);
    expect($tx->payment_status)->toBe('paid');
    expect((string) $tx->invoice_no)->toBe((string) $r->json('numero'));

    $linhas = DB::table('transaction_sell_lines')->where('transaction_id', $tx->id)->get();
    expect($linhas)->toHaveCount(3);
    expect($linhas->sum(fn ($l) => (float) $l->quantity * (float) $l->unit_price_inc_tax))->toEqualWithDelta(243.90, 0.0001);
    expect((float) $linhas->firstWhere('variation_id', $this->banner)->quantity)->toEqualWithDelta(2.0, 0.0001);

    $pagos = DB::table('transaction_payments')->where('transaction_id', $tx->id)->get();
    expect($pagos)->toHaveCount(1);
    expect((float) $pagos[0]->amount)->toEqualWithDelta(243.90, 0.0001);
    expect($pagos[0]->method)->toBe('custom_pay_1');

    // Estoque antes → depois.
    expect(appVcEstoque($this, $this->banner))->toEqualWithDelta(3.0, 0.0001);
    expect(appVcEstoque($this, $this->adesivo))->toEqualWithDelta(7.0, 0.0001);
    expect(DB::table('variation_location_details')->where('variation_id', $this->caneca)->exists())->toBeFalse();
});

it('UC-APPVR-11 · a mesma Idempotency-Key com o mesmo corpo devolve 200 com a MESMA venda, sem gravar nem baixar de novo; com outro corpo, 422 idempotencia_conflito', function () {
    $chave = 'chave-fixa-' . uniqid();
    $primeira = appVcPost($this, $this->corpo, $chave)->assertStatus(201);
    $vendas = appVcVendasNoLocal($this);

    $segunda = appVcPost($this, $this->corpo, $chave)->assertStatus(200);
    expect($segunda->json('id'))->toBe($primeira->json('id'));
    expect(appVcVendasNoLocal($this))->toBe($vendas);
    expect(appVcEstoque($this, $this->banner))->toEqualWithDelta(3.0, 0.0001);

    $outro = $this->corpo;
    $outro['metodo'] = 'dinheiro';
    appVcPost($this, $outro, $chave)->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');
    expect(appVcVendasNoLocal($this))->toBe($vendas);
});

it('UC-APPVR-12 · preço diferente do catálogo: 422 no item e NADA gravado (venda, estoque, chave)', function () {
    $corpo = $this->corpo;
    $corpo['itens'][0]['preco_unitario'] = '79.90';
    $corpo['total_previsto'] = '223.90';
    $vendas = appVcVendasNoLocal($this);

    appVcPost($this, $corpo, 'k-preco-' . uniqid())->assertStatus(422)
        ->assertJsonPath('erro', 'validacao')
        ->assertJsonPath('campos', fn ($c) => isset($c['itens.0.preco_unitario']));

    expect(appVcVendasNoLocal($this))->toBe($vendas);
    expect(appVcEstoque($this, $this->banner))->toEqualWithDelta(5.0, 0.0001);
    expect(DB::table('app_idempotencia')->where('business_id', APP_VC_BIZ)->where('user_id', $this->user->id)->count())->toBe(0);
});

it('UC-APPVR-13 · total_previsto diferente da soma: 422 total_previsto e nada gravado', function () {
    $corpo = $this->corpo;
    $corpo['total_previsto'] = '243.80';
    $vendas = appVcVendasNoLocal($this);

    appVcPost($this, $corpo)->assertStatus(422)->assertJsonPath('campos', fn ($c) => isset($c['total_previsto']));

    expect(appVcVendasNoLocal($this))->toBe($vendas);
    expect(appVcEstoque($this, $this->adesivo))->toEqualWithDelta(10.0, 0.0001);
});

it('UC-APPVR-14 · sem estoque e o business não vende sem estoque: 422 na quantidade e nada gravado; vendendo sem estoque, grava e o saldo fica negativo (controle)', function () {
    $corpo = $this->corpo;
    $corpo['itens'] = [['variacao_id' => $this->banner, 'quantidade' => '6.00', 'preco_unitario' => '89.90']];
    $corpo['total_previsto'] = '539.40';
    $vendas = appVcVendasNoLocal($this);

    appVcPost($this, $corpo)->assertStatus(422)->assertJsonPath('campos', fn ($c) => isset($c['itens.0.quantidade']));
    expect(appVcVendasNoLocal($this))->toBe($vendas);
    expect(appVcEstoque($this, $this->banner))->toEqualWithDelta(5.0, 0.0001);

    appVcVenderSemEstoque(true);
    appVcPost($this, $corpo)->assertStatus(201);
    expect(appVcEstoque($this, $this->banner))->toEqualWithDelta(-1.0, 0.0001);
});

it('UC-APPVR-15 · número fora do formato ("1.500", vírgula, 3 casas) é recusado no formato, antes de qualquer conta; quantidade fracionada também (v1 = inteiro)', function () {
    foreach (['1.500', '12,50', '12.505'] as $ruim) {
        $corpo = $this->corpo;
        $corpo['itens'][1]['preco_unitario'] = $ruim;
        appVcPost($this, $corpo)->assertStatus(422)->assertJsonPath('campos', fn ($c) => isset($c['itens.1.preco_unitario']));
    }
    $corpo = $this->corpo;
    $corpo['itens'][1]['quantidade'] = '2.50';
    appVcPost($this, $corpo)->assertStatus(422)->assertJsonPath('campos', fn ($c) => isset($c['itens.1.quantidade']));
    expect(appVcEstoque($this, $this->adesivo))->toEqualWithDelta(10.0, 0.0001);
});

it('UC-APPVR-16 · Tier 0: produto e cliente de outro business são recusados mesmo ligados ao local do 98', function () {
    $alheio = appVcProduto($this, APP_VC_OUTRO, 'AppVC alheio ' . uniqid(), 10, null);
    $corpo = ['cliente_id' => null, 'metodo' => 'dinheiro',
        'itens' => [['variacao_id' => $alheio, 'quantidade' => '1.00', 'preco_unitario' => '10.00']], 'total_previsto' => '10.00'];
    appVcPost($this, $corpo)->assertStatus(422)->assertJsonPath('campos', fn ($c) => isset($c['itens.0.variacao_id']));

    $clienteAlheio = DB::table('contacts')->insertGetId([
        'business_id' => APP_VC_OUTRO, 'type' => 'customer', 'name' => 'Cliente alheio', 'mobile' => '',
        'created_by' => $this->user->id, 'contact_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $corpo = $this->corpo;
    $corpo['cliente_id'] = $clienteAlheio;
    appVcPost($this, $corpo)->assertStatus(422)->assertJsonPath('campos', fn ($c) => isset($c['cliente_id']));
    expect(appVcEstoque($this, $this->banner))->toEqualWithDelta(5.0, 0.0001);
});

it('UC-APPVR-17 · permissão, forma de pagamento e header: sem permissão 403; boleto 422 metodo; sem Idempotency-Key 422', function () {
    Passport::actingAs(appVcUsuario(['location.' . $this->local]), [], 'api');
    appVcPost($this, $this->corpo)->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    Passport::actingAs($this->user, [], 'api');
    $corpo = $this->corpo;
    $corpo['metodo'] = 'boleto';
    appVcPost($this, $corpo)->assertStatus(422)->assertJsonPath('campos', fn ($c) => isset($c['metodo']));

    // withHeaders persiste entre requisições do mesmo teste: sem o flush, a chave anterior ia junto.
    $this->flushHeaders()->postJson('/api/app/vendas', $this->corpo)->assertStatus(422)
        ->assertJsonPath('campos', fn ($c) => isset($c['idempotency_key']));
    expect(appVcEstoque($this, $this->banner))->toEqualWithDelta(5.0, 0.0001);
});

it('UC-APPVR-18 · preço zero: com o ajuste ligado, produto a R$ 0,00 (ou que arredonda a 0, ou negativo) é 422 no item e nada é gravado; desligado (padrão), vende como antes', function () {
    $pre = 'AppVC0 ' . uniqid();
    $zero = appVcProduto($this, APP_VC_BIZ, $pre . ' Brinde', 0.00, 4);
    $quase = appVcProduto($this, APP_VC_BIZ, $pre . ' Quase zero', 0.004, 4);
    $negativo = appVcProduto($this, APP_VC_BIZ, $pre . ' Negativo', -1.00, 4);
    $comBrinde = [
        'cliente_id' => null, 'metodo' => 'pix',
        'itens' => [
            ['variacao_id' => $this->adesivo, 'quantidade' => '1.00', 'preco_unitario' => '12.50'],
            ['variacao_id' => $zero, 'quantidade' => '1.00', 'preco_unitario' => '0.00'],
        ],
        'total_previsto' => '12.50',
    ];

    appVcBloqueiaPrecoZero(true);
    $vendas = appVcVendasNoLocal($this);
    foreach ([$zero, $quase, $negativo] as $variacao) {
        $corpo = $comBrinde;
        $corpo['itens'][1]['variacao_id'] = $variacao;
        appVcPost($this, $corpo)->assertStatus(422)
            ->assertJsonPath('erro', 'validacao')
            ->assertJsonPath('campos', ['itens.1.preco_unitario' => 'Produto sem preço. Corrija o cadastro na web.']);
    }
    // Nada gravado: nem venda, nem baixa de estoque, nem chave de idempotência.
    expect(appVcVendasNoLocal($this))->toBe($vendas);
    expect(appVcEstoque($this, $this->adesivo))->toEqualWithDelta(10.0, 0.0001);
    expect(appVcEstoque($this, $zero))->toEqualWithDelta(4.0, 0.0001);
    expect(DB::table('app_idempotencia')->where('business_id', APP_VC_BIZ)->where('user_id', $this->user->id)->count())->toBe(0);

    // Ajuste ligado não muda venda com preço: o caso de referência grava os mesmos 243,90.
    $ref = appVcPost($this, $this->corpo)->assertStatus(201);
    expect((float) DB::table('transactions')->where('id', $ref->json('id'))->value('final_total'))->toEqualWithDelta(243.90, 0.0001);

    // Desligado (padrão): o brinde a R$ 0,00 vende como antes.
    appVcBloqueiaPrecoZero(false);
    $r = appVcPost($this, $comBrinde)->assertStatus(201);
    // Prova 1 — à mão: 1 × 12,50 + 1 × 0,00 = 12,50. Prova 2 — o que o ERP gravou, pelas linhas.
    expect((float) $r->json('total'))->toEqualWithDelta(12.50, 0.0001);
    $linhas = DB::table('transaction_sell_lines')->where('transaction_id', $r->json('id'))->get();
    expect($linhas)->toHaveCount(2);
    expect($linhas->sum(fn ($l) => (float) $l->quantity * (float) $l->unit_price_inc_tax))->toEqualWithDelta(12.50, 0.0001);
    expect(appVcEstoque($this, $zero))->toEqualWithDelta(3.0, 0.0001);
});
