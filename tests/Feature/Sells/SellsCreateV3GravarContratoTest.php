<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * UC-V380 / UC-V382 (resources/js/Pages/Sells/CreateV3.casos.md) — a V3 grava a venda.
 *
 * A V3 não tem store: ela posta em `/pos` (SellPosController@store, o caminho de produção)
 * o payload montado por `_components/v3/gravacao-dominio.ts`. A fixture
 * `tests/fixtures/sells-v3/venda-simples.json` é a MESMA que o vitest usa para provar que a
 * tela monta aquele payload — aqui ela é postada com os ids trocados pelos do tenant 98.
 *
 * Dupla prova da REGRA MESTRE (valor/estoque):
 *   Caminho 1 — conta à mão: 3 × 19,90 − 10% = 53,73 · 2 × 50,00 = 100,00 ·
 *               153,73 − 5,00 + frete 7,50 = 156,23; estoque 10 − 3 = 7 e 10 − 2 = 8.
 *   Caminho 2 — a mesma venda pelo payload do Create (formato do transform do Create.tsx),
 *               gravada pelo mesmo store(), comparada campo a campo.
 *
 * Setup copiado de SellsBonificacaoContratoTest.php (lastro de compra, sessão com o modelo
 * Business), com outro prefixo: os arquivos rodam no mesmo processo da lane.
 * Tenant 98 (ADR 0358). Nunca biz=4.
 */
uses(DatabaseTransactions::class);

function v3gravarFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/fixtures/sells-v3/venda-simples.json')), true);
}

/** Produto com 10 em estoque e lastro de compra (sem lastro o store recusa por PurchaseSellMismatch). */
function v3gravarProduto(object $test): object
{
    $produto = EstoqueFixture::singleProduct($test->bizId);
    EstoqueFixture::setStock($produto, 0, $test->locationId, 10);
    $compraId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $test->bizId, 'type' => 'purchase', 'status' => 'received',
        'location_id' => $test->locationId, 'payment_status' => 'paid',
        'transaction_date' => now()->subDay(), 'total_before_tax' => 0, 'final_total' => 0,
        'created_by' => $test->user->id, 'essentials_duration' => 0,
        'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compraId, 'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'], 'quantity' => 10,
        'quantity_sold' => 0, 'quantity_adjusted' => 0, 'quantity_returned' => 0,
        'purchase_price' => 0, 'purchase_price_inc_tax' => 0, 'item_tax' => 0,
        'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);

    return $produto;
}

/** Payload da V3: o da fixture, com os ids do tenant de teste no lugar dos de cena. */
function v3gravarPayloadV3(object $test, object $a, object $b): array
{
    $p = v3gravarFixture()['payload'];
    $p['location_id'] = $test->locationId;
    $p['contact_id'] = $test->contactId;
    $p['transaction_date'] = now()->format('d/m/Y H:i');
    foreach ([$a, $b] as $i => $produto) {
        $p['products'][$i]['product_id'] = $produto->productId;
        $p['products'][$i]['variation_id'] = (int) $produto->variations[0]['variation_id'];
    }

    return $p;
}

