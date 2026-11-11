<?php

declare(strict_types=1);

// @covers-us US-NFE-MANUAL — emitir NF-e/NFC-e exige `nfebrasil.emit.manage` (lote 3.1, N5-03, decisão [W] B-02).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Exceptions\NcmPadraoAusenteException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\EstoqueFixture;

/**
 * Contrato: quem não tem a permissão recebe 403 e nenhuma nota nasce; quem tem chega ao
 * controller. A prova de que a requisição chegou ao controller não é o status sozinho (lápide
 * §5 2026-09-27): com a permissão, a venda do 98 sem NCM padrão devolve o 422 do bloqueio de
 * NCM (vem do NfeService) e a venda do 99 devolve `transaction_not_found` (vem do guard
 * cross-tenant do controller). Nada vai à SEFAZ: o bloqueio de NCM acontece antes.
 *
 * MySQL · tenants 98 × 99 (ADR 0358). Fora do MySQL pula; leia assertions (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_business_configs') || ! Schema::hasColumn('business', 'ncm_padrao')) {
        $this->markTestSkipped('MySQL-only: precisa de nfe_business_configs e business.ncm_padrao reais.');
    }
    $this->biz = $this->seededTenant()->id;
    $this->outra = $this->seededSupportClientTenant()->id;

    // Sem NCM padrão: o caminho com permissão para no bloqueio de NCM (422), antes da SEFAZ.
    DB::table('nfe_business_configs')->updateOrInsert(
        ['business_id' => $this->biz],
        ['tributacao_default' => json_encode(['cfop' => '5102']), 'updated_at' => now()],
    );
    DB::table('business')->where('id', $this->biz)->update(['ncm_padrao' => '']);

    $this->venda = fn (int $biz): int => (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => EstoqueFixture::locationId($biz), 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'paid', 'final_total' => 10.00, 'total_before_tax' => 10.00,
        'invoice_no' => 'PERM31-' . uniqid(), 'transaction_date' => now(), 'created_by' => EstoqueFixture::userId($biz),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->emitir = fn (\App\User $u, int $tx) => $this->actingAs($u)
        ->withSession(['business.id' => $this->biz, 'user.business_id' => $this->biz])
        ->postJson("/nfe-brasil/transactions/{$tx}/emitir", ['modelo' => '65']);
});

it('3.1 · sem nfebrasil.emit.manage a emissão devolve 403 e nenhuma nota nasce', function () {
    $tx = ($this->venda)($this->biz);
    // Quem só cria venda e não recebeu a permissão (papel criado depois do backfill) não emite.
    $u = $this->usuarioComPermissoes(['sell.create']);

    ($this->emitir)($u, $tx)->assertStatus(403);

    expect(DB::table('nfe_emissoes')->where('business_id', $this->biz)->where('transaction_id', $tx)->count())->toBe(0);
});

it('3.1 · com a permissão a requisição chega ao NfeService (âncora: 422 do bloqueio de NCM)', function () {
    $tx = ($this->venda)($this->biz);
    $u = $this->usuarioComPermissoes(['nfebrasil.emit.manage']);

    ($this->emitir)($u, $tx)
        ->assertStatus(422)
        ->assertJsonPath('error', NcmPadraoAusenteException::ERRO);
});

it('3.1 · com a permissão, a venda do 99 pedida pelo 98 continua 404 (o isolamento chega ao controller)', function () {
    $alheia = ($this->venda)($this->outra);
    $u = $this->usuarioComPermissoes(['nfebrasil.emit.manage']);

    ($this->emitir)($u, $alheia)
        ->assertStatus(404)
        ->assertJsonPath('error', 'transaction_not_found');

    expect(DB::table('nfe_emissoes')->where('transaction_id', $alheia)->count())->toBe(0);
});

it('B-02 · a migration dá a permissão a quem cria venda, em cada empresa, e só a eles (idempotente)', function () {
    foreach (['sell.create', 'direct_sell.access', 'sell.view'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel = function (int $biz, array $perms): Role {
        $r = Role::create(['name' => 'P31' . uniqid() . '#' . $biz, 'business_id' => $biz, 'guard_name' => 'web']);
        $r->syncPermissions($perms);

        return $r;
    };
    $pdv = $papel($this->biz, ['sell.create']);
    $direta = $papel($this->biz, ['direct_sell.access']);
    $soVe = $papel($this->biz, ['sell.view']);
    $doOutro = $papel($this->outra, ['sell.create']);

    $direto = \App\User::factory()->create(['business_id' => $this->biz]);
    $direto->givePermissionTo('direct_sell.access');

    $migration = require base_path('Modules/NfeBrasil/Database/Migrations/2026_11_10_000001_grant_nfebrasil_emit_manage_to_sellers.php');
    $migration->up();
    $migration->up(); // idempotente: a segunda passada não duplica nem quebra

    $temEmitir = fn (int $roleId): bool => DB::table('role_has_permissions as rhp')
        ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
        ->where('rhp.role_id', $roleId)->where('p.name', 'nfebrasil.emit.manage')->exists();

    expect($temEmitir($pdv->id))->toBeTrue()
        ->and($temEmitir($direta->id))->toBeTrue()
        ->and($temEmitir($doOutro->id))->toBeTrue()
        ->and($temEmitir($soVe->id))->toBeFalse();

    // Cada papel recebe dentro da própria empresa: o conjunto que ganhou é exatamente o que já criava venda.
    $emitId = DB::table('permissions')->where('name', 'nfebrasil.emit.manage')->value('id');
    $origens = DB::table('permissions')->whereIn('name', ['sell.create', 'direct_sell.access'])->pluck('id');
    $criam = DB::table('role_has_permissions')->whereIn('permission_id', $origens)->distinct()->pluck('role_id')->sort()->values()->all();
    $emitem = DB::table('role_has_permissions')->where('permission_id', $emitId)->pluck('role_id')->sort()->values()->all();
    expect(array_values(array_diff($criam, $emitem)))->toBe([]);
    expect(DB::table('role_has_permissions')->where('permission_id', $emitId)->where('role_id', $pdv->id)->count())->toBe(1);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect(\App\User::findOrFail($direto->id)->can('nfebrasil.emit.manage'))->toBeTrue();
});
