<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Contrato da ficha da empresa (`/officeimpresso/computadores` e
 * `/officeimpresso/licenca_computado/licencas/{id}`) em React — thread Officeimpresso/11.
 * Casos: Pages/Officeimpresso/Empresa/Show.casos.md (UC-EMP-*), derivados do empresa-parity.md,
 * do RUNBOOK-empresa e da decisão D1 [W] 2026-10-12 — não do .tsx.
 *
 * Flag `useV2OfficeimpressoEmpresa` forçada por stub do FeatureFlagService. Tenant 98 (operadora)
 * × 99 (cliente) — ADR 0358. As máquinas criadas aqui são apagadas no afterEach; asserções só sobre
 * elas. MySQL-only.
 *
 * @covers-us US-OI-011
 * @covers-us US-OI-012
 * @see Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::computadores
 * @see Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::viewLicencas
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS exige MySQL (ADR 0358).');
    }
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    Permission::firstOrCreate(['name' => 'officeimpresso.access', 'guard_name' => 'web']);
    $this->oiFichaOperadora = $this->seededTenant();
    $this->oiFichaCliente = $this->seededSupportClientTenant();
    config(['constants.operator_business_id' => (int) $this->oiFichaOperadora->id]);
    $this->oiFichaMaquinas = [];
    $this->oiFichaUsers = [];
});

afterEach(function () {
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    if ($this->oiFichaMaquinas) {
        DB::table('licenca_computador')->whereIn('id', $this->oiFichaMaquinas)->delete();
    }
    foreach ($this->oiFichaUsers as $u) {
        $u->forceDelete();
    }
});

it('UC-EMP-01 · flag desligada serve a Blade nas duas rotas; ligada, a ficha React; ?legacy=1 volta à Blade', function () {
    $this->actingAs(oiFichaUser($this));
    $alheia = '/officeimpresso/licenca_computado/licencas/'.$this->oiFichaCliente->id;

    oiFichaFlag(false);
    $this->get('/officeimpresso/computadores')->assertOk()->assertViewIs('officeimpresso::licenca_computador.computadores');
    $this->get($alheia)->assertOk()->assertViewIs('officeimpresso::licenca_computador.computadores');

    oiFichaFlag(true);
    $this->get('/officeimpresso/computadores')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Officeimpresso/Empresa/Show')
        ->has('empresa')
        ->missing('computadores')
        ->missing('assinatura'));
    $this->get($alheia.'?legacy=1')->assertOk()->assertViewIs('officeimpresso::licenca_computador.computadores');
});

it('UC-EMP-19 · [T0] /computadores mostra a empresa da sessão', function () {
    oiFichaFlag(true);
    $this->actingAs(oiFichaUser($this));

    $this->get('/officeimpresso/computadores')->assertInertia(fn (AssertableInertia $p) => $p
        ->where('empresa.id', (int) $this->oiFichaOperadora->id));
});

it('UC-EMP-20 · [T0] a operadora abre a ficha de outra empresa pela URL (D1); id inexistente é 404', function () {
    oiFichaFlag(true);
    $this->actingAs(oiFichaUser($this));

    $this->get('/officeimpresso/licenca_computado/licencas/'.$this->oiFichaCliente->id)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->where('empresa.id', (int) $this->oiFichaCliente->id));

    $semEmpresa = (int) DB::table('business')->max('id') + 1000;
    $this->get('/officeimpresso/licenca_computado/licencas/'.$semEmpresa)->assertNotFound();
});

it('UC-EMP-21 · [T0] cada máquina é DTO de 9 campos: senha, serial e token nunca saem', function () {
    oiFichaFlag(true);
    $biz = (int) $this->oiFichaCliente->id;
    $id = oiFichaMaquina($this, $biz, ['serial' => 'OIFICHA-SERIAL-X9', 'bloqueado' => 1]);
    $this->actingAs(oiFichaUser($this));

    $r = oiFichaParcial($this, $biz, 'computadores');
    $linha = collect($r->json('props.computadores'))->firstWhere('id', $id);

    expect(array_keys($linha))->toEqual([
        'id', 'dt_cadastro', 'user_win', 'pasta_instalacao', 'versao_exe', 'ip_interno', 'caminho_banco', 'dt_ultimo_acesso', 'bloqueado',
    ]);
    expect($r->getContent())->not->toContain('OIFICHA-SERIAL-X9');
});

