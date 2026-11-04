<?php

declare(strict_types=1);
// Cobre UC-PIDX-38 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Separado para vendas e livre na lista (D5 · CV4). Mexe em ESTOQUE (só leitura), então a conta é
 * provada por dois caminhos: (1) o separado é a soma feita à mão das reservas ativas do cenário, e
 * também a soma tirada direto de `stock_reservations` por uma consulta do próprio teste; (2) o livre
 * mais o separado é o `estoque` que a mesma linha mostra na coluna Estoque.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration') || ! Schema::hasTable('stock_reservations')) {
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

function pslEntrar($teste): void
{
    $teste->user = $teste->usuarioComPermissoes(['product.view', 'access_all_locations'], $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function pslLinha($teste, int $produtoId): ?array
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

/** Reserva direta em `stock_reservations` (estado de partida, não pelo mecanismo que reserva). */
function pslReserva(int $biz, \Tests\Support\EstoqueProduto $p, int $variacao, int $local, float $qtd, string $status = 'active'): void
{
    DB::table('stock_reservations')->insert([
        'business_id' => $biz, 'transaction_id' => random_int(900000000, 999999999),
        'product_id' => $p->productId, 'variation_id' => $p->variations[$variacao]['variation_id'],
        'location_id' => $local, 'qty_reserved' => $qtd, 'status' => $status,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-PIDX-38 · o separado soma as reservas ativas e o livre é o saldo menos o separado', function () {
    $biz = $this->business->id;
    $p = EstoqueFixture::variableProduct($biz, 2);
    $matriz = EstoqueFixture::locationId($biz);
    $filial = EstoqueFixture::locationId($biz, 'filial');
    EstoqueFixture::setStock($p, 0, $matriz, 10);
    EstoqueFixture::setStock($p, 1, $filial, 4);
    // Ativas: 3 + 1,5 (duas vendas) e 2 noutra variação e local. Fora: consumida e liberada.
    pslReserva($biz, $p, 0, $matriz, 3);
    pslReserva($biz, $p, 0, $matriz, 1.5);
    pslReserva($biz, $p, 1, $filial, 2);
    pslReserva($biz, $p, 0, $matriz, 7, 'consumed');
    pslReserva($biz, $p, 0, $matriz, 9, 'released');

    pslEntrar($this);
    $linha = pslLinha($this, $p->productId);
    expect($linha)->not->toBeNull();

    // Caminho 1: a soma feita à mão das ativas, e a mesma soma tirada da tabela pelo teste.
    expect($linha['separado'])->toEqual(6.5);
    $daTabela = (float) DB::table('stock_reservations')->where('business_id', $biz)
        ->where('product_id', $p->productId)->where('status', 'active')->sum('qty_reserved');
    expect($linha['separado'])->toEqual($daTabela);
    // Caminho 2: livre + separado é o saldo da coluna Estoque da mesma linha.
    expect($linha['estoque'])->toEqual(14.0);
    expect($linha['livre'])->toEqual(7.5);
    expect($linha['livre'] + $linha['separado'])->toEqual($linha['estoque']);
});

it('UC-PIDX-38 · sem reserva ativa a linha não traz separado nem livre', function () {
    $biz = $this->business->id;
    $semReserva = EstoqueFixture::singleProduct($biz);
    $soLiberada = EstoqueFixture::singleProduct($biz);
    $matriz = EstoqueFixture::locationId($biz);
    EstoqueFixture::setStock($semReserva, 0, $matriz, 5);
    EstoqueFixture::setStock($soLiberada, 0, $matriz, 5);
    pslReserva($biz, $soLiberada, 0, $matriz, 2, 'released');

    pslEntrar($this);
    foreach ([$semReserva, $soLiberada] as $p) {
        $linha = pslLinha($this, $p->productId);
        // Controle positivo: a linha veio, com o saldo.
        expect($linha['estoque'])->toEqual(5.0);
        expect($linha)->not->toHaveKey('separado');
        expect($linha)->not->toHaveKey('livre');
    }
});

it('UC-PIDX-38 · reserva de outra empresa não entra, e o livre pode ficar negativo', function () {
    $biz = $this->business->id;
    $p = EstoqueFixture::singleProduct($biz);
    $matriz = EstoqueFixture::locationId($biz);
    EstoqueFixture::setStock($p, 0, $matriz, 2);
    pslReserva($biz, $p, 0, $matriz, 3);
    // Mesma variação e local, mas reservada com o business_id de outra empresa (Tier 0, ADR 0093).
    $outra = EstoqueFixture::secondBusinessId();
    expect($outra)->not->toBeNull();
    pslReserva((int) $outra, $p, 0, $matriz, 50);

    pslEntrar($this);
    $linha = pslLinha($this, $p->productId);

    expect($linha['separado'])->toEqual(3.0);
    expect($linha['livre'])->toEqual(-1.0);
});

it('UC-PIDX-38 · produto que não controla estoque não traz separado', function () {
    $biz = $this->business->id;
    $p = EstoqueFixture::singleProduct($biz, false);
    pslReserva($biz, $p, 0, EstoqueFixture::locationId($biz), 4);

    pslEntrar($this);
    $linha = pslLinha($this, $p->productId);

    expect($linha)->not->toBeNull();
    expect($linha['controla_estoque'])->toBeFalse();
    expect($linha)->not->toHaveKey('separado');
});
