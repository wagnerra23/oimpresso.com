<?php
// @covers-us US-PCP-026

declare(strict_types=1);
// Cobre UC-FLX-07 (as etapas da OS vêm do roteiro do produto) no lado da tela:
// UC-PCROT-05 e UC-PCROT-06 de Producao/Roteiros/Index.casos.md.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: vista Roteiros do protótipo (producao-cv-os.jsx, bloco "Produtos que usam este roteiro",
 * #9517 aprovado pelo [W]) e UC-FLX-07 de CasosDoPrototipo. Tenant 98 contra 99 (ADR 0358). Nunca biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_produto_roteiro')) {
        $this->markTestSkipped('pcp_produto_roteiro ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->b98 = $this->seededTenant();
    $this->b99 = $this->seededSupportClientTenant();
    $this->g98 = $this->usuarioComPermissoes(['producao.access'], $this->b98);
    $this->g99 = $this->usuarioComPermissoes(['producao.access'], $this->b99);
});

/** Login novo: SetSessionData só grava a empresa quando a sessão está vazia. */
function rptComo($teste, $user)
{
    $teste->flushSession();

    return $teste->actingAs($user);
}

function rptRoteiro(int $biz, string $nome): int
{
    return DB::table('pcp_roteiros')->insertGetId(['business_id' => $biz, 'familia' => 'Impressão digital', 'nome' => $nome.' '.uniqid(),
        'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
}

function rptProduto(int $biz, int $userId, string $nome, bool $inativo = false): int
{
    $unit = DB::table('units')->insertGetId(['business_id' => $biz, 'actual_name' => 'Unidade RPT', 'short_name' => 'UR', 'allow_decimal' => 1,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);

    return DB::table('products')->insertGetId(['business_id' => $biz, 'name' => $nome, 'type' => 'single', 'unit_id' => $unit, 'tax_type' => 'exclusive',
        'sku' => 'RPT-'.random_int(100000, 999999), 'barcode_type' => 'C128', 'enable_stock' => 0, 'alert_quantity' => 0, 'is_inactive' => $inativo ? 1 : 0,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
}

function rptLigar(int $biz, int $produto, int $roteiro): void
{
    DB::table('pcp_produto_roteiro')->insert(['business_id' => $biz, 'product_id' => $produto, 'roteiro_id' => $roteiro, 'created_at' => now(), 'updated_at' => now()]);
}

/** Prop deferida pedida como o browser pede (X-Inertia + X-Requested-With). */
function rptRoteiros($teste, $user): array
{
    rptComo($teste, $user);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $teste->get('/producao/roteiros', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'roteiros', 'X-Inertia-Partial-Component' => 'Producao/Roteiros/Index']);
    $r->assertOk();

    return collect((array) $r->json('props.roteiros'))->keyBy('id')->all();
}

function rptBusca($teste, $user, string $q): array
{
    rptComo($teste, $user);
    $r = $teste->getJson('/producao/roteiros/produtos/busca?q='.urlencode($q));
    $r->assertOk();

    return (array) $r->json();
}

it('UC-FLX-07 · UC-PCROT-05 · o roteiro traz o nome dos produtos ligados, e vínculo com produto de outra empresa não aparece', function () {
    $roteiro = rptRoteiro($this->b98->id, 'Lona');
    $lona = rptProduto($this->b98->id, (int) $this->g98->id, 'Lona 440 g impressa');
    $banner = rptProduto($this->b98->id, (int) $this->g98->id, 'Banner roll-up');
    $daOutra = rptProduto($this->b99->id, (int) $this->g99->id, 'Lona da outra empresa');
    rptLigar($this->b98->id, $lona, $roteiro);
    rptLigar($this->b98->id, $banner, $roteiro);
    rptLigar($this->b98->id, $daOutra, $roteiro); // vínculo podre: produto da 99 num roteiro da 98

    $r = rptRoteiros($this, $this->g98)[$roteiro];

    expect(array_column($r['produtos'], 'nome'))->toBe(['Banner roll-up', 'Lona 440 g impressa']);
    expect($r['produto_ids'])->toEqualCanonicalizing([$lona, $banner]);
    expect($r['produto_ids'])->not->toContain($daOutra);
});

it('UC-FLX-07 · UC-PCROT-06 · a busca acha pelo nome ou código, só ativos da empresa, e avisa o roteiro em que o produto já está', function () {
    $outro = rptRoteiro($this->b98->id, 'Adesivo');
    $ligado = rptProduto($this->b98->id, (int) $this->g98->id, 'Adesivo vinil impresso');
    $solto = rptProduto($this->b98->id, (int) $this->g98->id, 'Adesivo de frota');
    $inativo = rptProduto($this->b98->id, (int) $this->g98->id, 'Adesivo antigo', inativo: true);
    $daOutra = rptProduto($this->b99->id, (int) $this->g99->id, 'Adesivo da outra empresa');
    rptLigar($this->b98->id, $ligado, $outro);

    $achados = collect(rptBusca($this, $this->g98, 'Adesivo'))->keyBy('id');

    expect($achados->keys()->all())->toContain($ligado, $solto);
    expect($achados->has($inativo))->toBeFalse();
    expect($achados->has($daOutra))->toBeFalse();
    expect($achados[$ligado]['roteiro_id'])->toBe($outro);
    expect($achados[$solto]['roteiro_id'])->toBeNull();

    $sku = DB::table('products')->where('id', $solto)->value('sku');
    expect(array_column(rptBusca($this, $this->g98, $sku), 'id'))->toBe([$solto]);
    expect(rptBusca($this, $this->g98, 'A'))->toBe([]);
});

it('UC-PCROT-06 · a empresa 99 não acha produto da 98, e sem producao.access a busca é recusada', function () {
    $nome = 'Placa ACM 3 mm '.uniqid();
    $p98 = rptProduto($this->b98->id, (int) $this->g98->id, $nome);

    expect(array_column(rptBusca($this, $this->g99, $nome), 'id'))->not->toContain($p98);
    expect(array_column(rptBusca($this, $this->g98, $nome), 'id'))->toBe([$p98]);

    $semAcesso = $this->usuarioComPermissoes(['sell.view'], $this->b98);
    rptComo($this, $semAcesso)->getJson('/producao/roteiros/produtos/busca?q=Placa')->assertForbidden();
});
