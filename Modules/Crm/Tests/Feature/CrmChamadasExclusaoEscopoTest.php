<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Exclusão em massa de registros de chamada segue o escopo de quem exclui — achado B2 da thread Crm/12.
 *
 * Âncora: memory/requisitos/Crm/chamadas-parity.md §Achados B2 + RUNBOOK-chamadas.md §5.
 * `massDestroy` exigia só `crm_module`: quem só vê os próprios registros (`crm.view_own_call_log`)
 * apagava o de colega do mesmo negócio pelo id. Decisão do gerente da fila (13/10): mesma regra
 * do destroy individual — "só os seus" apaga só o que é seu; sem permissão de chamada, 403.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/CallLogController::massDestroy()
 */
const CHAE_TAG = '[chae12]';
const CHAE_BIZ = 98;
const CHAE_OUTRO = 99;
const CHAE_ROTA = '/crm/mass-delete-call-log';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_call_logs requer schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'crm_call_logs'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    chaeLimpa();
});

afterEach(fn () => chaeLimpa());

function chaeLimpa(): void
{
    if (Schema::hasTable('crm_call_logs')) {
        DB::table('crm_call_logs')->where('mobile_name', CHAE_TAG)->delete();
    }
}

/** Mesmo conserto do CrmAcompanhamentosContratoTest::acoNegocio — `id` é guarded. */
function chaeNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function chaeUsuario(string $username, array $permissoes, int $biz = CHAE_BIZ): User
{
    chaeNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Chae', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function chaeRegistro(int $biz, User $criador): int
{
    chaeNegocio($biz);

    return DB::table('crm_call_logs')->insertGetId([
        'business_id' => $biz, 'user_id' => $criador->id, 'call_type' => 'outgoing',
        'mobile_number' => '0', 'mobile_name' => CHAE_TAG, 'start_time' => now(), 'end_time' => now(),
        'duration' => 60, 'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function chaeExiste(int $id): bool
{
    return DB::table('crm_call_logs')->where('id', $id)->exists();
}

it('UC-CHAE-01 · quem só vê os próprios registros apaga só os seus, não o do colega', function () {
    $proprio = chaeUsuario('chae_proprio_test', ['crm.view_own_call_log']);
    $colega = chaeUsuario('chae_colega_test', ['crm.view_all_call_log']);
    $meu = chaeRegistro(CHAE_BIZ, $proprio);
    $doColega = chaeRegistro(CHAE_BIZ, $colega);

    $this->actingAs($proprio)->post(CHAE_ROTA, ['selected_rows' => $meu.','.$doColega])->assertRedirect();

    $this->assertFalse(chaeExiste($meu), 'não apagou o próprio registro');
    $this->assertTrue(chaeExiste($doColega), 'quem só vê os próprios apagou o registro do colega');
});

it('UC-CHAE-02 · quem vê todos apaga qualquer registro do negócio', function () {
    $todos = chaeUsuario('chae_todos_test', ['crm.view_all_call_log']);
    $colega = chaeUsuario('chae_colega_test', ['crm.view_own_call_log']);
    $doColega = chaeRegistro(CHAE_BIZ, $colega);

    $this->actingAs($todos)->post(CHAE_ROTA, ['selected_rows' => (string) $doColega])->assertRedirect();

    $this->assertFalse(chaeExiste($doColega), 'quem vê todos não conseguiu apagar o registro do colega');
});

it('UC-CHAE-03 · registro de outro negócio nunca é apagado [T0]', function () {
    $todos = chaeUsuario('chae_todos_test', ['crm.view_all_call_log']);
    $vizinho = chaeUsuario('chae_vizinho_test', ['crm.view_all_call_log'], CHAE_OUTRO);
    $deFora = chaeRegistro(CHAE_OUTRO, $vizinho);

    $this->actingAs($todos)->post(CHAE_ROTA, ['selected_rows' => (string) $deFora])->assertRedirect();

    $this->assertTrue(chaeExiste($deFora), 'apagou registro de chamada de outro business');
});

it('UC-CHAE-04 · sem permissão de registro de chamada é barrado e nada é apagado', function () {
    $sem = chaeUsuario('chae_sem_test', []);
    $colega = chaeUsuario('chae_colega_test', ['crm.view_all_call_log']);
    $doColega = chaeRegistro(CHAE_BIZ, $colega);

    $this->actingAs($sem)->post(CHAE_ROTA, ['selected_rows' => (string) $doColega])->assertForbidden();

    $this->assertTrue(chaeExiste($doColega), 'quem não tem permissão de chamada apagou registro');
});
