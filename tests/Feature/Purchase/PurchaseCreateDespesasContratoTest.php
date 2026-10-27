<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de VALOR da Nova compra React com despesas adicionais e anexo
 * (`PurchaseController@store`, o mesmo que a Blade usava).
 *
 * UC-PURCRE-14 `[V0]` — despesas somam ao total; total, frete e despesas chegam em formato que o
 * `num_uf` lê sem ambiguidade.
 * UC-PURCRE-15 — o anexo da nota é gravado na compra.
 *
 * POR QUE EXISTE: até 2026-10-27 despesas adicionais e anexo só existiam na Blade
 * `purchase/create.blade.php`; eles entram no React antes do cutover dela, pedido de [W].
 *
 * DUPLA PROVA: (a) os números enviados, montados à mão (40 + 5 + 12,50 + 2,50 = 60,00) e
 * gravados em `transactions`; (b) a soma refeita a partir das COLUNAS gravadas
 * (total_before_tax + shipping_charges + additional_expense_value_1..4), que tem de bater com o
 * final_total gravado. A outra perna do formato (o que a tela monta) está em
 * tests/js/compra-envio.test.ts.
 *
 * Tenant 98 (ADR 0358). Nunca biz=1, nunca biz=4.
 *
 * @covers-uc UC-PURCRE-14
 * @covers-uc UC-PURCRE-15
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS (MySQL) ausente — roda na lane purchase-pest / CT 100.');
    }
    Event::fake([App\Events\PurchaseCreatedOrModified::class]);

    $this->biz = EstoqueFixture::businessId();
    $role = Role::firstOrCreate(['name' => 'Admin#' . $this->biz, 'guard_name' => 'web'], ['business_id' => $this->biz]);
    $this->user = App\User::factory()->create([
        'business_id' => $this->biz,
        'username' => 'pcd_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $this->user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->location = EstoqueFixture::locationId($this->biz, '-PCD');
    $this->produto = EstoqueFixture::singleProduct($this->biz);
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz,
        'type' => 'supplier',
        'name' => 'Fornecedor PCD',
        'contact_id' => 'CT-PCD-' . uniqid(),
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

/** 4 un × 10,00 = 40,00 de itens; o resto vem de $extra. Formato da tela React (vírgula decimal). */
function pcdPayload($t, array $extra = []): array
{
    return array_merge([
        'ref_no' => 'PCD-' . uniqid(),
        'status' => 'received',
        'contact_id' => $t->contactId,
        'transaction_date' => now()->format('Y-m-d H:i'),
        'location_id' => $t->location,
        'exchange_rate' => '1',
        'total_before_tax' => '40',
        'discount_type' => 'fixed',
        'discount_amount' => '0',
        'tax_amount' => '0',
        'shipping_charges' => '0',
        'final_total' => '40',
        'purchases' => [[
            'product_id' => $t->produto->productId,
            'variation_id' => $t->produto->variationId(),
            'product_unit_id' => $t->produto->unitId,
            'quantity' => '4',
            'pp_without_discount' => '10.00',
            'discount_percent' => '0',
            'purchase_price' => '10.00',
            'purchase_price_inc_tax' => '10.00',
            'item_tax' => '0',
            'purchase_line_tax_id' => '',
        ]],
    ], $extra);
}

function pcdCompra(int $biz, string $ref): ?Transaction
{
    return Transaction::where('business_id', $biz)->where('type', 'purchase')->where('ref_no', $ref)->first();
}

it('UC-PURCRE-14 [V0] 40,00 de itens + 5,00 de frete + despesas 12,50 e 2,50 gravam total 60,00, pelos dois caminhos', function () {
    $dados = pcdPayload($this, [
        'shipping_charges' => '5',
        'final_total' => '60',
        'additional_expense_key_1' => 'Frete extra',
        'additional_expense_value_1' => '12,5',
        'additional_expense_key_2' => 'Seguro',
        'additional_expense_value_2' => '2,5',
        'additional_expense_key_3' => '',
        'additional_expense_value_3' => '',
        'additional_expense_key_4' => '',
        'additional_expense_value_4' => '',
    ]);
    $resp = $this->actingAs($this->user)->post('/purchases', $dados);

    $tx = pcdCompra($this->biz, $dados['ref_no']);
    expect($tx)->not->toBeNull('Compra não criada. HTTP=' . $resp->status() . ' flash=' . json_encode(session('status')));

    // (a) o que foi enviado, gravado
    expect(round((float) $tx->final_total, 2))->toBe(60.0);
    expect($tx->additional_expense_key_1)->toBe('Frete extra');
    expect(round((float) $tx->additional_expense_value_1, 2))->toBe(12.5);
    expect($tx->additional_expense_key_2)->toBe('Seguro');
    expect(round((float) $tx->additional_expense_value_2, 2))->toBe(2.5);
    expect($tx->additional_expense_key_3)->toBeNull();

    // (b) a soma refeita das colunas gravadas bate com o total gravado
    $refeito = (float) $tx->total_before_tax + (float) $tx->shipping_charges
        + (float) $tx->additional_expense_value_1 + (float) $tx->additional_expense_value_2
        + (float) $tx->additional_expense_value_3 + (float) $tx->additional_expense_value_4;
    expect(round($refeito, 2))->toBe(round((float) $tx->final_total, 2));
});

it('UC-PURCRE-14 [V0] o total no formato da tela ("3,5") grava 3,50; o float cru ("3.495") gravaria 3495', function () {
    // O antes: o que a tela mandava até 2026-10-27 para 1,5 × 2,33.
    expect(app(\App\Utils\Util::class)->num_uf('3.495'))->toBe(3495.0);

    $dados = pcdPayload($this, ['total_before_tax' => '3,5', 'final_total' => '3,5']);
    $this->actingAs($this->user)->post('/purchases', $dados);

    $tx = pcdCompra($this->biz, $dados['ref_no']);
    expect($tx)->not->toBeNull();
    expect(round((float) $tx->final_total, 2))->toBe(3.5);
    expect(round((float) $tx->total_before_tax, 2))->toBe(3.5);
});

it('UC-PURCRE-15 o anexo enviado pela tela fica gravado na compra', function () {
    Storage::fake(config('filesystems.default'));

    $dados = pcdPayload($this, [
        'document' => UploadedFile::fake()->create('nota-fornecedor.pdf', 20, 'application/pdf'),
    ]);
    $this->actingAs($this->user)->post('/purchases', $dados);

    $tx = pcdCompra($this->biz, $dados['ref_no']);
    expect($tx)->not->toBeNull();
    expect((string) $tx->document)->toEndWith('_nota-fornecedor.pdf');
    Storage::disk(config('filesystems.default'))->assertExists('documents/' . $tx->document);
});

it('UC-PURCRE-14 [V0] linha com custo 10,125 e quantidade 1,125 grava os dois como digitados', function () {
    // O antes: o float cru que a tela mandava viraria milhar.
    expect(app(\App\Utils\Util::class)->num_uf('10.125'))->toBe(10125.0);

    $dados = pcdPayload($this, ['total_before_tax' => '11,39', 'final_total' => '11,39']);
    $dados['purchases'][0] = array_merge($dados['purchases'][0], [
        'quantity' => '1,125',
        'pp_without_discount' => '10,125',
        'purchase_price' => '10,125',
        'purchase_price_inc_tax' => '10,125',
    ]);
    $this->actingAs($this->user)->post('/purchases', $dados);

    $tx = pcdCompra($this->biz, $dados['ref_no']);
    expect($tx)->not->toBeNull();
    $linha = DB::table('purchase_lines')->where('transaction_id', $tx->id)->first();
    expect(round((float) $linha->quantity, 4))->toBe(1.125);
    expect(round((float) $linha->purchase_price, 4))->toBe(10.125);
    // segundo caminho: quantidade × custo das colunas gravadas ≈ total dos itens
    expect(round((float) $linha->quantity * (float) $linha->purchase_price, 2))->toBe(11.39);
});
