<?php

declare(strict_types=1);
// Cobre UC-PIDX-40 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Quantos kits dá para vender em cada local (painel do kit, aba Composição). Mexe em ESTOQUE (só
 * leitura), então a conta é provada por dois caminhos: (1) a conta feita à mão no teste; (2) a conta
 * que o núcleo usa para o combo na venda (`ProductUtil::calculateComboQuantity`). Até a D-30 (2026-11-12)
 * a do núcleo errava com componente zerado; os casos D-30 abaixo provam que agora as duas dão o mesmo.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->inertia = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
    ];
});

function pkdEntrar($teste, array $permissoes = ['product.view', 'access_all_locations']): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function pkdDetalhe($teste, int $id): array
{
    $r = $teste->get('/products?ver=' . $id, $teste->inertia + [
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Inertia-Partial-Data' => 'detalhe',
    ]);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');

    return $r->json('props.detalhe');
}

/** A linha de um local em `kits_por_local`. */
function pkdLocal(array $d, int $localId): ?array
{
    $nome = DB::table('business_locations')->where('id', $localId)->value('name');
    foreach ($d['kits_por_local'] ?? [] as $l) {
        if ($l['local'] === $nome) {
            return $l;
        }
    }

    return null;
}

/** Kit com os itens dados: [[EstoqueProduto, quantidade, unit_id|null], ...]. */
function pkdKit(int $biz, array $itens): \Tests\Support\EstoqueProduto
{
    $kit = EstoqueFixture::singleProduct($biz, false);
    DB::table('products')->where('id', $kit->productId)->update(['type' => 'combo']);
    DB::table('variations')->where('id', $kit->variationId())->update([
        'combo_variations' => json_encode(array_map(fn ($i) => [
            'variation_id' => $i[0]->variationId(), 'quantity' => $i[1], 'unit_id' => $i[2] ?? $i[0]->unitId,
        ], $itens)),
    ]);

    return $kit;
}

it('UC-PIDX-40 · o kit sai do componente mais escasso, em cada local', function () {
    $biz = $this->business->id;
    $canetas = EstoqueFixture::singleProduct($biz);
    $cadernos = EstoqueFixture::singleProduct($biz);
    $matriz = EstoqueFixture::locationId($biz);
    $filial = EstoqueFixture::locationId($biz, 'filial');
    // Kit = 2 canetas + 1 caderno. Matriz: 10 canetas e 3 cadernos. Filial: 7 canetas e 9 cadernos.
    EstoqueFixture::setStock($canetas, 0, $matriz, 10);
    EstoqueFixture::setStock($cadernos, 0, $matriz, 3);
    EstoqueFixture::setStock($canetas, 0, $filial, 7);
    EstoqueFixture::setStock($cadernos, 0, $filial, 9);
    $kit = pkdKit($biz, [[$canetas, 2], [$cadernos, 1]]);
    $nomeCaderno = DB::table('products')->where('id', $cadernos->productId)->value('name');
    $nomeCaneta = DB::table('products')->where('id', $canetas->productId)->value('name');

    pkdEntrar($this);
    $d = pkdDetalhe($this, $kit->productId);

    // Caminho 1 — à mão: Matriz min(10÷2, 3÷1) = 3, limita o caderno; Filial min(7÷2, 9÷1) = 3, limita a caneta.
    expect(pkdLocal($d, $matriz))->toBe(['local' => DB::table('business_locations')->where('id', $matriz)->value('name'), 'kits' => 3, 'limita' => $nomeCaderno]);
    expect(pkdLocal($d, $filial)['kits'])->toBe(3);
    expect(pkdLocal($d, $filial)['limita'])->toBe($nomeCaneta);

    // Caminho 2 — a conta do núcleo para o combo na venda dá o mesmo nos dois locais.
    $combo = json_decode((string) DB::table('variations')->where('id', $kit->variationId())->value('combo_variations'), true);
    $util = app(\App\Utils\ProductUtil::class);
    expect((int) $util->calculateComboQuantity($matriz, $combo))->toBe(3);
    expect((int) $util->calculateComboQuantity($filial, $combo))->toBe(3);
});

it('UC-PIDX-40 · componente sem saldo dá zero kits, e ele é quem limita', function () {
    $biz = $this->business->id;
    $canetas = EstoqueFixture::singleProduct($biz);
    $cadernos = EstoqueFixture::singleProduct($biz);
    $matriz = EstoqueFixture::locationId($biz);
    EstoqueFixture::setStock($canetas, 0, $matriz, 0);
    EstoqueFixture::setStock($cadernos, 0, $matriz, 5);
    $kit = pkdKit($biz, [[$canetas, 2], [$cadernos, 1]]);

    pkdEntrar($this);
    $linha = pkdLocal(pkdDetalhe($this, $kit->productId), $matriz);

    expect($linha['kits'])->toBe(0);
    expect($linha['limita'])->toBe(DB::table('products')->where('id', $canetas->productId)->value('name'));
});