/** O mesmo pedido no formato do transform do Create.tsx (com as chaves de UI que ele carrega). */
function v3gravarPayloadCreate(object $test, object $a, object $b): array
{
    $linha = fn (object $produto, float $q, float $preco, float $desc) => [
        'product_id' => $produto->productId,
        'variation_id' => (int) $produto->variations[0]['variation_id'],
        'name' => 'x', 'variation' => null, 'sku' => 'x',
        'quantity' => $q, 'unit_price' => $preco, 'discount' => $desc, 'discount_type' => 'percentage',
        'imei_number' => '', 'unit_price_inc_tax' => $preco, 'item_tax' => 0, 'tax_id' => null,
        'line_discount_type' => 'percentage', 'line_discount_amount' => $desc,
        'enable_stock' => 1, 'product_type' => 'single',
    ];
    $pagamentos = [['amount' => 156.23, 'method' => 'cash', 'paid_on' => '', 'account_id' => null, 'note' => '']];

    return [
        'location_id' => $test->locationId, 'contact_id' => $test->contactId,
        'transaction_date' => now()->format('d/m/Y H:i'), 'status' => 'final',
        'invoice_scheme_id' => null, 'invoice_no' => '', 'pay_term_number' => '', 'pay_term_type' => 'days',
        'sinal_minimo_pct' => null, 'price_group_id' => null, 'commission_agent_id' => null,
        'tax_rate_id' => null, 'vehicle_id' => null,
        'products' => [$linha($a, 3, 19.9, 10), $linha($b, 2, 50, 0)],
        'payments' => $pagamentos, 'payment' => $pagamentos,
        'discount_type' => 'fixed', 'discount_amount' => 5,
        'rp_redeemed' => 0, 'rp_redeemed_amount' => '0.00', 'notes' => '', 'staff_note' => '',
        'is_recurring' => 0, 'customer_secondary_address' => '', 'sell_document' => null,
        'shipping' => ['details' => '', 'address' => '', 'cost' => 7.5, 'status' => '', 'deliver_to' => ''],
        'is_direct_sale' => 1, 'is_save_and_print' => 0,
        'commission_agent' => null, 'price_group' => null, 'default_price_group' => null,
        'sale_note' => '', 'additional_notes' => '',
        'shipping_details' => '', 'shipping_address' => '', 'shipping_charges' => 7.5,
        'shipping_status' => '', 'delivered_to' => '',
        'additional_expense_key_1' => '', 'additional_expense_value_1' => 0,
        'final_total' => 156.23,
    ];
}

