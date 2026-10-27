<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de VALOR da edição da compra (`PurchaseController@update`): salvar não apaga o que o
 * formulário não trouxe, e as despesas adicionais entram no total.
 *
 * UC-PUREDT-09 `[V0]` — a edição React leva as despesas e o total sai certo.
 * UC-PUREDT-10 `[V0]` — campo que o formulário não manda (campos personalizados, de frete,
 * despesas) fica como está gravado.
 *
 * O DEFEITO (antes de 2026-10-27): o `update` gravava `custom_field_1..4`,
 * `shipping_custom_field_1..5` e `additional_expense_*` sempre a partir do request, com null/0
 * quando ausentes. O Edit React não mandava nenhum deles, e é ele que abre pelo cockpit
 * ("Editar" e "Atualizar status" fazem router.visit). Salvar por ali zerava a despesa de 12,50 e
 * apagava o campo personalizado da compra, e o total era refeito sem a despesa.
 *
 * DUPLA PROVA: (a) as colunas gravadas depois do update; (b) a soma refeita das colunas
 * (itens + despesas) bate com o `final_total` gravado.
 *
 * Tenant 98 (ADR 0358). Nunca biz=1, nunca biz=4.
 *
 * @covers-uc UC-PUREDT-09
 * @covers-uc UC-PUREDT-10
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
        'username' => 'ped_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $this->user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->location = EstoqueFixture::locationId($this->biz, '-PED');
    $this->produto = EstoqueFixture::singleProduct($this->biz);
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz,
        'type' => 'supplier',
        'name' => 'Fornecedor PED',
        'contact_id' => 'CT-PED-' . uniqid(),
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Compra gravada como a Blade gravava: 4 × 10,00 + despesa 12,50 = 52,50, com campo
    // personalizado e campo de frete preenchidos.
    $this->ref = 'PED-' . uniqid();
    $this->actingAs($this->user)->post('/purchases', [
        'ref_no' => $this->ref,
        'status' => 'received',
        'contact_id' => $this->contactId,
        'transaction_date' => now()->format('Y-m-d H:i'),
        'location_id' => $this->location,
        'exchange_rate' => '1',
        'total_before_tax' => '40',
        'discount_type' => 'fixed',
        'discount_amount' => '0',
        'tax_amount' => '0',
        'shipping_charges' => '0',
        'final_total' => '52,5',
        'additional_expense_key_1' => 'Frete extra',
        'additional_expense_value_1' => '12,5',
        'custom_field_1' => 'OC-77',
        'shipping_custom_field_1' => 'Transportadora X',
        'purchases' => [[
            'product_id' => $this->produto->productId,
            'variation_id' => $this->produto->variationId(),
            'product_unit_id' => $this->produto->unitId,
            'quantity' => '4',
            'pp_without_discount' => '10.00',
            'discount_percent' => '0',
            'purchase_price' => '10.00',
            'purchase_price_inc_tax' => '10.00',
            'item_tax' => '0',
            'purchase_line_tax_id' => '',
        ]],
    ]);
    $this->compra = Transaction::where('business_id', $this->biz)->where('type', 'purchase')->where('ref_no', $this->ref)->firstOrFail();
    $this->linhaId = (int) DB::table('purchase_lines')->where('transaction_id', $this->compra->id)->value('id');
});

/** O que o Edit React manda: SEM custom_field_*, SEM shipping_custom_field_*. $extra completa. */
function pedEdicao($t, array $extra = []): array
{
    return array_merge([
        '_method' => 'PUT',
        'ref_no' => $t->ref,
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
        'final_total' => '52,5',
        'purchases' => [[
            'purchase_line_id' => $t->linhaId,
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

function pedSomaRefeita(Transaction $tx): float
{
    return round((float) $tx->total_before_tax + (float) $tx->shipping_charges
        + (float) $tx->additional_expense_value_1 + (float) $tx->additional_expense_value_2
        + (float) $tx->additional_expense_value_3 + (float) $tx->additional_expense_value_4, 2);
}

it('fixture: a compra nasce com 52,50, a despesa e os dois campos preenchidos', function () {
    expect(round((float) $this->compra->final_total, 2))->toBe(52.5);
    expect(round((float) $this->compra->additional_expense_value_1, 2))->toBe(12.5);
    expect($this->compra->custom_field_1)->toBe('OC-77');
    expect($this->compra->shipping_custom_field_1)->toBe('Transportadora X');
});

it('UC-PUREDT-10 [V0] salvar sem os campos personalizados e de frete mantém os dois como estavam', function () {
    $this->actingAs($this->user)->post("/purchases/{$this->compra->id}", pedEdicao($this, [
        'additional_expense_key_1' => 'Frete extra',
        'additional_expense_value_1' => '12,5',
    ]));

    $tx = $this->compra->fresh();
    expect($tx->custom_field_1)->toBe('OC-77');
    expect($tx->shipping_custom_field_1)->toBe('Transportadora X');
});

it('UC-PUREDT-10 [V0] formulário sem os pares de despesa mantém a despesa e o total, pelos dois caminhos', function () {
    // Como o Edit React salvava até 2026-10-27: sem additional_expense_*. Antes do conserto, a
    // despesa virava 0 e o total gravado era o que a tela mandava.
    $this->actingAs($this->user)->post("/purchases/{$this->compra->id}", pedEdicao($this));

    $tx = $this->compra->fresh();
    expect($tx->additional_expense_key_1)->toBe('Frete extra');
    expect(round((float) $tx->additional_expense_value_1, 2))->toBe(12.5);
    expect(round((float) $tx->final_total, 2))->toBe(52.5);
    expect(pedSomaRefeita($tx))->toBe(round((float) $tx->final_total, 2));
});

it('UC-PUREDT-09 [V0] a edição muda a despesa para 20,00 e o total vai a 60,00, pelos dois caminhos', function () {
    $this->actingAs($this->user)->post("/purchases/{$this->compra->id}", pedEdicao($this, [
        'final_total' => '60',
        'additional_expense_key_1' => 'Frete extra',
        'additional_expense_value_1' => '20',
        'additional_expense_key_2' => '',
        'additional_expense_value_2' => '',
    ]));

    $tx = $this->compra->fresh();
    expect(round((float) $tx->additional_expense_value_1, 2))->toBe(20.0);
    expect(round((float) $tx->final_total, 2))->toBe(60.0);
    expect(pedSomaRefeita($tx))->toBe(60.0);
});

it('UC-PUREDT-09 [V0] zerar a despesa pela tela tira ela do total (o par vem vazio de propósito)', function () {
    $this->actingAs($this->user)->post("/purchases/{$this->compra->id}", pedEdicao($this, [
        'final_total' => '40',
        'additional_expense_key_1' => '',
        'additional_expense_value_1' => '',
    ]));

    $tx = $this->compra->fresh();
    expect((float) $tx->additional_expense_value_1)->toBe(0.0);
    expect(round((float) $tx->final_total, 2))->toBe(40.0);
    expect(pedSomaRefeita($tx))->toBe(40.0);
});

it('UC-PUREDT-10 campo personalizado que VEM vazio é limpo: a regra respeita o que a tela manda', function () {
    $this->actingAs($this->user)->post("/purchases/{$this->compra->id}", pedEdicao($this, [
        'custom_field_1' => '',
        'additional_expense_key_1' => 'Frete extra',
        'additional_expense_value_1' => '12,5',
    ]));

    expect($this->compra->fresh()->custom_field_1)->toBeIn([null, '']);
});
