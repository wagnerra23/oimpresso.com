<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Contrato da tela /essentials/messages (mural interno) — `EssentialsMessageController`.
 *
 * UC-EMSG-01        — enviar mensagem grava no tenant da sessão e ela entra no mural.
 * UC-EMSG-02 `[T0]` — o mural não mostra mensagem de outro business.
 * UC-EMSG-03        — o polling devolve só mensagens de OUTROS, mais novas que o último visto.
 *
 * Os UC derivam do charter (`Index.charter.md` §Goals) + do controller real, nunca do protótipo.
 * Trio: resources/js/Pages/Essentials/Messages/{Index.charter.md,Index.casos.md}
 *
 * COMPLEMENTA o `Modules/Essentials/Tests/Feature/MessagesIndexTest.php`, que prova a FORMA
 * das props (lista, não paginator; `location_id` por mensagem). Este prova o COMPORTAMENTO:
 * escrita, isolamento de tenant e o recorte do polling.
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant do usuário = 98 (fictício), adversário = 2. NUNCA biz=4.
 * A mensagem alheia do UC-EMSG-02 tem `user_id` = EU — só o filtro de `business_id` a segura.
 *
 * `Notification::fake()`: o store dispara `NewMessageNotification` pros outros usuários do
 * tenant; o efeito colateral de notificação não é o que este contrato mede.
 *
 * `Tests\TestCase` NÃO se declara: `tests/Pest.php` já faz `uses(TestCase::class)->in('Feature')`.
 *
 * @covers-us US-ESS-012
 * @see Modules/Essentials/Http/Controllers/EssentialsMessageController.php
 */
uses(DatabaseTransactions::class);

const EMSG_BIZ = 98;          // tenant canônico de teste (ADR 0358)
const EMSG_BIZ_ALHEIO = 2;    // segundo tenant do seed
const EMSG_OUTRO_AUTOR = 987654321; // `user_id` sem FK — remetente que não sou eu

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL.');
    }
    foreach (['essentials_messages', 'business', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode o migrate do módulo Essentials.");
        }
    }

    $user = User::where('business_id', EMSG_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98 — o seed canônico (pest-mysql-setup) não rodou.');
    }
    $this->mUser = $user;

    // Admin#98 → Gate::before libera essentials.view_message / create_message.
    $role = Role::firstOrCreate(['name' => 'Admin#'.EMSG_BIZ, 'guard_name' => 'web'], ['business_id' => EMSG_BIZ]);
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Notification::fake();
    session()->flush(); // SetSessionData reconstrói a sessão a partir do usuário autenticado
    $this->actingAs($user);
});

function emsgMensagem(int $biz, int $userId, string $texto, string $quando): int
{
    return DB::table('essentials_messages')->insertGetId([
        'business_id' => $biz,
        'user_id' => $userId,
        'message' => $texto,
        'location_id' => null,
        'created_at' => $quando,
        'updated_at' => $quando,
    ]);
}

function emsgInertiaVersion(): string
{
    try {
        return (string) (new \App\Http\Middleware\HandleInertiaRequests)->version(request());
    } catch (\Throwable $e) {
        return '';
    }
}

/** Textos do mural via partial reload da prop deferida `messages`. */
function emsgMural($test): array
{
    $res = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => emsgInertiaVersion(),
        'X-Inertia-Partial-Data' => 'messages',
        'X-Inertia-Partial-Component' => 'Essentials/Messages/Index',
    ])->get('/essentials/messages');

    $res->assertStatus(200);
    $res->assertJsonPath('component', 'Essentials/Messages/Index');

    return collect($res->json('props.messages') ?? [])->keyBy('message')->all();
}

// ─────────────────────────────────────────────────────────────────────────────
it('UC-EMSG-01 · enviar mensagem grava no tenant da sessão e ela entra no mural', function () {
    $texto = 'EMSG01-'.uniqid();

    $res = $this->post('/essentials/messages', ['message' => $texto]);
    $res->assertStatus(302);
    $res->assertSessionHasNoErrors();

    $linhas = DB::table('essentials_messages')->where('message', $texto)->get();
    expect($linhas)->toHaveCount(1);
    expect((int) $linhas[0]->business_id)->toBe(EMSG_BIZ);
    expect((int) $linhas[0]->user_id)->toBe((int) $this->mUser->id);

    $mural = emsgMural($this);
    expect($mural)->toHaveKey($texto);
    expect((int) $mural[$texto]['user_id'])->toBe((int) $this->mUser->id);
});

it('UC-EMSG-02 · o mural não mostra mensagem de outro business', function () {
    $tag = 'EMSG02-'.uniqid();
    $agora = now()->format('Y-m-d H:i:s');
    emsgMensagem(EMSG_BIZ, (int) $this->mUser->id, "$tag-meu-tenant", $agora);
    emsgMensagem(EMSG_BIZ_ALHEIO, (int) $this->mUser->id, "$tag-outro-tenant", $agora);

    $mural = emsgMural($this);

    expect($mural)->toHaveKey("$tag-meu-tenant");      // controle positivo
    expect($mural)->not->toHaveKey("$tag-outro-tenant"); // Tier 0
});

it('UC-EMSG-03 · o polling devolve só mensagens de outros, mais novas que o último visto', function () {
    $tag = 'EMSG03-'.uniqid();
    $base = Carbon::parse('2031-01-15 12:00:00'); // futuro fixo: nada do seed é mais novo que isto
    emsgMensagem(EMSG_BIZ, EMSG_OUTRO_AUTOR, "$tag-nova-alheia", $base->format('Y-m-d H:i:s'));
    emsgMensagem(EMSG_BIZ, EMSG_OUTRO_AUTOR, "$tag-velha-alheia", $base->copy()->subHours(2)->format('Y-m-d H:i:s'));
    emsgMensagem(EMSG_BIZ, (int) $this->mUser->id, "$tag-nova-minha", $base->format('Y-m-d H:i:s'));
    emsgMensagem(EMSG_BIZ_ALHEIO, EMSG_OUTRO_AUTOR, "$tag-nova-outro-tenant", $base->format('Y-m-d H:i:s'));

    $ultimoVisto = $base->copy()->subHour()->format('Y-m-d H:i:s');
    $res = $this->getJson('/essentials/get-new-messages?last_chat_time='.urlencode($ultimoVisto));
    $res->assertStatus(200);

    $textos = array_column($res->json('messages') ?? [], 'message');

    expect($textos)->toContain("$tag-nova-alheia");                 // controle positivo
    expect($textos)->not->toContain("$tag-velha-alheia");            // já vista
    expect($textos)->not->toContain("$tag-nova-minha");              // a minha o cliente já tem
    expect($textos)->not->toContain("$tag-nova-outro-tenant");       // Tier 0
});