/** Posta e devolve venda, linhas (na ordem dos produtos), pagamentos e saldo de cada produto. */
function v3gravarVender(object $test, callable $montar): array
{
    $a = v3gravarProduto($test);
    $b = v3gravarProduto($test);
    $response = $test->post('/pos', $montar($test, $a, $b));

    $id = DB::table('transaction_sell_lines')->where('product_id', $a->productId)->value('transaction_id');
    \PHPUnit\Framework\Assert::assertNotNull(
        $id,
        'store() não gravou a venda. status='.$response->status()
            .' erros='.json_encode(session('errors')?->getBag('default')->all() ?? [], JSON_UNESCAPED_UNICODE)
    );

    return [
        'response' => $response,
        'venda' => DB::table('transactions')->where('id', $id)->first(),
        'linhas' => DB::table('transaction_sell_lines')->where('transaction_id', $id)->orderBy('product_id')->get(),
        'pago' => (float) DB::table('transaction_payments')->where('transaction_id', $id)->sum('amount'),
        'saldos' => [
            EstoqueFixture::currentStock($a, 0, $test->locationId),
            EstoqueFixture::currentStock($b, 0, $test->locationId),
        ],
    ];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    $this->bizId = EstoqueFixture::businessId(); // tenant 98 (ADR 0358 — nunca biz=4).
    $this->locationId = EstoqueFixture::locationId($this->bizId);
    $this->contactId = (int) DB::table('contacts')->where('business_id', $this->bizId)->orderBy('id')->value('id');
    $this->user = User::where('business_id', $this->bizId)->orderBy('id')->first();
    if (! $this->user || $this->contactId === 0) {
        $this->markTestSkipped('Sem user ou contato no business seeded.');
    }

    $this->actingAs($this->user);
    $business = \App\Business::findOrFail($this->bizId);
    $business->date_format = 'd/m/Y';
    $business->time_format = 24;
    session(['user.business_id' => $this->bizId, 'user.id' => $this->user->id, 'business' => $business]);

    $currency = DB::table('currencies')
        ->where('id', DB::table('business')->where('id', $this->bizId)->value('currency_id'))
        ->first();
    session(['currency' => [
        'id' => (int) ($currency->id ?? 1),
        'code' => (string) ($currency->code ?? 'BRL'),
        'symbol' => (string) ($currency->symbol ?? 'R$'),
        'thousand_separator' => (string) ($currency->thousand_separator ?? '.'),
        'decimal_separator' => (string) ($currency->decimal_separator ?? ','),
    ]]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('sell.create', 'web');
    $this->user->givePermissionTo('sell.create');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

it('UC-V380 · a venda da V3 grava o mesmo total, as mesmas linhas, o mesmo pagamento e a mesma baixa de estoque que a do Create', function () {
    $v3 = v3gravarVender($this, 'v3gravarPayloadV3');
    $create = v3gravarVender($this, 'v3gravarPayloadCreate');

    // Caminho 1 — conta à mão.
    expect((float) $v3['venda']->final_total)->toBe(156.23);
    expect((float) $v3['venda']->shipping_charges)->toBe(7.5);
    expect((float) $v3['venda']->discount_amount)->toBe(5.0);
    expect($v3['pago'])->toBe(156.23);
    expect($v3['venda']->payment_status)->toBe('paid');
    expect($v3['saldos'])->toBe([7.0, 8.0]);

    // Caminho 2 — campo a campo contra a venda do Create.
    foreach (['final_total', 'total_before_tax', 'tax_amount', 'discount_type', 'discount_amount', 'shipping_charges', 'payment_status', 'status', 'type'] as $campo) {
        expect((string) $v3['venda']->{$campo})->toBe((string) $create['venda']->{$campo});
    }
    expect($v3['linhas'])->toHaveCount(2);
    expect($create['linhas'])->toHaveCount(2);
    foreach ([0, 1] as $i) {
        foreach (['quantity', 'unit_price_before_discount', 'unit_price', 'unit_price_inc_tax', 'line_discount_type', 'line_discount_amount'] as $campo) {
            expect((string) $v3['linhas'][$i]->{$campo})->toBe((string) $create['linhas'][$i]->{$campo});
        }
    }
    expect($v3['pago'])->toBe($create['pago']);
    expect($v3['saldos'])->toBe($create['saldos']);
    expect((int) $v3['venda']->business_id)->toBe($this->bizId);
});

it('UC-V382 · com a flag desligada a tela segue preview; ligada, entrega o local e o cliente padrão DESTA empresa', function () {
    // Cliente padrão da empresa (o "consumidor final" do getWalkInCustomer). O seed pode não
    // marcar nenhum; aqui marca o contato do tenant 98 dentro da transação do teste.
    if (! DB::table('contacts')->where('business_id', $this->bizId)->where('is_default', 1)->whereIn('type', ['customer', 'both'])->exists()) {
        DB::table('contacts')->where('id', $this->contactId)->update(['is_default' => 1, 'type' => 'customer']);
    }

    // `forDropdown` só lista os locais permitidos ao usuário. Medido no 1º run do CI: sem esta
    // permissão a lista vinha vazia e a prop não nascia (o controller devolve null de propósito).
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('access_all_locations', 'web');
    $this->user->givePermissionTo('access_all_locations');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // Diagnóstico das três pernas, para o vermelho dizer qual faltou.
    config(['feature-flags.forced_on' => 'useV3SellsGravar']);
    expect(app(\App\Services\FeatureFlagService::class)->isOn('useV3SellsGravar'))->toBeTrue();
    expect(collect(\App\BusinessLocation::forDropdown($this->bizId))->keys()->first())->not->toBeNull();
    expect(app(\App\Utils\ContactUtil::class)->getWalkInCustomer($this->bizId)['id'] ?? null)->not->toBeNull();

    config(['feature-flags.forced_on' => '']);
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page
        ->component('Sells/CreateV3')
        ->missing('gravacao'));

    config(['feature-flags.forced_on' => 'useV3SellsGravar']);
    $resposta = $this->get('/sells/create-v3');
    $resposta->assertInertia(fn ($page) => $page
        ->component('Sells/CreateV3')
        ->has('gravacao.locationId')
        ->has('gravacao.contactId')
        ->where('cena.itens', []));

    $props = $resposta->viewData('page')['props'];
    expect((int) DB::table('business_locations')->where('id', $props['gravacao']['locationId'])->value('business_id'))->toBe($this->bizId);
    expect((int) DB::table('contacts')->where('id', $props['gravacao']['contactId'])->value('business_id'))->toBe($this->bizId);
});

/** Liga a gravação e garante o cliente padrão do tenant 98 (getWalkInCustomer). */
function v3gravarLigar(object $test): void
{
    if (! DB::table('contacts')->where('business_id', $test->bizId)->where('is_default', 1)->whereIn('type', ['customer', 'both'])->exists()) {
        DB::table('contacts')->where('id', $test->contactId)->update(['is_default' => 1, 'type' => 'customer']);
    }
    config(['feature-flags.forced_on' => 'useV3SellsGravar']);
}

function v3gravarPermitir(User $user, array $nomes): void
{
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($nomes as $nome) {
        Permission::findOrCreate($nome, 'web');
        $user->givePermissionTo($nome);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

it('UC-V384 · gravando, preço, desconto da linha e teto de desconto vêm do usuário, não da cena', function () {
    v3gravarLigar($this);
    v3gravarPermitir($this->user, ['access_all_locations']);
    DB::table('users')->where('id', $this->user->id)->update(['max_sales_discount_percent' => 5]);
    $this->user->refresh();
    $this->actingAs($this->user);

    // Sem as duas permissões: preço e desconto travados, teto 5%. A cena dizia editarPrecoItem=true.
    expect($this->user->can('edit_product_price_from_sale_screen'))->toBeFalse();
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page
        ->where('gravacao.permissoes.editarPreco', false)
        ->where('gravacao.permissoes.editarDesconto', false)
        ->where('gravacao.permissoes.descontoMaximo', fn ($v) => (float) $v === 5.0)
        ->where('cena.permissoes.editarPrecoItem', false));

    // Com as permissões e sem teto: tudo liberado.
    v3gravarPermitir($this->user, ['edit_product_price_from_sale_screen', 'edit_product_discount_from_sale_screen']);
    DB::table('users')->where('id', $this->user->id)->update(['max_sales_discount_percent' => null]);
    $this->user->refresh();
    $this->actingAs($this->user);
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page
        ->where('gravacao.permissoes.editarPreco', true)
        ->where('gravacao.permissoes.editarDesconto', true)
        ->where('gravacao.permissoes.descontoMaximo', null)
        ->where('cena.permissoes.editarPrecoItem', true));
});

it('UC-V385 · gravando, usuário sem nenhum local permitido não recebe a gravação (a tela segue preview)', function () {
    v3gravarLigar($this);
    // Controle positivo: com todos os locais a gravação nasce.
    v3gravarPermitir($this->user, ['access_all_locations']);
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page->has('gravacao.locationId'));

    // Sem access_all_locations e sem location.{id}: forDropdown volta vazio e não há onde gravar.
    $this->user->revokePermissionTo('access_all_locations');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->refresh();
    $this->actingAs($this->user);
    expect($this->user->permitted_locations($this->bizId))->not->toBe('all');
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page->missing('gravacao'));
});

