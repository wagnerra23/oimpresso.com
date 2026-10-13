<?php

declare(strict_types=1);
// Cobre UC-PEDC-01, UC-PEDC-02, UC-PEDC-03, UC-PEDC-04, UC-PEDC-05 (PurchaseOrder/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Thread compras/playbook/06, F3 — /purchase-order em Inertia atrás da flag useV2ComprasPedidos.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * O SQL é o mesmo da DataTable (PurchaseOrderController::consultaPedidos): PedidosCompraBaselineTest trava a Blade.
 * A quantidade restante é provada por dois caminhos: à mão sobre o fixture e o texto da coluna da DataTable.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). NUNCA biz=4.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('transactions') || ! Schema::hasTable('purchase_lines') || ! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-PEDC');
    $this->user = $this->usuarioComPermissoes(['purchase_order.view_all', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    pedContratoSessao($this, $this->user);
});

/** Sessão como o SetSessionData monta: data e quantidade leem `business.*` e `currency`. */
function pedContratoSessao($teste, $user): void
{
    session([
        'user.id' => $user->id, 'user.business_id' => $teste->business->id,
        'business' => ['id' => $teste->business->id, 'quantity_precision' => 2, 'date_format' => 'd/m/Y', 'time_format' => 24],
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
}

function pedContratoPedido(int $businessId, int $localId, int $criadoPor, array $campos = []): int
{
    return DB::table('transactions')->insertGetId(array_merge([
        'business_id' => $businessId, 'location_id' => $localId, 'created_by' => $criadoPor,
        'type' => 'purchase_order', 'status' => 'ordered', 'shipping_status' => 'ordered',
        'ref_no' => 'PO-'.uniqid(), 'transaction_date' => now(), 'total_before_tax' => 100,
        'final_total' => 100, 'tax_amount' => 0, 'discount_amount' => 0, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

function pedContratoLinha(int $pedidoId, int $businessId, float $pedido, float $comprado): void
{
    $produto = EstoqueFixture::singleProduct($businessId);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $pedidoId, 'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'], 'quantity' => $pedido,
        'po_quantity_purchased' => $comprado, 'purchase_price' => 1, 'purchase_price_inc_tax' => 1,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Prop deferida `pedidos` (linhas por `ref` + kpis). */
function pedContratoLista($teste, string $query = ''): array
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => 'PurchaseOrder/Index', 'X-Inertia-Partial-Data' => 'pedidos',
    ])->get('/purchase-order'.($query !== '' ? '?'.$query : ''));
    $r->assertOk();
    $p = $r->json('props.pedidos') ?? ['linhas' => [], 'kpis' => []];

    return ['linhas' => collect($p['linhas'])->keyBy('ref'), 'kpis' => $p['kpis']];
}

function pedContratoFlagDesligada(): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2ComprasPedidos' ? false : parent::isOn($flag, $attrs);
        }
    });
}

test('UC-PEDC-01 com a flag ligada, GET /purchase-order renderiza Inertia (não a DataTable)', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasPedidos']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/purchase-order');

    $r->assertOk();
    expect($r->json('component'))->toBe('PurchaseOrder/Index');
});

test('UC-PEDC-02 com a flag desligada, ou com ?legacy=1, GET /purchase-order segue na Blade', function () {
    pedContratoFlagDesligada();
    $this->get('/purchase-order')->assertOk()->assertViewIs('purchase_order.index');

    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    config(['feature-flags.forced_on' => 'useV2ComprasPedidos']);
    $this->get('/purchase-order?legacy=1')->assertOk()->assertViewIs('purchase_order.index');
});

test('UC-PEDC-03 Tier 0 — meus pedidos, com a mesma quantidade restante da Blade', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasPedidos']);
    $outro = $this->seededSupportClientTenant();
    $meu = 'PO-MEU-'.uniqid();
    $compra = 'COMPRA-'.uniqid();
    $alheio = 'PO-ALHEIO-'.uniqid();
    $id = pedContratoPedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $meu, 'status' => 'partial']);
    pedContratoLinha($id, $this->business->id, 10, 3);
    pedContratoLinha($id, $this->business->id, 5, 0);
    pedContratoPedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $compra, 'type' => 'purchase', 'status' => 'received']);
    pedContratoPedido($outro->id, EstoqueFixture::locationId($outro->id, '-PEDC'), $this->user->id, ['ref_no' => $alheio]);

    $linhas = pedContratoLista($this)['linhas'];

    expect($linhas->has($meu))->toBeTrue();     // controle positivo
    expect($linhas->has($compra))->toBeFalse();
    expect($linhas->has($alheio))->toBeFalse();

    // Caminho 1: à mão. (10 − 3) + (5 − 0) = 12.
    expect((float) $linhas[$meu]['restante'])->toBe(12.0);
    // Caminho 2: o texto é o mesmo da coluna da DataTable da Blade para o mesmo pedido.
    // `withHeaders` persiste entre requisições do mesmo teste: sem limpar, esta chamada sairia com o
    // `X-Inertia` da anterior e cairia na tela React, não na DataTable (run 38404610982).
    $this->flushHeaders();
    $blade = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get('/purchase-order');
    $coluna = collect($blade->json('data'))->first(fn ($l) => strip_tags((string) $l['ref_no']) === $meu);
    expect($coluna)->not->toBeNull();
    expect($linhas[$meu]['restante_texto'])->toBe(trim((string) $coluna['po_qty_remaining']));
    expect($linhas[$meu]['restante_texto'])->toBe('12,00');
});

test('UC-PEDC-04 Tier 0 — sem acesso a todos os locais, só os pedidos dos locais permitidos', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasPedidos']);
    $outroLocal = EstoqueFixture::locationId($this->business->id, '-PEDC2');
    $permitido = 'PO-LOC-OK-'.uniqid();
    $vetado = 'PO-LOC-NAO-'.uniqid();
    pedContratoPedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $permitido]);
    pedContratoPedido($this->business->id, $outroLocal, $this->user->id, ['ref_no' => $vetado]);

    // `permitted_locations()` lê as permissões DIRETAS do usuário, não as do papel.
    $restrito = $this->usuarioComPermissoes(['purchase_order.view_all'], $this->business);
    \Spatie\Permission\Models\Permission::findOrCreate("location.{$this->local}", 'web');
    $restrito->givePermissionTo("location.{$this->local}");
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $restrito = \App\User::findOrFail($restrito->id);
    $this->actingAs($restrito);
    pedContratoSessao($this, $restrito);

    $linhas = pedContratoLista($this)['linhas'];

    expect($linhas->has($permitido))->toBeTrue();
    expect($linhas->has($vetado))->toBeFalse();
});

test('UC-PEDC-05 o filtro de status chega ao servidor e o KPI conta só os não concluídos', function () {
    config(['feature-flags.forced_on' => 'useV2ComprasPedidos']);
    $parcial = 'PO-PARC-'.uniqid();
    $concluido = 'PO-CONC-'.uniqid();
    pedContratoPedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $parcial, 'status' => 'partial']);
    pedContratoPedido($this->business->id, $this->local, $this->user->id, ['ref_no' => $concluido, 'status' => 'completed']);

    $filtrada = pedContratoLista($this, 'status=partial')['linhas'];
    expect($filtrada->has($parcial))->toBeTrue();
    expect($filtrada->has($concluido))->toBeFalse();

    $toda = pedContratoLista($this);
    expect($toda['linhas']->has($concluido))->toBeTrue();
    $naoConcluidos = $toda['linhas']->where('status', '!=', 'completed')->count();
    expect($toda['kpis']['em_aberto'])->toBe($naoConcluidos);
});
