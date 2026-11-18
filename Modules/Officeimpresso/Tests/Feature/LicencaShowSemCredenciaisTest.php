<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * `GET /officeimpresso/licenca_computador/{id}` (LicencaComputadorController::show).
 *
 * Até 2026-11 devolvia o model `Licenca_Computador` cru, que não tem `$hidden`: senha,
 * contra-senha, serial, usuário, conexão e caminho do banco do desktop saíam para quem tinha
 * só `officeimpresso.access`, a permissão de leitura delegável ao suporte (auditoria
 * Officeimpresso 2026-11-05, eixo 5, A2). Agora sai um DTO com lista de permissão.
 *
 * Tenant 98 = empresa operadora; 99 = empresa cliente (ADR 0358). Valores de segredo são
 * fictícios. As máquinas criadas aqui são apagadas no afterEach. MySQL-only.
 *
 * @see Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::show
 */

defined('OISHOW_CAMPOS') || define('OISHOW_CAMPOS', [
    'id', 'business_id', 'hostname', 'user_win', 'hd', 'sistema_operacional', 'ip_interno',
    'processador', 'memoria', 'pasta_instalacao', 'versao_exe', 'versao_banco',
    'dt_cadastro', 'dt_ultimo_acesso', 'dt_ultima_assistencia', 'dt_validade',
    'bloqueado', 'motivo', 'created_at', 'updated_at',
]);

defined('OISHOW_SEGREDOS') || define('OISHOW_SEGREDOS', [
    'senha'         => 'OISHOW-SEN-X1',
    'contra_senha'  => 'OISHOW-CONTRA-X2',
    'serial'        => 'OISHOW-SERIAL-X3',
    'usuario'       => 'OISHOW-USU-X4',
    'conexao'       => 'OISHOW-CONEXAO-X5',
    'caminho_banco' => 'OISHOW-BANCO-X6.FDB',
]);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS exige MySQL (ADR 0358).');
    }
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    Permission::firstOrCreate(['name' => 'officeimpresso.access', 'guard_name' => 'web']);
    $this->oiShowOperadora = $this->seededTenant();
    $this->oiShowCliente = $this->seededSupportClientTenant();
    config(['constants.operator_business_id' => (int) $this->oiShowOperadora->id]);
    $this->oiShowMaquinas = [];
    $this->oiShowUsers = [];
});

afterEach(function () {
    if ($this->oiShowMaquinas ?? []) {
        DB::table('licenca_computador')->whereIn('id', $this->oiShowMaquinas)->delete();
    }
    foreach ($this->oiShowUsers ?? [] as $u) {
        $u->forceDelete();
    }
});

it('devolve só os campos da lista: nenhuma chave nem valor de credencial sai', function () {
    $id = oiShowMaquina($this, (int) $this->oiShowCliente->id, OISHOW_SEGREDOS);
    $this->actingAs(oiShowUser($this, (int) $this->oiShowOperadora->id));

    $r = $this->getJson('/officeimpresso/licenca_computador/'.$id)->assertOk();

    expect(array_keys($r->json()))->toEqual(OISHOW_CAMPOS);
    expect($r->json('id'))->toBe($id);
    expect($r->json('business_id'))->toBe((int) $this->oiShowCliente->id);

    $corpo = $r->getContent();
    foreach (OISHOW_SEGREDOS as $chave => $valor) {
        expect($r->json())->not->toHaveKey($chave);
        $this->assertStringNotContainsString($valor, $corpo, "o valor de `{$chave}` vazou no JSON");
    }
    foreach (['token', 'exe_caminho_banco'] as $chave) {
        expect($r->json())->not->toHaveKey($chave);
    }
});

it('[T0] usuário de empresa cliente (99) com officeimpresso.access não lê máquina da operadora (98)', function () {
    $id = oiShowMaquina($this, (int) $this->oiShowOperadora->id, OISHOW_SEGREDOS);
    $this->actingAs(oiShowUser($this, (int) $this->oiShowCliente->id));

    $r = $this->getJson('/officeimpresso/licenca_computador/'.$id);

    $r->assertForbidden();
    expect($r->getContent())->not->toContain('OISHOW-SERIAL-X3');
});

it('id inexistente responde 404 para a operadora', function () {
    $this->actingAs(oiShowUser($this, (int) $this->oiShowOperadora->id));
    $semMaquina = (int) DB::table('licenca_computador')->max('id') + 1000;

    $this->getJson('/officeimpresso/licenca_computador/'.$semMaquina)->assertNotFound();
});

// ── Helpers (prefixo oiShow — outros arquivos do módulo rodam no mesmo processo) ──

/** User SEM role — senão o Gate::before faria bypass de tudo. */
function oiShowUser($test, int $businessId): User
{
    $user = User::create([
        'business_id' => $businessId,
        'first_name'  => 'OI',
        'surname'     => 'Show',
        'username'    => 'oi_show_'.$businessId.'_'.uniqid(),
        'email'       => 'oi_show_'.$businessId.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
    $user->givePermissionTo('officeimpresso.access');
    $test->oiShowUsers[] = $user;

    return $user;
}

function oiShowMaquina($test, int $businessId, array $attrs = []): int
{
    $id = (int) DB::table('licenca_computador')->insertGetId(array_merge([
        'business_id' => $businessId,
        'hd'          => 'OISHOW-'.uniqid(),
        'hostname'    => 'OISHOW',
        'user_win'    => 'OISHOW',
        'bloqueado'   => 0,
    ], $attrs));
    $test->oiShowMaquinas[] = $id;

    return $id;
}