it('UC-V386 · gravando, a tela recebe as configurações do business lidas de pos_settings e de business', function () {
    v3gravarLigar($this);
    v3gravarPermitir($this->user, ['access_all_locations']);

    // Sem pos_settings: vale o default do BusinessUtil (desconto e imposto liberados, rascunho ligado).
    DB::table('business')->where('id', $this->bizId)->update([
        'pos_settings' => null,
        'item_addition_method' => 0,
        'sales_cmsn_agnt' => null,
        'default_sales_discount' => null,
    ]);
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page
        ->where('gravacao.config.descontoDaVenda', true)
        ->where('gravacao.config.impostoDoPedido', true)
        ->where('gravacao.config.rascunho', true)
        ->where('gravacao.config.venderSemEstoque', false)
        ->where('gravacao.config.somaNaLinha', false)
        ->where('gravacao.config.comissionado.tipo', null)
        ->where('gravacao.config.arredondamento', fn ($v) => (float) $v === 0.0));

    // Configurado: cada chave chega com o sentido do Blade (disable_* invertido).
    DB::table('business')->where('id', $this->bizId)->update([
        'pos_settings' => json_encode([
            'disable_discount' => 1, 'disable_order_tax' => 1, 'disable_draft' => 1,
            'disable_credit_sale_button' => 1, 'disable_recurring_invoice' => 1,
            'enable_transaction_date' => 1, 'enable_msp' => 1, 'allow_overselling' => 1,
            'is_commission_agent_required' => 1, 'is_pay_term_required' => 1,
            'amount_rounding_method' => '0.5',
        ]),
        'item_addition_method' => 1,
        'sales_cmsn_agnt' => 'cmsn_agnt',
        'default_sales_discount' => 2.5,
    ]);
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page
        ->where('gravacao.config.descontoDaVenda', false)
        ->where('gravacao.config.impostoDoPedido', false)
        ->where('gravacao.config.rascunho', false)
        ->where('gravacao.config.aPrazo', false)
        ->where('gravacao.config.recorrente', false)
        ->where('gravacao.config.dataEditavel', true)
        ->where('gravacao.config.precoMinimo', true)
        ->where('gravacao.config.venderSemEstoque', true)
        ->where('gravacao.config.comissionado.tipo', 'cmsn_agnt')
        ->where('gravacao.config.comissionado.obrigatorio', true)
        ->where('gravacao.config.prazoObrigatorio', true)
        ->where('gravacao.config.somaNaLinha', true)
        ->where('gravacao.config.descontoPadrao', fn ($v) => (float) $v === 2.5)
        ->where('gravacao.config.arredondamento', fn ($v) => (float) $v === 0.5)
        ->has('gravacao.prazo'));

    // Flag desligada: nada de config (a cena não consulta tabela de negócio).
    config(['feature-flags.forced_on' => '']);
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page->missing('gravacao'));
});

