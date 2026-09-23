<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(DatabaseTransactions::class);

/**
 * Contrato da tela Essentials/Reminders/Index — resources/js/Pages/Essentials/Reminders/Index.casos.md.
 *
 * Os UC derivam do charter (Goals, Non-Goals, "Backend contract", "Métricas de sucesso")
 * + do Controller real (`Modules\Essentials\Http\Controllers\ReminderController@index/@store`),
 * nunca do protótipo. Cada `it()` cita o UC-id no título (casos-gate G-2).
 *
 * Lembrete é POR USUÁRIO: o index filtra `business_id` E `user_id`. Por isso há dois
 * isolamentos distintos — entre businesses [T0] e entre usuários do mesmo business.
 *
 * Tenant: 98 (canônico, empresa FICTÍCIA) vs 99 (adversário cross-tenant) — ADR 0358.
 * NUNCA biz=4. ADR 0093 Tier 0 IRREVOGÁVEL.
 *
 * Fixtures por `DB::table`: `Reminder` usa `HasBusinessScope`, e o que se mede é o filtro
 * do CONTROLLER. `DatabaseTransactions`, nunca `RefreshDatabase`.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('essentials_reminders')) {
        $this->markTestSkipped('Tabela essentials_reminders ausente — rode migrate Modules/Essentials.');
    }

    $this->tenant = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    $user = User::where('business_id', $this->tenant->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user no tenant canônico — seed mínimo não rodou.');
    }
    $this->actor = $user;

    session()->flush();
    $this->actingAs($user);
});

function eremCriar(int $bizId, int $userId, string $nome, string $repeat = 'one_time'): int
{
    return (int) DB::table('essentials_reminders')->insertGetId([
        'business_id' => $bizId,
        'user_id' => $userId,
        'name' => $nome,
        'date' => '2026-10-15',
        'time' => '09:30:00',
        'end_time' => null,
        'repeat' => $repeat,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** `reminders` é prop eager no index() — vem já no primeiro render Inertia. */
function eremPagina($test): array
{
    $manifest = public_path('build-inertia/manifest.json');
    $response = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
    ])->get('/essentials/reminder');

    $response->assertStatus(200);

    return [
        'component' => $response->json('component'),
        'reminders' => $response->json('props.reminders') ?? [],
        'repeats' => $response->json('props.repeats') ?? [],
    ];
}

it('UC-EREM-01: a tela abre e mostra o meu lembrete com data, hora e repetição', function () {
    $nome = 'UC01 pagar fornecedor '.uniqid();
    $id = eremCriar($this->tenant->id, $this->actor->id, $nome, 'every_week');

    $pagina = eremPagina($this);

    expect($pagina['component'])->toBe('Essentials/Reminders/Index');
    $linha = collect($pagina['reminders'])->firstWhere('id', $id);
    expect($linha)->not->toBeNull();
    expect($linha['name'])->toBe($nome);
    expect($linha['time'])->toBe('09:30');
    expect($linha['repeat'])->toBe('every_week');
    // As 4 opções de repetição do form (charter Goals · Form campos)
    expect(collect($pagina['repeats'])->pluck('value')->all())
        ->toBe(['one_time', 'every_day', 'every_week', 'every_month']);
});

it('UC-EREM-02: [T0] lembrete de outro business nunca aparece', function () {
    $meu = eremCriar($this->tenant->id, $this->actor->id, 'UC02 meu '.uniqid());
    // mesmo user_id, business diferente — só o filtro de business separa os dois
    $alheio = eremCriar($this->adversario->id, $this->actor->id, 'UC02 alheio '.uniqid());

    $ids = collect(eremPagina($this)['reminders'])->pluck('id')->all();

    expect($ids)->toContain($meu);
    expect($ids)->not->toContain($alheio);
});

it('UC-EREM-03: lembrete de outro usuário do MESMO business não aparece', function () {
    $outro = User::where('business_id', $this->tenant->id)
        ->where('id', '!=', $this->actor->id)
        ->first();
    $outroId = $outro?->id ?? (int) DB::table('users')->insertGetId([
        'first_name' => 'CT Outro 98',
        'username' => 'ct_erem_outro_'.uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $this->tenant->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $meu = eremCriar($this->tenant->id, $this->actor->id, 'UC03 meu '.uniqid());
    $doOutro = eremCriar($this->tenant->id, $outroId, 'UC03 do outro '.uniqid());

    $ids = collect(eremPagina($this)['reminders'])->pluck('id')->all();

    expect($ids)->toContain($meu);
    expect($ids)->not->toContain($doOutro);
});

it('UC-EREM-04: criar lembrete grava no meu business e no meu usuário', function () {
    $nome = 'UC04 novo '.uniqid();

    $resp = $this->post('/essentials/reminder', [
        'name' => $nome,
        'date' => '2026-11-03',
        'time' => '14:00',
        'repeat' => 'every_month',
    ]);

    // POST de form: sucesso e recusa são ambos redirect — o que prova é o EFEITO.
    $resp->assertSessionHasNoErrors();
    $row = DB::table('essentials_reminders')->where('name', $nome)->first();
    expect($row)->not->toBeNull();
    expect((int) $row->business_id)->toBe((int) $this->tenant->id);
    expect((int) $row->user_id)->toBe((int) $this->actor->id);
    expect($row->repeat)->toBe('every_month');
    expect(substr((string) $row->time, 0, 5))->toBe('14:00');
});
