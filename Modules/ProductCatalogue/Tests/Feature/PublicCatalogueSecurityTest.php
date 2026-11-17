<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Vitrine pública sem login (/catalogue/{business_id}/{location_id}): o par empresa + local da URL
 * tem de pertencer à mesma empresa, e empresa inexistente dá 404.
 *
 * Tenant 98 contra o adversário 99 (ADR 0358). Cada caso de recusa tem, ao lado, o controle
 * positivo com o par certo: sem ele um 404 poderia vir só de fixture ausente. O corpo da resposta
 * é lido, não só o status.
 *
 * O que NÃO está aqui de propósito, porque já tem contrato em lane MySQL:
 *  - produto da 99 na listagem e no detalhe da 98: tests/Feature/Sells/CatalogoPublicoSituacaoContratoTest.php
 *  - script gravado pelo lojista na vitrine: tests/Feature/Sells/CatalogoPublicoXssContratoTest.php
 *
 * @see Modules\ProductCatalogue\Repositories\ProductCatalogueRepository::findLocationForBusiness
 */

beforeEach(function () {
    if (! Schema::hasTable('business_locations') || DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Vitrine pública depende do schema MySQL UltimatePOS (business_locations com FKs).');
    }

    $this->empresa = $this->seededTenant();
    $this->adversaria = $this->seededSupportClientTenant();
});

function pcsecLocal(int $bizId, string $nome): int
{
    $scheme = DB::table('invoice_schemes')->where('business_id', $bizId)->value('id') ?? DB::table('invoice_schemes')->value('id');
    $layout = DB::table('invoice_layouts')->where('business_id', $bizId)->value('id') ?? DB::table('invoice_layouts')->value('id');
    if (! $scheme || ! $layout) {
        test()->markTestSkipped('Sem invoice_scheme/invoice_layout no banco: as FKs de business_locations não teriam alvo.');
    }

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $bizId, 'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout,
        'name' => $nome, 'country' => 'Brasil', 'state' => 'SC', 'city' => 'Cidade Teste', 'zip_code' => '00000',
        'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('local da própria empresa abre; local da empresa 99 pela URL da 98 dá 404 sem mostrar o local', function () {
    $meu = pcsecLocal($this->empresa->id, '__PCSEC_LOCAL_98__');
    $alheio = pcsecLocal($this->adversaria->id, '__PCSEC_LOCAL_99__');

    // Controle positivo: o par certo renderiza a vitrine com o nome do local.
    $ok = $this->get("/catalogue/{$this->empresa->id}/{$meu}");
    $ok->assertOk();
    expect((string) $ok->getContent())->toContain('__PCSEC_LOCAL_98__');

    $cruzado = $this->get("/catalogue/{$this->empresa->id}/{$alheio}");
    $cruzado->assertNotFound();
    expect((string) $cruzado->getContent())->not->toContain('__PCSEC_LOCAL_99__');
});

it('o mesmo local da 99 abre pela URL da própria 99 (o 404 acima vem do cruzamento, não do local)', function () {
    $alheio = pcsecLocal($this->adversaria->id, '__PCSEC_LOCAL_99__');

    $r = $this->get("/catalogue/{$this->adversaria->id}/{$alheio}");

    $r->assertOk();
    expect((string) $r->getContent())->toContain('__PCSEC_LOCAL_99__');
});

it('empresa inexistente dá 404, mesmo com um local que existe', function () {
    $meu = pcsecLocal($this->empresa->id, '__PCSEC_LOCAL_98__');
    $inexistente = (int) DB::table('business')->max('id') + 1000;

    $r = $this->get("/catalogue/{$inexistente}/{$meu}");

    $r->assertNotFound();
    expect((string) $r->getContent())->not->toContain('__PCSEC_LOCAL_98__');
});

it('não há rota pública que liste empresas sem o business_id na URL', function () {
    $uris = collect(\Route::getRoutes())->map(fn ($r) => $r->uri())->all();

    // Controle positivo: a rota pública existe com os dois parâmetros.
    expect($uris)->toContain('catalogue/{business_id}/{location_id}');
    expect($uris)->not->toContain('catalogue');
    expect($uris)->not->toContain('catalogues');
    expect($uris)->not->toContain('catalogue/{business_id}');
});