it('UC-V392 · com amount_rounding_method = 1 a venda da fixture grava 156,00 e round_off_amount −0,23', function () {
    // Caminho 2 (servidor): o vitest prova que montarPayloadVenda, com arredondamento 1, leva
    // final_total 156 e round_off_amount −0,23 para esta fixture; aqui o store grava isso.
    $montar = function (object $test, object $a, object $b): array {
        $p = v3gravarPayloadV3($test, $a, $b);
        $p['final_total'] = 156;
        $p['round_off_amount'] = -0.23;
        $p['payment'][0]['amount'] = 156;

        return $p;
    };
    $v = v3gravarVender($this, $montar);

    expect((float) $v['venda']->final_total)->toBe(156.0);
    expect((float) $v['venda']->round_off_amount)->toBe(-0.23);
    // O resto da venda é o mesmo do UC-V380: só o total fecha no múltiplo.
    expect((float) $v['venda']->discount_amount)->toBe(5.0);
    expect((float) $v['venda']->shipping_charges)->toBe(7.5);
    expect($v['pago'])->toBe(156.0);
    expect($v['venda']->payment_status)->toBe('paid');
    expect($v['saldos'])->toBe([7.0, 8.0]);
});

it('UC-V393 · o cliente escolhido na consulta grava a venda com o contact_id, o prazo e o comissionado dele', function () {
    // Um 2º cliente do MESMO tenant, com prazo — o que a consulta (/contacts/customers) traria.
    $outro = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->bizId, 'type' => 'customer', 'name' => 'Cliente V3 Grupo 3',
        'contact_status' => 'active', 'mobile' => '', 'pay_term_number' => 30, 'pay_term_type' => 'days',
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $montar = function (object $test, object $a, object $b) use ($outro): array {
        $p = v3gravarPayloadV3($test, $a, $b);
        $p['contact_id'] = $outro;
        $p['pay_term_number'] = 30;
        $p['pay_term_type'] = 'days';
        $p['commission_agent'] = $test->user->id;

        return $p;
    };
    $v = v3gravarVender($this, $montar);

    expect((int) $v['venda']->contact_id)->toBe($outro);
    expect((int) $v['venda']->pay_term_number)->toBe(30);
    expect($v['venda']->pay_term_type)->toBe('days');
    expect((int) $v['venda']->commission_agent)->toBe((int) $this->user->id);
    // Trocar de cliente sem tabela não muda valor: o mesmo total do UC-V380.
    expect((float) $v['venda']->final_total)->toBe(156.23);
});

