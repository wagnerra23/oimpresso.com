<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-004

/**
 * Contrato da tela `/superadmin/communicator` — thread Superadmin/05 (Blade → Inertia).
 * UCs: Modules/Superadmin/Resources/js/Pages/superadmin/Comunicador/Index.casos.md
 *
 * ⚠️ SKIP em SQLite: precisa do schema UltimatePOS real. Leia *assertions*, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /superadmin/communicator requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('superadmin_communicator_logs') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
    config(['constants.administrator_usernames' => 'com_superadmin_test']);
    Notification::fake();
});

/** Tenants fictícios. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_COM = 98;
const BIZ_COM_OUTRO = 97;

const ROTA_COM = '/superadmin/communicator';

function comUsuario(string $username, bool $superadmin): User
{
    Business::firstOrCreate(['id' => BIZ_COM], ['name' => 'Tenant fictício comunicador', 'currency_id' => 1]);
    Business::firstOrCreate(['id' => BIZ_COM_OUTRO], ['name' => 'Tenant fictício comunicador B', 'currency_id' => 1]);

    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username . '@test.local', 'password' => bcrypt('secret'),
        'business_id' => BIZ_COM, 'first_name' => 'Com', 'last_name' => 'Teste',
    ]);

    if ($superadmin) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']));
    } else {
        $user->syncRoles([]);
        $user->syncPermissions([]);
    }

    return $user;
}

/** Pede as props deferred — sem isso o payload nem é calculado. */
function comPayload(string $prop): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $resposta = test()->actingAs(comUsuario('com_superadmin_test', true))->get(ROTA_COM, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $prop,
        'X-Inertia-Partial-Component' => 'superadmin/Comunicador/Index',
    ]);
    $resposta->assertOk();

    return (array) $resposta->json('props.' . $prop);
}

it('UC-SACOM-01 · a tela responde Inertia com o componente novo', function () {
    $this->actingAs(comUsuario('com_superadmin_test', true))
        ->get(ROTA_COM)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('superadmin/Comunicador/Index'));
});

it('UC-SACOM-02 · admin de negócio é barrado enquanto o superadmin passa', function () {
    $barrado = $this->actingAs(comUsuario('com_admin_test', false))->get(ROTA_COM);
    expect($barrado->getStatusCode())->toBeIn([302, 403]);

    $this->actingAs(comUsuario('com_superadmin_test', true))->get(ROTA_COM)->assertOk();
});

it('UC-SACOM-03 · a lista de destinatários traz negócios de tenants diferentes', function () {
    $ids = array_column(comPayload('negocios'), 'id');

    expect($ids)->toContain(BIZ_COM)->toContain(BIZ_COM_OUTRO);
});

it('UC-SACOM-04 · envio sem destinatário é recusado e não registra nada', function () {
    $assunto = 'Aviso fictício UC-SACOM-04 ' . uniqid();

    $this->actingAs(comUsuario('com_superadmin_test', true))
        ->post(ROTA_COM . '/send', ['recipients' => [], 'subject' => $assunto, 'message' => 'x'])
        ->assertSessionHasErrors('recipients');

    expect(DB::table('superadmin_communicator_logs')->where('subject', $assunto)->exists())->toBeFalse();
});

it('UC-SACOM-05 · o corpo é gravado escapado e a quebra de linha vira <br>', function () {
    $assunto = 'Aviso fictício UC-SACOM-05 ' . uniqid();

    $this->actingAs(comUsuario('com_superadmin_test', true))
        ->post(ROTA_COM . '/send', [
            'recipients' => [BIZ_COM], 'subject' => $assunto, 'message' => "<script>x</script>\nlinha 2",
        ])
        ->assertSessionHasNoErrors();

    $gravado = (string) DB::table('superadmin_communicator_logs')->where('subject', $assunto)->value('message');

    expect($gravado)->toContain('&lt;script&gt;');
    expect($gravado)->not->toContain('<script>');
    expect($gravado)->toContain('<br>');
});

it('UC-SACOM-06 · o envio aparece no histórico com o alcance e resumo sem tag', function () {
    $assunto = 'Aviso fictício UC-SACOM-06 ' . uniqid();

    $this->actingAs(comUsuario('com_superadmin_test', true))
        ->post(ROTA_COM . '/send', ['recipients' => [BIZ_COM, BIZ_COM_OUTRO], 'subject' => $assunto, 'message' => "um\ndois"])
        ->assertSessionHasNoErrors();

    $envio = collect(comPayload('historico'))->firstWhere('assunto', $assunto);

    expect($envio)->not->toBeNull();
    expect($envio['destinatarios'])->toBe(2);
    expect($envio['resumo'])->not->toContain('<br>');
});
