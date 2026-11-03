<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Services\InitialStageResolver;
use App\Scopes\ScopeByBusiness;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * UC-V400 / UC-V401 / UC-V402 (resources/js/Pages/Sells/CreateV3.casos.md) — a venda gravada
 * pela V3 nasce no pipeline FSM; a venda do `/pos` sem a marca da V3 não (ADR 0143).
 *
 * Medido em produção na empresa 1 em 2026-11-03: a venda OS00135, gravada pela V3, ficou com
 * `current_stage_id` nulo, e "Salvar como orçamento" não gravava nada.
 *
 * O payload é o da fixture da prova dupla (`tests/fixtures/sells-v3/venda-simples.json`), o
 * mesmo que o vitest prova que a tela monta, com os ids trocados pelos do tenant 98.
 * O estágio esperado sai por dois caminhos: o nome escrito no caso (paid / quote_sent) e o
 * `InitialStageResolver` aplicado à venda gravada.
 *
 * Tenant 98 (ADR 0358). Nunca biz=4 nem biz=1.
 */
uses(DatabaseTransactions::class);

/** Produto com 10 em estoque e lastro de compra (sem lastro o store recusa a venda final). */
function v3pipeProduto(object $test): object
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

/** Posta o payload da V3 (ajustado por `$mudar`) e devolve a venda gravada e o produto A. */
function v3pipeVender(object $test, callable $mudar): array
{
    $a = v3pipeProduto($test);
    $b = v3pipeProduto($test);
    $p = json_decode(file_get_contents(base_path('tests/fixtures/sells-v3/venda-simples.json')), true)['payload'];
    $p['location_id'] = $test->locationId;
    $p['contact_id'] = $test->contactId;
    $p['transaction_date'] = now()->format('d/m/Y H:i');
    foreach ([$a, $b] as $i => $produto) {
        $p['products'][$i]['product_id'] = $produto->productId;
        $p['products'][$i]['variation_id'] = (int) $produto->variations[0]['variation_id'];
    }

    $response = $test->post('/pos', $mudar($p));
    $id = DB::table('transaction_sell_lines')->where('product_id', $a->productId)->value('transaction_id');

    return [
        'response' => $response,
        'id' => $id,
        'venda' => $id ? DB::table('transactions')->where('id', $id)->first() : null,
        'historico' => $id ? DB::table('sale_stage_history')->where('transaction_id', $id)->get() : collect(),
        'saldoA' => EstoqueFixture::currentStock($a, 0, $test->locationId),
    ];
}

/** Id do estágio `$key` no processo `venda_com_producao` deste tenant. */
function v3pipeEstagio(int $bizId, string $key): ?int
{
    $processo = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $bizId)->where('key', 'venda_com_producao')->first();

    return $processo ? (int) $processo->stages()->where('key', $key)->value('id') : null;
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

it('UC-V400 · a venda gravada pela V3 nasce no estágio do InitialStageResolver, com o início no histórico', function () {
    $r = v3pipeVender($this, fn (array $p) => $p);

    expect($r['venda'])->not->toBeNull();
    expect($r['venda']->status)->toBe('final');
    expect($r['venda']->payment_status)->toBe('paid');

    // Caminho 1 — o caso: venda final paga nasce em "Paga".
    $paga = v3pipeEstagio($this->bizId, 'paid');
    expect($paga)->toBeGreaterThan(0);
    expect((int) $r['venda']->current_stage_id)->toBe($paga);

    // Caminho 2 — o resolver aplicado à venda gravada aponta o mesmo estágio.
    $chave = app(InitialStageResolver::class)->resolve(\App\Transaction::findOrFail($r['id']));
    expect(v3pipeEstagio($this->bizId, $chave))->toBe($paga);

    // Início auditado: uma linha, sem ação, de nenhum estágio para "Paga", da empresa da venda.
    expect($r['historico'])->toHaveCount(1);
    $h = $r['historico']->first();
    expect($h->action_id)->toBeNull();
    expect($h->from_stage_id)->toBeNull();
    expect((int) $h->to_stage_id)->toBe($paga);
    expect((int) $h->business_id)->toBe($this->bizId);
    expect((int) $h->user_id)->toBe((int) $this->user->id);
    expect(json_decode($h->payload_snapshot, true)['origem'])->toBe('venda_v3');

    // O valor e o estoque são os do UC-V380 — entrar no pipeline não muda a venda.
    expect((float) $r['venda']->final_total)->toBe(156.23);
    expect($r['saldoA'])->toBe(7.0);
});

it('UC-V400 · a marca de origem só aceita venda_v3: outro valor é recusado e nada grava', function () {
    $r = v3pipeVender($this, fn (array $p) => array_merge($p, ['origem_tela' => 'qualquer_coisa']));

    expect($r['id'])->toBeNull();
    $r['response']->assertSessionHasErrors('origem_tela');
});

it('UC-V401 · "Salvar como orçamento" grava orçamento, sem baixar estoque, e nasce em "Orçamento enviado"', function () {
    $r = v3pipeVender($this, fn (array $p) => array_merge($p, [
        'status' => 'quotation',
        'payment' => [['amount' => 0, 'method' => 'cash', 'paid_on' => '', 'account_id' => null, 'note' => '']],
    ]));

    expect($r['venda'])->not->toBeNull();
    expect($r['venda']->status)->toBe('draft');
    expect($r['venda']->sub_status)->toBe('quotation');
    expect((int) $r['venda']->is_quotation)->toBe(1);
    expect((float) $r['venda']->final_total)->toBe(156.23);
    expect($r['saldoA'])->toBe(10.0);

    $enviado = v3pipeEstagio($this->bizId, 'quote_sent');
    expect($enviado)->toBeGreaterThan(0);
    expect((int) $r['venda']->current_stage_id)->toBe($enviado);
    expect(app(InitialStageResolver::class)->resolve(\App\Transaction::findOrFail($r['id'])))->toBe('quote_sent');

    expect($r['historico'])->toHaveCount(1);
    expect($r['historico']->first()->action_id)->toBeNull();
    expect((int) $r['historico']->first()->to_stage_id)->toBe($enviado);
});

it('UC-V402 · venda pelo /pos sem a marca da V3 continua sem pipeline (fronteira do Sells/Create)', function () {
    $r = v3pipeVender($this, function (array $p) {
        unset($p['origem_tela']);

        return $p;
    });

    expect($r['venda'])->not->toBeNull();
    expect($r['venda']->status)->toBe('final');
    expect($r['venda']->current_stage_id)->toBeNull();
    expect($r['historico'])->toHaveCount(0);
});