it('UC-V393 · a busca de clientes da consulta só alcança cadastros desta empresa', function () {
    $alheio = (int) DB::table('business')->where('id', '!=', $this->bizId)->orderBy('id')->value('id');
    if ($alheio === 0) {
        $this->markTestSkipped('Sem um segundo business no seed para a prova cross-tenant.');
    }
    $nome = 'V3Grupo3Isolamento';
    $comum = ['type' => 'customer', 'contact_status' => 'active', 'mobile' => '', 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()];
    $meu = (int) DB::table('contacts')->insertGetId(['business_id' => $this->bizId, 'name' => "$nome nosso"] + $comum);
    $dele = (int) DB::table('contacts')->insertGetId(['business_id' => $alheio, 'name' => "$nome alheio"] + $comum);

    $ids = collect($this->getJson('/contacts/customers?q='.$nome, ['X-Requested-With' => 'XMLHttpRequest'])->json())->pluck('id')->map(fn ($i) => (int) $i);

    expect($ids)->toContain($meu);   // controle positivo: a busca acha
    expect($ids)->not->toContain($dele);
});

it('UC-V394 · "Novo cliente" na consulta só vem com customer.create', function () {
    v3gravarLigar($this);
    v3gravarPermitir($this->user, ['access_all_locations']);
    expect($this->user->can('customer.create'))->toBeFalse();
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page->where('gravacao.permissoes.criarCliente', false));

    v3gravarPermitir($this->user, ['customer.create']);
    $this->user->refresh();
    $this->actingAs($this->user);
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page->where('gravacao.permissoes.criarCliente', true));
});

it('UC-V395 · a lista de comissionados segue sales_cmsn_agnt e é só desta empresa', function () {
    v3gravarLigar($this);
    v3gravarPermitir($this->user, ['access_all_locations']);

    DB::table('business')->where('id', $this->bizId)->update(['sales_cmsn_agnt' => null]);
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page->where('gravacao.comissionados', []));

    DB::table('business')->where('id', $this->bizId)->update(['sales_cmsn_agnt' => 'user']);
    $doTenant = User::where('business_id', $this->bizId)->pluck('id')->map(fn ($i) => (int) $i)->all();
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page->where('gravacao.comissionados', function ($lista) use ($doTenant) {
        $ids = collect($lista)->pluck('id')->map(fn ($i) => (int) $i);

        return $ids->contains((int) $this->user->id) && $ids->diff($doTenant)->isEmpty();
    }));
});

it('UC-V396 · a seção de pagamento só vem com sell.payments', function () {
    v3gravarLigar($this);
    v3gravarPermitir($this->user, ['access_all_locations']);
    expect($this->user->can('sell.payments'))->toBeFalse();
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page->where('gravacao.permissoes.registrarPagamento', false));

    v3gravarPermitir($this->user, ['sell.payments']);
    $this->user->refresh();
    $this->actingAs($this->user);
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page->where('gravacao.permissoes.registrarPagamento', true));
});

it('UC-V397 · gravando, as tabelas de preço são só as permitidas e a inicial é a do local', function () {
    v3gravarLigar($this);
    v3gravarPermitir($this->user, ['access_all_locations']);
    $novo = fn (string $nome) => (int) DB::table('selling_price_groups')->insertGetId([
        'name' => $nome.'-'.uniqid(), 'business_id' => $this->bizId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $permitida = $novo('V3-Permitida');
    $proibida = $novo('V3-Proibida');
    v3gravarPermitir($this->user, ["selling_price_group.$permitida"]);
    // O local da gravação é o primeiro permitido, como no controller (e no SellPosController@create).
    $local = (int) collect(\App\BusinessLocation::forDropdown($this->bizId))->keys()->first();
    DB::table('business_locations')->where('id', $local)->update(['selling_price_group_id' => $permitida]);
    $this->user->refresh();
    $this->actingAs($this->user);

    // Sem access_default_selling_price: sem o id 0; só a permitida; a do local vem como inicial.
    expect($this->user->can('access_default_selling_price'))->toBeFalse();
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page
        ->where('gravacao.tabelaPadraoId', $permitida)
        ->where('gravacao.tabelas', function ($lista) use ($permitida, $proibida) {
            $ids = collect($lista)->pluck('id')->map(fn ($i) => (int) $i);

            return $ids->contains($permitida) && ! $ids->contains($proibida) && ! $ids->contains(0);
        }));

    // Com a permissão do preço padrão: o id 0 entra. Local apontando para a proibida: sem inicial.
    v3gravarPermitir($this->user, ['access_default_selling_price']);
    DB::table('business_locations')->where('id', $local)->update(['selling_price_group_id' => $proibida]);
    $this->user->refresh();
    $this->actingAs($this->user);
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page
        ->where('gravacao.tabelaPadraoId', null)
        ->where('gravacao.tabelas', fn ($lista) => collect($lista)->pluck('id')->map(fn ($i) => (int) $i)->contains(0)));
});

