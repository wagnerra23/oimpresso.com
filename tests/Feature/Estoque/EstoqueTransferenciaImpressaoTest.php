<?php

declare(strict_types=1);

// @covers-uc UC-TRIDX-04 (resources/js/Pages/StockTransfer/Index.casos.md)

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * GET /stock-transfers/print/{id} — o recibo que o "Imprimir" da lista React pede.
 *
 * CONTRATO: Index.casos UC-TRIDX-04 (recibo da Blade `stock_transfer.print`, só do próprio
 * business). A impressão fica em Blade por decisão [W] de 2026-10-27.
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    $this->usuario = User::find(EstoqueFixture::userId($this->biz));
    Permission::firstOrCreate(['name' => 'purchase.create', 'guard_name' => 'web']);
    $this->usuario->givePermissionTo('purchase.create');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sessao = [
        'user.id' => $this->usuario->id,
        'user.business_id' => $this->biz,
        'business' => Business::find($this->biz),
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ];

    // Transferência pendente: origem e destino do próprio business, sem mexer em saldo.
    $origem = EstoqueFixture::locationId($this->biz, '-TI-ORIG-' . uniqid());
    $destino = EstoqueFixture::locationId($this->biz, '-TI-DEST-' . uniqid());
    $this->ref = 'TI-' . uniqid();
    $base = [
        'business_id' => $this->biz, 'status' => 'pending', 'payment_status' => 'paid',
        'transaction_date' => now(), 'total_before_tax' => 0, 'final_total' => 0,
        'created_by' => $this->usuario->id, 'essentials_duration' => 0, 'ref_no' => $this->ref,
        'created_at' => now(), 'updated_at' => now(),
    ];
    $this->vendaId = (int) DB::table('transactions')->insertGetId($base + ['type' => 'sell_transfer', 'location_id' => $origem]);
    DB::table('transactions')->insert($base + ['type' => 'purchase_transfer', 'location_id' => $destino, 'transfer_parent_id' => $this->vendaId]);
});

it('UC-TRIDX-04 · o recibo da própria transferência sai com a referência dela', function () {
    $r = $this->actingAs($this->usuario)->withSession($this->sessao)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get("/stock-transfers/print/{$this->vendaId}");

    $r->assertOk();
    expect($r->json('success'))->toBe(1);
    expect((string) $r->json('receipt.html_content'))->toContain($this->ref);
});

it('UC-TRIDX-04 · [T0] outro business não recebe o recibo', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Sem 2º business semeado.');
    }
    $alheio = User::find(EstoqueFixture::userId($outro));

    $r = $this->actingAs($alheio)
        ->withSession(['user.id' => $alheio->id, 'user.business_id' => $outro] + $this->sessao)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get("/stock-transfers/print/{$this->vendaId}");

    expect($r->json('success'))->toBe(0);
    expect($r->json('receipt'))->toBeNull();
});
