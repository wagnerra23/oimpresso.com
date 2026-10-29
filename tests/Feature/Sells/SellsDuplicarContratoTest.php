<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Duplicar a venda guardando a origem (`GET /sells/duplicate/{id}` → `SellController::duplicateSell`).
 *
 * Casos: P-035 em `Sells/Create.casos.md` (UC-SDUP-01..03). Derivados do aceite do pedido,
 * não do código.
 *
 * REGRA MESTRE (valor e estoque): esta mudança não muda conta nenhuma. A cópia continua com o
 * preço da origem (D-05 segue em aberto para o [W]) e nasce rascunho, que não mexe em estoque.
 * Valores à mão: origem = 3 un × 20,00 = 60,00 · cópia = 3 un × 20,00 = 60,00 · estoque do
 * produto no local: 10 antes e 10 depois de duplicar.
 *
 * Tenants: 98 (canônico, ADR 0358) × outro business semeado. Nunca biz=1 nem biz=4.
 * Não roda local (proibicoes.md): lane sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function sdupUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SDUP',
        'username' => 'sdup_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('transactions', 'duplicated_from_id')) {
        $this->markTestSkipped('Schema UltimatePOS MySQL ausente — roda na lane sells-pest.yml / CT 100.');
    }

    $this->bizId = EstoqueFixture::businessId();
    $this->outroBizId = EstoqueFixture::secondBusinessId();
    expect($this->bizId)->toBe(98);
    if ($this->outroBizId === null) {
        $this->markTestSkipped('Lane sem 2º business semeado — contrato cross-tenant não exercitável.');
    }

    $this->prefixo = 'SDUP' . strtoupper(substr(uniqid(), -6)) . '-';
    $this->user = sdupUsuario($this->bizId, ['sell.create', 'direct_sell.view', 'access_all_locations']);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->bizId, 'user.id' => $this->user->id]);

    // Origem: venda final, 1 linha de 3 un × 20,00, dentro de um pipeline FSM qualquer.
    $this->local = EstoqueFixture::locationId($this->bizId, 'SDUP');
    $this->produto = EstoqueFixture::singleProduct($this->bizId);
    EstoqueFixture::setStock($this->produto, 0, $this->local, 10);
    $venda = EstoqueFixture::saleWithLine($this->produto, 0, $this->local, 3, 20.0);
    $this->origemId = $venda['transaction_id'];
    DB::table('transactions')->where('id', $this->origemId)->update([
        'invoice_no' => $this->prefixo . 'ORIG',
        'is_direct_sale' => 1,
        'process_id' => 987001,
        'current_stage_id' => 987002,
    ]);

    $this->duplicar = function (int $id) {
        $antes = (int) DB::table('transactions')->max('id');
        $resp = $this->get('/sells/duplicate/' . $id);

        return [$resp, DB::table('transactions')->where('id', '>', $antes)->where('duplicated_from_id', $id)->first()];
    };
});

it('UC-SDUP-01 duplicar cria um rascunho novo que guarda a venda de origem', function () {
    [$resp, $copia] = ($this->duplicar)($this->origemId);

    $resp->assertRedirect();
    expect($copia)->not->toBeNull('Duplicar não gravou a origem na cópia (P-035).');
    expect((int) $copia->duplicated_from_id)->toBe($this->origemId);
    expect((int) $copia->business_id)->toBe($this->bizId);
    expect($copia->status)->toBe('draft');
    expect($copia->payment_status)->toBeNull();
    expect($copia->invoice_no)->not->toBe($this->prefixo . 'ORIG');
});

it('UC-SDUP-02 a cópia leva os mesmos itens e preços da origem e não mexe no estoque', function () {
    [, $copia] = ($this->duplicar)($this->origemId);
    expect($copia)->not->toBeNull();

    // Prova 1 — à mão: 3 × 20,00 = 60,00 nas duas.
    expect((float) $copia->final_total)->toBe(60.0);

    // Prova 2 — as linhas da cópia contra as da origem, campo a campo.
    $linhas = fn (int $id) => DB::table('transaction_sell_lines')->where('transaction_id', $id)
        ->orderBy('id')->get(['product_id', 'variation_id', 'quantity', 'unit_price_inc_tax'])
        ->map(fn ($l) => [(int) $l->product_id, (int) $l->variation_id, (float) $l->quantity, (float) $l->unit_price_inc_tax])
        ->all();
    expect($linhas((int) $copia->id))->toBe($linhas($this->origemId));
    expect($linhas((int) $copia->id))->toBe([[$this->produto->productId, $this->produto->variations[0]['variation_id'], 3.0, 20.0]]);

    expect(EstoqueFixture::currentStock($this->produto, 0, $this->local))->toBe(10.0);
});

it('UC-SDUP-03 a cópia nasce fora do pipeline FSM, sem herdar o estágio da origem', function () {
    [, $copia] = ($this->duplicar)($this->origemId);
    expect($copia)->not->toBeNull();

    expect($copia->process_id)->toBeNull();
    expect($copia->current_stage_id)->toBeNull();
    // A origem não foi tocada.
    expect((int) DB::table('transactions')->where('id', $this->origemId)->value('current_stage_id'))->toBe(987002);
});
