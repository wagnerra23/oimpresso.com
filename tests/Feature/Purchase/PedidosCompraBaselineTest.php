<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Baseline F2 (MWART, ADR 0104) de /purchase-order — o que a Blade lista ANTES da tela React.
 *
 * Thread compras/playbook/06 (Pedidos de compra). Trava o ramo DataTable de
 * `PurchaseOrderController::index` como ele é hoje, para a F3 (tela React atrás de flag)
 * provar que não mudou o que a lista devolve. Sem mudança de código de produção.
 *
 * "Quantidade restante" é QUANTIDADE (não valor nem movimento de estoque): a coluna exibe
 * SUM(quantity − po_quantity_purchased) das linhas do pedido. O caso dela prova o mesmo
 * número por dois caminhos — a conta à mão sobre o fixture e a coluna da DataTable.
 *
 * Por que cada isolamento roda em PAR (controle positivo + contrato): sem a linha própria
 * aparecendo, "a alheia não aparece" fica verde com lista vazia ou 403 (§5 2026-07-24).
 *
 * Tenant do usuário = 98 (ADR 0358). O outro negócio é o 99 com location própria: a lista
 * faz INNER join em `business_locations`, então sem location o pedido alheio sumiria até
 * vazando. NUNCA biz=4.
 *
 * Prefixo `pedBase` nas funções (o escopo global do Pest é compartilhado).
 * Mapa de paridade: memory/requisitos/Compras/_telas/purchase-order-parity.md.
 *
 * @see app/Http/Controllers/PurchaseOrderController.php::index
 * @see memory/requisitos/Compras/_telas/RUNBOOK-purchase-order-index.md
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('transactions') || ! Schema::hasTable('purchase_lines') || ! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-PED');
    $this->user = $this->usuarioComPermissoes(['purchase_order.view_all', 'access_all_locations'], $this->business);
    $this->actingAs($this->user);
    pedBaseSessao($this, $this->user);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

/** Sessão como o SetSessionData monta: a coluna de quantidade lê `business.*` e `currency`. */
function pedBaseSessao($teste, $user): void
{
    session([
        'user.id' => $user->id, 'user.business_id' => $teste->business->id,
        'business' => ['id' => $teste->business->id, 'quantity_precision' => 2, 'date_format' => 'd/m/Y', 'time_format' => 24],
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
}

/** Pedido de compra cru na `transactions`. */
function pedBasePedido(int $businessId, int $localId, int $criadoPor, array $campos = []): int
{
    return DB::table('transactions')->insertGetId(array_merge([
        'business_id' => $businessId, 'location_id' => $localId, 'created_by' => $criadoPor,
        'type' => 'purchase_order', 'status' => 'ordered', 'shipping_status' => 'ordered',
        'ref_no' => 'PO-'.uniqid(), 'transaction_date' => now(), 'total_before_tax' => 100,
        'final_total' => 100, 'tax_amount' => 0, 'discount_amount' => 0, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

/** Linha do pedido: `pedido` e `comprado` viram `quantity` e `po_quantity_purchased`. */
function pedBaseLinha(int $pedidoId, int $businessId, float $pedido, float $comprado): void
{
    $produto = EstoqueFixture::singleProduct($businessId);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $pedidoId, 'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'], 'quantity' => $pedido,
        'po_quantity_purchased' => $comprado, 'purchase_price' => 1, 'purchase_price_inc_tax' => 1,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Linhas da DataTable, por `ref_no`. */
function pedBaseLista($teste, string $query = ''): \Illuminate\Support\Collection
{
    $r = $teste->withHeaders($teste->ajax)->get('/purchase-order'.($query !== '' ? '?'.$query : ''));
    $r->assertOk();

    return collect($r->json('data'))->keyBy(fn ($l) => strip_tags((string) $l['ref_no']));
}

test('baseline: a lista traz os pedidos do negócio e não os de outro negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $localOutro = EstoqueFixture::locationId($outro->id, '-PED');
    $meu = 'PO-MEU-'.uniqid();
    $alheio = 'PO-ALHEIO-'.uniqid();
    pedBasePedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $meu]);
    pedBasePedido($outro->id, $localOutro, $this->user->id, ['ref_no' => $alheio]);

    $refs = pedBaseLista($this)->keys()->all();

    expect($refs)->toContain($meu);      // controle positivo
    expect($refs)->not->toContain($alheio);
});

test('baseline: a lista traz só pedido de compra — compra recebida do mesmo negócio fica fora', function () {
    $pedido = 'PO-TIPO-'.uniqid();
    $compra = 'COMPRA-TIPO-'.uniqid();
    pedBasePedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $pedido]);
    pedBasePedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $compra, 'type' => 'purchase', 'status' => 'received']);

    $refs = pedBaseLista($this)->keys()->all();

    expect($refs)->toContain($pedido);
    expect($refs)->not->toContain($compra);
});

