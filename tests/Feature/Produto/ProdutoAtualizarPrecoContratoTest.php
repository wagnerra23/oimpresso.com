<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato da tela Produto/AtualizarPreco (`/update-product-price`) — playbook Produto · thread 06.
 *
 * Os UCs vêm do contrato, não do código:
 *   resources/js/Pages/Produto/AtualizarPreco/Index.casos.md (UC-PATPRC-01..08)
 *
 * ⛔ Regra mestre de valor: a thread troca só a TELA. UC-PATPRC-02 é a dupla prova (mesma planilha
 *    pelo caminho antigo e pelo novo, preços gravados idênticos) e UC-PATPRC-03 a prévia sem gravar.
 * ⛔ Tenant 98 (ADR 0358) contra o cliente fictício 99. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 *
 * @see app/Http/Controllers/SellingPriceGroupController.php updateProductPrice() · import() · conferirPlanilha()
 */
uses(DatabaseTransactions::class);

const PATPRC_TAG = '[patprc06]';

function patprcUsuario(int $bizId, array $permissoes = []): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'Preco T06', 'username' => 'patprc_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $bizId, 'user_type' => 'user', 'allow_login' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function patprcLogin(object $test, User $user): object
{
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);

    return $test->actingAs($user);
}

function patprcGrupo(int $bizId, string $nome, int $ativo = 1): int
{
    return (int) DB::table('selling_price_groups')->insertGetId([
        'name' => $nome . ' ' . PATPRC_TAG, 'business_id' => $bizId, 'is_active' => $ativo,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Produto single do tenant com 1 variação; devolve [variation_id, sub_sku]. */
function patprcVariacao(int $bizId): array
{
    $vid = EstoqueFixture::singleProduct($bizId)->variationId();

    return [$vid, (string) DB::table('variations')->where('id', $vid)->value('sub_sku')];
}

/** Planilha no formato do export(): product · sku · Selling Price Including Tax · <grupo>. */
function patprcPlanilha(string $grupo, array $linhas, bool $texto = false): UploadedFile
{
    $ss = new Spreadsheet();
    $sh = $ss->getActiveSheet();
    foreach (['product', 'sku', 'Selling Price Including Tax', $grupo] as $c => $h) {
        $sh->setCellValue(Coordinate::stringFromColumnIndex($c + 1) . '1', $h);
    }
    foreach ($linhas as $i => $linha) {
        foreach ($linha as $c => $v) {
            $cel = Coordinate::stringFromColumnIndex($c + 1) . ($i + 2);
            $texto || $c < 2
                ? $sh->setCellValueExplicit($cel, (string) $v, DataType::TYPE_STRING)
                : $sh->setCellValue($cel, $v);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'patprc') . '.xlsx';
    (new Xlsx($ss))->save($path);

    return new UploadedFile($path, 'precos.xlsx', null, null, true);
}

/** Tudo que o import() pode gravar numa variação + o preço do grupo. */
function patprcFoto(int $variationId, int $grupoId): array
{
    $v = DB::table('variations')->where('id', $variationId)->first(['sell_price_inc_tax', 'default_sell_price', 'profit_percent']);
    $g = DB::table('variation_group_prices')->where('variation_id', $variationId)->where('price_group_id', $grupoId)->value('price_inc_tax');

    return [
        'venda' => (string) $v->sell_price_inc_tax, 'base' => (string) $v->default_sell_price,
        'margem' => (string) $v->profit_percent, 'grupo' => $g === null ? null : (string) $g,
    ];
}

function patprcRestaura(int $variationId, int $grupoId, array $foto): void
{
    DB::table('variations')->where('id', $variationId)->update([
        'sell_price_inc_tax' => $foto['venda'], 'default_sell_price' => $foto['base'], 'profit_percent' => $foto['margem'],
    ]);
    DB::table('variation_group_prices')->where('variation_id', $variationId)->where('price_group_id', $grupoId)->delete();
}

function patprcInertia(): array
{
    return [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = $this->seededTenant();
    $this->vizinho = $this->seededSupportClientTenant();
});

it('UC-PATPRC-01 · abre Produto/AtualizarPreco/Index só com os grupos ativos do negócio; 403 sem product.update; ?classico=1 segue Blade', function () {
    $ativo = patprcGrupo($this->biz->id, 'Atacado');
    patprcGrupo($this->biz->id, 'Funcionario', 0);
    patprcGrupo($this->vizinho->id, 'Vizinho');
    $user = patprcUsuario($this->biz->id, ['product.update']);

    patprcLogin($this, $user)->get('/update-product-price')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Produto/AtualizarPreco/Index', false)
            ->where('grupos', fn ($grupos) => collect($grupos)->filter(fn ($g) => str_contains($g['nome'], PATPRC_TAG))
                ->pluck('id')->values()->all() === [$ativo]));

    patprcLogin($this, $user)->get('/update-product-price?classico=1')->assertOk()
        ->assertViewIs('selling_price_group.update_product_price');

    patprcLogin($this, patprcUsuario($this->biz->id))->get('/update-product-price')->assertForbidden();
});

it('UC-PATPRC-02 · a mesma planilha grava preços idênticos pelo caminho antigo e pelo novo', function () {
    [$vid, $sku] = patprcVariacao($this->biz->id);
    $grupo = patprcGrupo($this->biz->id, 'Atacado');
    $nome = 'Atacado ' . PATPRC_TAG;
    $user = patprcUsuario($this->biz->id, ['product.update']);
    $inicial = patprcFoto($vid, $grupo);

    // Ponto decimal: os dois caminhos gravam, e gravam o mesmo número.
    patprcLogin($this, $user)->post('/import-product-price', ['product_group_prices' => patprcPlanilha($nome, [['P', $sku, 1234.56, 999.9]])])
        ->assertRedirect('update-product-price');
    $antigo = patprcFoto($vid, $grupo);
    patprcRestaura($vid, $grupo, $inicial);
    expect(patprcFoto($vid, $grupo))->toBe($inicial);

    patprcLogin($this, $user)->post('/import-product-price', ['product_group_prices' => patprcPlanilha($nome, [['P', $sku, 1234.56, 999.9]])], patprcInertia())
        ->assertRedirect('update-product-price');
    $novo = patprcFoto($vid, $grupo);

    expect($novo)->toBe($antigo);
    expect((float) $novo['venda'])->toBe(1234.56);
    expect((float) $novo['base'])->toBe(1234.56);
    expect((float) $novo['grupo'])->toBe(999.9);

    // Vírgula decimal + milhar ("1.234,56"): os dois caminhos recusam e não gravam nada.
    patprcRestaura($vid, $grupo, $inicial);
    patprcLogin($this, $user)->post('/import-product-price', ['product_group_prices' => patprcPlanilha($nome, [['P', $sku, '1.234,56', '999,90']], true)]);
    $antigoVirgula = patprcFoto($vid, $grupo);
    patprcLogin($this, $user)->post('/import-product-price', ['product_group_prices' => patprcPlanilha($nome, [['P', $sku, '1.234,56', '999,90']], true)], patprcInertia());

    expect(patprcFoto($vid, $grupo))->toBe($antigoVirgula);
    expect($antigoVirgula)->toBe($inicial);
});

it('UC-PATPRC-03 · a conferência lista antes → depois por SKU e não grava', function () {
    [$vid, $sku] = patprcVariacao($this->biz->id);
    $grupo = patprcGrupo($this->biz->id, 'Atacado');
    $nome = 'Atacado ' . PATPRC_TAG;
    $user = patprcUsuario($this->biz->id, ['product.update']);
    $inicial = patprcFoto($vid, $grupo);

    $resp = patprcLogin($this, $user)->post('/import-product-price', [
        'product_group_prices' => patprcPlanilha($nome, [['P', $sku, 1234.56, 999.9]]), 'conferir' => '1',
    ], ['Accept' => 'application/json']);
    $resp->assertOk();

    expect($resp->json('ok'))->toBeTrue();
    $porCampo = collect($resp->json('linhas'))->where('sku', $sku)->keyBy('campo');
    expect((float) $porCampo['Preço de venda']['antes'])->toBe((float) $inicial['venda']);
    expect((float) $porCampo['Preço de venda']['depois'])->toBe(1234.56);
    expect($porCampo[$nome]['antes'])->toBeNull();
    expect((float) $porCampo[$nome]['depois'])->toBe(999.9);

    expect(patprcFoto($vid, $grupo))->toBe($inicial);
});

it('UC-PATPRC-04 · a conferência não vaza o outro negócio, alerta o SKU e não toca o preço dele', function () {
    [$vidVizinho, $skuVizinho] = patprcVariacao($this->vizinho->id);
    $grupoVizinho = patprcGrupo($this->vizinho->id, 'Atacado');
    $nomeProdutoVizinho = (string) DB::table('products')->join('variations', 'variations.product_id', '=', 'products.id')
        ->where('variations.id', $vidVizinho)->value('products.name');
    patprcGrupo($this->biz->id, 'Atacado');
    $user = patprcUsuario($this->biz->id, ['product.update']);
    $inicialVizinho = patprcFoto($vidVizinho, $grupoVizinho);

    $resp = patprcLogin($this, $user)->post('/import-product-price', [
        'product_group_prices' => patprcPlanilha('Atacado ' . PATPRC_TAG, [['P', $skuVizinho, 1234.56, 999.9]]), 'conferir' => '1',
    ], ['Accept' => 'application/json']);
    $resp->assertOk();

    expect($resp->json('linhas'))->toBe([]);
    expect($resp->json('alertas'))->toBe([['sku' => $skuVizinho, 'tipo' => 'outro_negocio']]);
    expect(str_contains($resp->getContent(), $nomeProdutoVizinho))->toBeFalse();
    expect(patprcFoto($vidVizinho, $grupoVizinho))->toBe($inicialVizinho);
});

it('UC-PATPRC-05 · a recusa do import() chega na tela como prop erro', function () {
    $user = patprcUsuario($this->biz->id, ['product.update']);

    patprcLogin($this, $user)->withSession(['notification' => ['success' => 0, 'msg' => 'Preço não numérico encontrado na linha 1']])
        ->get('/update-product-price')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Produto/AtualizarPreco/Index', false)
            ->where('erro', 'Preço não numérico encontrado na linha 1'));
});

/** Todos os preços de grupo gravados numa variação, de qualquer grupo (prova de "não tocou"). */
function patprcGruposDaVariacao(int $variationId): array
{
    return DB::table('variation_group_prices')->where('variation_id', $variationId)
        ->orderBy('price_group_id')->get(['price_group_id', 'price_inc_tax'])
        ->map(fn ($r) => [(int) $r->price_group_id, (string) $r->price_inc_tax])->all();
}

it('UC-PATPRC-06 · o import() grava só no próprio negócio quando o SKU coincide com o de outro (P0 Tier 0)', function () {
    // Vizinho criado ANTES: a variação dele tem o id menor — é a que o `first()` sem filtro pegava.
    [$vidVizinho, $sku] = patprcVariacao($this->vizinho->id);
    $grupoVizinho = patprcGrupo($this->vizinho->id, 'Atacado');
    [$vid] = patprcVariacao($this->biz->id);
    DB::table('variations')->where('id', $vid)->update(['sub_sku' => $sku]);
    expect($vidVizinho)->toBeLessThan($vid);

    $grupo = patprcGrupo($this->biz->id, 'Atacado');
    $user = patprcUsuario($this->biz->id, ['product.update']);
    $vizinhoAntes = patprcFoto($vidVizinho, $grupoVizinho);
    $gruposVizinhoAntes = patprcGruposDaVariacao($vidVizinho);

    patprcLogin($this, $user)->post('/import-product-price', [
        'product_group_prices' => patprcPlanilha('Atacado ' . PATPRC_TAG, [['P', $sku, 1234.56, 999.9]]),
    ])->assertRedirect('update-product-price')->assertSessionHas('status.success', 1);

    // O 99 fica intacto: preço de venda, base, margem e nenhum preço de grupo novo.
    expect(patprcFoto($vidVizinho, $grupoVizinho))->toBe($vizinhoAntes);
    expect(patprcGruposDaVariacao($vidVizinho))->toBe($gruposVizinhoAntes);
    // O 98 recebe a planilha.
    $novo = patprcFoto($vid, $grupo);
    expect((float) $novo['venda'])->toBe(1234.56);
    expect((float) $novo['grupo'])->toBe(999.9);

    // SKU que só existe no 99: a linha é recusada com o SKU no motivo e nada é gravado.
    [$vidSo99, $skuSo99] = patprcVariacao($this->vizinho->id);
    $so99Antes = patprcFoto($vidSo99, $grupoVizinho);
    patprcLogin($this, $user)->post('/import-product-price', [
        'product_group_prices' => patprcPlanilha('Atacado ' . PATPRC_TAG, [['P', $skuSo99, 777.7, 666.6]]),
    ])->assertRedirect('update-product-price')
        ->assertSessionHas('notification.success', 0)
        ->assertSessionHas('notification.msg', fn ($m) => str_contains((string) $m, $skuSo99));
    expect(patprcFoto($vidSo99, $grupoVizinho))->toBe($so99Antes);
    expect(patprcGruposDaVariacao($vidSo99))->toBe([]);
});

it('UC-PATPRC-07 · planilha só com SKUs do próprio negócio grava o mesmo que antes do conserto (regressão de valor)', function () {
    [$vidA, $skuA] = patprcVariacao($this->biz->id);
    [$vidB, $skuB] = patprcVariacao($this->biz->id);
    $grupo = patprcGrupo($this->biz->id, 'Atacado');
    $user = patprcUsuario($this->biz->id, ['product.update']);
    $antesB = patprcFoto($vidB, $grupo);

    patprcLogin($this, $user)->post('/import-product-price', [
        'product_group_prices' => patprcPlanilha('Atacado ' . PATPRC_TAG, [['A', $skuA, 1234.56, 999.9], ['B', $skuB, 20, 55.5]]),
    ])->assertRedirect('update-product-price')->assertSessionHas('status.success', 1);

    // Recálculo à mão (2º caminho, sem passar pelo Util): fixture sem imposto e custo 10.
    //   base = 1234.56 × 100 / (100 + 0) = 1234.56 · margem = (1234.56 − 10) / 10 × 100 = 12245.6
    $a = patprcFoto($vidA, $grupo);
    expect(round((float) $a['venda'], 4))->toBe(1234.56);
    expect(round((float) $a['base'], 4))->toBe(1234.56);
    expect(round((float) $a['margem'], 4))->toBe(12245.6);
    expect(round((float) $a['grupo'], 4))->toBe(999.9);

    // Preço de venda igual ao atual (20): a variação não é regravada, só o preço do grupo entra.
    $b = patprcFoto($vidB, $grupo);
    expect([$b['venda'], $b['base'], $b['margem']])->toBe([$antesB['venda'], $antesB['base'], $antesB['margem']]);
    expect(round((float) $b['grupo'], 4))->toBe(55.5);
});

it('UC-PATPRC-08 · exportar e importar exigem product.update', function () {
    [$vid, $sku] = patprcVariacao($this->biz->id);
    $grupo = patprcGrupo($this->biz->id, 'Atacado');
    $semPermissao = patprcUsuario($this->biz->id);
    $inicial = patprcFoto($vid, $grupo);

    patprcLogin($this, $semPermissao)->get('/export-product-price')->assertForbidden();
    patprcLogin($this, $semPermissao)->post('/import-product-price', [
        'product_group_prices' => patprcPlanilha('Atacado ' . PATPRC_TAG, [['P', $sku, 1234.56, 999.9]]),
    ])->assertForbidden();
    expect(patprcFoto($vid, $grupo))->toBe($inicial);

    // Controle: com a permissão, o mesmo export responde (o export() abre um ob_start que não fecha).
    $nivel = ob_get_level();
    patprcLogin($this, patprcUsuario($this->biz->id, ['product.update']))->get('/export-product-price')->assertOk();
    while (ob_get_level() > $nivel) {
        ob_end_clean();
    }
});
