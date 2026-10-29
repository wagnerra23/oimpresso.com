<?php
// @covers-us US-COMM-016

declare(strict_types=1);

use App\Business;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * UC-SBCOM-01 · P-156 — comissão do representante por cliente, liberada por parcela recebida.
 *
 * Âncora: resources/js/Pages/Report/SalesRepresentative/Index.casos.md (UC-SBCOM-*), derivada do
 * pedido P-156 e da medição no Delphi do Sabor Brasil (`COMISSAO_FINANCEIRO`: comissão = parcela
 * × % da venda, base com ST).
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('transactions', 'comissao_cliente_percentual')) {
        $this->markTestSkipped('Schema ausente — roda na lane MySQL (sells-pest).');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->negocio = Business::findOrFail($this->biz);
    $this->dono = EstoqueFixture::userId($this->biz);
    $this->loc = EstoqueFixture::locationId($this->biz, 'SBCOM');

    $this->cliente = fn (int $biz, ?float $pct) => (int) DB::table('contacts')->insertGetId(['business_id' => $biz, 'type' => 'customer',
        'name' => 'Cliente SBCOM', 'contact_id' => 'SBCOM-' . uniqid(), 'comissao_representante_percentual' => $pct,
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now()]);
});

it('UC-SBCOM-01 a venda nasce com o % do cliente congelado; cliente sem % fica sem regra', function () {
    $novo = function (int $contato): Transaction {
        $t = new Transaction(['business_id' => $this->biz, 'location_id' => $this->loc, 'type' => 'sell', 'status' => 'draft',
            'payment_status' => 'due', 'contact_id' => $contato, 'transaction_date' => now(), 'final_total' => 10,
            'created_by' => $this->dono]);
        $t->save();

        return $t;
    };

    $comPct = ($this->cliente)($this->biz, 7.5);
    $venda = $novo($comPct);
    DB::table('contacts')->where('id', $comPct)->update(['comissao_representante_percentual' => 15]);

    expect((float) $venda->fresh()->comissao_cliente_percentual)->toBe(7.5);
    expect($novo(($this->cliente)($this->biz, null))->fresh()->comissao_cliente_percentual)->toBeNull();
});
