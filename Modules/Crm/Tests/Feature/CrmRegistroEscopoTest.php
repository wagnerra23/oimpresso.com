<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Thread Crm/10 · o registro de acompanhamento (`ScheduleLogController`) segue o escopo da
 * leitura — D5 ([W] 2026-10-07): "restringir: store respeita access_own_schedule; o dono
 * decide pelo papel; sem config nova".
 *
 * UC-CRMACO-22 (Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/Index.casos.md):
 *   só-own → acompanhamento de colega do mesmo negócio → 403 e nada gravado;
 *   só-own → o próprio → grava;  all → o de colega → grava.
 * UC-CRMACO-23 [T0]: editar e excluir registro de acompanhamento de outro negócio → 404 e
 * nada muda (antes o `update` sem `status` e o `destroy` não conferiam o negócio).
 *
 * Valores lidos do banco. Tenant 98 e 99 (ADR 0358). NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
const RGE_TAG = '[crm10]';
const RGE_BIZ = 98;
const RGE_OUTRO = 99;
const RGE_AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_schedules requer schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'crm_schedules', 'crm_schedule_users', 'crm_schedule_logs'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    rgeLimpa();
});

afterEach(fn () => rgeLimpa());

function rgeLimpa(): void
{
    if (Schema::hasTable('crm_schedules')) {
        $ids = DB::table('crm_schedules')->where('title', 'like', '%'.RGE_TAG.'%')->pluck('id');
        DB::table('crm_schedule_logs')->whereIn('schedule_id', $ids)->delete();
        DB::table('crm_schedule_users')->whereIn('schedule_id', $ids)->delete();
        DB::table('crm_schedules')->whereIn('id', $ids)->delete();
    }
    if (Schema::hasTable('contacts')) {
        DB::table('contacts')->where('name', 'like', '%'.RGE_TAG.'%')->delete();
    }
}

/** Mesmo conserto de FK do CrmAcompanhamentosContratoTest::acoNegocio (id é guarded). */
function rgeNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function rgeUsuario(string $username, array $permissoes, int $biz = RGE_BIZ): User
{
    rgeNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Rge', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));

    return $user;
}

/** Acompanhamento criado por e atribuído a `$dono`. */
function rgeAcompanhamento(int $biz, string $titulo, User $dono): int
{
    rgeNegocio($biz);
    $contato = DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Contato '.RGE_TAG, 'mobile' => '0',
        'created_by' => $dono->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $id = DB::table('crm_schedules')->insertGetId([
        'business_id' => $biz, 'contact_id' => $contato, 'title' => $titulo.' '.RGE_TAG,
        'status' => 'scheduled', 'schedule_type' => 'call', 'is_recursive' => 0,
        'start_datetime' => now(), 'end_datetime' => now()->addMinutes(30),
        'created_by' => $dono->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('crm_schedule_users')->insert(['schedule_id' => $id, 'user_id' => $dono->id, 'created_at' => now(), 'updated_at' => now()]);

    return $id;
}

function rgeRegistro(int $scheduleId, User $autor, string $assunto): int
{
    return DB::table('crm_schedule_logs')->insertGetId([
        'schedule_id' => $scheduleId, 'subject' => $assunto, 'log_type' => 'call',
        'start_datetime' => now(), 'end_datetime' => now()->addMinutes(5),
        'created_by' => $autor->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function rgeCorpo(int $scheduleId, string $assunto): array
{
    return [
        'schedule_id' => $scheduleId, 'subject' => $assunto, 'log_type' => 'call',
        'start_datetime' => '2026-10-10T09:00', 'end_datetime' => '2026-10-10T09:10',
        'description' => '', 'status' => 'completed',
    ];
}

it('UC-CRMACO-22 · quem só vê os próprios não registra em acompanhamento de colega; registra no seu; quem vê todos registra no do colega', function () {
    $colega = rgeUsuario('rge_colega_test', ['crm.access_own_schedule']);
    $soMeus = rgeUsuario('rge_so_meus_test', ['crm.access_own_schedule']);
    $todos = rgeUsuario('rge_todos_test', ['crm.access_all_schedule']);

    $doColega = rgeAcompanhamento(RGE_BIZ, 'Do colega', $colega);
    $meu = rgeAcompanhamento(RGE_BIZ, 'Meu', $soMeus);

    // só-own → colega: 403, nada gravado, status intacto.
    $this->actingAs($soMeus)->post('/crm/follow-up-log', rgeCorpo($doColega, 'Intruso'), RGE_AJAX)->assertForbidden();
    expect(DB::table('crm_schedule_logs')->where('schedule_id', $doColega)->count())->toBe(0);
    expect(DB::table('crm_schedules')->where('id', $doColega)->value('status'))->toBe('scheduled');

    // só-own → o próprio: grava (controle positivo do mesmo papel).
    $this->actingAs($soMeus)->post('/crm/follow-up-log', rgeCorpo($meu, 'No meu'), RGE_AJAX)
        ->assertOk()->assertJson(['success' => true]);
    expect(DB::table('crm_schedule_logs')->where('schedule_id', $meu)->value('subject'))->toBe('No meu');

    // all → o do colega: grava (o recorte é pelo papel, não pela tela).
    $this->actingAs($todos)->post('/crm/follow-up-log', rgeCorpo($doColega, 'Pelo gestor'), RGE_AJAX)
        ->assertOk()->assertJson(['success' => true]);
    expect(DB::table('crm_schedule_logs')->where('schedule_id', $doColega)->value('subject'))->toBe('Pelo gestor');
});

it('UC-CRMACO-22 · quem só vê os próprios também não edita nem exclui registro do acompanhamento de colega', function () {
    $colega = rgeUsuario('rge_colega_test', ['crm.access_own_schedule']);
    $soMeus = rgeUsuario('rge_so_meus_test', ['crm.access_own_schedule']);

    $doColega = rgeAcompanhamento(RGE_BIZ, 'Do colega', $colega);
    $registro = rgeRegistro($doColega, $colega, 'Original');

    $this->actingAs($soMeus)->put('/crm/follow-up-log/'.$registro, rgeCorpo($doColega, 'Trocado'), RGE_AJAX)->assertForbidden();
    $this->actingAs($soMeus)->delete('/crm/follow-up-log/'.$registro.'?schedule_id='.$doColega, [], RGE_AJAX)->assertForbidden();

    expect(DB::table('crm_schedule_logs')->where('id', $registro)->value('subject'))->toBe('Original');
});

it('UC-CRMACO-23 · editar sem status e excluir registro de acompanhamento de outro negócio dá 404 e nada muda [T0]', function () {
    $user = rgeUsuario('rge_todos_test', ['crm.access_all_schedule']);
    $vizinho = rgeUsuario('rge_vizinho_test', ['crm.access_all_schedule'], RGE_OUTRO);

    $alheio = rgeAcompanhamento(RGE_OUTRO, 'Do vizinho', $vizinho);
    $registro = rgeRegistro($alheio, $vizinho, 'Do vizinho');

    // Sem `status`: era exatamente o caminho que pulava a conferência do negócio.
    $corpo = rgeCorpo($alheio, 'Invadido');
    unset($corpo['status']);
    $this->actingAs($user)->put('/crm/follow-up-log/'.$registro, $corpo, RGE_AJAX)->assertNotFound();
    $this->actingAs($user)->delete('/crm/follow-up-log/'.$registro.'?schedule_id='.$alheio, [], RGE_AJAX)->assertNotFound();

    expect(DB::table('crm_schedule_logs')->where('id', $registro)->value('subject'))->toBe('Do vizinho');
});
