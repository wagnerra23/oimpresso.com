<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Contrato da lista de licenças (`/officeimpresso/licenca_computador`) — thread
 * Officeimpresso/06 PR-a (2026-10-01). Casos: Pages/Officeimpresso/Licencas/Index.casos.md
 * (UC-OILIC-*), derivados da ficha 06, do RUNBOOK-licencas e do licencas-parity — não do .tsx.
 *
 * Asserções só sobre as linhas que ESTE teste cria (marcador único): no CT 100 o banco
 * persiste entre runs. Tenant canônico 98 (ADR 0358), nunca biz=4. MySQL-only.
 *
 * @covers-us US-OI-008
 * @covers-us US-OI-009
 * @see Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::index
 * @see Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::toggleBlock (PR-b, UC-OILIC-10..15)
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS necessário (ADR 0358).');
    }
    // Mesmos dois superglobais que o layout Blade lê direto (ver LogsBaselineTest).
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    // O tenant de teste (biz=98) faz o papel da empresa OPERADORA: as permissões
    // delegáveis de escrita só valem para usuário dela (AcessoOperador).
    if ($operador = static::resolveSeededTenant()) {
        config(['constants.operator_business_id' => (int) $operador->id]);
    }

    $this->oiLicMarca = 'OILIC' . strtoupper(substr(uniqid(), -8));
    $this->oiLicIds = [];
    $this->oiLicUsers = [];
});

afterEach(function () {
    if ($this->oiLicIds) {
        DB::table('licenca_log')->whereIn('licenca_id', $this->oiLicIds)->delete();
        DB::table('licenca_computador')->whereIn('id', $this->oiLicIds)->delete();
    }
    foreach ($this->oiLicUsers as $u) {
        $u->forceDelete();
    }
});

it('UC-OILIC-01 · sem officeimpresso.access nem superadmin, 403 — com a flag ligada também', function () {
    $biz = $this->seededTenant();
    $this->actingAs(oiLicUser($this, $biz->id, null));

    oiLicFlag(false);
    $this->get('/officeimpresso/licenca_computador')->assertForbidden();
    oiLicFlag(true);
    $this->get('/officeimpresso/licenca_computador')->assertForbidden();
});

it('UC-OILIC-02 · com a flag OFF a rota segue servindo o Blade', function () {
    $biz = $this->seededTenant();
    $this->actingAs(oiLicUser($this, $biz->id, 'officeimpresso.access'));
    oiLicFlag(false);

    // `viewData` só existe em resposta de view: se virar Inertia sem a flag, quebra aqui.
    expect($this->get('/officeimpresso/licenca_computador')->viewData('licencas'))->not->toBeNull();
});

it('UC-OILIC-03 · com a flag ON responde Officeimpresso/Licencas/Index, lista adiada e permissão eager', function () {
    $biz = $this->seededTenant();
    $this->actingAs(oiLicUser($this, $biz->id, 'officeimpresso.access'));
    oiLicFlag(true);

    $this->get('/officeimpresso/licenca_computador')
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Officeimpresso/Licencas/Index')
            ->where('permissions.pode_ver_todas_empresas', false)
            ->missing('licencas'));
});

it('UC-OILIC-04 · quem tem só officeimpresso.access vê apenas as máquinas do negócio da sessão', function () {
    $casa = $this->seededTenant();
    $outro = $this->seededSupportClientTenant();
    $this->actingAs(oiLicUser($this, $casa->id, 'officeimpresso.access'));
    $minha = oiLicMaquina($this, $casa->id);
    $alheia = oiLicMaquina($this, $outro->id);

    $ids = collect(oiLicParcial($this))->pluck('id')->all();

    expect($ids)->toContain($minha);
    expect($ids)->not->toContain($alheia);
});

it('UC-OILIC-05 · superadmin vê máquinas de todos os negócios, com o nome da empresa', function () {
    $casa = $this->seededTenant();
    $outro = $this->seededSupportClientTenant();
    $this->actingAs(oiLicUser($this, $casa->id, 'superadmin'));
    $alheia = oiLicMaquina($this, $outro->id);

    $linha = collect(oiLicParcial($this))->firstWhere('id', $alheia);

    expect($linha)->not->toBeNull();
    expect($linha['business_id'])->toBe((int) $outro->id);
    expect($linha['empresa'])->toBe(DB::table('business')->where('id', $outro->id)->value('name'));
});

