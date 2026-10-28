<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Contrato da lista de Empresas licenciadas (`/officeimpresso/businessall`) em React — thread
 * Officeimpresso/11. Casos: Pages/Officeimpresso/Empresas/Index.casos.md (UC-EMPS-*), derivados do
 * empresas-parity.md, do RUNBOOK-empresas e da decisão D1 [W] 2026-10-12 — não do .tsx.
 *
 * A flag `useV2OfficeimpressoEmpresas` é forçada por stub do FeatureFlagService (mesmo desenho do
 * ClientesIndexContratoTest). Tenant 98 (operadora) × 99 (cliente) — ADR 0358. O teste marca os
 * dois como licenciados e devolve o valor original no afterEach: no CT 100 o banco persiste.
 * Asserções só sobre as empresas e máquinas que ESTE teste toca. MySQL-only.
 *
 * @covers-us US-OI-014
 * @covers-us US-OI-015
 * @see Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::businessall
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS exige MySQL (ADR 0358).');
    }
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    Permission::firstOrCreate(['name' => 'officeimpresso.access', 'guard_name' => 'web']);
    $this->oiEmpOperadora = $this->seededTenant();
    $this->oiEmpCliente = $this->seededSupportClientTenant();
    config(['constants.operator_business_id' => (int) $this->oiEmpOperadora->id]);

    $this->oiEmpOriginal = DB::table('business')
        ->whereIn('id', [$this->oiEmpOperadora->id, $this->oiEmpCliente->id])
        ->get(['id', 'is_officeimpresso', 'officeimpresso_bloqueado', 'versao_obrigatoria'])
        ->keyBy('id');
    $this->oiEmpMaquinas = [];
    $this->oiEmpUsers = [];
});

afterEach(function () {
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    foreach ($this->oiEmpOriginal as $id => $linha) {
        DB::table('business')->where('id', $id)->update((array) $linha);
    }
    if ($this->oiEmpMaquinas) {
        DB::table('licenca_computador')->whereIn('id', $this->oiEmpMaquinas)->delete();
    }
    foreach ($this->oiEmpUsers as $u) {
        $u->forceDelete();
    }
});

it('UC-EMPS-01 · serve a tela React com a lista adiada, mesmo com a flag antiga desligada', function () {
    $this->actingAs(oiEmpUser($this));

    // Cutover 2026-10-27: a flag `useV2OfficeimpressoEmpresas` saiu. Uma regra no GrowthBook que
    // ainda a desligue não pode trazer a Blade de volta — a view nem existe mais.
    oiEmpFlag(false);
    expect(view()->exists('officeimpresso::licenca_computador.businessall'))->toBeFalse();
    $this->get('/officeimpresso/businessall')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Officeimpresso/Empresas/Index')
            ->missing('empresas')
            ->missing('kpis'));
});

it('UC-EMPS-10 · só aparece empresa marcada como licenciada', function () {
    oiEmpFlag(true);
    oiEmpMarca($this, $this->oiEmpOperadora->id, ['is_officeimpresso' => 1]);
    oiEmpMarca($this, $this->oiEmpCliente->id, ['is_officeimpresso' => 0]);
    $this->actingAs(oiEmpUser($this));

    $ids = collect(oiEmpParcial($this, 'empresas')->json('props.empresas'))->pluck('id')->all();

    expect($ids)->toContain((int) $this->oiEmpOperadora->id);
    expect($ids)->not->toContain((int) $this->oiEmpCliente->id);
});

it('UC-EMPS-11 · [T0] a lista é global por design: a operadora vê a empresa cliente', function () {
    oiEmpFlag(true);
    oiEmpMarca($this, $this->oiEmpCliente->id, ['is_officeimpresso' => 1]);
    $this->actingAs(oiEmpUser($this));

    $ids = collect(oiEmpParcial($this, 'empresas')->json('props.empresas'))->pluck('id')->all();

    expect($ids)->toContain((int) $this->oiEmpCliente->id);
});

it('UC-EMPS-12 · [T0] cada linha é o DTO de 8 campos, nunca o model Business', function () {
    oiEmpFlag(true);
    oiEmpMarca($this, $this->oiEmpOperadora->id, ['is_officeimpresso' => 1]);
    $this->actingAs(oiEmpUser($this));

    $linha = collect(oiEmpParcial($this, 'empresas')->json('props.empresas'))->firstWhere('id', (int) $this->oiEmpOperadora->id);

    expect($linha)->toBeArray();
    expect(array_keys($linha))->toEqual([
        'id', 'name', 'razao_social', 'cnpj', 'versao_disponivel', 'versao_obrigatoria', 'ultimo_acesso', 'bloqueado',
    ]);
});

