<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato da lista de devoluções (`GET /sell-return` → `SellReturnController@index`,
 * ramo Inertia → `SellReturn/Index`). Thread 03 de venda-menu, PR 1 de 2.
 *
 * UCs: resources/js/Pages/SellReturn/Index.casos.md (UC-SRIDX-01..07).
 *
 * Tenants: 98 (canônico de teste, ADR 0358) × 99 (adversário, `seededSupportClientTenant()`,
 * criado se faltar). Nunca biz=4.
 *
 * REGRA MESTRE valor: a tela só LÊ. Os números esperados são derivados à mão da fixture,
 * com datas isoladas para não somar dado de outro teste:
 *
 *   tenant 98 (venda de origem V0, sem linhas)
 *     D1 final_total 100.00 · payment_status paid  · mês corrente · criada pelo user A
 *     D2 final_total  40.50 · payment_status due   · mês corrente · criada pelo user B
 *     D3 final_total  25.00 · payment_status partial· 2 meses atrás · criada pelo user A
 *     DR draft 999.00 (NÃO entra: status != final)
 *   tenant 99
 *     DX final_total 70000.00 · due · mês corrente (NUNCA aparece no 98)
 *
 *   ⇒ tenant 98, quem vê todas: linhas 3 · com_saldo 2 (D2, D3) · no_mes 2 · valor_mes 140.50
 *
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

/** Versão Inertia igual à do servidor — evita o 409 antes de o controller rodar. */
function sridxVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Headers que o cliente Inertia manda de verdade (X-Inertia E X-Requested-With). */
function sridxHeaders(array $extra = []): array
{
    return array_merge([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => sridxVersao(),
        'X-Requested-With' => 'XMLHttpRequest',
    ], $extra);
}

/** Página inicial (sem as props deferidas), com pré-condição anti-vácuo. */
function sridxPagina(object $test): array
{
    $res = $test->withHeaders(sridxHeaders())->get('/sell-return');
    $res->assertStatus(200);
    $page = json_decode($res->getContent(), true);

    expect($page['component'] ?? null)->toBe('SellReturn/Index');

    return $page;
}

/** Partial reload das props deferidas `kpis` + `devolucoes`. */
function sridxDeferidas(object $test): array
{
    $res = $test->withHeaders(sridxHeaders([
        'X-Inertia-Partial-Data' => 'kpis,devolucoes',
        'X-Inertia-Partial-Component' => 'SellReturn/Index',
    ]))->get('/sell-return');
    $res->assertStatus(200);
    $page = json_decode($res->getContent(), true);

    expect($page['component'] ?? null)->toBe('SellReturn/Index');
    expect(array_key_exists('kpis', $page['props']))->toBeTrue();
    expect(array_key_exists('devolucoes', $page['props']))->toBeTrue();

    return $page['props'];
}

function sridxTransacao(array $campos): int
{
    return DB::table('transactions')->insertGetId(array_merge([
        'status' => 'final',
        'payment_status' => 'paid',
        'invoice_no' => 'SRX-' . uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $campos));
}

function sridxUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SRX Devolucao',
        'username' => 'srx_' . uniqid(),
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

function sridxLogin(object $test, User $user): void
{
    $test->actingAs($user);
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ]);
}

