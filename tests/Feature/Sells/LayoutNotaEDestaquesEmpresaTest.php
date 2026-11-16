<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leitura por id vindo da URL, restrita à empresa da sessão:
 *  - GET /invoice-layouts/{id}/edit (InvoiceLayoutController@edit)
 *  - GET /sells/pos/get-featured-products/{location_id} (SellPosController@getFeaturedProducts)
 *
 * Tier 0 (ADR 0093): id de outra empresa → 404 (controle negativo); id da própria → 200 (controle
 * positivo, em par).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('invoice_layouts')) {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    $this->biz = (int) $this->seededTenant()->id;
    $this->outro = (int) \App\Business::where('id', '!=', $this->biz)->orderBy('id')->value('id');
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    $this->user = $this->usuarioComPermissoes(['invoice_settings.access', 'sell.create'], $this->seededTenant());
});

function layoutDestaqueGet($t, string $url)
{
    return $t->actingAs($t->user)
        ->withSession(['user.business_id' => $t->biz, 'business.id' => $t->biz, 'user.id' => $t->user->id])
        ->get($url);
}

it('layout de nota: outra empresa 404, própria 200', function () {
    $alheio = (int) DB::table('invoice_layouts')->where('business_id', $this->outro)->value('id');
    $meu = (int) DB::table('invoice_layouts')->where('business_id', $this->biz)->value('id');
    if (! $alheio || ! $meu) {
        $this->markTestSkipped('Lane sem layout de nota nas duas empresas.');
    }

    expect(layoutDestaqueGet($this, "/invoice-layouts/{$alheio}/edit")->status())->toBe(404);
    layoutDestaqueGet($this, "/invoice-layouts/{$meu}/edit")->assertOk();
});

it('produtos em destaque: local de outra empresa 404, próprio 200', function () {
    $alheio = (int) DB::table('business_locations')->where('business_id', $this->outro)->value('id');
    $meu = (int) DB::table('business_locations')->where('business_id', $this->biz)->value('id');
    if (! $alheio || ! $meu) {
        $this->markTestSkipped('Lane sem local nas duas empresas.');
    }

    expect(layoutDestaqueGet($this, "/sells/pos/get-featured-products/{$alheio}")->status())->toBe(404);
    layoutDestaqueGet($this, "/sells/pos/get-featured-products/{$meu}")->assertOk();
});