it('UC-V398 · com a tabela, a busca devolve 15,00 para A e nada para B, e a venda grava 143,00 (sem tabela, 156,23)', function () {
    // Caminho 2 da REGRA MESTRE: o preço sai do filterProduct real (o /products/list da V3) e o
    // total do store real. O caminho 1 é tests/js/tabela-preco-dominio.test.ts (mesma conta).
    $grupo = null;
    $montar = function (object $test, object $a, object $b) use (&$grupo): array {
        $va = (int) $a->variations[0]['variation_id'];
        $vb = (int) $b->variations[0]['variation_id'];
        DB::table('variations')->where('id', $va)->update(['sell_price_inc_tax' => 19.9]);
        DB::table('variations')->where('id', $vb)->update(['sell_price_inc_tax' => 50]);
        $grupo = (int) DB::table('selling_price_groups')->insertGetId([
            'name' => 'V3-Grupo3b-'.uniqid(), 'business_id' => $test->bizId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('variation_group_prices')->insert([
            'variation_id' => $va, 'price_group_id' => $grupo, 'price_inc_tax' => 15, 'price_type' => 'fixed',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // A regra do precoDaBusca sobre a linha que o /products/list devolve com price_group.
        $preco = function (object $produto, int $variacao) use ($test, $grupo): float {
            $nome = (string) DB::table('products')->where('id', $produto->productId)->value('name');
            $linha = app(\App\Utils\ProductUtil::class)
                ->filterProduct($test->bizId, $nome, null, null, $grupo, [], ['name'])
                ->firstWhere('variation_id', $variacao);
            expect($linha)->not->toBeNull();

            return $linha->variation_group_price !== null ? (float) $linha->variation_group_price : (float) $linha->selling_price;
        };
        $pa = $preco($a, $va);
        $pb = $preco($b, $vb);
        expect([$pa, $pb])->toBe([15.0, 50.0]);

        $p = v3gravarPayloadV3($test, $a, $b);
        $p['products'][0]['unit_price'] = $pa;
        $p['products'][0]['unit_price_inc_tax'] = $pa;
        $p['products'][1]['unit_price'] = $pb;
        $p['products'][1]['unit_price_inc_tax'] = $pb;
        $p['price_group'] = $grupo;
        $p['default_price_group'] = $grupo;
        // Conta à mão: 3 × 15,00 − 10% = 40,50 · 2 × 50,00 = 100,00 · 140,50 − 5,00 + 7,50 = 143,00.
        $p['final_total'] = 143;
        $p['payment'][0]['amount'] = 143;

        return $p;
    };
    $v = v3gravarVender($this, $montar);

    expect((float) $v['venda']->final_total)->toBe(143.0);
    expect((int) $v['venda']->selling_price_group_id)->toBe($grupo);
    // Preço da linha antes do desconto dela: o da tabela para A, o base para B.
    expect($v['linhas']->map(fn ($l) => (float) $l->unit_price_before_discount)->sort()->values()->all())->toBe([15.0, 50.0]);
    expect($v['pago'])->toBe(143.0);
    expect($v['venda']->payment_status)->toBe('paid');
    // Estoque não muda com a tabela: as mesmas baixas do UC-V380.
    expect($v['saldos'])->toBe([7.0, 8.0]);
});
