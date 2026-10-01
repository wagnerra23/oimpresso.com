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
 * Contrato da tela Produto/Cadastros (`/units`, abas Unidades, Categorias e Marcas) — playbook Produto · thread 02.
 *
 * Os UCs vêm do contrato, não do código:
 *   resources/js/Pages/Produto/Cadastros/Index.casos.md (UC-PCADAP-01..11)
 *
 * ⛔ Tenant 98 (ADR 0358) contra o cliente fictício 99. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 *
 * @see app/Http/Controllers/UnitController.php  cadastros()
 * @see app/Http/Controllers/BrandController.php destroy()
 * @see app/Http/Controllers/TaxonomyController.php destroy()
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
