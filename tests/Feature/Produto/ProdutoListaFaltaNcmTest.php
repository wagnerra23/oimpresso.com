<?php

declare(strict_types=1);
// Cobre UC-PIDX-42 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\Tributacao\SaudeFiscalService;
use Tests\Support\EstoqueFixture;

/**
 * Filtro "Falta NCM" da consulta de produto (`?falta_ncm=1`), pedido da PraMoto em 2026-10-27. A regra é
 * a MESMA do card de Saúde fiscal (`SaudeFiscalService`, item `produto_sem_ncm`): NCM válido tem 8 dígitos
 * e não é 00000000. Prova dupla do conjunto: (1) cada caso do cenário dentro ou fora; (2) o quanto o card
 * conta a mais com o cenário é o mesmo tanto que a lista filtrada (só ativos) mostra a mais.
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

function plfnEntrar($teste): void
{
    $teste->user = $teste->usuarioComPermissoes(['product.view', 'access_all_locations'], $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function plfnLista($teste, string $query): array
{
    $r = $teste->get('/products' . $query, $teste->inertia + ['X-Inertia-Partial-Data' => 'lista']);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');

    return $r->json('props.lista');
}

function plfnProduto(int $biz, string $ncm, bool $ativo = true): int
{
    $p = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $p->productId)->update(['ncm' => $ncm, 'is_inactive' => $ativo ? 0 : 1]);

    return $p->productId;
}

/** O que o card de Saúde fiscal conta em "Produtos ativos sem NCM válido" (0 quando não aparece). */
function plfnContagemDoCard(int $biz): int
{
    $m = new ReflectionMethod(SaudeFiscalService::class, 'produtosSemNcm');
    $item = $m->invoke(app(SaudeFiscalService::class), $biz);

    return $item === null ? 0 : (int) $item['contagem'];
}

it('UC-PIDX-42 · o filtro mostra os produtos sem NCM válido e deixa de fora o NCM de 8 dígitos', function () {
    $biz = $this->business->id;
    $nunca = plfnProduto($biz, '0');
    $vazio = plfnProduto($biz, '');
    $curto = plfnProduto($biz, '491110');
    $zeros = plfnProduto($biz, '00000000');
    $valido = plfnProduto($biz, '49111090');

    plfnEntrar($this);
    $ids = array_column(plfnLista($this, '?falta_ncm=1&per_page=-1')['data'], 'id');

    expect($ids)->toContain($nunca, $vazio, $curto, $zeros);
    expect($ids)->not->toContain($valido);
});

it('UC-PIDX-42 · a lista filtrada só com os ativos cresce o mesmo tanto que o card de Saúde fiscal', function () {
    $biz = $this->business->id;
    plfnEntrar($this);
    $cardAntes = plfnContagemDoCard($biz);
    $listaAntes = plfnLista($this, '?falta_ncm=1&active_state=active&per_page=-1')['total'];

    plfnProduto($biz, '0');
    plfnProduto($biz, '1234');
    plfnProduto($biz, '00000000');
    plfnProduto($biz, '0', false);      // inativo: o card não conta e o link abre só os ativos
    plfnProduto($biz, '49111090');      // NCM válido: nenhum dos dois conta

    // Caminho 1 — à mão: três ativos sem NCM válido.
    expect(plfnContagemDoCard($biz) - $cardAntes)->toBe(3);
    // Caminho 2 — a lista que o link do card abre.
    expect(plfnLista($this, '?falta_ncm=1&active_state=active&per_page=-1')['total'] - $listaAntes)->toBe(3);
});

it('UC-PIDX-42 · sem o filtro a lista traz também o NCM válido; o filtro não enxerga outra empresa', function () {
    $biz = $this->business->id;
    $semNcm = plfnProduto($biz, '0');
    $valido = plfnProduto($biz, '49111090');
    $outra = EstoqueFixture::secondBusinessId();
    expect($outra)->not->toBeNull();
    $deFora = plfnProduto((int) $outra, '0');

    plfnEntrar($this);
    $toda = array_column(plfnLista($this, '?per_page=-1')['data'], 'id');
    expect($toda)->toContain($semNcm, $valido);

    $filtrada = array_column(plfnLista($this, '?falta_ncm=1&per_page=-1')['data'], 'id');
    expect($filtrada)->toContain($semNcm);
    expect($filtrada)->not->toContain($deFora);
});
