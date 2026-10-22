<?php

declare(strict_types=1);

use App\Http\Middleware\AdminSidebarMenu;
use App\Http\Middleware\CheckUserLogin;
use App\Http\Middleware\Language;
use App\Http\Middleware\SetSessionData;
use App\Http\Middleware\Timezone;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\AplicarCustoDoRecebimento;
use Modules\Compras\Services\PropostasDePreco;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * US-COM-028 · UC-FLX-17 — aprovar ou recusar o preço proposto pelo recebimento da compra.
 *
 * Âncora: memory/requisitos/Compras/SPEC.md US-COM-028 + resources/js/Pages/Purchase/Show.casos.md
 * (UC-PURSHW-07..09). Regra mestre de valor (proibicoes.md): dois caminhos, números concretos.
 *
 * @covers-us US-COM-028
 *
 * Arranjo — o mesmo de AplicarCustoDoRecebimentoTest (tenant 98, imposto 10%):
 *   antes: custo 10,0000 / 11,0000 · preço 15,0000 / 16,5000 · margem 50%
 *   A 2026-03-01 recebida 10 × 11,00 · B 2026-03-10 recebida 10 × 13,20, regra "propor"
 *   depois do recebimento: custo 11,0000 / 12,1000 · preço 15,0000 / 16,5000 · margem 36,3636
 *   proposta: 16,5000 → 18,1500
 *
 * Caminho 1 (à mão): aprovar → com imposto 18,1500 · sem imposto 18,15 × 100 / 110 = 16,5000
 *                    · margem (16,5 − 11) / 11 × 100 = 50,0000.
 * Caminho 2: rodar a regra "aplicar" sobre o mesmo arranjo, noutra variação, e comparar.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage().' — rode no MySQL real (lane compras-pest ou CT 100).');
    }

    $this->user = User::factory()->create(['business_id' => $this->biz->id, 'username' => 'prop_preco_'.uniqid(), 'user_type' => 'user', 'allow_login' => 1]);
    $agora = Carbon::now();
    $sku = 'PROPPRECO-'.uniqid();

    $unitId = (int) (DB::table('units')->where('business_id', $this->biz->id)->value('id')
        ?? DB::table('units')->insertGetId([
            'business_id' => $this->biz->id, 'actual_name' => 'Unidade '.$sku, 'short_name' => 'un',
            'allow_decimal' => 0, 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]));
    $taxId = DB::table('tax_rates')->insertGetId([
        'business_id' => $this->biz->id, 'name' => 'Imposto 10 '.$sku, 'amount' => 10, 'is_tax_group' => 0,
        'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
    ]);

    // Cria produto + variação com o "antes" do arranjo e as duas compras recebidas.
    $this->insumo = function (string $sufixo) use ($agora, $unitId, $taxId, $sku): array {
        $productId = DB::table('products')->insertGetId([
            'business_id' => $this->biz->id, 'name' => 'Vinil '.$sku.$sufixo, 'type' => 'single', 'unit_id' => $unitId,
            'tax' => $taxId, 'sku' => $sku.$sufixo, 'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive',
            'barcode_type' => 'C128', 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $grupo = DB::table('product_variations')->insertGetId([
            'product_id' => $productId, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $variationId = DB::table('variations')->insertGetId([
            'default_purchase_price' => 10, 'dpp_inc_tax' => 11, 'profit_percent' => 50, 'default_sell_price' => 15, 'sell_price_inc_tax' => 16.5,
            'product_id' => $productId, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.$sufixo.'-1',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $compra = function (string $data, float $preco) use ($productId, $variationId): Transaction {
            $tid = DB::table('transactions')->insertGetId([
                'business_id' => $this->biz->id, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due',
                'transaction_date' => $data, 'ref_no' => 'PROPPRECO-'.uniqid(), 'final_total' => 10 * $preco,
                'total_before_tax' => 10 * $preco, 'tax_amount' => 0, 'discount_amount' => 0,
                'created_by' => $this->user->id, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('purchase_lines')->insert([
                'transaction_id' => $tid, 'product_id' => $productId, 'variation_id' => $variationId,
                'quantity' => 10, 'quantity_returned' => 0, 'pp_without_discount' => $preco,
                'purchase_price' => $preco, 'purchase_price_inc_tax' => $preco, 'item_tax' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return Transaction::withoutGlobalScopes()->findOrFail($tid); // SUPERADMIN: fixture de teste lida por id
        };
        $compra('2026-03-01 09:00:00', 11.00);

        return [$variationId, $compra('2026-03-10 09:00:00', 13.20)];
    };

    $this->regra = function (array $params): void {
        DB::table('fsm_modelos_empresa')->where('business_id', $this->biz->id)->delete();
        DB::table('fsm_modelos_empresa')->insert([
            'business_id' => $this->biz->id, 'modelo' => 'legado', 'created_at' => now(), 'updated_at' => now(),
            'regras_compra' => json_encode(['insumo.custo' => ['ativo' => true, 'params' => $params]]),
        ]);
    };
    $this->variacao = fn (int $id) => (array) DB::table('variations')->where('id', $id)
        ->first(['default_purchase_price', 'dpp_inc_tax', 'profit_percent', 'default_sell_price', 'sell_price_inc_tax']);

    ($this->regra)(['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'propor', 'aprovacao' => 'recebimento']);
    [$this->variationId, $this->compraB] = ($this->insumo)('-P');
    app(AplicarCustoDoRecebimento::class)->aoReceber($this->compraB);
    $this->propostaId = (int) DB::table('compras_propostas_preco')->where('variation_id', $this->variationId)->value('id');
    $this->servico = app(PropostasDePreco::class);
});

it('UC-PURSHW-07 · US-COM-028 · o detalhe da compra lista a proposta pendente com custo e preço antes/depois', function () {
    $dados = $this->servico->daCompra($this->biz->id, $this->compraB->id);

    expect($dados['aprovacao'])->toBe('recebimento')
        ->and($dados['aviso'])->toBeNull()
        ->and($dados['itens'])->toHaveCount(1)
        ->and($dados['itens'][0])->toMatchArray([
            'id' => $this->propostaId, 'status' => 'pendente',
            'custo_antes' => 11.0, 'custo_depois' => 12.1, 'preco_antes' => 16.5, 'preco_proposto' => 18.15,
            'preco_atual' => 16.5, 'preco_mudou' => false,
        ]);
    // A outra empresa não vê a proposta, nem pelo id da compra.
    expect($this->servico->daCompra($this->outra->id, $this->compraB->id)['itens'])->toBe([]);
});

it('UC-PURSHW-08 · US-COM-028 · aprovar grava o preço proposto e devolve a margem de antes (dois caminhos)', function () {
    expect(($this->variacao)($this->variationId))->toMatchArray([
        'default_sell_price' => '15.0000', 'sell_price_inc_tax' => '16.5000', 'profit_percent' => '36.3636',
    ]);

    $r = $this->servico->aprovar($this->biz->id, $this->propostaId, $this->user->id);

    // Caminho 1: a conta à mão do docblock.
    $depois = ['default_sell_price' => '16.5000', 'sell_price_inc_tax' => '18.1500', 'profit_percent' => '50.0000'];
    expect(($this->variacao)($this->variationId))->toMatchArray($depois + ['default_purchase_price' => '11.0000', 'dpp_inc_tax' => '12.1000'])
        ->and($r['antes'])->toBe(['default_sell_price' => '15.0000', 'sell_price_inc_tax' => '16.5000', 'profit_percent' => '36.3636'])
        ->and($r['depois'])->toBe($depois);

    // Caminho 2: a regra "aplicar" sobre o mesmo arranjo chega ao mesmo preço e margem.
    ($this->regra)(['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'aplicar']);
    [$outraVariacao, $outraCompra] = ($this->insumo)('-A');
    app(AplicarCustoDoRecebimento::class)->aoReceber($outraCompra);
    expect(($this->variacao)($outraVariacao))->toMatchArray($depois);

    $p = DB::table('compras_propostas_preco')->where('id', $this->propostaId)->first();
    expect($p->status)->toBe('aprovada')->and((int) $p->decided_by)->toBe($this->user->id)->and($p->decided_at)->not->toBeNull();

    // Decidida não se decide de novo.
    expect(fn () => $this->servico->recusar($this->biz->id, $this->propostaId, $this->user->id))
        ->toThrow(DomainException::class, 'já foi decidida');
});

it('UC-PURSHW-09 · US-COM-028 · recusar mantém o preço; preço mudado depois da proposta bloqueia a aprovação', function () {
    // Alguém mudou o preço no produto depois do recebimento: aprovar sobrescreveria a mudança.
    DB::table('variations')->where('id', $this->variationId)->update(['sell_price_inc_tax' => 17.6, 'default_sell_price' => 16]);
    expect($this->servico->daCompra($this->biz->id, $this->compraB->id)['itens'][0]['preco_mudou'])->toBeTrue()
        ->and(fn () => $this->servico->aprovar($this->biz->id, $this->propostaId, $this->user->id))
        ->toThrow(DomainException::class, 'mudou desde a proposta');
    expect(($this->variacao)($this->variationId))->toMatchArray(['sell_price_inc_tax' => '17.6000', 'default_sell_price' => '16.0000']);

    $this->servico->recusar($this->biz->id, $this->propostaId, $this->user->id);

    expect(DB::table('compras_propostas_preco')->where('id', $this->propostaId)->value('status'))->toBe('recusada')
        ->and(($this->variacao)($this->variationId))->toMatchArray(['sell_price_inc_tax' => '17.6000', 'default_sell_price' => '16.0000']);
});

it('UC-PURSHW-09 · US-COM-028 · rota exige product.update e não alcança proposta de outra empresa', function () {
    $this->withoutMiddleware([SetSessionData::class, AdminSidebarMenu::class, CheckUserLogin::class, Language::class, Timezone::class]);
    $url = "/compras/propostas-preco/{$this->propostaId}/aprovar";

    // Sem a permissão: 403 e a proposta segue pendente com o preço de antes.
    $this->actingAs($this->user)->post($url)->assertForbidden();
    expect(DB::table('compras_propostas_preco')->where('id', $this->propostaId)->value('status'))->toBe('pendente');

    // Usuário de outra empresa, com a permissão: 404, nada muda.
    $perm = Permission::firstOrCreate(['name' => 'product.update', 'guard_name' => 'web']);
    $intruso = User::factory()->create(['business_id' => $this->outra->id, 'username' => 'prop_preco_x_'.uniqid(), 'user_type' => 'user', 'allow_login' => 1]);
    $intruso->givePermissionTo($perm);
    $this->actingAs($intruso)->post($url)->assertNotFound();
    expect(DB::table('compras_propostas_preco')->where('id', $this->propostaId)->value('status'))->toBe('pendente')
        ->and(($this->variacao)($this->variationId)['sell_price_inc_tax'])->toBe('16.5000');

    // Com a permissão, da empresa: aprova e volta para a tela.
    $this->user->givePermissionTo($perm);
    $this->actingAs($this->user)->from("/purchases/{$this->compraB->id}")->post($url)
        ->assertRedirect("/purchases/{$this->compraB->id}")->assertSessionHas('success');
    expect(($this->variacao)($this->variationId)['sell_price_inc_tax'])->toBe('18.1500');
});

it('UC-PURSHW-07 · US-COM-028 · regra em Conferido avisa que o recebimento não aplica', function () {
    ($this->regra)(['etapa' => 'conferido', 'metodo' => 'medio', 'preco' => 'propor']);

    $dados = $this->servico->daCompra($this->biz->id, $this->compraB->id);

    expect($dados['aviso'])->toContain('Conferido')->toContain('Recebido')
        ->and(app(AplicarCustoDoRecebimento::class)->regra($this->biz->id))->toBeNull();
});
