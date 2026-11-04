<?php

declare(strict_types=1);
// Cobre UC-PIDX-39 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Saldo também em embalagens inteiras (CV2): o servidor manda, por produto, a maior unidade
 * cadastrada como múltiplo da unidade dele (ex.: Rolo = 80 m²). A conta dos rolos é da tela e é
 * provada em tests/js/produto-lista-embalagem.test.tsx; aqui, quem é a embalagem e quando ela vem.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration') || ! Schema::hasColumn('business', 'enable_sub_units')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->inertia = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Requested-With' => 'XMLHttpRequest',
    ];
    DB::table('business')->where('id', $this->business->id)->update(['enable_sub_units' => 1]);
});

function pemEntrar($teste): void
{
    $teste->user = $teste->usuarioComPermissoes(['product.view', 'access_all_locations'], $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function pemLinha($teste, int $produtoId): ?array
{
    $r = $teste->get('/products?per_page=-1', $teste->inertia + ['X-Inertia-Partial-Data' => 'lista']);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');
    foreach ($r->json('props.lista.data') as $linha) {
        if ($linha['id'] === $produtoId) {
            return $linha;
        }
    }

    return null;
}

/** Unidade nova da empresa; com $base e $fator, é múltiplo dela. */
function pemUnidade(int $biz, string $nome, ?int $base = null, ?float $fator = null): int
{
    return (int) DB::table('units')->insertGetId([
        'business_id' => $biz, 'actual_name' => $nome, 'short_name' => mb_substr($nome, 0, 3) . '-' . bin2hex(random_bytes(2)),
        'allow_decimal' => 1, 'base_unit_id' => $base, 'base_unit_multiplier' => $fator,
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Produto simples na unidade dada, com saldo. */
function pemProduto(int $biz, int $unidade, float $saldo, bool $controla = true): int
{
    $p = EstoqueFixture::singleProduct($biz, $controla);
    DB::table('products')->where('id', $p->productId)->update(['unit_id' => $unidade]);
    EstoqueFixture::setStock($p, 0, EstoqueFixture::locationId($biz), $saldo);

    return $p->productId;
}

it('UC-PIDX-39 · vem a maior unidade cadastrada como múltiplo da unidade do produto', function () {
    $biz = $this->business->id;
    $m2 = pemUnidade($biz, 'Metro quadrado');
    pemUnidade($biz, 'Meio rolo', $m2, 40);
    pemUnidade($biz, 'Rolo', $m2, 80);
    $lona = pemProduto($biz, $m2, 170);

    pemEntrar($this);
    $linha = pemLinha($this, $lona);

    expect($linha['estoque'])->toEqual(170.0);
    expect($linha['embalagem'])->toEqual(['nome' => 'Rolo', 'fator' => 80.0]);
});

it('UC-PIDX-39 · o produto que escolheu as subunidades só usa as escolhidas', function () {
    $biz = $this->business->id;
    $m2 = pemUnidade($biz, 'Metro quadrado');
    $meio = pemUnidade($biz, 'Meio rolo', $m2, 40);
    pemUnidade($biz, 'Rolo', $m2, 80);
    $lona = pemProduto($biz, $m2, 170);
    DB::table('products')->where('id', $lona)->update(['sub_unit_ids' => json_encode([(string) $meio])]);

    pemEntrar($this);
    expect(pemLinha($this, $lona)['embalagem'])->toEqual(['nome' => 'Meio rolo', 'fator' => 40.0]);
});

it('UC-PIDX-39 · sem subunidades ligadas na empresa, sem múltiplo, excluída ou sem controle de estoque: não vem', function () {
    $biz = $this->business->id;
    $m2 = pemUnidade($biz, 'Metro quadrado');
    $rolo = pemUnidade($biz, 'Rolo', $m2, 80);
    $comRolo = pemProduto($biz, $m2, 170);
    $semMultiplo = pemProduto($biz, pemUnidade($biz, 'Peça'), 5);
    $semControle = pemProduto($biz, $m2, 0, false);

    pemEntrar($this);
    // Controle positivo: com tudo ligado, o produto com rolo traz a embalagem.
    expect(pemLinha($this, $comRolo))->toHaveKey('embalagem');
    expect(pemLinha($this, $semMultiplo))->not->toHaveKey('embalagem');
    expect(pemLinha($this, $semControle))->not->toHaveKey('embalagem');

    DB::table('units')->where('id', $rolo)->update(['deleted_at' => now()]);
    expect(pemLinha($this, $comRolo))->not->toHaveKey('embalagem');

    DB::table('units')->where('id', $rolo)->update(['deleted_at' => null]);
    DB::table('business')->where('id', $biz)->update(['enable_sub_units' => 0]);
    expect(pemLinha($this, $comRolo))->not->toHaveKey('embalagem');
});

it('UC-PIDX-39 · unidade de outra empresa apontando para a mesma base não vale', function () {
    $biz = $this->business->id;
    $m2 = pemUnidade($biz, 'Metro quadrado');
    $lona = pemProduto($biz, $m2, 170);
    $outra = EstoqueFixture::secondBusinessId();
    expect($outra)->not->toBeNull();
    pemUnidade((int) $outra, 'Rolo alheio', $m2, 80);

    pemEntrar($this);
    $linha = pemLinha($this, $lona);
    expect($linha)->not->toBeNull();
    expect($linha)->not->toHaveKey('embalagem');
});