it('UC-EMP-15 · o status de cada máquina sai do bloqueio dela', function () {
    oiFichaFlag(true);
    $biz = (int) $this->oiFichaCliente->id;
    $bloqueada = oiFichaMaquina($this, $biz, ['bloqueado' => 1]);
    $liberada = oiFichaMaquina($this, $biz, ['bloqueado' => 0]);
    $this->actingAs(oiFichaUser($this));

    $linhas = collect(oiFichaParcial($this, $biz, 'computadores')->json('props.computadores'))->keyBy('id');

    expect($linhas[$bloqueada]['bloqueado'])->toBeTrue();
    expect($linhas[$liberada]['bloqueado'])->toBeFalse();
});

it('UC-EMP-04 · a ficha traz versões e o último acesso da máquina mais recente', function () {
    oiFichaFlag(true);
    $biz = (int) $this->oiFichaCliente->id;
    $antes = DB::table('business')->where('id', $biz)->value('versao_obrigatoria');
    DB::table('business')->where('id', $biz)->update(['versao_obrigatoria' => '6.7.12']);
    // 2037: TIMESTAMP tem teto em 2038, e no CT 100 o banco persiste — a mais recente tem de ser esta.
    oiFichaMaquina($this, $biz, ['dt_ultimo_acesso' => '2037-01-10 08:00:00']);
    oiFichaMaquina($this, $biz, ['dt_ultimo_acesso' => '2037-03-05 14:30:00']);
    $this->actingAs(oiFichaUser($this));

    try {
        $this->get('/officeimpresso/licenca_computado/licencas/'.$biz)->assertInertia(fn (AssertableInertia $p) => $p
            ->where('empresa.versao_obrigatoria', '6.7.12')
            ->where('empresa.ultimo_acesso', fn ($v) => str_starts_with((string) $v, '2037-03-05T14:30')));
    } finally {
        DB::table('business')->where('id', $biz)->update(['versao_obrigatoria' => $antes]);
    }
});

// ── Helpers (prefixo oiFicha — outros arquivos do módulo rodam no mesmo processo) ──

function oiFichaFlag(bool $ligada): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class($ligada) extends \App\Services\FeatureFlagService
    {
        public function __construct(private bool $ligada) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2OfficeimpressoEmpresa' && $this->ligada;
        }
    });
}

/** User da operadora (98) SEM role — senão o Gate::before faria bypass de tudo. */
function oiFichaUser($test): User
{
    $biz = (int) $test->oiFichaOperadora->id;
    $user = User::create([
        'business_id' => $biz,
        'first_name'  => 'OI',
        'surname'     => 'Ficha11',
        'username'    => 'oi_ficha11_'.$biz.'_'.uniqid(),
        'email'       => 'oi_ficha11_'.$biz.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
    $user->givePermissionTo('officeimpresso.access');
    $test->oiFichaUsers[] = $user;

    return $user;
}

function oiFichaMaquina($test, int $businessId, array $attrs = []): int
{
    $id = (int) DB::table('licenca_computador')->insertGetId(array_merge([
        'business_id' => $businessId,
        'hd'          => 'OIFICHA11-'.uniqid(),
        'hostname'    => 'OIFICHA11',
        'user_win'    => 'OIFICHA11',
        'bloqueado'   => 0,
    ], $attrs));
    $test->oiFichaMaquinas[] = $id;

    return $id;
}

/** Partial reload do navegador pedindo uma prop adiada da ficha de `$businessId`. */
function oiFichaParcial($test, int $businessId, string $prop)
{
    $r = $test->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Officeimpresso/Empresa/Show',
        'X-Inertia-Partial-Data' => $prop,
    ])->get('/officeimpresso/licenca_computado/licencas/'.$businessId);
    $r->assertOk();

    return $r;
}