/** Linha da lista pelo id da devolução. */
function sridxLinha(array $linhas, int $id): ?array
{
    foreach ($linhas as $l) {
        if ((int) ($l['id'] ?? 0) === $id) {
            return $l;
        }
    }

    return null;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['transactions', 'transaction_payments', 'business_locations', 'business', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id; // cria o 99 se faltar

    // PRÉ-CONDIÇÃO: os dois papéis são empresas DISTINTAS (senão o [T0] é tautológico).
    expect($this->outroBizId)->not->toBe($this->bizId);

    $loc = EstoqueFixture::locationId($this->bizId);
    $locOutro = EstoqueFixture::locationId($this->outroBizId);

    $this->userA = sridxUsuario($this->bizId, ['access_sell_return', 'access_all_locations']);
    $this->userB = sridxUsuario($this->bizId, ['access_own_sell_return', 'access_all_locations']);

    $mes = now()->startOfMonth()->addDays(1)->format('Y-m-d') . ' 10:00:00';
    $antigo = now()->startOfMonth()->subMonths(2)->addDays(3)->format('Y-m-d') . ' 10:00:00';

    $base98 = ['business_id' => $this->bizId, 'location_id' => $loc];

    $this->venda = sridxTransacao($base98 + [
        'type' => 'sell', 'created_by' => $this->userA->id, 'transaction_date' => $antigo,
        'invoice_no' => 'SRX-VENDA-' . uniqid(), 'final_total' => 500.00, 'total_before_tax' => 500.00,
    ]);

    $dev = fn (array $c) => sridxTransacao($base98 + [
        'type' => 'sell_return', 'return_parent_id' => $this->venda,
    ] + $c);

    $this->d1 = $dev(['created_by' => $this->userA->id, 'transaction_date' => $mes,
        'final_total' => 100.00, 'total_before_tax' => 100.00, 'payment_status' => 'paid']);
    $this->d2 = $dev(['created_by' => $this->userB->id, 'transaction_date' => $mes,
        'final_total' => 40.50, 'total_before_tax' => 40.50, 'payment_status' => 'due']);
    $this->d3 = $dev(['created_by' => $this->userA->id, 'transaction_date' => $antigo,
        'final_total' => 25.00, 'total_before_tax' => 25.00, 'payment_status' => 'partial']);
    $this->dr = $dev(['created_by' => $this->userA->id, 'transaction_date' => $mes, 'status' => 'draft',
        'final_total' => 999.00, 'total_before_tax' => 999.00, 'payment_status' => 'due']);

    DB::table('transaction_payments')->insert([
        'transaction_id' => $this->d3, 'business_id' => $this->bizId, 'amount' => 10.00,
        'method' => 'cash', 'is_return' => 0, 'paid_on' => now(), 'created_by' => $this->userA->id,
        'payment_ref_no' => 'SRX-' . uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $vendaOutro = sridxTransacao(['business_id' => $this->outroBizId, 'location_id' => $locOutro,
        'type' => 'sell', 'created_by' => $this->userA->id, 'transaction_date' => $mes,
        'final_total' => 70000.00, 'total_before_tax' => 70000.00]);
    $this->dx = sridxTransacao(['business_id' => $this->outroBizId, 'location_id' => $locOutro,
        'type' => 'sell_return', 'return_parent_id' => $vendaOutro, 'created_by' => $this->userA->id,
        'transaction_date' => $mes, 'final_total' => 70000.00, 'total_before_tax' => 70000.00,
        'payment_status' => 'due']);

    sridxLogin($this, $this->userA);
});

it('UC-SRIDX-01 a visita Inertia recebe a página React, não o JSON do DataTable', function () {
    $page = sridxPagina($this);

    // As props caras são deferidas: não vêm na carga inicial, vêm no partial reload.
    expect(array_key_exists('devolucoes', $page['props']))->toBeFalse();
    expect($page['props']['permissions']['ver_todas'])->toBeTrue();

    $props = sridxDeferidas($this);
    expect(count($props['devolucoes']['linhas']))->toBe(3);
    expect((int) $props['devolucoes']['total'])->toBe(3);
});

it('UC-SRIDX-02 [T0] devolução de outro business não entra na lista nem nos números', function () {
    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a devolução alheia existe, no mesmo mês.
    expect(DB::table('transactions')->where('id', $this->dx)->where('business_id', $this->outroBizId)->exists())->toBeTrue();

    $props = sridxDeferidas($this);

    expect(sridxLinha($props['devolucoes']['linhas'], $this->dx))->toBeNull();
    expect(round((float) $props['kpis']['valor_mes'], 2))->toBe(140.5);
    expect((int) $props['kpis']['com_saldo'])->toBe(2);
});

it('UC-SRIDX-03 sem permissão de devolução a tela devolve 403', function () {
    $semPermissao = sridxUsuario($this->bizId, ['access_all_locations']);
    sridxLogin($this, $semPermissao);

    $this->withHeaders(sridxHeaders())->get('/sell-return')->assertStatus(403);
});

it('UC-SRIDX-04 quem só tem access_own_sell_return vê apenas as devoluções que criou', function () {
    sridxLogin($this, $this->userB);

    $page = sridxPagina($this);
    expect($page['props']['permissions']['ver_todas'])->toBeFalse();
    expect($page['props']['permissions']['ver_proprias'])->toBeTrue();

    $props = sridxDeferidas($this);
    $ids = array_map(fn ($l) => (int) $l['id'], $props['devolucoes']['linhas']);

    expect($ids)->toBe([$this->d2]);
    expect((int) $props['kpis']['no_mes'])->toBe(1);
    expect(round((float) $props['kpis']['valor_mes'], 2))->toBe(40.5);
});

it('UC-SRIDX-05 [V0] os três números do topo são leitura do que está gravado', function () {
    $kpis = sridxDeferidas($this)['kpis'];

    // com saldo = payment_status != paid → D2 (due) + D3 (partial); o rascunho DR fica fora.
    expect((int) $kpis['com_saldo'])->toBe(2);
    // no mês = D1 + D2; D3 é de 2 meses atrás, DR é rascunho.
    expect((int) $kpis['no_mes'])->toBe(2);
    expect(round((float) $kpis['valor_mes'], 2))->toBe(140.5);
});

it('UC-SRIDX-06 cada linha traz a venda de origem, o valor e o que já foi pago', function () {
    $linhas = sridxDeferidas($this)['devolucoes']['linhas'];

    $d3 = sridxLinha($linhas, $this->d3);
    expect($d3)->not->toBeNull();
    expect((int) $d3['venda_id'])->toBe($this->venda);
    expect($d3['venda_numero'])->toStartWith('SRX-VENDA-');
    expect(round((float) $d3['total'], 2))->toBe(25.0);
    expect(round((float) $d3['pago'], 2))->toBe(10.0);
    expect($d3['situacao_pagamento'])->toBe('partial');

    // O rascunho não é devolução registrada.
    expect(sridxLinha($linhas, $this->dr))->toBeNull();

    // Mais recente primeiro: D2 (mês corrente, id maior) antes de D3 (2 meses atrás).
    $ordem = array_map(fn ($l) => (int) $l['id'], $linhas);
    expect(array_search($this->d2, $ordem, true))->toBeLessThan(array_search($this->d3, $ordem, true));
});

it('UC-SRIDX-07 o DataTable legado continua respondendo à chamada ajax sem X-Inertia', function () {
    $res = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/sell-return');
    $res->assertStatus(200);

    $json = json_decode($res->getContent(), true);
    expect(is_array($json))->toBeTrue();
    expect(array_key_exists('component', $json))->toBeFalse();
    expect(array_key_exists('data', $json))->toBeTrue();
    expect(count($json['data']))->toBe(3);
});
