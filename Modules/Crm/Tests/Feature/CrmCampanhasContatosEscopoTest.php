<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Campanha só alcança contato do próprio negócio — achado A1 da thread Crm/11.
 *
 * Âncora: memory/requisitos/Crm/campanhas-parity.md §Achados A1 + ADR 0093 (Tier 0).
 * Antes deste conserto, `StoreCampaignRequest` validava `contact_id.*` só como inteiro, o
 * `update` não validava nada, e `show` resolvia os ids com `CrmContact::find()` sem escopo:
 * um id de contato de outro negócio era gravado e aparecia (e recebia o envio).
 *
 * Nunca envia: nenhum caso pede `send_notification` nem chama a rota de envio, e o
 * `Notification::fake()` segura qualquer e-mail.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/CampaignController
 * @see Modules/Crm/Http/Requests/StoreCampaignRequest
 */
const CAMC_TAG = '[camc11]';
const CAMC_BIZ = 98;
const CAMC_OUTRO = 99;
const CAMC_ROTA = '/crm/campaigns';
const CAMC_JSON = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_campaigns requer schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'crm_campaigns'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    Notification::fake();
    camcLimpa();
});

afterEach(fn () => camcLimpa());

function camcLimpa(): void
{
    if (Schema::hasTable('crm_campaigns')) {
        DB::table('crm_campaigns')->where('name', 'like', '%'.CAMC_TAG.'%')->delete();
    }
    if (Schema::hasTable('contacts')) {
        DB::table('contacts')->where('name', 'like', '%'.CAMC_TAG.'%')->delete();
    }
}

/** Mesmo conserto do CrmAcompanhamentosContratoTest::acoNegocio — `id` é guarded. */
function camcNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function camcUsuario(string $username, int $biz = CAMC_BIZ): User
{
    camcNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Camc', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions([Permission::firstOrCreate(['name' => 'crm.access_all_campaigns', 'guard_name' => 'web'])]);

    return $user;
}

function camcContato(int $biz, string $nome, User $criador, string $tipo = 'customer'): int
{
    camcNegocio($biz);

    return DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => $tipo, 'name' => $nome.' '.CAMC_TAG, 'mobile' => '0',
        'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function camcCorpo(string $nome, array $extra = []): array
{
    return array_merge([
        'name' => $nome.' '.CAMC_TAG, 'campaign_type' => 'email', 'subject' => 'Assunto',
        'email_body' => 'Corpo', 'to' => 'customer',
    ], $extra);
}

function camcGravada(string $nome): ?object
{
    return DB::table('crm_campaigns')->where('name', $nome.' '.CAMC_TAG)->first();
}

it('UC-CAMC-01 · criar campanha com contato de outro negócio é recusado e nada é gravado [T0]', function () {
    $user = camcUsuario('camc_user_test');
    $vizinho = camcUsuario('camc_vizinho_test', CAMC_OUTRO);
    $deFora = camcContato(CAMC_OUTRO, 'Cliente vizinho', $vizinho);

    $this->actingAs($user)->post(CAMC_ROTA, camcCorpo('Com contato de fora', ['contact_id' => [$deFora]]), CAMC_JSON)
        ->assertStatus(422);
    $this->assertNull(camcGravada('Com contato de fora'), 'gravou campanha apontando contato de outro business');

    $this->actingAs($user)->post(CAMC_ROTA, camcCorpo('Com lead de fora', ['lead_id' => [$deFora]]), CAMC_JSON)
        ->assertStatus(422);
    $this->assertNull(camcGravada('Com lead de fora'), 'gravou campanha apontando lead de outro business');
});

it('UC-CAMC-02 · criar campanha com contato do próprio negócio continua gravando os ids', function () {
    $user = camcUsuario('camc_user_test');
    $cliente = camcContato(CAMC_BIZ, 'Cliente meu', $user);
    $lead = camcContato(CAMC_BIZ, 'Lead meu', $user, 'lead');

    $this->actingAs($user)->post(CAMC_ROTA, camcCorpo('Com contatos meus', ['contact_id' => [$cliente], 'lead_id' => [$lead]]), CAMC_JSON)
        ->assertRedirect();

    $gravada = camcGravada('Com contatos meus');
    expect($gravada)->not->toBeNull();
    expect(array_map('intval', json_decode($gravada->contact_ids, true)))->toEqualCanonicalizing([$cliente, $lead]);
    expect((int) $gravada->business_id)->toBe(CAMC_BIZ);
});

it('UC-CAMC-03 · editar campanha para apontar contato de outro negócio é recusado [T0]', function () {
    $user = camcUsuario('camc_user_test');
    $vizinho = camcUsuario('camc_vizinho_test', CAMC_OUTRO);
    $meu = camcContato(CAMC_BIZ, 'Cliente meu', $user);
    $deFora = camcContato(CAMC_OUTRO, 'Cliente vizinho', $vizinho);

    $this->actingAs($user)->post(CAMC_ROTA, camcCorpo('Para editar', ['contact_id' => [$meu]]), CAMC_JSON)->assertRedirect();
    $id = (int) camcGravada('Para editar')->id;

    $this->actingAs($user)->put(CAMC_ROTA.'/'.$id, camcCorpo('Para editar', ['contact_id' => [$deFora]]), CAMC_JSON)
        ->assertStatus(422);
    expect(array_map('intval', json_decode(camcGravada('Para editar')->contact_ids, true)))->toBe([$meu]);
});

it('UC-CAMC-04 · ver campanha com id de contato de outro negócio (dado antigo) não mostra esse contato [T0]', function () {
    $user = camcUsuario('camc_user_test');
    $vizinho = camcUsuario('camc_vizinho_test', CAMC_OUTRO);
    $meu = camcContato(CAMC_BIZ, 'Cliente meu', $user);
    $deFora = camcContato(CAMC_OUTRO, 'Cliente vizinho', $vizinho);
    $id = DB::table('crm_campaigns')->insertGetId([
        'business_id' => CAMC_BIZ, 'name' => 'Legado misto '.CAMC_TAG, 'campaign_type' => 'email',
        'subject' => 'Assunto', 'email_body' => 'Corpo', 'contact_ids' => json_encode([$meu, $deFora]),
        'additional_info' => '{}', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $resposta = $this->actingAs($user)->get(CAMC_ROTA.'/'.$id, CAMC_JSON);
    $resposta->assertOk();
    $resposta->assertSee('Cliente meu '.CAMC_TAG, false);
    $resposta->assertDontSee('Cliente vizinho '.CAMC_TAG, false);
});
