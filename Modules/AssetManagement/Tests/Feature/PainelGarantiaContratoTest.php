<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Contrato da "Situação da garantia" e do KPI "Garantia vencida ou vencendo" do Painel.
 *
 * Defende UC-PAT-10 (`resources/js/Pages/Patrimonio/Index.casos.md`): cada bem conta UMA vez,
 * pela garantia MAIS RECENTE (a que termina por último — [W] 2026-09-30).
 *
 * Por DELTA, não por valor absoluto: o CT 100 é base persistente e o tenant 98 já tem bens.
 * O teste lê os baldes antes e depois dos fixtures, e o esperado de cada balde está escrito à
 * mão abaixo — é o segundo caminho da REGRA MESTRE de valor (o primeiro é a conta do SQL).
 *
 * ADR 0358: tenant 98 é o fictício canônico; 99 é o adversário. biz=4 é proibido.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('assets') || ! Schema::hasTable('asset_warranties')) {
        $this->markTestSkipped('Tabelas assets/asset_warranties ausentes — rode migrate primeiro');
    }
});

/** `is_admin()` do Painel é `hasRole('Admin#<biz>')` — sem ele os KPIs e os baldes voltam null. */
function painelGarantiaAdmin(int $businessId): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'painel_garantia_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.$businessId, 'guard_name' => 'web'],
        ['business_id' => $businessId]
    );
    $user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * Valor de cada bem = 2 × 1500 = 3000. As datas de garantia são relativas a hoje.
 *
 * @param  array<int, string>  $fins  end_date de cada garantia registrada (vazio = sem registro)
 */
function painelGarantiaBem(int $businessId, int $ownerId, string $codigo, array $fins): Asset
{
    $bem = Asset::create([
        'business_id' => $businessId,
        'name' => 'Bem '.$codigo,
        'asset_code' => $codigo,
        'quantity' => 2,
        'unit_price' => 1500.00,
        'is_allocatable' => 1,
        'purchase_type' => 'owned',
        'created_by' => $ownerId,
    ]);
    foreach ($fins as $fim) {
        DB::table('asset_warranties')->insert([
            'asset_id' => $bem->id,
            'start_date' => now()->subYears(3)->toDateString(),
            'end_date' => $fim,
            'additional_cost' => 0,
        ]);
    }

    return $bem;
}

/** Lê as props DEFERIDAS `garantia` e `kpis` pelo partial reload que o browser faz. */
function painelGarantiaLer(User $user, int $businessId): array
{
    $sessao = [
        'user.business_id' => $businessId,
        'user' => ['business_id' => $businessId, 'id' => $user->id],
        'business.date_format' => 'd/m/Y',
    ];

    test()->flushHeaders();
    $inicial = test()->actingAs($user)->withSession($sessao)->get('/asset/dashboard');
    expect($inicial->status())->toBe(200);
    $versao = data_get($inicial->viewData('page'), 'version');

    $r = test()->actingAs($user)->withSession($sessao)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $versao,
            'X-Inertia-Partial-Component' => 'Patrimonio/Index',
            'X-Inertia-Partial-Data' => 'garantia,kpis',
        ])
        ->get('/asset/dashboard');
    expect($r->status())->toBe(200);

    $baldes = collect(data_get($r->json(), 'props.garantia', []))->keyBy('balde');
    expect($baldes)->toHaveCount(4);

    return [
        'baldes' => $baldes->map(fn ($b) => ['bens' => (int) $b['bens'], 'valor' => (float) $b['valor']])->all(),
        'critica' => (int) data_get($r->json(), 'props.kpis.garantiaCritica'),
    ];
}

function painelGarantiaLimpar(): void
{
    $ids = Asset::where('asset_code', 'like', 'PAINEL-GAR-%')->pluck('id');
    if ($ids->isNotEmpty()) {
        DB::table('asset_warranties')->whereIn('asset_id', $ids)->delete();
        Asset::whereIn('id', $ids)->forceDelete();
    }
    $users = User::withTrashed()->where('username', 'like', 'painel_garantia_%')->get();
    foreach ($users as $u) {
        // Só o vínculo do fixture: o role `Admin#98` pode ser do seed e não é deste teste.
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
}

it('UC-PAT-10: cada bem conta uma vez, pela garantia mais recente — nos baldes, no valor e no KPI', function () {
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();
    $bizId = (int) $dono->id;
    $owner = (int) $dono->owner_id;

    try {
        $user = painelGarantiaAdmin($bizId);
        $antes = painelGarantiaLer($user, $bizId);

        $vencida = now()->subDays(5)->toDateString();
        $umAno = now()->addYear()->toDateString();
        $doisAnos = now()->addYears(2)->toDateString();

        // A renovada: a velha venceu, a nova vale — é "vigente", não "vencida" nem crítica.
        painelGarantiaBem($bizId, $owner, 'PAINEL-GAR-RENOVADA', [$vencida, $umAno]);
        // Duas garantias vigentes: um bem só, valor contado uma vez.
        painelGarantiaBem($bizId, $owner, 'PAINEL-GAR-DUPLA', [$umAno, $doisAnos]);
        painelGarantiaBem($bizId, $owner, 'PAINEL-GAR-VENCIDA', [$vencida]);
        // Sem registro é o 4º balde, nunca "vencida" (charter R3).
        painelGarantiaBem($bizId, $owner, 'PAINEL-GAR-SEM', []);
        // Outro business com garantia vencida: não pode mexer em número nenhum.
        painelGarantiaBem((int) $adversario->id, (int) $adversario->owner_id, 'PAINEL-GAR-ADV', [$vencida]);

        $depois = painelGarantiaLer($user, $bizId);

        $delta = [];
        foreach (['vigente', 'vencendo', 'vencida', 'sem'] as $k) {
            $delta[$k] = [
                'bens' => $depois['baldes'][$k]['bens'] - $antes['baldes'][$k]['bens'],
                'valor' => round($depois['baldes'][$k]['valor'] - $antes['baldes'][$k]['valor'], 2),
            ];
        }

        // Esperado à mão (cada bem vale 3000): vigente = RENOVADA + DUPLA; vencida = VENCIDA;
        // sem = SEM. Com a regra antiga seriam vigente 2 bens / 9000 e vencida 2 bens / 6000.
        expect($delta)->toBe([
            'vigente' => ['bens' => 2, 'valor' => 6000.0],
            'vencendo' => ['bens' => 0, 'valor' => 0.0],
            'vencida' => ['bens' => 1, 'valor' => 3000.0],
            'sem' => ['bens' => 1, 'valor' => 3000.0],
        ]);

        // KPI: só a VENCIDA é crítica. Com a regra antiga a RENOVADA também somaria (+2).
        expect($depois['critica'] - $antes['critica'])->toBe(1);
    } finally {
        painelGarantiaLimpar();
    }
});