it('UC-EMPS-04 · versão obrigatória vem do cadastro e o último acesso é o da máquina mais recente', function () {
    oiEmpFlag(true);
    $biz = (int) $this->oiEmpCliente->id;
    oiEmpMarca($this, $biz, ['is_officeimpresso' => 1, 'versao_obrigatoria' => '6.7.12']);
    // 2037: no CT 100 o banco persiste, então a máquina mais recente tem de ser a deste teste; e a
    // coluna é TIMESTAMP (teto em 2038) — 2099 virava a data zero e o teste media o lixo, não o MAX.
    oiEmpMaquina($this, $biz, '2037-01-10 08:00:00');
    oiEmpMaquina($this, $biz, '2037-03-05 14:30:00');
    oiEmpMaquina($this, $biz, null);
    $this->actingAs(oiEmpUser($this));

    $linha = collect(oiEmpParcial($this, 'empresas')->json('props.empresas'))->firstWhere('id', $biz);

    expect($linha['versao_obrigatoria'])->toBe('6.7.12');
    expect(substr((string) $linha['ultimo_acesso'], 0, 16))->toBe('2037-03-05T14:30');
});

it('UC-EMPS-05 · o status sai do bloqueio da empresa: bloqueada e liberada', function () {
    oiEmpFlag(true);
    oiEmpMarca($this, $this->oiEmpCliente->id, ['is_officeimpresso' => 1, 'officeimpresso_bloqueado' => 1]);
    oiEmpMarca($this, $this->oiEmpOperadora->id, ['is_officeimpresso' => 1, 'officeimpresso_bloqueado' => 0]);
    $this->actingAs(oiEmpUser($this));

    $linhas = collect(oiEmpParcial($this, 'empresas')->json('props.empresas'))->keyBy('id');

    expect($linhas[(int) $this->oiEmpCliente->id]['bloqueado'])->toBeTrue();
    expect($linhas[(int) $this->oiEmpOperadora->id]['bloqueado'])->toBeFalse();
});

it('UC-EMPS-02 · os KPIs vêm do backend e somam total = ativas + bloqueadas', function () {
    oiEmpFlag(true);
    oiEmpMarca($this, $this->oiEmpOperadora->id, ['is_officeimpresso' => 1, 'officeimpresso_bloqueado' => 0]);
    oiEmpMarca($this, $this->oiEmpCliente->id, ['is_officeimpresso' => 1, 'officeimpresso_bloqueado' => 1]);
    $this->actingAs(oiEmpUser($this));

    $esperado = DB::table('business')->where('is_officeimpresso', 1)->count();
    $bloq = DB::table('business')->where('is_officeimpresso', 1)->where('officeimpresso_bloqueado', 1)->count();
    $kpis = oiEmpParcial($this, 'kpis')->json('props.kpis');

    expect($kpis['total'])->toBe($esperado);
    expect($kpis['bloqueadas'])->toBe($bloq);
    expect($kpis['ativas'])->toBe($esperado - $bloq);
    expect($bloq)->toBeGreaterThanOrEqual(1);
});

// ── Helpers (prefixo oiEmp — outros arquivos do módulo rodam no mesmo processo) ──

function oiEmpFlag(bool $ligada): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class($ligada) extends \App\Services\FeatureFlagService
    {
        public function __construct(private bool $ligada) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2OfficeimpressoEmpresas' && $this->ligada;
        }
    });
}

/** User da operadora (98) SEM role — senão o Gate::before faria bypass de tudo. */
function oiEmpUser($test): User
{
    $biz = (int) $test->oiEmpOperadora->id;
    $user = User::create([
        'business_id' => $biz,
        'first_name'  => 'OI',
        'surname'     => 'Empresas11',
        'username'    => 'oi_emp11_'.$biz.'_'.uniqid(),
        'email'       => 'oi_emp11_'.$biz.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
    $user->givePermissionTo('officeimpresso.access');
    $test->oiEmpUsers[] = $user;

    return $user;
}

function oiEmpMarca($test, int $businessId, array $campos): void
{
    DB::table('business')->where('id', $businessId)->update($campos);
}

function oiEmpMaquina($test, int $businessId, ?string $acesso): void
{
    $test->oiEmpMaquinas[] = (int) DB::table('licenca_computador')->insertGetId([
        'business_id'      => $businessId,
        'hd'               => 'OIEMP11-'.uniqid(),
        'hostname'         => 'OIEMP11',
        'bloqueado'        => 0,
        'dt_ultimo_acesso' => $acesso,
    ]);
}

/** Partial reload do navegador pedindo uma prop adiada. */
function oiEmpParcial($test, string $prop)
{
    $r = $test->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Officeimpresso/Empresas/Index',
        'X-Inertia-Partial-Data' => $prop,
    ])->get('/officeimpresso/businessall');
    $r->assertOk();

    return $r;
}