test('baseline quantidade: restante é pedido menos já comprado, somado nas linhas — por dois caminhos', function () {
    $ref = 'PO-QTD-'.uniqid();
    $id = pedBasePedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $ref, 'status' => 'partial']);
    pedBaseLinha($id, $this->business->id, 10, 3);
    pedBaseLinha($id, $this->business->id, 5, 0);

    // Caminho 1: à mão. (10 − 3) + (5 − 0) = 12.
    $aMao = (10 - 3) + (5 - 0);
    expect($aMao)->toBe(12);

    // Caminho 2: a coluna da DataTable, formatada pelo @format_quantity (precisão 2, vírgula decimal).
    $celula = (string) pedBaseLista($this)[$ref]['po_qty_remaining'];
    expect(trim($celula))->toBe(number_format($aMao, 2, ',', '.'));
});

test('baseline: o filtro de status traz só o status pedido', function () {
    $parcial = 'PO-PARC-'.uniqid();
    $concluido = 'PO-CONC-'.uniqid();
    pedBasePedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $parcial, 'status' => 'partial']);
    pedBasePedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $concluido, 'status' => 'completed']);

    $refs = pedBaseLista($this, 'status=partial')->keys()->all();

    expect($refs)->toContain($parcial);
    expect($refs)->not->toContain($concluido);
});

test('baseline: com só "ver os próprios", a lista traz só os pedidos que o usuário criou', function () {
    $proprio = $this->usuarioComPermissoes(['purchase_order.view_own', 'access_all_locations'], $this->business);
    $meu = 'PO-PROPRIO-'.uniqid();
    $doColega = 'PO-COLEGA-'.uniqid();
    pedBasePedido($this->business->id, $this->local, $proprio->id, ['ref_no' => $meu]);
    pedBasePedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $doColega]);

    $this->actingAs($proprio);
    pedBaseSessao($this, $proprio);
    $refs = pedBaseLista($this)->keys()->all();

    expect($refs)->toContain($meu);
    expect($refs)->not->toContain($doColega);
});

test('baseline: sem acesso a todos os locais, só aparecem pedidos dos locais permitidos', function () {
    $outroLocal = EstoqueFixture::locationId($this->business->id, '-PED2');
    $restrito = $this->usuarioComPermissoes(['purchase_order.view_all'], $this->business);
    // `permitted_locations()` lê as permissões DIRETAS do usuário, não as do papel.
    \Spatie\Permission\Models\Permission::findOrCreate("location.{$this->local}", 'web');
    $restrito->givePermissionTo("location.{$this->local}");
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $restrito = \App\User::findOrFail($restrito->id);

    $permitido = 'PO-LOC-OK-'.uniqid();
    $vetado = 'PO-LOC-NAO-'.uniqid();
    pedBasePedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $permitido]);
    pedBasePedido($this->business->id, $outroLocal, $this->user->id, ['ref_no' => $vetado]);

    $this->actingAs($restrito);
    pedBaseSessao($this, $restrito);
    $refs = pedBaseLista($this)->keys()->all();

    expect($refs)->toContain($permitido);
    expect($refs)->not->toContain($vetado);
});

test('baseline: sem permissão de ver pedidos, a lista responde 403', function () {
    $semAcesso = $this->usuarioComPermissoes(['access_all_locations'], $this->business);
    $this->actingAs($semAcesso);
    pedBaseSessao($this, $semAcesso);

    $this->withHeaders($this->ajax)->get('/purchase-order')->assertForbidden();
});
