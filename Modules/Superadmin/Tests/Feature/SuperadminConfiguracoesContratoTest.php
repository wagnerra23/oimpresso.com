<?php

declare(strict_types=1);

use App\Business;
use App\User;
use App\Utils\BusinessUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Superadmin\Http\Controllers\SuperadminSettingsController;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-008

/**
 * Contrato da tela `/superadmin/settings` — thread Superadmin/05, parte 2 (Blade → Inertia).
 * UCs: Modules/Superadmin/Resources/js/Pages/superadmin/Configuracoes/Index.casos.md
 *
 * O `update()` grava no `.env`. O teste troca o controller por uma subclasse que grava num
 * arquivo temporário (`envPath()`): o `.env` real do runner nunca é tocado.
 *
 * ⚠️ SKIP em SQLite: precisa do schema UltimatePOS real. Leia *assertions*, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /superadmin/settings requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('system') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
    config(['constants.administrator_usernames' => 'cfg_superadmin_test']);
});

/** Tenant fictício. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_CFG = 98;

const ROTA_CFG = '/superadmin/settings';

/** Valor fictício de senha — o caso prova que ELE não aparece no payload. */
const SEGREDO_CFG = 'senha-ficticia-uc-sacfg-7Q2';

function cfgUsuario(string $username, bool $superadmin): User
{
    Business::firstOrCreate(['id' => BIZ_CFG], ['name' => 'Tenant fictício configurações', 'currency_id' => 1]);

    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username . '@test.local', 'password' => bcrypt('secret'),
        'business_id' => BIZ_CFG, 'first_name' => 'Cfg', 'last_name' => 'Teste',
    ]);

    if ($superadmin) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']));
    } else {
        $user->syncRoles([]);
        $user->syncPermissions([]);
    }

    return $user;
}

/** Põe uma variável de ambiente onde o `env()` do Laravel lê, e devolve como desfazer. */
function cfgEnv(string $chave, ?string $valor): void
{
    if ($valor === null) {
        unset($_ENV[$chave], $_SERVER[$chave]);
        putenv($chave);

        return;
    }
    $_ENV[$chave] = $valor;
    $_SERVER[$chave] = $valor;
    putenv($chave . '=' . $valor);
}

/** Arquivo `.env` temporário + controller que grava nele. Devolve o caminho. */
function cfgEnvTemporario(string $conteudo): string
{
    $caminho = tempnam(sys_get_temp_dir(), 'cfg-env-');
    file_put_contents($caminho, $conteudo);

    $controller = new class(app(BusinessUtil::class)) extends SuperadminSettingsController {
        public static string $caminho = '';

        protected function envPath(): string
        {
            return self::$caminho;
        }
    };
    $controller::$caminho = $caminho;
    app()->instance(SuperadminSettingsController::class, $controller);

    return $caminho;
}

/** PUT da tela, dentro de transação: os checkboxes da tabela `system` voltam ao que eram. */
function cfgSalvar(array $dados): void
{
    DB::beginTransaction();
    try {
        test()->actingAs(cfgUsuario('cfg_superadmin_test', true))
            ->put(ROTA_CFG, $dados)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    } finally {
        DB::rollBack();
    }
}

it('UC-SACFG-01 · a tela responde Inertia com o componente novo', function () {
    $this->actingAs(cfgUsuario('cfg_superadmin_test', true))
        ->get(ROTA_CFG)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('superadmin/Configuracoes/Index'));
});

it('UC-SACFG-02 · admin de negócio é barrado enquanto o superadmin passa', function () {
    $barrado = $this->actingAs(cfgUsuario('cfg_admin_test', false))->get(ROTA_CFG);
    expect($barrado->getStatusCode())->toBeIn([302, 403]);

    $this->actingAs(cfgUsuario('cfg_superadmin_test', true))->get(ROTA_CFG)->assertOk();
});

it('UC-SACFG-03 · o segredo nunca sai nas props, só o "definido"', function () {
    $antes = env('MAIL_PASSWORD');
    cfgEnv('MAIL_PASSWORD', SEGREDO_CFG);

    try {
        $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
        $resposta = $this->actingAs(cfgUsuario('cfg_superadmin_test', true))->get(ROTA_CFG, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $versao,
            'X-Inertia-Partial-Data' => 'config',
            'X-Inertia-Partial-Component' => 'superadmin/Configuracoes/Index',
        ]);
        $resposta->assertOk();

        // Âncora positiva: o payload deferred foi de fato calculado (senão o "não contém" passa por vácuo).
        expect($resposta->json('props.config.segredos.MAIL_PASSWORD'))->toBeTrue();
        expect($resposta->json('props.config.valores'))->not->toHaveKey('MAIL_PASSWORD');
        expect($resposta->getContent())->not->toContain(SEGREDO_CFG);
    } finally {
        cfgEnv('MAIL_PASSWORD', $antes === null ? null : (string) $antes);
    }
});

it('UC-SACFG-04 · segredo em branco mantém a senha gravada', function () {
    $caminho = cfgEnvTemporario("MAIL_HOST=\"antigo.local\"\nMAIL_PASSWORD=\"" . SEGREDO_CFG . "\"\n");

    cfgSalvar(['MAIL_HOST' => 'novo.local', 'MAIL_PASSWORD' => '']);

    $gravado = (string) file_get_contents($caminho);
    @unlink($caminho);

    // O outro campo foi regravado — prova que o update rodou sobre ESTE arquivo.
    expect($gravado)->toContain('MAIL_HOST="novo.local"');
    expect($gravado)->toContain('MAIL_PASSWORD="' . SEGREDO_CFG . '"');
});

it('UC-SACFG-05 · segredo preenchido é regravado', function () {
    $caminho = cfgEnvTemporario('MAIL_PASSWORD="' . SEGREDO_CFG . "\"\n");

    cfgSalvar(['MAIL_PASSWORD' => 'senha-nova-uc-sacfg-05']);

    $gravado = (string) file_get_contents($caminho);
    @unlink($caminho);

    expect($gravado)->toContain('MAIL_PASSWORD="senha-nova-uc-sacfg-05"');
    expect($gravado)->not->toContain(SEGREDO_CFG);
});

it('UC-SACFG-06 · quebra de linha num valor não abre linha nova no .env', function () {
    $caminho = cfgEnvTemporario("MAIL_FROM_NAME=\"Plataforma\"\n");

    cfgSalvar(['MAIL_FROM_NAME' => "Plataforma\nINJETADO_UC_SACFG=1"]);

    $linhas = file($caminho, FILE_IGNORE_NEW_LINES);
    @unlink($caminho);

    expect(collect($linhas)->first(fn ($l) => str_starts_with((string) $l, 'MAIL_FROM_NAME=')))->not->toBeNull();
    expect(collect($linhas)->first(fn ($l) => str_starts_with((string) $l, 'INJETADO_UC_SACFG')))->toBeNull();
});
