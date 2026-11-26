<?php
// @covers-us US-ESTOQUE-004

declare(strict_types=1);

use App\Domain\Fsm\Models\StockReservation;
use App\Domain\Fsm\SideEffects\ReservarEstoque;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Reserva da venda sem mexer na tela de Venda (decisão [W] 2026-11-26).
 *
 * Âncora: US-ESTOQUE-004 em memory/requisitos/Estoque/SPEC.md. Regra do [W]: "estoque reservado
 * não é baixado novamente; o material, quando efetivada a saída, sai do saldo reservado e baixa do
 * estoque total".
 *
 * Números feitos à mão (saldo 10 no local da venda):
 *   venda de 5, 1 devolvida  → reserva 4; saldo segue 10 (reserva ≠ baixa)
 *   venda vira final         → reserva 4 sai de ativa (released); este código não baixa: saldo 10
 *                              (a baixa única é a do núcleo, que levaria o saldo a 6)
 *   produto sem controle de estoque / venda já final → nenhuma reserva
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('stock_reservations')) {
        $this->markTestSkipped('Schema UltimatePOS/stock_reservations ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->loc = EstoqueFixture::locationId($this->biz, 'RESV');

    // Venda em rascunho (orçamento aprovado, ainda sem saída) com 1 linha.
    $this->venda = function (int $biz, int $loc, bool $enableStock = true, float $vendida = 5, float $devolvida = 0) {
        $p = EstoqueFixture::singleProduct($biz, $enableStock);
        EstoqueFixture::setStock($p, 0, $loc, 10);
        $ids = EstoqueFixture::saleWithLine($p, 0, $loc, $vendida);
        DB::table('transactions')->where('id', $ids['transaction_id'])->update(['status' => 'draft']);
        DB::table('transaction_sell_lines')->where('id', $ids['sell_line_id'])->update(['quantity_returned' => $devolvida]);
        // SUPERADMIN: teste sem sessão; a empresa é a da própria venda.
        $tx = Transaction::withoutGlobalScopes()->findOrFail($ids['transaction_id']);

        return [$p, $tx];
    };
    $this->reservas = fn (int $txId, string $status = 'active') => DB::table('stock_reservations')
        ->where('transaction_id', $txId)->where('status', $status)->get();
});

it('sem lista de itens, reserva as linhas da venda (vendido − devolvido) e não mexe no saldo', function () {
    [$p, $tx] = ($this->venda)($this->biz, $this->loc, true, 5, 1);

    (new ReservarEstoque())->execute($tx, []);

    $r = ($this->reservas)($tx->id);
    expect($r)->toHaveCount(1);
    expect([(int) $r[0]->variation_id, (int) $r[0]->location_id, (float) $r[0]->qty_reserved, (int) $r[0]->business_id])
        ->toBe([$p->variations[0]['variation_id'], $this->loc, 4.0, $this->biz]);
    expect(EstoqueFixture::currentStock($p, 0, $this->loc))->toBe(10.0);

    // Rodar de novo não duplica.
    (new ReservarEstoque())->execute($tx, []);
    expect(($this->reservas)($tx->id))->toHaveCount(1);
});

it('não reserva produto sem controle de estoque nem venda já finalizada', function () {
    [, $semEstoque] = ($this->venda)($this->biz, $this->loc, false);
    (new ReservarEstoque())->execute($semEstoque, []);
    expect(($this->reservas)($semEstoque->id))->toHaveCount(0);

    [, $final] = ($this->venda)($this->biz, $this->loc);
    DB::table('transactions')->where('id', $final->id)->update(['status' => 'final']);
    $final->refresh();
    (new ReservarEstoque())->execute($final, []);
    expect(($this->reservas)($final->id))->toHaveCount(0);
});

it('saída efetivada: a reserva sai do saldo reservado e o observer não baixa de novo', function () {
    [$p, $tx] = ($this->venda)($this->biz, $this->loc, true, 4);
    (new ReservarEstoque())->execute($tx, []);
    expect(($this->reservas)($tx->id))->toHaveCount(1);

    $tx->status = 'final';
    $tx->save();

    expect(($this->reservas)($tx->id))->toHaveCount(0);
    expect(($this->reservas)($tx->id, StockReservation::STATUS_RELEASED))->toHaveCount(1);
    // O observer não toca o saldo; a baixa única é a do núcleo, fora deste código.
    expect(EstoqueFixture::currentStock($p, 0, $this->loc))->toBe(10.0);
});

it('venda cancelada antes da saída libera a reserva', function () {
    [, $tx] = ($this->venda)($this->biz, $this->loc);
    (new ReservarEstoque())->execute($tx, []);

    $tx->forceFill(['status' => 'cancelled'])->save();

    expect(($this->reservas)($tx->id))->toHaveCount(0);
    expect(($this->reservas)($tx->id, StockReservation::STATUS_RELEASED))->toHaveCount(1);
});

it('[T0] finalizar a venda de uma empresa não encerra a reserva da outra', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Só um business semeado.');
    }
    [, $minha] = ($this->venda)($this->biz, $this->loc);
    [, $dele] = ($this->venda)($outro, EstoqueFixture::locationId($outro, 'RESV'));
    (new ReservarEstoque())->execute($minha, []);
    (new ReservarEstoque())->execute($dele, []);
    expect((int) ($this->reservas)($dele->id)[0]->business_id)->toBe($outro);

    $minha->status = 'final';
    $minha->save();

    expect(($this->reservas)($minha->id))->toHaveCount(0);
    expect(($this->reservas)($dele->id))->toHaveCount(1);
});
