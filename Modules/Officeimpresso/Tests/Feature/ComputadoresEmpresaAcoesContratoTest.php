<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Contrato das AÇÕES na ficha React (`Officeimpresso/Empresa/Show`): editar a configuração do
 * desktop, bloquear/liberar a empresa inteira (empresa-parity 16-21) e bloquear/liberar uma máquina
 * (item 34). Casos: Pages/Officeimpresso/Empresa/Show.casos.md (UC-EMP-08, 09, 16, 22, 23, 24),
 * derivados do empresa-parity.md e do RUNBOOK-empresa — não do .tsx.
 *
 * Tenant 98 (operadora) × 99 (cliente) — ADR 0358. As colunas mexidas na empresa 99 são lidas antes e
 * devolvidas no afterEach: no CT 100 o banco persiste entre runs. MySQL-only.
 *
 * @covers-us US-OI-011
 * @see Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::businessupdate
 * @see Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::businessbloqueado
 * @see Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::toggleBlock
 */

const OI_ACOES_COLUNAS = ['caminho_banco_servidor', 'versao_obrigatoria', 'versao_disponivel', 'officeimpresso_bloqueado'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS exige MySQL (ADR 0358).');
    }
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    foreach (['officeimpresso.access', 'officeimpresso.empresa.gerenciar', 'officeimpresso.licencas.gerenciar'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->oiAcoesOperadora = $this->seededTenant();
    $this->oiAcoesCliente = $this->seededSupportClientTenant();
    config(['constants.operator_business_id' => (int) $this->oiAcoesOperadora->id]);
    $this->oiAcoesUsers = [];
    $this->oiAcoesMaquinas = [];
    $this->oiAcoesAntes = [];
    foreach ([$this->oiAcoesOperadora, $this->oiAcoesCliente] as $b) {
        $this->oiAcoesAntes[(int) $b->id] = (array) DB::table('business')->where('id', $b->id)->first(OI_ACOES_COLUNAS);
    }
});

afterEach(function () {
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    foreach ($this->oiAcoesAntes as $id => $colunas) {
        DB::table('business')->where('id', $id)->update($colunas);
    }
    if ($this->oiAcoesMaquinas) {
        DB::table('licenca_log')->whereIn('licenca_id', $this->oiAcoesMaquinas)->delete();
        DB::table('licenca_computador')->whereIn('id', $this->oiAcoesMaquinas)->delete();
    }
    foreach ($this->oiAcoesUsers as $u) {
        $u->forceDelete();
    }
});

it('UC-EMP-22 · a ficha diz o que o usuário pode fazer: só leitura não vê ação de empresa', function () {
    oiAcoesFlag();
    $this->actingAs(oiAcoesUser($this, $this->oiAcoesOperadora, ['officeimpresso.access']));
    $this->get('/officeimpresso/computadores')->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Officeimpresso/Empresa/Show')
        ->where('permissions.empresa', false)
        ->where('permissions.gerenciar', false));

    $this->actingAs(oiAcoesUser($this, $this->oiAcoesOperadora, ['officeimpresso.access', 'officeimpresso.empresa.gerenciar']));
    $this->get('/officeimpresso/computadores')->assertInertia(fn (AssertableInertia $p) => $p
        ->where('permissions.empresa', true)
        ->where('permissions.gerenciar', false));

    $this->actingAs(oiAcoesUser($this, $this->oiAcoesOperadora, ['officeimpresso.access', 'officeimpresso.licencas.gerenciar']));
    $this->get('/officeimpresso/computadores')->assertInertia(fn (AssertableInertia $p) => $p
        ->where('permissions.empresa', false)
        ->where('permissions.gerenciar', true));
});

it('UC-EMP-16 · bloquear máquina pela ficha exige motivo, registra no histórico e a tabela mostra o novo status', function () {
    oiAcoesFlag();
    $biz = (int) $this->oiAcoesCliente->id;
    $id = oiAcoesMaquina($this, $biz);
    $this->actingAs(oiAcoesUser($this, $this->oiAcoesOperadora, ['officeimpresso.access', 'officeimpresso.licencas.gerenciar']));
    $url = '/officeimpresso/licenca_computador/'.$id.'/toggle-block';

    $this->post($url, ['bloquear' => true, 'motivo' => 'ab'])->assertSessionHasErrors('motivo');
    expect((bool) DB::table('licenca_computador')->where('id', $id)->value('bloqueado'))->toBeFalse();

    $this->post($url, ['bloquear' => true, 'motivo' => 'máquina trocada'])->assertRedirect()->assertSessionHasNoErrors();
    expect((int) DB::table('licenca_log')->where('licenca_id', $id)->where('event', 'maquina_bloqueada')->value('business_id'))->toBe($biz);

    $linha = collect(oiAcoesParcial($this, $biz)->json('props.computadores'))->firstWhere('id', $id);
    expect($linha['bloqueado'])->toBeTrue();
});

it('UC-EMP-24 · [T0] usuário de empresa CLIENTE com licencas.gerenciar não bloqueia máquina de outra empresa', function () {
    $id = oiAcoesMaquina($this, (int) $this->oiAcoesOperadora->id);
    $this->actingAs(oiAcoesUser($this, $this->oiAcoesCliente, ['officeimpresso.access', 'officeimpresso.licencas.gerenciar']));

    $this->post('/officeimpresso/licenca_computador/'.$id.'/toggle-block', ['bloquear' => true, 'motivo' => 'tentativa de fora'])
        ->assertForbidden();

    expect((bool) DB::table('licenca_computador')->where('id', $id)->value('bloqueado'))->toBeFalse();
    expect(DB::table('licenca_log')->where('licenca_id', $id)->count())->toBe(0);
});

