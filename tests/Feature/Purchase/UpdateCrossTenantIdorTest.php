<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIX 1 — IDOR de escrita cross-tenant em DINHEIRO (Purchase).
 *
 * PurchaseController@update fazia Transaction::findOrFail($id)->update() SEM where
 * business_id. App\Transaction NÃO tem global scope (guarded=['id'], extends Model),
 * então um usuário do business A alterava/apagava lançamento financeiro do business B.
 *
 * Fix: scopar a busca por business_id (session('user.business_id')), replicando o
 * padrão que JÁ existe em edit()/destroy() do mesmo arquivo. Cross-tenant agora 404
 * (ModelNotFoundException) em vez de carregar+mutar o Transaction de outro tenant.
 *
 * Default biz=1 (Wagner WR2 SC, ADR 0101); adversário cross-tenant = biz=99 (improvável
 * existir). NUNCA biz=4 — feedback_test_business_id_1_nunca_4 + tests/Unit/BusinessIdGuardTest.
 *
 * Quarentena Onda 2 SDD: schema sintético manual incompatível com MySQL persistente.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor.');
    }

    Schema::create('transactions', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id')->unsigned();
        $t->string('type')->default('purchase');
        $t->string('status')->default('received');
        $t->string('payment_status')->default('due');
        $t->integer('contact_id')->unsigned()->nullable();
        $t->dateTime('transaction_date')->nullable();
        $t->decimal('final_total', 22, 4)->default(0);
        $t->integer('created_by')->unsigned()->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('transactions');
    }
});

function idorCriarPurchase(int $bizId, float $total = 100.0): Transaction
{
    return Transaction::forceCreate([
        'business_id' => $bizId,
        'type' => 'purchase',
        'status' => 'received',
        'payment_status' => 'due',
        'contact_id' => 1,
        'transaction_date' => now(),
        'final_total' => $total,
        'created_by' => 1,
    ]);
}

// ─── O EXPLOIT FECHADO ──────────────────────────────────────────────────────

it('cross-tenant: usuário biz=1 NÃO resolve Transaction de biz=99 (404 ModelNotFoundException)', function () {
    $purchaseVitima = idorCriarPurchase(99, 5000.0);

    // Padrão EXATO que PurchaseController@update agora usa pra resolver o $transaction.
    $business_id = 1; // session('user.business_id') do atacante

    expect(fn () => Transaction::where('business_id', $business_id)
        ->where('id', $purchaseVitima->id)
        ->firstOrFail()
    )->toThrow(ModelNotFoundException::class);
});

it('cross-tenant: o lançamento financeiro da vítima permanece INTACTO (sem update cross-tenant)', function () {
    $purchaseVitima = idorCriarPurchase(99, 5000.0);

    $business_id = 1; // atacante

    // O fluxo do controller: resolve scopado → se 404, nunca chega no ->update().
    $transaction = Transaction::where('business_id', $business_id)
        ->where('id', $purchaseVitima->id)
        ->first();

    expect($transaction)->toBeNull();

    // Banco inalterado: o final_total da vítima continua 5000, business_id 99.
    $fresh = Transaction::find($purchaseVitima->id);
    expect((float) $fresh->final_total)->toBe(5000.0);
    expect($fresh->business_id)->toBe(99);
});

// ─── COMPORTAMENTO LEGÍTIMO PRESERVADO ──────────────────────────────────────

it('same-tenant: usuário biz=1 resolve E atualiza o PRÓPRIO Transaction normalmente', function () {
    $purchase = idorCriarPurchase(1, 100.0);

    $business_id = 1;

    $transaction = Transaction::where('business_id', $business_id)
        ->where('id', $purchase->id)
        ->firstOrFail();

    expect($transaction->id)->toBe($purchase->id);

    $transaction->update(['final_total' => 250.0]);

    $fresh = Transaction::find($purchase->id);
    expect((float) $fresh->final_total)->toBe(250.0);
});

// ─── ANTI-REGRESSÃO DE FONTE (a catraca não pode reverter pro findOrFail nu) ──

it('Controller@update scopa a busca por business_id (sem findOrFail nu)', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/PurchaseController.php'));

    // Recorta o corpo do método update() pra não pegar matches de outros métodos.
    $start = strpos($source, 'public function update(Request $request, $id)');
    $end = strpos($source, 'public function destroy($id)', $start);
    $updateBody = substr($source, $start, $end - $start);

    // A busca do $transaction tem que ser scopada por business_id.
    expect($updateBody)->toMatch("/Transaction::where\\('business_id', \\\$business_id\\)/");

    // E NÃO pode mais existir o Transaction::findOrFail($id) nu dentro do update().
    expect($updateBody)->not->toContain('Transaction::findOrFail($id)');
});
