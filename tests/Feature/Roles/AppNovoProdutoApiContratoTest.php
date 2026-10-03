<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * API de Novo produto do app das lojas (tela 20) — POST /api/app/produtos e GET /produtos/opcoes.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §9.4. Decisão [W] 2026-10-02: SEM preço
 * (a variação nasce zerada, como na tela React). Categoria/unidade só do business (UC-PCAD-05).
 * NÃO derivado do controller.
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 da lane. Transação revertida.
 */

const APP_NPR_BIZ = 98;
const APP_NPR_OUTRO = 2;

function appNprUsuario(array $permissoes): User
{
    $user = User::factory()->create(['business_id' => APP_NPR_BIZ]);
    $papel = Role::create(['name' => 'AppNpr' . uniqid() . '#' . APP_NPR_BIZ, 'business_id' => APP_NPR_BIZ, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

function appNprUnidade(int $biz, int $criadoPor, string $curta): int
{
    return (int) DB::table('units')->insertGetId([
        'business_id' => $biz, 'actual_name' => 'Unidade ' . $curta, 'short_name' => $curta, 'allow_decimal' => 1,
        'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_NPR_BIZ, APP_NPR_OUTRO])->count() !== 2) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('cria produto simples SEM preço, com unidade, mínimo e fiscal, no business do token; código gerado', function () {
    $u = appNprUsuario(['product.create', 'access_all_locations']);
    $unid = appNprUnidade(APP_NPR_BIZ, (int) $u->id, 'm²');
    Passport::actingAs($u, [], 'api');

    $r = $this->postJson('/api/app/produtos', [
        'business_id' => APP_NPR_OUTRO,
        'nome' => 'Lona fosca 440g', 'codigo' => null, 'unidade_id' => $unid,
        'estoque' => ['controla' => true, 'minimo' => 12.5],
        'fiscal' => ['ncm' => '39219090', 'cfop_interno' => '5102'],
    ])->assertStatus(201);

    $id = (int) $r->json('id');
    $p = DB::table('products')->where('id', $id)->first();
    expect((int) $p->business_id)->toBe(APP_NPR_BIZ);
    expect($p->type)->toBe('single');
    expect((int) $p->unit_id)->toBe($unid);
    expect((int) $p->enable_stock)->toBe(1);
    expect((float) $p->alert_quantity)->toBe(12.5);
    expect($p->ncm)->toBe('39219090');
    expect($p->cfop_interno)->toBe('5102');
    expect(trim((string) $p->sku))->not->toBe('');
    expect($r->json('codigo'))->toBe($p->sku);

    // Sem preço (decisão [W]): uma variação, com os valores zerados.
    $v = DB::table('variations')->where('product_id', $id)->get();
    expect($v)->toHaveCount(1);
    expect((float) $v[0]->default_sell_price)->toBe(0.0);
    expect((float) $v[0]->sell_price_inc_tax)->toBe(0.0);
    expect((float) $v[0]->default_purchase_price)->toBe(0.0);
});

it('unidade ou categoria de OUTRO business responde 422 e não grava', function () {
    $u = appNprUsuario(['product.create']);
    $unidAlheia = appNprUnidade(APP_NPR_OUTRO, (int) $u->id, 'un');
    Passport::actingAs($u, [], 'api');
    $antes = DB::table('products')->where('business_id', APP_NPR_BIZ)->count();

    $r = $this->postJson('/api/app/produtos', ['nome' => 'X', 'unidade_id' => $unidAlheia])
        ->assertStatus(422)->assertJsonPath('erro', 'validacao');
    expect(array_keys($r->json('campos')))->toContain('unidade_id');
    expect(DB::table('products')->where('business_id', APP_NPR_BIZ)->count())->toBe($antes);
});

it('validação aninhada: NCM com 7 dígitos e mínimo negativo voltam por campo', function () {
    $u = appNprUsuario(['product.create']);
    $unid = appNprUnidade(APP_NPR_BIZ, (int) $u->id, 'un');
    Passport::actingAs($u, [], 'api');

    $r = $this->postJson('/api/app/produtos', [
        'nome' => 'X', 'unidade_id' => $unid,
        'estoque' => ['controla' => true, 'minimo' => -1], 'fiscal' => ['ncm' => '1234567'],
    ])->assertStatus(422);
    expect(array_keys($r->json('campos')))->toContain('estoque.minimo');
    expect(array_keys($r->json('campos')))->toContain('fiscal.ncm');
});

it('sem product.create: 403 no POST e nas opções; opções trazem só as unidades do business', function () {
    Passport::actingAs(appNprUsuario([]), [], 'api');
    $this->postJson('/api/app/produtos', ['nome' => 'X'])->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    $this->getJson('/api/app/produtos/opcoes')->assertStatus(403);

    $u = appNprUsuario(['product.create']);
    $minha = appNprUnidade(APP_NPR_BIZ, (int) $u->id, 'mil');
    $alheia = appNprUnidade(APP_NPR_OUTRO, (int) $u->id, 'cx');
    Passport::actingAs($u, [], 'api');
    $ids = collect($this->getJson('/api/app/produtos/opcoes')->assertOk()->json('unidades'))->pluck('id')->all();
    expect($ids)->toContain($minha);
    expect($ids)->not->toContain($alheia);
});