it('UC-PIDX-40 · D-30 · componente zerado no começo ou no meio da lista dá zero kits na conta da venda', function () {
    // Decisão [F] 2026-11-12 (D-30): a conta do núcleo usava 0 como "ainda não calculado", e o componente
    // seguinte sobrescrevia o zero. Antes: 5 kits e 4 kits; à mão e no painel: 0 e 0.
    $biz = $this->business->id;
    $matriz = EstoqueFixture::locationId($biz);
    $a = EstoqueFixture::singleProduct($biz);
    $b = EstoqueFixture::singleProduct($biz);
    $c = EstoqueFixture::singleProduct($biz);
    EstoqueFixture::setStock($a, 0, $matriz, 0);
    EstoqueFixture::setStock($b, 0, $matriz, 5);
    $primeiroZerado = pkdKit($biz, [[$a, 2], [$b, 1]]);

    $d = EstoqueFixture::singleProduct($biz);
    EstoqueFixture::setStock($c, 0, $matriz, 10);
    EstoqueFixture::setStock($d, 0, $matriz, 4);
    $meioZerado = pkdKit($biz, [[$c, 1], [$a, 1], [$d, 1]]);

    $util = app(\App\Utils\ProductUtil::class);
    $combo = fn ($kit) => json_decode((string) DB::table('variations')->where('id', $kit->variationId())->value('combo_variations'), true);

    pkdEntrar($this);
    foreach ([$primeiroZerado, $meioZerado] as $kit) {
        // Caminho 1 — a conta do núcleo, usada pela venda.
        expect((int) $util->calculateComboQuantity($matriz, $combo($kit)))->toBe(0);
        // Caminho 2 — a conta do painel (UC-PIDX-40), feita à parte.
        expect(pkdLocal(pkdDetalhe($this, $kit->productId), $matriz)['kits'])->toBe(0);
    }

    // Controle: com saldo em todos, as duas contas seguem iguais e diferentes de zero.
    EstoqueFixture::setStock($a, 0, $matriz, 6);
    expect((int) $util->calculateComboQuantity($matriz, $combo($primeiroZerado)))->toBe(3);
    expect(pkdLocal(pkdDetalhe($this, $primeiroZerado->productId), $matriz)['kits'])->toBe(3);
});

it('UC-PIDX-40 · a quantidade em subunidade conta pelo multiplicador', function () {
    $biz = $this->business->id;
    $parafusos = EstoqueFixture::singleProduct($biz);
    $matriz = EstoqueFixture::locationId($biz);
    // Caixa com 12 parafusos, como subunidade da unidade do parafuso.
    $caixa = (int) DB::table('units')->insertGetId([
        'business_id' => $biz, 'actual_name' => 'Caixa 12', 'short_name' => 'CX12-' . bin2hex(random_bytes(2)),
        'allow_decimal' => 0, 'base_unit_id' => $parafusos->unitId, 'base_unit_multiplier' => 12,
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now(),
    ]);
    EstoqueFixture::setStock($parafusos, 0, $matriz, 30);
    // Kit leva 1 caixa (= 12 parafusos): 30 ÷ 12 = 2 kits.
    $kit = pkdKit($biz, [[$parafusos, 1, $caixa]]);

    pkdEntrar($this);
    expect(pkdLocal(pkdDetalhe($this, $kit->productId), $matriz)['kits'])->toBe(2);
    $combo = json_decode((string) DB::table('variations')->where('id', $kit->variationId())->value('combo_variations'), true);
    expect((int) app(\App\Utils\ProductUtil::class)->calculateComboQuantity($matriz, $combo))->toBe(2);
});

it('UC-PIDX-40 · componente sem controle de estoque não limita; se nenhum controla, não há número', function () {
    $biz = $this->business->id;
    $servico = EstoqueFixture::singleProduct($biz, false);
    $cadernos = EstoqueFixture::singleProduct($biz);
    $matriz = EstoqueFixture::locationId($biz);
    EstoqueFixture::setStock($cadernos, 0, $matriz, 4);
    $comServico = pkdKit($biz, [[$servico, 1], [$cadernos, 2]]);
    $soServico = pkdKit($biz, [[$servico, 1]]);

    pkdEntrar($this);
    $linha = pkdLocal(pkdDetalhe($this, $comServico->productId), $matriz);
    expect($linha['kits'])->toBe(2);
    expect($linha['limita'])->toBe(DB::table('products')->where('id', $cadernos->productId)->value('name'));

    $d = pkdDetalhe($this, $soServico->productId);
    // Controle positivo: a composição veio, só o número não.
    expect($d['composicao'])->toHaveCount(1);
    expect($d['kits_por_local'])->toBeNull();
});

it('UC-PIDX-40 · quem vê um local só recebe só aquele local', function () {
    $biz = $this->business->id;
    $cadernos = EstoqueFixture::singleProduct($biz);
    $matriz = EstoqueFixture::locationId($biz);
    $filial = EstoqueFixture::locationId($biz, 'filial');
    EstoqueFixture::setStock($cadernos, 0, $matriz, 4);
    EstoqueFixture::setStock($cadernos, 0, $filial, 8);
    $kit = pkdKit($biz, [[$cadernos, 1]]);

    // `permitted_locations()` lê a permissão DIRETA do usuário, não a do papel.
    pkdEntrar($this, ['product.view']);
    \Spatie\Permission\Models\Permission::findOrCreate('location.' . $matriz, 'web');
    $this->user->givePermissionTo('location.' . $matriz);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user = \App\User::findOrFail($this->user->id);
    $this->actingAs($this->user);

    $d = pkdDetalhe($this, $kit->productId);
    expect(array_column($d['kits_por_local'], 'kits'))->toBe([4]);
    expect(pkdLocal($d, $filial))->toBeNull();
});
