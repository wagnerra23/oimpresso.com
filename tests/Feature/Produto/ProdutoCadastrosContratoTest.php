<?php

declare(strict_types=1);

use App\Product;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato da tela Produto/Cadastros (`/units` e as rotas das outras abas) — playbook Produto · threads 02 e 03.
 *
 * Os UCs vêm do contrato, não do código:
 *   resources/js/Pages/Produto/Cadastros/Index.casos.md (UC-PCADAP-01..16)
 *
 * ⛔ Tenant 98 (ADR 0358) contra o cliente fictício 99. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 *
 * @see app/Http/Controllers/UnitController.php  cadastros()
 * @see app/Http/Controllers/BrandController.php destroy()
 * @see app/Http/Controllers/TaxonomyController.php destroy()
 * @see app/Http/Controllers/VariationTemplateController.php index() · destroy()
 */
uses(DatabaseTransactions::class);

const PCADAP_TAG = '[pcadap02]';

function pcadapUsuario(int $bizId, array $permissoes = []): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'Cadastros T02', 'username' => 'pcadap_' . uniqid(), 'password' => bcrypt('ci'),
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

function pcadapLogin(object $test, User $user): object
{
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);

    return $test->actingAs($user);
}

function pcadapUnidade(int $bizId, string $nome, string $simbolo, ?int $baseId = null, ?float $mult = null): int
{
    return (int) DB::table('units')->insertGetId([
        'business_id' => $bizId, 'actual_name' => $nome . ' ' . PCADAP_TAG, 'short_name' => $simbolo,
        'allow_decimal' => 0, 'base_unit_id' => $baseId, 'base_unit_multiplier' => $mult,
        'created_by' => EstoqueFixture::userId($bizId), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function pcadapMarca(int $bizId, string $nome): int
{
    return (int) DB::table('brands')->insertGetId([
        'business_id' => $bizId, 'name' => $nome . ' ' . PCADAP_TAG,
        'created_by' => EstoqueFixture::userId($bizId), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function pcadapCategoria(int $bizId, string $nome, int $paiId = 0, string $tipo = 'product'): int
{
    return (int) DB::table('categories')->insertGetId([
        'business_id' => $bizId, 'name' => $nome . ' ' . PCADAP_TAG, 'short_code' => 'PC' . random_int(100, 999),
        'parent_id' => $paiId, 'category_type' => $tipo,
        'created_by' => EstoqueFixture::userId($bizId), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function pcadapProduto(int $bizId, int $unitId, ?int $brandId = null, ?int $catId = null, ?int $subCatId = null): void
{
    $sku = 'PCADAP-' . strtoupper(bin2hex(random_bytes(4)));
    Product::forceCreate([
        'name' => 'Produto ' . PCADAP_TAG, 'business_id' => $bizId, 'type' => 'single',
        'unit_id' => $unitId, 'brand_id' => $brandId, 'category_id' => $catId, 'sub_category_id' => $subCatId, 'tax_type' => 'exclusive', 'enable_stock' => 0,
        'sku' => $sku, 'barcode_type' => 'C128', 'created_by' => EstoqueFixture::userId($bizId),
    ]);
}

/** Props deferidas pedidas como o browser pede (X-Inertia + X-Requested-With). */
function pcadapProps(object $test, User $user, string $props, string $query = ''): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resp = pcadapLogin($test, $user)->get('/units' . $query, [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $props,
        'X-Inertia-Partial-Component' => 'Produto/Cadastros/Index',
    ]);
    $resp->assertOk();

    return (array) $resp->json('props');
}

function pcadapNomes(array $linhas): array
{
    return collect($linhas)->pluck('nome')->filter(fn ($n) => str_contains((string) $n, PCADAP_TAG))->values()->all();
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = $this->seededTenant();
    $this->vizinho = $this->seededSupportClientTenant();
});

it('UC-PCADAP-01 · /units abre Produto/Cadastros/Index, aba pela query', function () {
    $user = pcadapUsuario($this->biz->id, ['unit.view', 'brand.view']);

    pcadapLogin($this, $user)->get('/units')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Produto/Cadastros/Index', false)->where('aba', 'unidades'));
    pcadapLogin($this, $user)->get('/units?aba=marcas')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Produto/Cadastros/Index', false)->where('aba', 'marcas'));
});

it('UC-PCADAP-02 · visita Inertia não cai no DataTables; ajax clássico segue JSON e ?classico=1 segue Blade', function () {
    $user = pcadapUsuario($this->biz->id, ['unit.view']);
    pcadapUnidade($this->biz->id, 'Metro quadrado', 'm2');

    expect(pcadapNomes(pcadapProps($this, $user, 'unidades')['unidades'] ?? []))->toContain('Metro quadrado ' . PCADAP_TAG);

    $json = pcadapLogin($this, $user)->get('/units', ['X-Requested-With' => 'XMLHttpRequest']);
    $json->assertOk();
    $this->assertArrayHasKey('data', (array) $json->json(), 'o DataTables da tela clássica deve continuar recebendo JSON');
    $this->assertNull($json->headers->get('X-Inertia'), 'ajax sem X-Inertia não pode virar resposta Inertia');

    pcadapLogin($this, $user)
        ->withSession(['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']])
        ->get('/units?classico=1')->assertOk()->assertViewIs('unit.index');
});

it('UC-PCADAP-03 · cada aba pela sua permissão; sem nenhuma, 403', function () {
    $soUnidade = pcadapUsuario($this->biz->id, ['unit.view']);
    pcadapMarca($this->biz->id, 'Marca escondida');

    $props = pcadapProps($this, $soUnidade, 'can,marcas');
    expect($props['can']['marcas']['view'])->toBeFalse();
    expect($props['can']['unidades']['view'])->toBeTrue();
    $this->assertEmpty($props['marcas'] ?? null, 'quem não tem brand.view recebeu a lista de marcas');

    pcadapLogin($this, pcadapUsuario($this->biz->id))->get('/units')->assertForbidden();
});

it('UC-PCADAP-04 · unidade e marca de outro negócio não aparecem [T0]', function () {
    $user = pcadapUsuario($this->biz->id, ['unit.view', 'brand.view']);
    pcadapUnidade($this->biz->id, 'Minha unidade', 'mu');
    pcadapUnidade($this->vizinho->id, 'Unidade vizinha', 'uv');
    pcadapMarca($this->biz->id, 'Minha marca');
    pcadapMarca($this->vizinho->id, 'Marca vizinha');

    $props = pcadapProps($this, $user, 'unidades,marcas');
    $unidades = pcadapNomes($props['unidades'] ?? []);
    $marcas = pcadapNomes($props['marcas'] ?? []);

    expect($unidades)->toContain('Minha unidade ' . PCADAP_TAG);
    expect($marcas)->toContain('Minha marca ' . PCADAP_TAG);
    $this->assertNotContains('Unidade vizinha ' . PCADAP_TAG, $unidades, 'vazou unidade de outro business');
    $this->assertNotContains('Marca vizinha ' . PCADAP_TAG, $marcas, 'vazou marca de outro business');
});

it('UC-PCADAP-05 · unidade em uso: a tela sabe a contagem e o servidor recusa a exclusão', function () {
    $user = pcadapUsuario($this->biz->id, ['unit.view', 'unit.delete']);
    $m2 = pcadapUnidade($this->biz->id, 'Em uso', 'm2u');
    pcadapProduto($this->biz->id, $m2);

    $linha = collect(pcadapProps($this, $user, 'unidades')['unidades'] ?? [])->firstWhere('id', $m2);
    expect($linha['em_uso'] ?? null)->toBe(1);

    $resp = pcadapLogin($this, $user)->delete("/units/{$m2}", [], ['X-Requested-With' => 'XMLHttpRequest']);
    $resp->assertOk();
    expect($resp->json('success'))->toBeFalse();
    expect(DB::table('units')->where('id', $m2)->whereNull('deleted_at')->exists())->toBeTrue();
});

it('UC-PCADAP-06 · unidade livre sai', function () {
    $user = pcadapUsuario($this->biz->id, ['unit.view', 'unit.delete']);
    $kg = pcadapUnidade($this->biz->id, 'Quilograma', 'kgp');

    $resp = pcadapLogin($this, $user)->delete("/units/{$kg}", [], ['X-Requested-With' => 'XMLHttpRequest']);
    expect($resp->json('success'))->toBeTrue();
    expect(DB::table('units')->where('id', $kg)->whereNull('deleted_at')->exists())->toBeFalse();
});

it('UC-PCADAP-07 · marca em uso não sai; marca livre sai', function () {
    $user = pcadapUsuario($this->biz->id, ['brand.view', 'brand.delete']);
    $usada = pcadapMarca($this->biz->id, 'Vinilcor');
    $livre = pcadapMarca($this->biz->id, 'Sem uso');
    pcadapProduto($this->biz->id, EstoqueFixture::unitId($this->biz->id), $usada);

    $recusa = pcadapLogin($this, $user)->delete("/brands/{$usada}", [], ['X-Requested-With' => 'XMLHttpRequest']);
    expect($recusa->json('success'))->toBeFalse();
    expect($recusa->json('em_uso'))->toBe(1);
    expect(DB::table('brands')->where('id', $usada)->whereNull('deleted_at')->exists())->toBeTrue();

    $ok = pcadapLogin($this, $user)->delete("/brands/{$livre}", [], ['X-Requested-With' => 'XMLHttpRequest']);
    expect($ok->json('success'))->toBeTrue();
    expect(DB::table('brands')->where('id', $livre)->whereNull('deleted_at')->exists())->toBeFalse();
});

it('UC-PCADAP-08 · múltiplo de base aparece escrito na linha', function () {
    $user = pcadapUsuario($this->biz->id, ['unit.view']);
    $un = pcadapUnidade($this->biz->id, 'Unidade base', 'Unx');
    $cx = pcadapUnidade($this->biz->id, 'Caixa', 'cx', $un, 1000);

    $linha = collect(pcadapProps($this, $user, 'unidades')['unidades'] ?? [])->firstWhere('id', $cx);
    expect($linha['base'] ?? null)->toBe('1 cx = 1000 Unx');
});

it('UC-PCADAP-09 · aba Categorias: só as de produto do meu negócio, pai seguido das filhas [T0]', function () {
    $user = pcadapUsuario($this->biz->id, ['category.view']);
    $pai = pcadapCategoria($this->biz->id, 'Comunicação visual');
    $filha = pcadapCategoria($this->biz->id, 'Lonas', $pai);
    pcadapCategoria($this->biz->id, 'Taxonomia de outro módulo', 0, 'device');
    pcadapCategoria($this->vizinho->id, 'Categoria vizinha');

    $props = pcadapProps($this, $user, 'can,categorias', '?aba=categorias');
    expect($props['can']['categorias']['view'])->toBeTrue();
    expect($props['can']['unidades']['view'])->toBeFalse();
    $nomes = pcadapNomes($props['categorias'] ?? []);

    $this->assertNotContains('Categoria vizinha ' . PCADAP_TAG, $nomes, 'vazou categoria de outro business');
    $this->assertNotContains('Taxonomia de outro módulo ' . PCADAP_TAG, $nomes, 'categoria que não é de produto entrou na aba');
    expect(array_values(array_intersect($nomes, ['Comunicação visual ' . PCADAP_TAG, 'Lonas ' . PCADAP_TAG])))
        ->toBe(['Comunicação visual ' . PCADAP_TAG, 'Lonas ' . PCADAP_TAG]);

    $linhas = collect($props['categorias']);
    expect($linhas->firstWhere('id', $filha)['pai'] ?? null)->toBe('Comunicação visual ' . PCADAP_TAG);
    expect($linhas->firstWhere('id', $filha)['pai_id'] ?? null)->toBe($pai);
    expect($linhas->firstWhere('id', $pai)['filhas'] ?? null)->toBe(1);

    $semCategoria = pcadapProps($this, pcadapUsuario($this->biz->id, ['unit.view']), 'can,categorias');
    expect($semCategoria['can']['categorias']['view'])->toBeFalse();
    $this->assertEmpty($semCategoria['categorias'] ?? null, 'quem não tem category.view recebeu a lista de categorias');
});

it('UC-PCADAP-10 · categoria em uso (pela subcategoria) não sai, e a tela sabe a contagem', function () {
    $user = pcadapUsuario($this->biz->id, ['category.view', 'category.delete']);
    $pai = pcadapCategoria($this->biz->id, 'Fachadas');
    $sub = pcadapCategoria($this->biz->id, 'ACM', $pai);
    pcadapProduto($this->biz->id, EstoqueFixture::unitId($this->biz->id), null, $pai, $sub);

    $linha = collect(pcadapProps($this, $user, 'categorias', '?aba=categorias')['categorias'] ?? [])->firstWhere('id', $sub);
    expect($linha['em_uso'] ?? null)->toBe(1);

    $resp = pcadapLogin($this, $user)->delete("/taxonomies/{$sub}", [], ['X-Requested-With' => 'XMLHttpRequest']);
    $resp->assertOk();
    expect($resp->json('success'))->toBeFalse();
    expect($resp->json('em_uso'))->toBe(1);
    expect(DB::table('categories')->where('id', $sub)->whereNull('deleted_at')->exists())->toBeTrue();
});

it('UC-PCADAP-11 · categoria com subcategoria não sai; depois de esvaziada, sai', function () {
    $user = pcadapUsuario($this->biz->id, ['category.view', 'category.delete']);
    $pai = pcadapCategoria($this->biz->id, 'Sinalização');
    $sub = pcadapCategoria($this->biz->id, 'Placas', $pai);

    $recusa = pcadapLogin($this, $user)->delete("/taxonomies/{$pai}", [], ['X-Requested-With' => 'XMLHttpRequest']);
    expect($recusa->json('success'))->toBeFalse();
    expect($recusa->json('filhas'))->toBe(1);
    expect(DB::table('categories')->where('id', $pai)->whereNull('deleted_at')->exists())->toBeTrue();
    expect(DB::table('categories')->where('id', $sub)->whereNull('deleted_at')->exists())->toBeTrue();

    expect(pcadapLogin($this, $user)->delete("/taxonomies/{$sub}", [], ['X-Requested-With' => 'XMLHttpRequest'])->json('success'))->toBeTrue();
    expect(pcadapLogin($this, $user)->delete("/taxonomies/{$pai}", [], ['X-Requested-With' => 'XMLHttpRequest'])->json('success'))->toBeTrue();
    expect(DB::table('categories')->where('id', $pai)->whereNull('deleted_at')->exists())->toBeFalse();
});

it('UC-PCADAP-12 · editar unidade não mexe na conversão de estoque sem pedido explícito', function () {
    $user = pcadapUsuario($this->biz->id, ['unit.view', 'unit.update']);
    $un = pcadapUnidade($this->biz->id, 'Unidade base', 'Unb');
    $cx = pcadapUnidade($this->biz->id, 'Caixa', 'cxb', $un, 1000);
    $meio = pcadapUnidade($this->biz->id, 'Meia', 'mei', $un, 0.5);
    $ajax = ['X-Requested-With' => 'XMLHttpRequest'];
    $base = fn (int $id) => DB::table('units')->where('id', $id)->first(['base_unit_id', 'base_unit_multiplier']);

    // 1) POST sem o campo `define_base_unit` (form parcial/API): a base FICA. Antes, zerava.
    pcadapLogin($this, $user)->put("/units/{$cx}", [
        'actual_name' => 'Caixa ' . PCADAP_TAG, 'short_name' => 'cxb', 'allow_decimal' => 0,
    ], $ajax)->assertOk();
    expect((int) $base($cx)->base_unit_id)->toBe($un);
    expect((float) $base($cx)->base_unit_multiplier)->toBe(1000.0);

    // 2) O modal clássico mostra o múltiplo sem vírgula de milhar e sem arredondar:
    //    "1,000" o num_uf lê como 1; "0,5" virava "1" no number_format sem casas.
    $form = pcadapLogin($this, $user)->get("/units/{$cx}/edit", $ajax)->assertOk()->getContent();
    expect($form)->toContain('value="1000"');
    expect($form)->not->toContain('value="1,000"');
    $formMeio = pcadapLogin($this, $user)->get("/units/{$meio}/edit", $ajax)->assertOk()->getContent();
    expect($formMeio)->toContain('value="0,5"');

    // 3) Salvar o modal sem tocar em nada (hidden 0 + checkbox 1, o que o serialize manda) preserva 1000.
    pcadapLogin($this, $user)->put("/units/{$cx}", [
        'actual_name' => 'Caixa ' . PCADAP_TAG, 'short_name' => 'cxb', 'allow_decimal' => 0,
        'define_base_unit' => '1', 'base_unit_id' => $un, 'base_unit_multiplier' => '1000',
    ], $ajax)->assertOk();
    expect((float) $base($cx)->base_unit_multiplier)->toBe(1000.0);

    // 4) Desmarcar é explícito (`define_base_unit=0`): aí a base sai.
    pcadapLogin($this, $user)->put("/units/{$cx}", [
        'actual_name' => 'Caixa ' . PCADAP_TAG, 'short_name' => 'cxb', 'allow_decimal' => 0,
        'define_base_unit' => '0',
    ], $ajax)->assertOk();
    expect($base($cx)->base_unit_id)->toBeNull();
    expect($base($cx)->base_unit_multiplier)->toBeNull();
});

// ── Thread 03 · abas Variações, Grupos de preço e Garantias ─────────────────────────────────

function pcadapVariacao(int $bizId, string $nome, array $valores = []): int
{
    $id = (int) DB::table('variation_templates')->insertGetId([
        'business_id' => $bizId, 'name' => $nome . ' ' . PCADAP_TAG, 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($valores as $v) {
        DB::table('variation_value_templates')->insert(['name' => $v, 'variation_template_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
    }

    return $id;
}

/** Produto variável do negócio `$bizId` usando o modelo `$templateId`. */
function pcadapUsaVariacao(int $bizId, int $templateId): void
{
    $produto = Product::forceCreate([
        'name' => 'Variável ' . PCADAP_TAG, 'business_id' => $bizId, 'type' => 'variable',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 0,
        'sku' => 'PCADAPV-' . strtoupper(bin2hex(random_bytes(4))), 'barcode_type' => 'C128', 'created_by' => EstoqueFixture::userId($bizId),
    ]);
    DB::table('product_variations')->insert([
        'variation_template_id' => $templateId, 'name' => 'Cor', 'product_id' => $produto->id, 'is_dummy' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-PCADAP-13 · /variation-templates, /selling-price-group e /warranties abrem Produto/Cadastros na aba de cada um', function () {
    $user = pcadapUsuario($this->biz->id, ['variation.view', 'warranty.view', 'product.create', 'unit.view']);

    foreach (['/variation-templates' => 'variacoes', '/selling-price-group' => 'grupos', '/warranties' => 'garantias', '/units' => 'unidades'] as $rota => $aba) {
        pcadapLogin($this, $user)->get($rota)->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Produto/Cadastros/Index', false)->where('aba', $aba));
    }

    $json = pcadapLogin($this, $user)->get('/warranties', ['X-Requested-With' => 'XMLHttpRequest']);
    $json->assertOk();
    $this->assertArrayHasKey('data', (array) $json->json(), 'o DataTables da tela clássica de garantias deve continuar recebendo JSON');
    $this->assertNull($json->headers->get('X-Inertia'), 'ajax sem X-Inertia não pode virar resposta Inertia');
});

it('UC-PCADAP-14 · variações, grupos e garantias de outro negócio não aparecem; uso conta só produto meu [T0]', function () {
    $user = pcadapUsuario($this->biz->id, ['variation.view', 'warranty.view', 'product.create']);
    $cor = pcadapVariacao($this->biz->id, 'Cor', ['Azul', 'Vermelho']);
    pcadapVariacao($this->vizinho->id, 'Variação vizinha');
    pcadapUsaVariacao($this->biz->id, $cor);
    pcadapUsaVariacao($this->vizinho->id, $cor); // referência do vizinho ao MEU modelo não conta
    foreach ([$this->biz->id => 'Atacado', $this->vizinho->id => 'Grupo vizinho'] as $biz => $nome) {
        DB::table('selling_price_groups')->insert(['business_id' => $biz, 'name' => $nome . ' ' . PCADAP_TAG, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
    foreach ([$this->biz->id => '12 meses', $this->vizinho->id => 'Garantia vizinha'] as $biz => $nome) {
        DB::table('warranties')->insert(['business_id' => $biz, 'name' => $nome . ' ' . PCADAP_TAG, 'duration' => 12, 'duration_type' => 'months', 'created_at' => now(), 'updated_at' => now()]);
    }

    $props = pcadapProps($this, $user, 'variacoes,grupos,garantias', '?aba=variacoes');
    expect(pcadapNomes($props['variacoes'] ?? []))->toBe(['Cor ' . PCADAP_TAG]);
    expect(pcadapNomes($props['grupos'] ?? []))->toBe(['Atacado ' . PCADAP_TAG]);
    expect(pcadapNomes($props['garantias'] ?? []))->toBe(['12 meses ' . PCADAP_TAG]);

    $linha = collect($props['variacoes'])->firstWhere('id', $cor);
    expect($linha['em_uso'])->toBe(1);
    expect($linha['valores'])->toBe(['Azul', 'Vermelho']);
    expect(collect($props['garantias'])->firstWhere('nome', '12 meses ' . PCADAP_TAG)['duracao'])->toBe('12 meses');
});

it('UC-PCADAP-15 · variação em uso não sai; variação livre sai', function () {
    $user = pcadapUsuario($this->biz->id, ['variation.view', 'variation.delete']);
    $usada = pcadapVariacao($this->biz->id, 'Acabamento');
    $livre = pcadapVariacao($this->biz->id, 'Gramatura');
    pcadapUsaVariacao($this->biz->id, $usada);

    $recusa = pcadapLogin($this, $user)->delete("/variation-templates/{$usada}", [], ['X-Requested-With' => 'XMLHttpRequest']);
    $recusa->assertOk();
    expect($recusa->json('success'))->toBeFalse();
    expect(DB::table('variation_templates')->where('id', $usada)->exists())->toBeTrue();

    $ok = pcadapLogin($this, $user)->delete("/variation-templates/{$livre}", [], ['X-Requested-With' => 'XMLHttpRequest']);
    expect($ok->json('success'))->toBeTrue();
    expect(DB::table('variation_templates')->where('id', $livre)->exists())->toBeFalse();
});

it('UC-PCADAP-16 · permissões das abas novas: grupos por product.create, garantia nunca oferece excluir', function () {
    $props = pcadapProps($this, pcadapUsuario($this->biz->id, ['warranty.view', 'warranty.delete', 'unit.view']), 'can,grupos,variacoes');
    expect($props['can']['garantias']['view'])->toBeTrue();
    expect($props['can']['garantias']['delete'])->toBeFalse();
    expect($props['can']['grupos']['view'])->toBeFalse();
    expect($props['can']['variacoes']['view'])->toBeFalse();
    $this->assertEmpty($props['grupos'] ?? null, 'quem não tem product.create recebeu os grupos de preço');
    $this->assertEmpty($props['variacoes'] ?? null, 'quem não tem variation.view recebeu as variações');

    pcadapLogin($this, pcadapUsuario($this->biz->id, ['unit.view']))->get('/variation-templates')->assertForbidden();
});

// ── Thread 10 · PR-a · drawer de criar/editar (Unidades e Marcas) ──────────────────────────
// O corpo de cada requisição é o que o CadastroDrawer.tsx monta (JSON, X-Requested-With).

it('UC-PCADAP-17 · drawer de unidade grava o múltiplo pela mesma rota e a edição devolve o que leu', function () {
    $user = pcadapUsuario($this->biz->id, ['unit.view', 'unit.create', 'unit.update']);
    $un = pcadapUnidade($this->biz->id, 'Unidade base', 'Und');
    $ajax = ['X-Requested-With' => 'XMLHttpRequest'];
    $base = fn (string $simbolo) => DB::table('units')->where('business_id', $this->biz->id)->where('short_name', $simbolo)
        ->first(['id', 'base_unit_id', 'base_unit_multiplier']);

    // Caminho 1: o que o operador digita, como o drawer manda (pt-BR, vírgula decimal e ponto de milhar).
    foreach (['mei' => ['0,5', 0.5], 'cxd' => ['1.000', 1000.0]] as $simbolo => [$digitado, $esperado]) {
        pcadapLogin($this, $user)->postJson('/units', [
            'actual_name' => "Drawer {$simbolo} " . PCADAP_TAG, 'short_name' => $simbolo, 'allow_decimal' => 0,
            'define_base_unit' => '1', 'base_unit_id' => $un, 'base_unit_multiplier' => $digitado,
        ], $ajax)->assertOk()->assertJson(['success' => true]);
        expect((int) $base($simbolo)->base_unit_id)->toBe($un);
        expect((float) $base($simbolo)->base_unit_multiplier)->toBe($esperado);
    }

    // Caminho 2: o drawer de edição abre com o múltiplo da prop e salva sem mexer — o valor não anda.
    $linhas = collect(pcadapProps($this, $user, 'unidades')['unidades'] ?? []);
    foreach (['mei' => 0.5, 'cxd' => 1000.0] as $simbolo => $esperado) {
        $linha = $linhas->firstWhere('simbolo', $simbolo);
        expect($linha['base_id'])->toBe($un);
        pcadapLogin($this, $user)->putJson("/units/{$linha['id']}", [
            'actual_name' => $linha['nome'], 'short_name' => $simbolo, 'allow_decimal' => 0,
            'define_base_unit' => '1', 'base_unit_id' => $linha['base_id'], 'base_unit_multiplier' => $linha['multiplicador'],
        ], $ajax)->assertOk()->assertJson(['success' => true]);
        expect((float) $base($simbolo)->base_unit_multiplier)->toBe($esperado);
    }
    expect($linhas->firstWhere('simbolo', 'mei')['multiplicador'])->toBe('0,5');
    expect($linhas->firstWhere('simbolo', 'cxd')['multiplicador'])->toBe('1000');

    // Desligar o múltiplo no drawer de edição manda define_base_unit=0: aí a base sai.
    $cx = $base('cxd')->id;
    pcadapLogin($this, $user)->putJson("/units/{$cx}", [
        'actual_name' => 'Drawer cxd ' . PCADAP_TAG, 'short_name' => 'cxd', 'allow_decimal' => 0, 'define_base_unit' => '0',
    ], $ajax)->assertOk()->assertJson(['success' => true]);
    expect($base('cxd')->base_unit_id)->toBeNull();
});

it('UC-PCADAP-18 · unidade base de outro negócio, ou a própria, é recusada e nada é gravado [T0]', function () {
    $user = pcadapUsuario($this->biz->id, ['unit.view', 'unit.create', 'unit.update']);
    $alheia = pcadapUnidade($this->vizinho->id, 'Base do vizinho', 'Unv');
    $minha = pcadapUnidade($this->biz->id, 'Minha', 'mnh');
    $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

    pcadapLogin($this, $user)->postJson('/units', [
        'actual_name' => 'Intrusa ' . PCADAP_TAG, 'short_name' => 'itr', 'allow_decimal' => 0,
        'define_base_unit' => '1', 'base_unit_id' => $alheia, 'base_unit_multiplier' => '10',
    ], $ajax)->assertOk()->assertJson(['success' => false]);
    expect(DB::table('units')->where('short_name', 'itr')->where('business_id', $this->biz->id)->exists())->toBeFalse();

    foreach ([$alheia, $minha] as $baseId) {
        pcadapLogin($this, $user)->putJson("/units/{$minha}", [
            'actual_name' => 'Renomeada ' . PCADAP_TAG, 'short_name' => 'mnh', 'allow_decimal' => 0,
            'define_base_unit' => '1', 'base_unit_id' => $baseId, 'base_unit_multiplier' => '10',
        ], $ajax)->assertOk()->assertJson(['success' => false]);
        $gravada = DB::table('units')->where('id', $minha)->first(['actual_name', 'base_unit_id']);
        expect($gravada->base_unit_id)->toBeNull();
        expect($gravada->actual_name)->toBe('Minha ' . PCADAP_TAG);
    }
});

it('UC-PCADAP-19 · drawer de marca cria e edita no meu negócio; marca do vizinho não muda [T0]', function () {
    $user = pcadapUsuario($this->biz->id, ['brand.view', 'brand.create', 'brand.update']);
    $vizinha = pcadapMarca($this->vizinho->id, 'Marca vizinha');
    $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

    pcadapLogin($this, $user)->postJson('/brands', [
        'name' => 'Vinilcor ' . PCADAP_TAG, 'description' => 'Lonas e banners.', 'use_for_repair' => 0,
    ], $ajax)->assertOk()->assertJson(['success' => true]);
    $id = (int) DB::table('brands')->where('business_id', $this->biz->id)->where('name', 'Vinilcor ' . PCADAP_TAG)->value('id');
    expect($id)->toBeGreaterThan(0);

    pcadapLogin($this, $user)->putJson("/brands/{$id}", [
        'name' => 'Vinilcor Pro ' . PCADAP_TAG, 'description' => 'Lonas.', 'use_for_repair' => 0,
    ], $ajax)->assertOk()->assertJson(['success' => true]);
    expect(DB::table('brands')->where('id', $id)->value('name'))->toBe('Vinilcor Pro ' . PCADAP_TAG);

    pcadapLogin($this, $user)->putJson("/brands/{$vizinha}", ['name' => 'Tomada ' . PCADAP_TAG, 'description' => ''], $ajax)
        ->assertOk()->assertJson(['success' => false]);
    expect(DB::table('brands')->where('id', $vizinha)->value('name'))->toBe('Marca vizinha ' . PCADAP_TAG);

    $props = pcadapProps($this, $user, 'oficina,marcas', '?aba=marcas');
    expect($props['oficina'])->toBe(app(\App\Utils\ModuleUtil::class)->isModuleInstalled('Repair'));
    expect(collect($props['marcas'] ?? [])->firstWhere('id', $id))->toHaveKey('oficina');
});

// ── Thread 10 · PR-b · drawer de Categorias, Variações e Garantias ─────────────────────────

it('UC-PCADAP-20 · drawer de categoria: subcategoria só sob categoria principal do meu negócio [T0]', function () {
    $user = pcadapUsuario($this->biz->id, ['category.view', 'category.create', 'category.update']);
    $pai = pcadapCategoria($this->biz->id, 'Comunicação visual');
    $filha = pcadapCategoria($this->biz->id, 'Lonas', $pai);
    $alheia = pcadapCategoria($this->vizinho->id, 'Categoria vizinha');
    $ajax = ['X-Requested-With' => 'XMLHttpRequest'];
    $novaSob = fn (int $paiId, string $nome) => pcadapLogin($this, $user)->postJson('/taxonomies', [
        'name' => $nome . ' ' . PCADAP_TAG, 'short_code' => 'NV', 'description' => '', 'category_type' => 'product',
        'add_as_sub_cat' => 1, 'parent_id' => $paiId,
    ], $ajax)->assertOk();

    $novaSob($pai, 'Fachadas')->assertJson(['success' => true]);
    expect((int) DB::table('categories')->where('name', 'Fachadas ' . PCADAP_TAG)->value('parent_id'))->toBe($pai);

    // Pai de outro negócio e pai que já é subcategoria: recusados, nada gravado.
    $novaSob($alheia, 'Intrusa')->assertJson(['success' => false]);
    $novaSob($filha, 'Neta')->assertJson(['success' => false]);
    expect(DB::table('categories')->whereIn('name', ['Intrusa ' . PCADAP_TAG, 'Neta ' . PCADAP_TAG])->exists())->toBeFalse();

    // Edição: pai = ela mesma, ou categoria com filhas virando filha — recusadas, o vínculo fica.
    $outra = pcadapCategoria($this->biz->id, 'Impressos');
    foreach ([[$outra, $outra], [$pai, $outra]] as [$id, $novoPai]) {
        pcadapLogin($this, $user)->putJson("/taxonomies/{$id}", [
            'name' => 'Renomeada ' . PCADAP_TAG, 'short_code' => 'RN', 'description' => '', 'add_as_sub_cat' => 1, 'parent_id' => $novoPai,
        ], $ajax)->assertOk()->assertJson(['success' => false]);
        expect((int) DB::table('categories')->where('id', $id)->value('parent_id'))->toBe(0);
    }
    expect((int) DB::table('categories')->where('id', $filha)->value('parent_id'))->toBe($pai);
});

it('UC-PCADAP-21 · drawer de variação renomeia valor pelo id e acrescenta valor novo', function () {
    $user = pcadapUsuario($this->biz->id, ['variation.view', 'variation.create', 'variation.update']);
    $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

    pcadapLogin($this, $user)->postJson('/variation-templates', [
        'name' => 'Cor ' . PCADAP_TAG, 'variation_values' => ['Branco', 'Preto'],
    ], $ajax)->assertOk()->assertJson(['success' => true]);

    $linha = collect(pcadapProps($this, $user, 'variacoes', '?aba=variacoes')['variacoes'] ?? [])->firstWhere('nome', 'Cor ' . PCADAP_TAG);
    expect($linha['valores'])->toBe(['Branco', 'Preto']);
    expect($linha['valor_ids'])->toHaveCount(2);

    // O que o drawer de edição manda: valores existentes por id + o novo em `variation_values`.
    pcadapLogin($this, $user)->putJson("/variation-templates/{$linha['id']}", [
        'name' => 'Cor ' . PCADAP_TAG,
        'edit_variation_values' => [$linha['valor_ids'][0] => 'Branco gelo', $linha['valor_ids'][1] => 'Preto'],
        'variation_values' => ['Azul'],
    ], $ajax)->assertOk()->assertJson(['success' => true]);
    $valores = DB::table('variation_value_templates')->where('variation_template_id', $linha['id'])->orderBy('id')->pluck('name')->all();
    expect($valores)->toBe(['Branco gelo', 'Preto', 'Azul']);
});

it('UC-PCADAP-22 · drawer de garantia cria e edita; a lista devolve o prazo cru pro drawer; a do vizinho não muda [T0]', function () {
    $user = pcadapUsuario($this->biz->id, ['warranty.view', 'warranty.create', 'warranty.update']);
    $ajax = ['X-Requested-With' => 'XMLHttpRequest'];
    $vizinha = (int) DB::table('warranties')->insertGetId(['business_id' => $this->vizinho->id, 'name' => 'Vizinha ' . PCADAP_TAG,
        'duration' => 3, 'duration_type' => 'months', 'created_at' => now(), 'updated_at' => now()]);

    pcadapLogin($this, $user)->postJson('/warranties', [
        'name' => '12 meses ' . PCADAP_TAG, 'description' => 'Estrutura.', 'duration' => '12', 'duration_type' => 'months',
    ], $ajax)->assertOk()->assertJson(['success' => true]);
    $linha = collect(pcadapProps($this, $user, 'garantias', '?aba=garantias')['garantias'] ?? [])->firstWhere('nome', '12 meses ' . PCADAP_TAG);
    expect($linha['duracao_n'])->toBe('12');
    expect($linha['duracao_tipo'])->toBe('months');

    pcadapLogin($this, $user)->putJson("/warranties/{$linha['id']}", [
        'name' => '1 ano ' . PCADAP_TAG, 'description' => 'Estrutura.', 'duration' => '1', 'duration_type' => 'years',
    ], $ajax)->assertOk()->assertJson(['success' => true]);
    $gravada = DB::table('warranties')->where('id', $linha['id'])->first(['duration', 'duration_type']);
    expect((int) $gravada->duration)->toBe(1);
    expect($gravada->duration_type)->toBe('years');

    pcadapLogin($this, $user)->putJson("/warranties/{$vizinha}", ['name' => 'Tomada', 'duration' => '9', 'duration_type' => 'days'], $ajax)
        ->assertOk()->assertJson(['success' => false]);
    expect(DB::table('warranties')->where('id', $vizinha)->value('name'))->toBe('Vizinha ' . PCADAP_TAG);
});