it('UC-EMP-08 · editar grava caminho do banco e versões da empresa da URL', function () {
    $alvo = (int) $this->oiAcoesCliente->id;
    $this->actingAs(oiAcoesUser($this, $this->oiAcoesOperadora, ['officeimpresso.empresa.gerenciar']));

    $this->post('/officeimpresso/licenca_computador/businessupdate/'.$alvo, [
        'caminho_banco_servidor' => 'C:\\OIACOES\\BANCO.FDB',
        'versao_obrigatoria'     => '9.1.2',
        'versao_disponivel'      => '9.1.3',
    ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status');

    $b = DB::table('business')->where('id', $alvo)->first(OI_ACOES_COLUNAS);
    expect($b->caminho_banco_servidor)->toBe('C:\\OIACOES\\BANCO.FDB');
    expect($b->versao_obrigatoria)->toBe('9.1.2');
    expect($b->versao_disponivel)->toBe('9.1.3');
});

it('UC-EMP-08 · o erro de validação volta no campo que o formulário manda e nada é gravado (parity D3)', function () {
    $alvo = (int) $this->oiAcoesCliente->id;
    $antes = DB::table('business')->where('id', $alvo)->value('caminho_banco_servidor');
    $this->actingAs(oiAcoesUser($this, $this->oiAcoesOperadora, ['officeimpresso.empresa.gerenciar']));

    $this->post('/officeimpresso/licenca_computador/businessupdate/'.$alvo, [
        'caminho_banco_servidor' => str_repeat('x', 256),
        'versao_obrigatoria'     => '9.9.9',
    ])->assertRedirect()->assertSessionHasErrors('caminho_banco_servidor');

    expect(DB::table('business')->where('id', $alvo)->value('caminho_banco_servidor'))->toBe($antes);
    expect(DB::table('business')->where('id', $alvo)->value('versao_obrigatoria'))->not->toBe('9.9.9');
});

it('UC-EMP-09 · bloquear é intenção: bloqueia uma vez, o segundo pedido não desfaz', function () {
    $alvo = (int) $this->oiAcoesCliente->id;
    DB::table('business')->where('id', $alvo)->update(['officeimpresso_bloqueado' => 0]);
    $this->actingAs(oiAcoesUser($this, $this->oiAcoesOperadora, ['officeimpresso.empresa.gerenciar']));
    $url = '/officeimpresso/licenca_computador/businessbloqueado/'.$alvo;

    $this->post($url, ['bloquear' => true])->assertRedirect()->assertSessionHasNoErrors();
    expect((bool) DB::table('business')->where('id', $alvo)->value('officeimpresso_bloqueado'))->toBeTrue();

    $this->post($url, ['bloquear' => true])->assertRedirect()->assertSessionHasErrors('bloquear');
    expect((bool) DB::table('business')->where('id', $alvo)->value('officeimpresso_bloqueado'))->toBeTrue();

    $this->post($url, ['bloquear' => false])->assertRedirect()->assertSessionHasNoErrors();
    expect((bool) DB::table('business')->where('id', $alvo)->value('officeimpresso_bloqueado'))->toBeFalse();
});

it('UC-EMP-23 · [T0] usuário de empresa CLIENTE com a permissão não edita nem bloqueia outra empresa', function () {
    $alvo = (int) $this->oiAcoesOperadora->id;
    $antes = (array) DB::table('business')->where('id', $alvo)->first(OI_ACOES_COLUNAS);
    $this->actingAs(oiAcoesUser($this, $this->oiAcoesCliente, ['officeimpresso.access', 'officeimpresso.empresa.gerenciar']));

    $this->post('/officeimpresso/licenca_computador/businessupdate/'.$alvo, [
        'caminho_banco_servidor' => 'C:\\OIACOES\\INVASOR.FDB',
        'versao_obrigatoria'     => '0.0.1',
    ])->assertForbidden();
    $this->post('/officeimpresso/licenca_computador/businessbloqueado/'.$alvo, ['bloquear' => ! $antes['officeimpresso_bloqueado']])
        ->assertForbidden();

    expect((array) DB::table('business')->where('id', $alvo)->first(OI_ACOES_COLUNAS))->toEqual($antes);
});

// ── Helpers (prefixo oiAcoes — outros arquivos do módulo rodam no mesmo processo) ──

function oiAcoesFlag(): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function __construct() {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2OfficeimpressoEmpresa';
        }
    });
}

function oiAcoesMaquina($test, int $businessId): int
{
    $id = (int) DB::table('licenca_computador')->insertGetId([
        'business_id' => $businessId,
        'hd'          => 'OIACOES11-'.uniqid(),
        'hostname'    => 'OIACOES11',
        'user_win'    => 'OIACOES11',
        'bloqueado'   => 0,
    ]);
    $test->oiAcoesMaquinas[] = $id;

    return $id;
}

/** Partial reload do navegador pedindo as máquinas da ficha de `$businessId`. */
function oiAcoesParcial($test, int $businessId)
{
    $r = $test->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Officeimpresso/Empresa/Show',
        'X-Inertia-Partial-Data' => 'computadores',
    ])->get('/officeimpresso/licenca_computado/licencas/'.$businessId);
    $r->assertOk();

    return $r;
}

/** User SEM role — senão o Gate::before faria bypass. Permissões dadas direto. */
function oiAcoesUser($test, $business, array $permissoes): User
{
    $biz = (int) $business->id;
    $user = User::create([
        'business_id' => $biz,
        'first_name'  => 'OI',
        'surname'     => 'Acoes11',
        'username'    => 'oi_acoes11_'.$biz.'_'.uniqid(),
        'email'       => 'oi_acoes11_'.$biz.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
    $user->givePermissionTo($permissoes);
    $test->oiAcoesUsers[] = $user;

    return $user;
}