it('UC-OILIC-06 · a linha nunca leva senha, contra-senha, serial nem token', function () {
    $biz = $this->seededTenant();
    $this->actingAs(oiLicUser($this, $biz->id, 'officeimpresso.access'));
    $id = oiLicMaquina($this, $biz->id, ['senha' => 'S3GR3D0', 'contra_senha' => 'C0NTR4', 'serial' => 'SER-' . substr(uniqid(), -6)]);

    $r = oiLicParcialResposta($this);
    $linha = collect($r->json('props.licencas'))->firstWhere('id', $id);

    expect($linha)->not->toBeNull();
    foreach (['senha', 'contra_senha', 'serial', 'token'] as $campo) {
        expect(array_key_exists($campo, $linha))->toBeFalse();
    }
    expect($r->getContent())->not->toContain('S3GR3D0');
    expect($r->getContent())->not->toContain('C0NTR4');
});

it('UC-OILIC-07 · o frescor do último acesso segue as faixas 24 h · 7 d · 30 d', function () {
    $biz = $this->seededTenant();
    $this->actingAs(oiLicUser($this, $biz->id, 'officeimpresso.access'));
    $casos = [
        'recente'  => oiLicMaquina($this, $biz->id, ['dt_ultimo_acesso' => now()->subHours(2)]),
        'fresc'    => oiLicMaquina($this, $biz->id, ['dt_ultimo_acesso' => now()->subDays(3)]),
        'frio'     => oiLicMaquina($this, $biz->id, ['dt_ultimo_acesso' => now()->subDays(15)]),
        'distante' => oiLicMaquina($this, $biz->id, ['dt_ultimo_acesso' => null]),
    ];

    $linhas = collect(oiLicParcial($this))->keyBy('id');

    foreach ($casos as $esperado => $id) {
        expect($linhas[$id]['frescor'])->toBe($esperado);
    }
});

it('UC-OILIC-08 · HD presente em outro negócio é avisado ao superadmin com a contagem', function () {
    $casa = $this->seededTenant();
    $outro = $this->seededSupportClientTenant();
    $this->actingAs(oiLicUser($this, $casa->id, 'superadmin'));
    $hd = $this->oiLicMarca . 'HD';
    $a = oiLicMaquina($this, $casa->id, ['hd' => $hd]);
    oiLicMaquina($this, $outro->id, ['hd' => $hd]);
    $sozinha = oiLicMaquina($this, $casa->id);

    $linhas = collect(oiLicParcial($this))->keyBy('id');

    expect($linhas[$a]['hd_compartilhado'])->toBe(1);
    expect($linhas[$sozinha]['hd_compartilhado'])->toBe(0);
});

it('UC-OILIC-09 · a linha traz versões do executável e do banco, validade e situação com motivo', function () {
    $biz = $this->seededTenant();
    $this->actingAs(oiLicUser($this, $biz->id, 'officeimpresso.access'));
    $id = oiLicMaquina($this, $biz->id, [
        'versao_exe' => '6.7.14', 'versao_banco' => '1474', 'dt_validade' => '2027-03-31 00:00:00',
        'bloqueado' => 1, 'motivo' => 'contrato suspenso',
    ]);

    $linha = collect(oiLicParcial($this))->firstWhere('id', $id);

    expect($linha['versao_exe'])->toBe('6.7.14');
    expect($linha['versao_banco'])->toBe('1474');
    expect($linha['dt_validade'])->toBe('2027-03-31');
    expect($linha['bloqueado'])->toBeTrue();
    expect($linha['motivo'])->toBe('contrato suspenso');
});

it('UC-OILIC-10 · a ficha do drawer traz o equipamento e nunca senha, contra-senha, serial, token nem usuário', function () {
    $biz = $this->seededTenant();
    $this->actingAs(oiLicUser($this, $biz->id, 'officeimpresso.access'));
    $id = oiLicMaquina($this, $biz->id, [
        'sistema_operacional' => 'Windows 11', 'ip_interno' => '10.0.0.7', 'versao_exe' => '6.7.14',
        'senha' => 'S3GR3D0', 'contra_senha' => 'C0NTR4', 'usuario' => 'US3RDB',
    ]);

    $r = oiLicDetalheResposta($this, $id);
    $ficha = $r->json('props.detalhe.ficha');

    expect($ficha['id'])->toBe($id);
    expect($ficha['sistema_operacional'])->toBe('Windows 11');
    expect($ficha['ip_interno'])->toBe('10.0.0.7');
    foreach (['senha', 'contra_senha', 'serial', 'token', 'usuario', 'conexao'] as $campo) {
        expect(array_key_exists($campo, $ficha))->toBeFalse();
    }
    foreach (['S3GR3D0', 'C0NTR4', 'US3RDB'] as $segredo) {
        expect($r->getContent())->not->toContain($segredo);
    }
});

