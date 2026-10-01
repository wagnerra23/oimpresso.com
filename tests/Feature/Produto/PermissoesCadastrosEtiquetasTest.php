<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\EstoqueFixture;

/**
 * Playbook Produto · thread 01 (achado A-P1) — Variações, Garantias e Etiquetas passam a exigir
 * permissão própria (D1 [W] 2026-10-01: `variation.*` · `warranty.*` · `print_labels.access`).
 *
 * ÂNCORA (contrato, não implementação):
 *   - `00-INDICE.md` §1 A-P1 + ficha `03-permissoes.md`: "sem permissão → 403; com → 200".
 *   - `_DECISOES-W-2026-10-01.md` D1/D2: nomes das permissões e o perfil alvo.
 *   - ADR 0093 (Tier 0): nenhuma rota destas pode ler/escrever dado de outro negócio.
 *   - Instrução [W] desta thread: quem acessa hoje não perde acesso no deploy (backfill).
 *
 * ⛔ Tenant 98 (ADR 0358) contra o cliente fictício 99. NUNCA biz=4.
 * ⚠️ `/labels/preview` termina com `exit` no caminho feliz — só o exercitamos nos caminhos que
 *    abortam ANTES (403 e 404). O 200 de etiqueta é provado por `/labels/add-product-row`.
 */
uses(DatabaseTransactions::class);

function permT01Usuario(int $bizId, array $permissoes = []): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'T01 Perm',
        'username' => 'perm_t01_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function permT01Login(object $test, User $user): void
{
    $test->actingAs($user);
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);
}

