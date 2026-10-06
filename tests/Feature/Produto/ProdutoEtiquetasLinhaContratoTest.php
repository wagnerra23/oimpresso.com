<?php

declare(strict_types=1);

use App\User;
use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Linha da folha de etiquetas em JSON (`GET /labels/add-product-row`) — playbook Produto · thread 04.
 *
 * Contrato: charter F1 de Etiquetas (`prototipo-ui/cowork/Wagner/cowork-inbox/produto-telas-novas/
 * Etiquetas.charter.md`) — R2 "o preço impresso é o do grupo de preço da linha" e R3 "Com imposto /
 * Sem imposto". UCs UC-PETQ-02..05 (o casos.md nasce ao lado da Page, no PR seguinte da thread).
 *
 * ⛔ Regra mestre de valor: a impressão (preview()) NÃO muda. Esta rota só REAPRESENTA o preço que a
 *    impressão sairia. Dupla prova em UC-PETQ-02: (a) número fixado à mão a partir do cadastro e
 *    (b) a mesma chamada de ProductUtil que preview() faz, formatada como preview_2.blade.php imprime.
 * ⛔ Tenant 98 (ADR 0358) contra o cliente fictício 99. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 *
 * @see app/Http/Controllers/LabelsController.php addProductRow() · linhaEtiqueta() · precoImpresso()
 */
uses(DatabaseTransactions::class);

function petqUsuario(int $bizId): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'Etiqueta T04', 'username' => 'petq_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $bizId, 'user_type' => 'user', 'allow_login' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    $user->givePermissionTo(Permission::findOrCreate('print_labels.access', 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function petqLinhas(object $test, User $user, int $productId): \Illuminate\Testing\TestResponse
{
    $test->actingAs($user);
    session([
        'user.business_id' => (int) $user->business_id, 'user.id' => $user->id,
        'currency' => ['symbol' => 'R$', 'decimal_separator' => ',', 'thousand_separator' => '.'],
        'business.currency_precision' => 2,
    ]);

    return $test->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/labels/add-product-row?product_id=' . $productId . '&variation_id=0&row_count=0');
}

function petqGrupo(int $bizId, string $nome): int
{
    return (int) DB::table('selling_price_groups')->insertGetId([
        'name' => $nome . ' [petq04]', 'business_id' => $bizId, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function petqPrecoGrupo(int $variationId, int $grupoId, float $valor, string $tipo = 'fixed'): void
{
    $linha = ['variation_id' => $variationId, 'price_group_id' => $grupoId, 'price_inc_tax' => $valor,
        'created_at' => now(), 'updated_at' => now()];
    if (Schema::hasColumn('variation_group_prices', 'price_type')) {
        $linha['price_type'] = $tipo;
    }
    DB::table('variation_group_prices')->insert($linha);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = $this->seededTenant();
    $this->vizinho = $this->seededSupportClientTenant();
});

it('UC-PETQ-02 · o preço da linha é o que a impressão sairia, por grupo e por tipo (dupla prova)', function () {
    $p = EstoqueFixture::singleProduct($this->biz->id);
    $vid = $p->variationId();
    // Imposto de 10% no produto: separa "Com imposto" de "Sem imposto" no preço do grupo.
    $taxa = (int) DB::table('tax_rates')->insertGetId([
        'business_id' => $this->biz->id, 'name' => 'IVA petq04', 'amount' => 10,
        'created_by' => EstoqueFixture::userId($this->biz->id), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('products')->where('id', $p->productId)->update(['tax' => $taxa]);
    $atacado = petqGrupo($this->biz->id, 'Atacado');
    petqPrecoGrupo($vid, $atacado, 15.5);

    $linhas = petqLinhas($this, petqUsuario($this->biz->id), $p->productId)->assertOk()->json('linhas');

    expect($linhas)->toHaveCount(1);
    expect($linhas[0]['variation_id'])->toBe($vid);

    // (a) Número à mão: venda 20 (fixture) · grupo 15,5 com imposto · 15,5 × 100 / 110 = 14,0909 sem.
    expect($linhas[0]['precos']['0'])->toBe(['inclusive' => 'R$ 20,00', 'exclusive' => 'R$ 20,00']);
    expect($linhas[0]['precos'][(string) $atacado])->toBe(['inclusive' => 'R$ 15,50', 'exclusive' => 'R$ 14,09']);

    // (b) A mesma chamada que preview() faz no tipo "Sem imposto", formatada como preview_2 imprime.
    $g = app(ProductUtil::class)->getVariationGroupPrice($vid, $atacado, $taxa);
    expect($linhas[0]['precos'][(string) $atacado]['exclusive'])
        ->toBe('R$ ' . number_format((float) $g['price_exc_tax'], 2, ',', '.'));
    expect($linhas[0]['precos'][(string) $atacado]['inclusive'])
        ->toBe('R$ ' . number_format((float) $g['price_inc_tax'], 2, ',', '.'));
});

it('UC-PETQ-03 · grupo percentual sai como a impressão calcula; grupo sem preço vira null', function () {
    $p = EstoqueFixture::singleProduct($this->biz->id);
    $vid = $p->variationId();
    $percentual = petqGrupo($this->biz->id, 'Convenio');
    $semPreco = petqGrupo($this->biz->id, 'Funcionario');
    if (! Schema::hasColumn('variation_group_prices', 'price_type')) {
        $this->markTestSkipped('variation_group_prices sem price_type neste schema.');
    }
    petqPrecoGrupo($vid, $percentual, 10, 'percentage');

    $precos = petqLinhas($this, petqUsuario($this->biz->id), $p->productId)->assertOk()->json('linhas.0.precos');

    // calc_percentage(20, 10) = 2 — o que getVariationGroupPrice devolve para grupo percentual.
    expect($precos[(string) $percentual]['inclusive'])->toBe('R$ 2,00');
    expect($precos[(string) $semPreco])->toBe(['inclusive' => null, 'exclusive' => null]);
});

it('UC-PETQ-04 · produto e grupo de outro negócio não entram na linha', function () {
    $alheio = EstoqueFixture::singleProduct($this->vizinho->id);
    $grupoAlheio = petqGrupo($this->vizinho->id, 'Vizinho');
    $proprio = EstoqueFixture::singleProduct($this->biz->id);
    $user = petqUsuario($this->biz->id);

    expect(petqLinhas($this, $user, $alheio->productId)->assertOk()->json('linhas'))->toBe([]);

    $precos = petqLinhas($this, $user, $proprio->productId)->assertOk()->json('linhas.0.precos');
    expect($precos)->toHaveKey('0');
    expect(array_key_exists((string) $grupoAlheio, $precos))->toBeFalse();
});

it('UC-PETQ-05 · a Blade segue recebendo o HTML da linha', function () {
    $p = EstoqueFixture::singleProduct($this->biz->id);
    $this->actingAs(petqUsuario($this->biz->id));
    session(['user.business_id' => (int) $this->biz->id]);

    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get('/labels/add-product-row?product_id=' . $p->productId . '&row_count=0')
        ->assertOk()->assertViewIs('labels.partials.show_table_rows');
});