it('UC-OILIC-11 · a ficha de máquina de outro negócio não abre para quem vê só a sessão', function () {
    $casa = $this->seededTenant();
    $outro = $this->seededSupportClientTenant();
    $this->actingAs(oiLicUser($this, $casa->id, 'officeimpresso.access'));
    $alheia = oiLicMaquina($this, $outro->id);
    $propria = oiLicMaquina($this, $casa->id);

    expect(oiLicDetalheResposta($this, $alheia)->json('props.detalhe'))->toBeNull();
    expect(oiLicDetalheResposta($this, $propria)->json('props.detalhe.ficha.id'))->toBe($propria);
});

it('UC-OILIC-12 · o histórico traz os acessos e os bloqueios da máquina, e só dela', function () {
    $biz = $this->seededTenant();
    $this->actingAs(oiLicUser($this, $biz->id, 'officeimpresso.access'));
    $id = oiLicMaquina($this, $biz->id);
    $vizinha = oiLicMaquina($this, $biz->id);
    $base = ['business_id' => $biz->id, 'created_at' => now()];
    DB::table('licenca_log')->insert([
        $base + ['licenca_id' => $id, 'event' => 'login_success', 'source' => 'delphi_middleware', 'metadata' => null],
        $base + ['licenca_id' => $id, 'event' => 'maquina_bloqueada', 'source' => 'admin_action', 'metadata' => json_encode(['motivo' => 'contrato suspenso'])],
        $base + ['licenca_id' => $vizinha, 'event' => 'login_error', 'source' => 'delphi_middleware', 'metadata' => null],
    ]);

    $hist = collect(oiLicDetalheResposta($this, $id)->json('props.detalhe.historico'));

    expect($hist->pluck('evento')->sort()->values()->all())->toBe(['login_success', 'maquina_bloqueada']);
    expect($hist->firstWhere('evento', 'maquina_bloqueada')['motivo'])->toBe('contrato suspenso');
});

it('UC-OILIC-13 · bloquear pelo drawer sem motivo (ou com menos de 5 letras) não muda nada', function () {
    $biz = $this->seededTenant();
    $this->actingAs(oiLicUser($this, $biz->id, 'officeimpresso.licencas.gerenciar'));
    $id = oiLicMaquina($this, $biz->id);

    foreach ([[], ['motivo' => 'abc']] as $extra) {
        $this->post('/officeimpresso/licenca_computador/' . $id . '/toggle-block', ['bloquear' => true] + $extra)
            ->assertSessionHasErrors('motivo');
    }

    expect((int) DB::table('licenca_computador')->where('id', $id)->value('bloqueado'))->toBe(0);
    expect(DB::table('licenca_log')->where('licenca_id', $id)->count())->toBe(0);
});

it('UC-OILIC-14 · bloquear com motivo registra no histórico com o negócio do EQUIPAMENTO e não reescreve a mensagem do desktop', function () {
    $casa = $this->seededTenant();
    $outro = $this->seededSupportClientTenant();
    $admin = oiLicUser($this, $casa->id, 'superadmin');
    $this->actingAs($admin);
    $id = oiLicMaquina($this, $outro->id, ['motivo' => 'mensagem antiga ao desktop']);

    $this->post('/officeimpresso/licenca_computador/' . $id . '/toggle-block', ['bloquear' => true, 'motivo' => 'contrato encerrado pelo cliente'])
        ->assertSessionHasNoErrors();

    $maq = DB::table('licenca_computador')->where('id', $id)->first();
    expect((int) $maq->bloqueado)->toBe(1);
    expect($maq->motivo)->toBe('mensagem antiga ao desktop');
    $log = DB::table('licenca_log')->where('licenca_id', $id)->first();
    expect($log)->not->toBeNull();
    expect((int) $log->business_id)->toBe((int) $outro->id);
    expect((int) $log->user_id)->toBe((int) $admin->id);
    expect([$log->event, $log->source])->toBe(['maquina_bloqueada', 'admin_action']);
    expect(json_decode($log->metadata, true)['motivo'])->toBe('contrato encerrado pelo cliente');
});

it('UC-OILIC-15 · intenção já cumprida não inverte o estado; o toggle sem intenção segue sem motivo', function () {
    $biz = $this->seededTenant();
    $this->actingAs(oiLicUser($this, $biz->id, 'officeimpresso.licencas.gerenciar'));
    $id = oiLicMaquina($this, $biz->id, ['bloqueado' => 1]);

    $this->post('/officeimpresso/licenca_computador/' . $id . '/toggle-block', ['bloquear' => true, 'motivo' => 'segundo clique'])
        ->assertSessionHasErrors('bloquear');
    expect((int) DB::table('licenca_computador')->where('id', $id)->value('bloqueado'))->toBe(1);

    // Blade e tela de Logs não mandam `bloquear`: o toggle de sempre, sem motivo exigido.
    $this->post('/officeimpresso/licenca_computador/' . $id . '/toggle-block')->assertSessionHasNoErrors();
    expect((int) DB::table('licenca_computador')->where('id', $id)->value('bloqueado'))->toBe(0);
});

