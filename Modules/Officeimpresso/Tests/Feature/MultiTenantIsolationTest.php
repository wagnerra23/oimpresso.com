<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Isolamento entre empresas (Tier 0, ADR 0093) chamando as ROTAS do módulo.
 *
 * A versão anterior deste arquivo inseria em biz=1 e consultava com `where('business_id', 99)`
 * escrito no próprio teste: provava o SQL do teste, não o código do módulo (auditoria 2026-11-05,
 * eixo 1, achado 7). As Entities do módulo não têm global scope; quem isola é o controller, e é
 * ele que este arquivo exercita.
 *
 * Só entra aqui o que os contratos da lane ainda não cobrem. Já cobertos e NÃO repetidos:
 *   - lista e drawer de licenças escopados à sessão → LicencasIndexContratoTest UC-OILIC-04/11;
 *   - bloquear máquina de outra empresa → LicencasIndexContratoTest + ComputadoresEmpresaAcoes UC-EMP-24;
 *   - lista de credenciais OAuth da sessão → ClientesSegredoUmaVezTest ("98 não vê o client do 99");
 *   - empresa cliente lendo licença/log → LicencasAcessoPermissionTest ("operador · ... 403").
 *
 * Tenant 98 = operadora, 99 = empresa cliente (ADR 0358, `seededTenant()`). Nunca biz=1 nem biz=4.
 * Cada caso tem uma âncora positiva (a mesma ação no próprio tenant produz efeito), senão um
 * "nada mudou" passaria também quando a requisição nem chegou ao controller.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Passport + schema UltimatePOS exigem MySQL (ADR 0358).');
    }
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';
    // O guard auth:api lê a chave pública do Passport. A lane roda em ordem aleatória: sem
    // isto o caso do audit só passava quando outro arquivo tinha gerado as chaves antes.
    if (! file_exists(storage_path('oauth-private.key')) || ! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }

    foreach (['superadmin', 'officeimpresso.access', 'officeimpresso.licencas.gerenciar'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->oiIsoOperadora = $this->seededTenant();
    $this->oiIsoCliente = $this->seededSupportClientTenant();
    config(['constants.operator_business_id' => (int) $this->oiIsoOperadora->id]);
});

it('[T0] excluir credencial OAuth só apaga a da empresa da sessão — a de outra empresa fica', function () {
    $superadmin = oiIsoUser((int) $this->oiIsoOperadora->id, ['superadmin']);
    $minha = oiIsoClient(oiIsoUser((int) $this->oiIsoOperadora->id, []), 'OIISO credencial 98');
    $alheia = oiIsoClient(oiIsoUser((int) $this->oiIsoCliente->id, []), 'OIISO credencial 99');

    $this->actingAs($superadmin);
    $this->delete('/officeimpresso/client/'.$alheia)->assertRedirect();
    $this->delete('/officeimpresso/client/'.$minha)->assertRedirect();

    expect(DB::table('oauth_clients')->where('id', $alheia)->exists())->toBeTrue();
    // âncora positiva: a mesma rota apagou a credencial do próprio tenant
    expect(DB::table('oauth_clients')->where('id', $minha)->exists())->toBeFalse();
});

it('[T0] o formulário de edição de máquina não abre máquina de outra empresa', function () {
    $operador = oiIsoUser((int) $this->oiIsoOperadora->id, ['officeimpresso.licencas.gerenciar']);
    $minha = oiIsoMaquina((int) $this->oiIsoOperadora->id);
    $alheia = oiIsoMaquina((int) $this->oiIsoCliente->id);

    $this->actingAs($operador);
    $this->get('/officeimpresso/licenca_computador/'.$alheia.'/edit')->assertNotFound();
    // âncora positiva: a máquina do próprio tenant abre e o formulário recebe ESSA máquina.
    // (O Blade do formulário não imprime o HD — medido na CI, run 44383147672 — então a
    // prova é a variável que o controller entrega à view.)
    $this->get('/officeimpresso/licenca_computador/'.$minha.'/edit')
        ->assertOk()
        ->assertViewHas('licenca', fn ($l) => (int) $l->id === $minha && (int) $l->business_id === (int) $this->oiIsoOperadora->id);
});

it('[T0] o audit do desktop grava a empresa do TOKEN; business_id no corpo não troca de empresa', function () {
    $desktop = oiIsoUser((int) $this->oiIsoCliente->id, []);
    Passport::actingAs($desktop, ['*']);

    $r = $this->postJson('/api/officeimpresso/audit', [
        // evento da lista do desktop: o #11157 (Lote 3) fecha o endpoint aos eventos `desktop_*`
        'event'       => 'desktop_timeout',
        'business_id' => (int) $this->oiIsoOperadora->id,
        'endpoint'    => '/oiiso',
    ])->assertCreated();

    $log = DB::table('licenca_log')->where('id', $r->json('id'))->first();
    expect($log)->not->toBeNull();
    expect((int) $log->business_id)->toBe((int) $this->oiIsoCliente->id);
    expect((int) $log->user_id)->toBe((int) $desktop->id);
});

/** User SEM role — senão o Gate::before faria bypass. Permissões dadas direto. */
function oiIsoUser(int $businessId, array $permissoes): User
{
    $user = User::create([
        'business_id' => $businessId,
        'first_name'  => 'OI',
        'surname'     => 'Isolamento',
        'username'    => 'oi_iso_'.$businessId.'_'.uniqid(),
        'email'       => 'oi_iso_'.$businessId.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
    if ($permissoes) {
        $user->givePermissionTo($permissoes);
    }

    return $user;
}

function oiIsoMaquina(int $businessId): int
{
    return (int) DB::table('licenca_computador')->insertGetId([
        'business_id' => $businessId,
        'hd'          => 'OIISO-'.$businessId.'-'.uniqid(),
        'hostname'    => 'OIISO',
        'user_win'    => 'OIISO',
        'bloqueado'   => 0,
    ]);
}

/** Query builder, não o model: o Passport 13 gera UUID no id e o schema legado é int (#8362). */
function oiIsoClient(User $dono, string $nome): int
{
    return (int) DB::table('oauth_clients')->insertGetId([
        'user_id'                => $dono->id,
        'name'                   => $nome,
        'secret'                 => bin2hex(random_bytes(20)),
        'redirect'               => 'http://localhost',
        'personal_access_client' => 0,
        'password_client'        => 0,
        'revoked'                => 0,
        'created_at'             => now(),
        'updated_at'             => now(),
    ]);
}
