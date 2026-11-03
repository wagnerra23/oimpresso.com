<?php

declare(strict_types=1);
// Cobre UC-PIDX-35 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Clicar no indicador "Abaixo do alerta" filtra a lista (`?abaixo_do_alerta=1`), com a MESMA regra do
 * indicador: estoque gerenciado, alerta definido e estoque ≤ alerta. O número do indicador é provado
 * pelas linhas que o filtro devolve, e cada caso de fora da regra tem um produto próprio no cenário.
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
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Requested-With' => 'XMLHttpRequest',
    ];
});

function pabaEntrar($teste): void
{
    $teste->user = $teste->usuarioComPermissoes(['product.view', 'access_all_locations'], $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function pabaProp($teste, string $prop, string $query = ''): array
{
    $r = $teste->get('/products' . $query, $teste->inertia + ['X-Inertia-Partial-Data' => $prop]);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');

    return $r->json('props.' . $prop);
}

/** Produto simples com alerta e saldo num local; `enable_stock` como pedido. */
function pabaProduto(int $biz, ?float $alerta, ?float $saldo, bool $controla = true): int
{
    $p = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $p->productId)->update(['alert_quantity' => $alerta, 'enable_stock' => $controla ? 1 : 0]);
    if ($saldo !== null) {
        EstoqueFixture::setStock($p, 0, EstoqueFixture::locationId($biz), $saldo);
    }

    return $p->productId;
}

it('UC-PIDX-35 · o filtro mostra exatamente os produtos que o indicador conta', function () {
    $biz = $this->business->id;
    $abaixo = pabaProduto($biz, 5, 2);
    $noLimite = pabaProduto($biz, 5, 5);
    $inativoAbaixo = pabaProduto($biz, 5, 1);
    DB::table('products')->where('id', $inativoAbaixo)->update(['is_inactive' => 1]);
    $acima = pabaProduto($biz, 5, 9);
    $semAlerta = pabaProduto($biz, null, 0);
    $semControle = pabaProduto($biz, 5, 0, false);

    pabaEntrar($this);
    $kpis = pabaProp($this, 'kpis');
    $filtrada = pabaProp($this, 'lista', '?abaixo_do_alerta=1&per_page=-1');
    $ids = array_column($filtrada['data'], 'id');

    // Os do cenário: dentro os que a regra pega (inclusive no limite e inativo, como o indicador conta)…
    expect($ids)->toContain($abaixo, $noLimite, $inativoAbaixo);
    // …e fora os outros três, cada um por um motivo.
    expect($ids)->not->toContain($acima);
    expect($ids)->not->toContain($semAlerta);
    expect($ids)->not->toContain($semControle);

    // O número do indicador é o total da lista filtrada, e toda linha dela está marcada "abaixo do alerta".
    expect($filtrada['total'])->toBe($kpis['abaixo_do_alerta']);
    expect(array_filter($filtrada['data'], fn ($l) => $l['abaixo_do_alerta'] !== true))->toBe([]);
});

it('UC-PIDX-35 · sem o filtro a lista volta a ter todos', function () {
    $biz = $this->business->id;
    $abaixo = pabaProduto($biz, 5, 2);
    $acima = pabaProduto($biz, 5, 9);

    pabaEntrar($this);
    $toda = pabaProp($this, 'lista', '?per_page=-1');
    $ids = array_column($toda['data'], 'id');

    expect($ids)->toContain($abaixo, $acima);
    expect($toda['total'])->toBeGreaterThan(pabaProp($this, 'lista', '?abaixo_do_alerta=1&per_page=-1')['total']);
});

it('UC-PIDX-35 · o filtro junta com os outros e não enxerga outra empresa', function () {
    $biz = $this->business->id;
    $ativoAbaixo = pabaProduto($biz, 5, 2);
    $inativoAbaixo = pabaProduto($biz, 5, 1);
    DB::table('products')->where('id', $inativoAbaixo)->update(['is_inactive' => 1]);

    // Outra empresa com um produto abaixo do alerta: não pode aparecer (Tier 0, ADR 0093).
    $outra = EstoqueFixture::secondBusinessId();
    expect($outra)->not->toBeNull();
    $deFora = pabaProduto((int) $outra, 5, 0);

    pabaEntrar($this);
    $soInativos = array_column(pabaProp($this, 'lista', '?abaixo_do_alerta=1&active_state=inactive&per_page=-1')['data'], 'id');

    expect($soInativos)->toContain($inativoAbaixo);
    expect($soInativos)->not->toContain($ativoAbaixo);
    expect(array_column(pabaProp($this, 'lista', '?abaixo_do_alerta=1&per_page=-1')['data'], 'id'))->not->toContain($deFora);
});

it('UC-PIDX-35 · "Exportar filtrados" segue o filtro', function () {
    $biz = $this->business->id;
    $abaixo = pabaProduto($biz, 5, 2);
    $acima = pabaProduto($biz, 5, 9);
    $nomeAbaixo = DB::table('products')->where('id', $abaixo)->value('name');
    $nomeAcima = DB::table('products')->where('id', $acima)->value('name');

    pabaEntrar($this);
    $csv = $this->get('/products?exportar=csv&abaixo_do_alerta=1');
    $csv->assertOk();
    $texto = $csv->getContent();

    expect($texto)->toContain($nomeAbaixo);
    expect(str_contains($texto, $nomeAcima))->toBeFalse();
});
