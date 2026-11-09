<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * "Sugerir pela curva" na nova compra (US-VEST-031): o que o servidor entrega à tela.
 * Usuário do tenant 98 contra o 99 (ADR 0358). Nunca biz=4.
 *
 * @covers-uc UC-PURCRE-18  a empresa 98 não enxerga dado da 99
 * @covers-uc UC-PURCRE-19  o botão depende do pacote da empresa
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $user = $this->usuarioComPermissoes(['purchase.create', 'access_all_locations'], $this->business);
    session(['user.business_id' => $this->business->id, 'user.id' => $user->id, 'business.id' => $this->business->id,
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'decimal_separator' => ',', 'thousand_separator' => '.']]);
    $this->actingAs($user);
});

it('UC-PURCRE-18 · produto de outra empresa não devolve sugestão [T0]', function () {
    $alheio = EstoqueFixture::variableProduct((int) $this->seededSupportClientTenant()->id);
    $meu = EstoqueFixture::variableProduct((int) $this->business->id);

    $r = $this->getJson('/vestuario/curva-compra?product_id=' . $alheio->productId . '&total=10')->assertOk();
    expect($r->json('motivo'))->toBe('produto_nao_encontrado')->and($r->json('linhas'))->toBe([]);

    // controle: o produto da própria empresa é encontrado (só não vendeu)
    expect($this->getJson('/vestuario/curva-compra?product_id=' . $meu->productId . '&total=10')->json('motivo'))
        ->toStartWith('sem_venda_no_produto');
});

it('UC-PURCRE-19 · a tela recebe o botão conforme o Vestuário no pacote', function (bool $noPacote) {
    $this->partialMock(ModuleUtil::class, function ($m) use ($noPacote) {
        $m->shouldReceive('isSubscribed')->andReturn(true);
        $m->shouldReceive('hasThePermissionInSubscription')
            ->andReturnUsing(fn ($biz, $permissao) => $permissao === 'vestuario_module' ? $noPacote : true);
    });

    $r = $this->get('/purchases/create', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request())])->assertOk();

    expect($r->json('component'))->toBe('Purchase/Create')
        ->and($r->json('props.curva_compra_disponivel'))->toBe($noPacote);
})->with([true, false]);
