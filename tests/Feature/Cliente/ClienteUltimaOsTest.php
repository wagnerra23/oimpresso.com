<?php

declare(strict_types=1);
// Cobre UC-CIDX-05 (Index.casos.md) - G-2 rastreabilidade caso-teste.

/**
 * Coluna "Última OS" da lista de clientes (thread cliente/02, decisão [W] 2026-10-14): a prop
 * `customers` traz, por contato, a venda mais recente (type=sell, maior transaction_date) com id e
 * nº — e só da empresa da sessão (Tier 0, ADR 0093).
 *
 * Discriminante por construção: duas vendas com datas diferentes (a mais nova tem o MAIOR id e a
 * MENOR data não serve de atalho) e uma venda ainda mais nova do mesmo contato em OUTRA empresa.
 * Um MAX(id), um MAX sem escopo ou um "primeira venda" saem com outro nº e o assert cai.
 *
 * Carga parcial com os headers do @inertiajs/core (§5 2026-09-08). Tenant fictício 98 (ADR 0358).
 */

use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

const CUOS_BIZ = 98;

function cuosPedir($test)
{
    // A versão vem do próprio middleware (como no RedirectLegacyContactsTest): o md5 do manifest
    // não bate com ela no CI, e aí a resposta sai HTML 200 em vez do JSON do Inertia.
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $versao,
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Cliente/Index',
        'X-Inertia-Partial-Data' => 'customers',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get('/cliente?type=customer&q=' . urlencode($test->marcador));
}

function cuosVenda(int $biz, int $contato, string $nf, string $data, int $userId): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
        'contact_id' => $contato, 'invoice_no' => $nf, 'transaction_date' => $data,
        'final_total' => 10.00, 'created_by' => $userId, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', CUOS_BIZ)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();

    $user = User::factory()->create(['business_id' => CUOS_BIZ]);
    $papel = Role::create(['name' => 'CuosTeste' . uniqid() . '#' . CUOS_BIZ, 'business_id' => CUOS_BIZ, 'guard_name' => 'web']);
    Permission::findOrCreate('customer.view', 'web');
    $papel->syncPermissions(['customer.view']);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user = User::findOrFail($user->id);

    $this->marcador = 'CUOS Cliente ' . uniqid();
    $this->contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => CUOS_BIZ, 'type' => 'customer', 'name' => $this->marcador,
        'contact_id' => 'CUOS' . random_int(1000, 9999), 'mobile' => '00000000000',
        'created_by' => $this->user->id, 'is_customer' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function cuosLinha($test): ?array
{
    $test->actingAs($test->user);
    session(['user.business_id' => CUOS_BIZ, 'business.id' => CUOS_BIZ]);
    $r = cuosPedir($test);
    $r->assertOk();
    $rows = collect($r->json('props.customers.data'));

    return $rows->firstWhere('id', $test->contato);
}

it('UC-CIDX-05: last_os é a venda de MAIOR data do contato, não a de maior id', function () {
    $nova = cuosVenda(CUOS_BIZ, $this->contato, 'CUOS-NOVA', '2026-09-30 10:00:00', $this->user->id);
    cuosVenda(CUOS_BIZ, $this->contato, 'CUOS-VELHA', '2026-01-05 10:00:00', $this->user->id); // id maior, data menor

    $linha = cuosLinha($this);

    expect($linha)->not->toBeNull();
    expect($linha['last_os'])->toBe(['id' => $nova, 'invoice_no' => 'CUOS-NOVA']);
});

it('UC-CIDX-05: venda mais nova do mesmo contato em OUTRA empresa não aparece (Tier 0)', function () {
    $outra = (int) DB::table('business')->where('id', '!=', CUOS_BIZ)->value('id');
    if ($outra === 0) {
        $this->markTestSkipped('Lane sem segunda empresa para o controle cross-tenant.');
    }
    $minha = cuosVenda(CUOS_BIZ, $this->contato, 'CUOS-MINHA', '2026-03-01 10:00:00', $this->user->id);
    cuosVenda($outra, $this->contato, 'CUOS-ALHEIA', '2026-09-30 10:00:00', $this->user->id);

    $linha = cuosLinha($this);

    expect($linha['last_os'])->toBe(['id' => $minha, 'invoice_no' => 'CUOS-MINHA']);
});

it('UC-CIDX-05: contato sem venda tem last_os null (a célula mostra —)', function () {
    $linha = cuosLinha($this);

    expect($linha)->not->toBeNull();
    expect($linha['last_os'])->toBeNull();
});
