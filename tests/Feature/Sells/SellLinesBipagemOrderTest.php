<?php

declare(strict_types=1);

use App\Product;
use App\Transaction;
use App\TransactionSellLine;
use Illuminate\Support\Facades\DB;

/**
 * US-SELL — Transaction::sell_lines() preserva ordem de bipagem/inserção.
 *
 * Bug reportado por Guilherme (Rota Livre, biz=4, 2026-07-24 WhatsApp): recibo/romaneio
 * impresso saía com os produtos ordenados do menor pro maior código, em vez da ordem
 * que a peça foi bipada no balcão. Causa: nenhuma query de `sell_lines` (getReceiptDetails,
 * sheetData, show(), edit()) tinha ORDER BY explícito — o resultado ficava sujeito ao
 * plano de execução do MySQL, que pode preferir um índice secundário (ex: product_id)
 * em vez do scan pela PK, silenciosamente reordenando por código do produto.
 *
 * Fix: `Transaction::sell_lines()` agora tem `orderBy('transaction_sell_lines.id')` —
 * id ASC corresponde exatamente à sequência de `saveMany()` em
 * TransactionUtil::createOrUpdateSellLines(), que por sua vez preserva a ordem do
 * array `products` enviado pelo frontend (ordem de bipagem, Sells/Create.tsx
 * handleAddProduct faz push no fim do array).
 *
 * @see app/Transaction.php sell_lines()
 * @see app/Utils/TransactionUtil.php getReceiptDetails() / createOrUpdateSellLines()
 */

const BIZ_ROTA_LIVRE_TEST = 1; // Wagner WR2 — smoke NUNCA em biz=4 cliente (ADR 0101)
const SELL_LINES_ORDER_INVOICE_PREFIX = 'INV-BIPAGEM-TEST';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0101).');
    }
});

afterEach(function () {
    DB::table('transaction_sell_lines')
        ->whereIn('transaction_id', function ($q) {
            $q->select('id')->from('transactions')->where('invoice_no', 'like', SELL_LINES_ORDER_INVOICE_PREFIX . '-%');
        })
        ->delete();
    DB::table('transactions')->where('invoice_no', 'like', SELL_LINES_ORDER_INVOICE_PREFIX . '-%')->delete();
});

/**
 * 3 produtos reais de biz=1 com pelo menos 1 variação — usados pra montar linhas
 * cujo product_id NÃO segue a ordem de inserção (embaralhado de propósito).
 *
 * @return array<int, array{product_id:int, variation_id:int}>
 */
function bipagemTestProducts(): array
{
    $products = Product::withoutGlobalScopes()
        ->where('business_id', BIZ_ROTA_LIVRE_TEST)
        ->whereHas('variations')
        ->with(['variations' => fn ($q) => $q->limit(1)])
        ->orderByDesc('id')
        ->limit(3)
        ->get();

    return $products->map(fn ($p) => [
        'product_id' => $p->id,
        'variation_id' => $p->variations->first()->id,
    ])->all();
}

function bipagemTestTransaction(): Transaction
{
    $tx = new Transaction();
    $tx->business_id = BIZ_ROTA_LIVRE_TEST;
    $tx->location_id = 1;
    $tx->type = 'sell';
    $tx->status = 'final';
    $tx->payment_status = 'due';
    $tx->contact_id = 1;
    $tx->invoice_no = SELL_LINES_ORDER_INVOICE_PREFIX . '-' . uniqid();
    $tx->transaction_date = now();
    $tx->total_before_tax = 30;
    $tx->final_total = 30;
    $tx->created_by = 1;
    $tx->save();

    return $tx;
}

it('sell_lines() retorna linhas na ordem de bipagem (id ASC), não na ordem do código do produto', function () {
    $catalog = bipagemTestProducts();

    if (count($catalog) < 3) {
        $this->markTestSkipped('biz=1 precisa de >= 3 produtos com variação (seed UltimatePOS ausente).');
    }

    // Ordem de código descendente: catalog[0] é o produto de MAIOR id/código.
    // Bipagem embaralhada de propósito — nem ascendente nem descendente por código,
    // pra provar que a ordem de retorno segue INSERÇÃO, não o campo do código.
    $scanOrder = [$catalog[1], $catalog[0], $catalog[2]];

    $tx = bipagemTestTransaction();

    $insertedIds = [];
    foreach ($scanOrder as $item) {
        $line = new TransactionSellLine();
        $line->transaction_id = $tx->id;
        $line->product_id = $item['product_id'];
        $line->variation_id = $item['variation_id'];
        $line->quantity = 1;
        $line->unit_price = 10;
        $line->unit_price_inc_tax = 10;
        $line->item_tax = 0;
        $line->save();
        $insertedIds[] = $line->id;
    }

    // Reload forçado — sem cache de relação, pra exercitar a query real.
    $reloaded = $tx->fresh();

    expect($reloaded->sell_lines->pluck('id')->all())->toBe($insertedIds);
    expect($reloaded->sell_lines->pluck('product_id')->all())
        ->toBe(array_column($scanOrder, 'product_id'))
        ->not->toBe(collect($scanOrder)->pluck('product_id')->sort()->values()->all());

    // Mesma garantia via sell_lines() query builder direto (usado por
    // getReceiptDetails/sheetData/show/edit — todos chamam ->sell_lines() ou
    // acessam a relação já carregada).
    expect(
        $tx->sell_lines()->whereNull('parent_sell_line_id')->get()->pluck('id')->all()
    )->toBe($insertedIds);
});