it('operador · cliente com licencas.gerenciar não bloqueia máquina de outra empresa e a tela não oferece o botão', function () {
    $operador = $this->seededTenant();
    $cliente = $this->seededSupportClientTenant();
    $user = oiLicUser($this, (int) $cliente->id, 'officeimpresso.licencas.gerenciar');
    Permission::firstOrCreate(['name' => 'officeimpresso.access', 'guard_name' => 'web']);
    $user->givePermissionTo('officeimpresso.access');
    $this->actingAs($user);
    $id = oiLicMaquina($this, (int) $operador->id);

    $this->post('/officeimpresso/licenca_computador/' . $id . '/toggle-block', ['bloquear' => true, 'motivo' => 'tentativa de outro negócio'])
        ->assertForbidden();
    $this->post('/officeimpresso/licenca_computador/' . $id . '/toggle-block')->assertForbidden();

    // Nada mudou: nem o estado, nem o histórico.
    expect((int) DB::table('licenca_computador')->where('id', $id)->value('bloqueado'))->toBe(0);
    expect(DB::table('licenca_log')->where('licenca_id', $id)->count())->toBe(0);

    // Desde a trava de `officeimpresso.access` (também só da operadora) a tela nem abre
    // para empresa cliente — antes ela abria sem o botão de bloquear.
    oiLicFlag(true);
    $this->get('/officeimpresso/licenca_computador')->assertForbidden();
});

it('operador · usuário da operadora com licencas.gerenciar bloqueia máquina de empresa cliente', function () {
    $operador = $this->seededTenant();
    $cliente = $this->seededSupportClientTenant();
    $this->actingAs(oiLicUser($this, (int) $operador->id, 'officeimpresso.licencas.gerenciar'));
    $id = oiLicMaquina($this, (int) $cliente->id);

    $this->post('/officeimpresso/licenca_computador/' . $id . '/toggle-block', ['bloquear' => true, 'motivo' => 'suporte da operadora'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect((int) DB::table('licenca_computador')->where('id', $id)->value('bloqueado'))->toBe(1);
});

// ── Helpers (prefixo oiLic — o LogsBaselineTest roda no mesmo processo) ──────

function oiLicUser($test, int $businessId, ?string $permissao): User
{
    $user = User::create([
        'business_id' => $businessId,
        'first_name'  => 'OI',
        'surname'     => 'Licencas',
        'username'    => 'oi_lic_' . $businessId . '_' . uniqid(),
        'email'       => 'oi_lic_' . $businessId . '_' . uniqid() . '@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
    if ($permissao) {
        Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
        $user->givePermissionTo($permissao);
    }
    $test->oiLicUsers[] = $user;

    return $user;
}

function oiLicMaquina($test, int $businessId, array $attrs = []): int
{
    $id = DB::table('licenca_computador')->insertGetId(array_merge([
        'business_id' => $businessId,
        'hd'          => $test->oiLicMarca . '-' . uniqid(),
        'user_win'    => $test->oiLicMarca,
        'hostname'    => $test->oiLicMarca,
        'bloqueado'   => 0,
    ], $attrs));
    $test->oiLicIds[] = $id;

    return $id;
}

/** Partial reload do navegador pedindo a prop adiada `licencas`. */
function oiLicParcialResposta($test)
{
    oiLicFlag(true);
    $r = $test->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Officeimpresso/Licencas/Index',
        'X-Inertia-Partial-Data' => 'licencas',
    ])->get('/officeimpresso/licenca_computador');
    $r->assertOk();

    return $r;
}

/** Partial reload que o drawer faz: `only: ['detalhe']` com `?licenca={id}`. */
function oiLicDetalheResposta($test, int $id)
{
    oiLicFlag(true);
    $r = $test->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Officeimpresso/Licencas/Index',
        'X-Inertia-Partial-Data' => 'detalhe',
    ])->get('/officeimpresso/licenca_computador?licenca=' . $id);
    $r->assertOk();

    return $r;
}

function oiLicParcial($test): array
{
    return oiLicParcialResposta($test)->json('props.licencas') ?? [];
}

function oiLicFlag(bool $ligada): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class($ligada) extends \App\Services\FeatureFlagService
    {
        public function __construct(private bool $ligada) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $this->ligada;
        }
    });
}
