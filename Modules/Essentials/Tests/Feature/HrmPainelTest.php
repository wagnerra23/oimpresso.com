<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsHoliday;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Thread 06 do playbook hrm — o Painel (/hrm/dashboard) vira Page Inertia.
 * Contrato: resources/js/Pages/Essentials/Painel.casos.md.
 *
 * Tenant 98 (canônico, fictício) × 99 (adversário) — ADR 0358. NUNCA biz=4.
 * `DatabaseTransactions`, nunca `RefreshDatabase` (a lane roda em MySQL semeado).
 */
beforeEach(function () {
    if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    foreach (['essentials_holidays', 'essentials_leaves', 'essentials_user_sales_targets'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode migrate Modules/Essentials.");
        }
    }

    $this->tenant = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    $user = User::where('business_id', $this->tenant->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user no tenant canônico — seed mínimo não rodou.');
    }
    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.$this->tenant->id, 'guard_name' => 'web'],
        ['business_id' => $this->tenant->id]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role->name);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    session()->flush();
    $this->actingAs($user);
});

/** Headers que o NAVEGADOR manda numa visita Inertia — `X-Requested-With` vai em toda visita. */
function painelHeaders(array $extra = []): array
{
    $manifest = public_path('build-inertia/manifest.json');

    return array_merge([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
    ], $extra);
}

function painelPayload($test): array
{
    $response = $test->withHeaders(painelHeaders([
        'X-Inertia-Partial-Data' => 'painel',
        'X-Inertia-Partial-Component' => 'Essentials/Painel',
    ]))->get('/hrm/dashboard');
    $response->assertStatus(200);

    return $response->json('props.painel') ?? [];
}

it('UC-PAINEL-00: a rota nomeada do menu (hrmDashboard) leva ao painel', function () {
    expect(route('hrmDashboard', [], false))->toBe('/hrm/dashboard');
    $this->withHeaders(painelHeaders())->get(route('hrmDashboard'))->assertStatus(200);
});

it('UC-PAINEL-01: renderiza Essentials/Painel com o conteúdo adiado', function () {
    $response = $this->withHeaders(painelHeaders())->get('/hrm/dashboard');

    $response->assertStatus(200);
    expect($response->json('component'))->toBe('Essentials/Painel');
    expect($response->json('props.is_admin'))->toBeTrue();
    // `painel` é Inertia::defer: não vem no first render.
    expect($response->json('props'))->not->toHaveKey('painel');
});

it('UC-PAINEL-02: feriado e colaboradores do tenant adversário não aparecem', function () {
    $amanha = now()->addDay()->format('Y-m-d');
    $meu = EssentialsHoliday::create([
        'business_id' => $this->tenant->id, 'name' => 'UC02-meu-'.uniqid(),
        'start_date' => $amanha, 'end_date' => $amanha,
    ]);
    $alheio = EssentialsHoliday::create([
        'business_id' => $this->adversario->id, 'name' => 'UC02-alheio-'.uniqid(),
        'start_date' => $amanha, 'end_date' => $amanha,
    ]);

    $payload = painelPayload($this);
    $ids = array_column($payload['feriados'], 'id');

    expect($ids)->toContain($meu->id);
    expect($ids)->not->toContain($alheio->id);
    expect($payload['colaboradores'])
        ->toBe(User::where('business_id', $this->tenant->id)->user()->count());
});

it('UC-PAINEL-03: o payload não carrega presença nem venda realizada', function () {
    $chaves = array_keys(painelPayload($this));
    sort($chaves);

    expect($chaves)->toBe(['colaboradores', 'faixas_meta', 'feriados', 'minhas_licencas', 'setores']);
});