function permT01Ajax(object $test): object
{
    return $test->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = $this->seededTenant();
    $this->bizAlheio = $this->seededSupportClientTenant();
    foreach (['variation.view', 'variation.create', 'variation.update', 'variation.delete',
        'warranty.view', 'warranty.create', 'warranty.update', 'warranty.delete',
        'print_labels.access'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
});

// ── 403 sem permissão · 200 com ──────────────────────────────────────────────

it('variações: sem variation.view → 403; com → 200', function () {
    permT01Login($this, permT01Usuario($this->biz->id));
    expect(permT01Ajax($this)->get('/variation-templates')->getStatusCode())->toBe(403);

    permT01Login($this, permT01Usuario($this->biz->id, ['variation.view']));
    expect(permT01Ajax($this)->get('/variation-templates')->getStatusCode())->toBe(200);
});

it('variações: excluir exige variation.delete mesmo com as demais', function () {
    $id = DB::table('variation_templates')->insertGetId([
        'name' => 'T01 propria', 'business_id' => $this->biz->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    permT01Login($this, permT01Usuario($this->biz->id, ['variation.view', 'variation.create', 'variation.update']));

    expect(permT01Ajax($this)->delete("/variation-templates/{$id}")->getStatusCode())->toBe(403);
    expect(DB::table('variation_templates')->where('id', $id)->exists())->toBeTrue();
});

it('garantias: sem warranty.view → 403; com → 200', function () {
    permT01Login($this, permT01Usuario($this->biz->id));
    expect(permT01Ajax($this)->get('/warranties')->getStatusCode())->toBe(403);

    permT01Login($this, permT01Usuario($this->biz->id, ['warranty.view']));
    expect(permT01Ajax($this)->get('/warranties')->getStatusCode())->toBe(200);
});

it('etiquetas: sem print_labels.access → 403 nas três rotas; com → 200', function () {
    permT01Login($this, permT01Usuario($this->biz->id));
    expect(permT01Ajax($this)->get('/labels/show')->getStatusCode())->toBe(403);
    expect(permT01Ajax($this)->get('/labels/add-product-row')->getStatusCode())->toBe(403);
    expect(permT01Ajax($this)->get('/labels/preview')->getStatusCode())->toBe(403);

    permT01Login($this, permT01Usuario($this->biz->id, ['print_labels.access']));
    expect(permT01Ajax($this)->get('/labels/add-product-row')->getStatusCode())->toBe(200);
});

// ── cross-tenant 98 × 99 ─────────────────────────────────────────────────────

it('cross-tenant: variação do negócio 99 não é excluída pelo 98', function () {
    $alheia = DB::table('variation_templates')->insertGetId([
        'name' => 'T01 alheia 99', 'business_id' => $this->bizAlheio->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    permT01Login($this, permT01Usuario($this->biz->id, ['variation.delete']));

    permT01Ajax($this)->delete("/variation-templates/{$alheia}");

    expect(DB::table('variation_templates')->where('id', $alheia)->exists())->toBeTrue();
});

it('cross-tenant: valor de variação do 99 não é renomeado via template do 98', function () {
    $propria = DB::table('variation_templates')->insertGetId([
        'name' => 'T01 propria', 'business_id' => $this->biz->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $alheia = DB::table('variation_templates')->insertGetId([
        'name' => 'T01 alheia', 'business_id' => $this->bizAlheio->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $valorAlheio = DB::table('variation_value_templates')->insertGetId([
        'name' => 'AZUL-99', 'variation_template_id' => $alheia,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    permT01Login($this, permT01Usuario($this->biz->id, ['variation.update']));

    $resp = permT01Ajax($this)->put("/variation-templates/{$propria}", [
        'name' => 'T01 propria',
        'edit_variation_values' => [$valorAlheio => 'INVADIDO'],
    ]);

    // anti-vácuo: a requisição chegou ao update (não parou no gate de permissão)
    expect($resp->getStatusCode())->toBe(200);
    expect(DB::table('variation_value_templates')->where('id', $valorAlheio)->value('name'))->toBe('AZUL-99');
});

it('cross-tenant: garantia do 99 não é alterada pelo 98', function () {
    $alheia = DB::table('warranties')->insertGetId([
        'name' => 'T01 garantia 99', 'business_id' => $this->bizAlheio->id,
        'duration' => 3, 'duration_type' => 'months',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    permT01Login($this, permT01Usuario($this->biz->id, ['warranty.update']));

    permT01Ajax($this)->put("/warranties/{$alheia}", [
        'name' => 'INVADIDA', 'duration' => 1, 'duration_type' => 'days',
    ]);

    expect(DB::table('warranties')->where('id', $alheia)->value('name'))->toBe('T01 garantia 99');
});

it('cross-tenant: configuração de etiqueta do 99 → 404 no preview do 98', function () {
    $alheia = DB::table('barcodes')->insertGetId([
        'name' => 'T01 etiqueta 99', 'business_id' => $this->bizAlheio->id,
        'width' => 2, 'height' => 1, 'stickers_in_one_row' => 1, 'stickers_in_one_sheet' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    permT01Login($this, permT01Usuario($this->biz->id, ['print_labels.access']));

    $resp = permT01Ajax($this)->get('/labels/preview?barcode_setting=' . $alheia);

    expect($resp->getStatusCode())->toBe(404);
});

// ── backfill: ninguém perde acesso no deploy ─────────────────────────────────

it('migration: papel com product.create herda variation.* e warranty.*; todo papel herda print_labels.access', function () {
    Permission::findOrCreate('product.create', 'web');
    $comProduto = Role::create(['name' => 'T01Cadastro#' . $this->biz->id, 'business_id' => $this->biz->id, 'guard_name' => 'web']);
    $comProduto->givePermissionTo('product.create');
    $vazio = Role::create(['name' => 'T01Vazio#' . $this->biz->id, 'business_id' => $this->biz->id, 'guard_name' => 'web']);

    $migration = require database_path('migrations/2026_10_01_120000_add_variation_warranty_print_labels_permissions.php');
    $migration->up();
    $migration->up(); // idempotente
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $comProduto = $comProduto->fresh();
    $vazio = $vazio->fresh();
    foreach (['variation.view', 'variation.create', 'variation.update', 'variation.delete',
        'warranty.view', 'warranty.create', 'warranty.update', 'warranty.delete', 'print_labels.access'] as $p) {
        expect($comProduto->hasPermissionTo($p))->toBeTrue();
    }
    expect($vazio->hasPermissionTo('print_labels.access'))->toBeTrue();
    expect($vazio->hasPermissionTo('variation.view'))->toBeFalse();
    expect($vazio->hasPermissionTo('warranty.view'))->toBeFalse();
});
