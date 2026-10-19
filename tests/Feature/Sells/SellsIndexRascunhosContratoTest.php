<?php

// @covers-us US-SELL-060

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Pílula "Rascunhos" da consulta de vendas (`GET /sells-list-json?status=draft` →
 * `SellController@inertiaList`). Pedido da ROTA LIVRE, decisão [W] 2026-10-19: o rascunho
 * mora na consulta de vendas, e os totais de faturado continuam só com venda final.
 *
 * UCs: resources/js/Pages/Sells/Index.casos.md (UC-SIDX-04, UC-SIDX-05). Fontes: aceite da
 * US-SELL-060 (escopo business_id, só status draft, cotação é tela própria) + CU-SELL-08 do
 * SDD-tela-venda-v1.0 + REGRA MESTRE valor/estoque (o totalizador é valor na tela).
 *
 * Fixture identificada pelo prefixo no invoice_no — a lane MySQL persiste dados alheios no
 * tenant 98, então só as linhas com o prefixo contam; o contador e o totalizador são medidos
 * por DELTA. Tenants 98 × 99 (ADR 0358). Nunca biz=4. Não roda local (CT 100 / CI).
 */
uses(DatabaseTransactions::class);

function rscUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'RSC Rascunho',
        'username' => 'rsc_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function rscLogin(object $test, User $user): void
{
    $test->actingAs($user);
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);
}

function rscTx(object $test, int $bizId, int $locationId, int $userId, string $sufixo, string $status, ?string $subStatus, float $total = 10): int
{
    return DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => $locationId,
        'created_by' => $userId,
        'type' => 'sell',
        'status' => $status,
        'sub_status' => $subStatus,
        'is_direct_sale' => 1,
        'payment_status' => 'due',
        'invoice_no' => $test->prefixo . $sufixo,
        'transaction_date' => now(),
        'total_before_tax' => $total,
        'final_total' => $total,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** JSON da consulta de vendas como o front pede (per_page alto: a fixture cabe numa página). */
function rscJson(object $test, string $query = '')
{
    return $test->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells-list-json?per_page=200&limit=200&q=' . urlencode($test->prefixo) . $query);
}

/** invoice_no (só os desta fixture) devolvidos pela consulta. */
function rscNumeros(array $json, string $prefixo): array
{
    $nos = [];
    foreach ($json['data'] as $row) {
        $no = (string) $row['invoice_no'];
        if (str_starts_with($no, $prefixo)) {
            $nos[] = substr($no, strlen($prefixo));
        }
    }
    sort($nos);

    return $nos;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['transactions', 'business', 'business_locations', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->prefixo = 'RSC' . strtoupper(substr(uniqid(), -6)) . '-';
    $this->loc = EstoqueFixture::locationId($this->bizId);
    $this->locOutro = EstoqueFixture::locationId($this->outroBizId);

    $this->vendedorA = rscUsuario($this->bizId, ['direct_sell.view', 'draft.view_own', 'access_all_locations']);
    $this->vendedorB = rscUsuario($this->bizId, ['direct_sell.view', 'draft.view_own', 'access_all_locations']);
    $this->estranho = rscUsuario($this->outroBizId, ['direct_sell.view', 'draft.view_all', 'access_all_locations']);

    rscTx($this, $this->bizId, $this->loc, $this->vendedorA->id, 'rA', 'draft', null, 700);
    rscTx($this, $this->bizId, $this->loc, $this->vendedorB->id, 'rB', 'draft', null, 300);
    rscTx($this, $this->bizId, $this->loc, $this->vendedorA->id, 'qA', 'draft', 'quotation');
    rscTx($this, $this->bizId, $this->loc, $this->vendedorA->id, 'fA', 'final', null, 150);
    rscTx($this, $this->outroBizId, $this->locOutro, $this->estranho->id, 'rX', 'draft', null);

    // Anti-vácuo: as 5 linhas existem com o prefixo — o que some da lista some pelo filtro.
    expect(DB::table('transactions')->where('invoice_no', 'like', $this->prefixo . '%')->count())->toBe(5);
});

it('UC-SIDX-04 [T0] a pílula Rascunhos traz só rascunho comum do business — sem cotação, sem venda final, sem outra empresa — e respeita draft.view_own', function () {
    rscLogin($this, rscUsuario($this->bizId, ['direct_sell.view', 'draft.view_all', 'access_all_locations']));

    $json = rscJson($this, '&status=draft')->assertOk()->json();
    expect(rscNumeros($json, $this->prefixo))->toBe(['rA', 'rB']);
    foreach ($json['data'] as $row) {
        expect($row['status'])->toBe('draft');
    }

    // draft.view_own: cada vendedor vê só o dele (controle positivo com o outro vendedor).
    rscLogin($this, $this->vendedorA);
    expect(rscNumeros(rscJson($this, '&status=draft')->assertOk()->json(), $this->prefixo))->toBe(['rA']);
    rscLogin($this, $this->vendedorB);
    expect(rscNumeros(rscJson($this, '&status=draft')->assertOk()->json(), $this->prefixo))->toBe(['rB']);

    // Sem permissão de rascunho: a pílula é negada e o contador vem nulo (a pílula some).
    rscLogin($this, rscUsuario($this->bizId, ['direct_sell.view', 'access_all_locations']));
    rscJson($this, '&status=draft')->assertStatus(403);
    expect(rscJson($this)->assertOk()->json('drafts_count'))->toBeNull();
});

it('UC-SIDX-05 [V0] [T0] rascunho não entra na lista nem nos totais das vendas finais, e o contador da pílula sobe só com rascunho comum do business', function () {
    rscLogin($this, rscUsuario($this->bizId, ['direct_sell.view', 'draft.view_all', 'access_all_locations']));

    // Consulta padrão (sem status): só a venda final da fixture, e o totalizador é dela.
    $json = rscJson($this)->assertOk()->json();
    expect(rscNumeros($json, $this->prefixo))->toBe(['fA']);
    expect($json['totals']['count'])->toBe(1);
    expect((float) $json['totals']['sum_final_total'])->toBe(150.0);

    // Contador da pílula: medido por delta (a lane persiste rascunhos alheios no tenant 98).
    $antes = (int) $json['drafts_count'];
    expect($antes)->toBeGreaterThanOrEqual(2);

    rscTx($this, $this->bizId, $this->loc, $this->vendedorA->id, 'qB', 'draft', 'quotation');
    rscTx($this, $this->outroBizId, $this->locOutro, $this->estranho->id, 'rY', 'draft', null);
    expect((int) rscJson($this)->json('drafts_count'))->toBe($antes);

    rscTx($this, $this->bizId, $this->loc, $this->vendedorB->id, 'rC', 'draft', null, 999);
    $depois = rscJson($this)->assertOk()->json();
    expect((int) $depois['drafts_count'])->toBe($antes + 1);
    // O rascunho novo (999) não mexe no total das vendas finais.
    expect((float) $depois['totals']['sum_final_total'])->toBe(150.0);
});
