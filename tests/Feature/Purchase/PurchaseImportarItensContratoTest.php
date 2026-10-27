<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Importar itens da compra por planilha na tela React (`PurchaseController@importarItens`,
 * POST /purchases/importar-itens). Mesmo modelo da Blade.
 *
 * UC-PURCRE-19 `[V0]` — a linha volta com o custo já com desconto: 10,50 com 10% = 9,45.
 * UC-PURCRE-20 `[T0]` — SKU de outra empresa não entra; sem permissão, 403.
 *
 * DUPLA PROVA do custo: (a) o que o endpoint devolve; (b) a conta à mão, 10,50 × (1 − 0,10).
 * A linha ainda não grava nada: ela entra na tela e o valor gravado sai do store, que já tem
 * prova própria (PurchaseCreateDespesasContratoTest).
 *
 * Tenant 98 (ADR 0358). Nunca biz=1, nunca biz=4.
 *
 * @covers-uc UC-PURCRE-19
 * @covers-uc UC-PURCRE-20
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS (MySQL) ausente — roda na lane purchase-pest / CT 100.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->user = App\User::factory()->create([
        'business_id' => $this->biz,
        'username' => 'pii_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    Permission::firstOrCreate(['name' => 'purchase.create', 'guard_name' => 'web']);
    $this->user->givePermissionTo('purchase.create');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->location = EstoqueFixture::locationId($this->biz, '-PII');
    $this->produto = EstoqueFixture::singleProduct($this->biz);
    $this->sku = (string) DB::table('variations')->where('id', $this->produto->variationId())->value('sub_sku');
    $this->sessao = ['user' => ['business_id' => $this->biz, 'id' => $this->user->id]];
});

function piiPlanilha(string $sku, string $quantidade, string $custo, string $desconto): UploadedFile
{
    $csv = "SKU,Quantidade,Custo,Desconto,Imposto,Lote,Fabricacao,Validade\n"
        . "\"{$sku}\",\"{$quantidade}\",\"{$custo}\",\"{$desconto}\",\"\",\"\",\"\",\"\"\n";

    return UploadedFile::fake()->createWithContent('itens.csv', $csv);
}

it('UC-PURCRE-19 [V0] a linha importada volta com quantidade 2 e custo 10,50 com 10% = 9,45, pelos dois caminhos', function () {
    expect($this->sku)->not->toBe('');

    $r = $this->actingAs($this->user)->withSession($this->sessao)
        ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post('/purchases/importar-itens', ['file' => piiPlanilha($this->sku, '2', '10,5', '10'), 'location_id' => $this->location]);

    $r->assertOk();
    $linha = $r->json('linhas.0');
    expect($r->json('linhas'))->toHaveCount(1);
    expect((int) $linha['variation_id'])->toBe($this->produto->variationId());
    expect((float) $linha['quantity'])->toBe(2.0);
    expect((float) $linha['pp_without_discount'])->toBe(10.5);
    expect((float) $linha['discount_percent'])->toBe(10.0);

    // (a) devolvido · (b) conta à mão
    $aMao = round(10.5 * (1 - 10 / 100), 4);
    expect((float) $linha['purchase_price'])->toBe($aMao);
    expect((float) $linha['purchase_price'])->toBe(9.45);
    expect((float) $linha['purchase_price_inc_tax'])->toBe(9.45);
});

it('UC-PURCRE-20 [T0] SKU de outra empresa não é encontrado e nenhuma linha volta', function () {
    $outra = (int) DB::table('business')->whereIn('id', [99, 2])->where('id', '!=', $this->biz)->orderByDesc('id')->value('id');
    if ($outra <= 0) {
        $this->markTestSkipped('Sem empresa adversária fictícia (99 ou 2) neste ambiente.');
    }
    $alheio = EstoqueFixture::singleProduct($outra);
    $skuAlheio = (string) DB::table('variations')->where('id', $alheio->variationId())->value('sub_sku');

    $r = $this->actingAs($this->user)->withSession($this->sessao)
        ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post('/purchases/importar-itens', ['file' => piiPlanilha($skuAlheio, '1', '5', '0'), 'location_id' => $this->location]);

    $r->assertStatus(422);
    expect($r->json('linhas'))->toBe([]);
    expect((string) $r->json('erro'))->not->toBe('');
});

it('UC-PURCRE-20 sem permissão de criar nem editar compra, 403', function () {
    $this->user->revokePermissionTo('purchase.create');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user)->withSession($this->sessao)
        ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post('/purchases/importar-itens', ['file' => piiPlanilha($this->sku, '1', '5', '0'), 'location_id' => $this->location])
        ->assertForbidden();
});
