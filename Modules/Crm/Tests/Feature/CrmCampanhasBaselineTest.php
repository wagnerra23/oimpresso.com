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
 * Baseline da tela `/crm/campaigns` (Campanhas) ANTES da migração — thread Crm/11, F2 do MWART.
 *
 * Prende o comportamento da Blade/DataTables que a F3 tem de manter. Âncora:
 *   memory/requisitos/Crm/campanhas-parity.md (linhas 1-5 e 10) + RUNBOOK-campanhas.md §5.
 *
 * Nunca envia: nenhum teste chama `send-campaign-notification`, e o `Notification::fake()`
 * do beforeEach segura e-mail caso algum caminho dispare (RUNBOOK §6).
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/CampaignController
 */
const CAMB_TAG = '[camb11]';
const CAMB_BIZ = 98;
const CAMB_OUTRO = 99;
const CAMB_ROTA = '/crm/campaigns';
const CAMB_AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_campaigns requer schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'crm_campaigns'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    Notification::fake();
    cambLimpa();
});

afterEach(fn () => cambLimpa());

function cambLimpa(): void
{
    if (Schema::hasTable('crm_campaigns')) {
        DB::table('crm_campaigns')->where('name', 'like', '%'.CAMB_TAG.'%')->delete();
    }
}

/** Mesmo conserto do CrmAcompanhamentosContratoTest::acoNegocio — `id` é guarded. */
function cambNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function cambUsuario(string $username, array $permissoes, int $biz = CAMB_BIZ): User
{
    cambNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Camb', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));

    return $user;
}

function cambCampanha(int $biz, string $nome, User $criador, string $tipo = 'email'): int
{
    cambNegocio($biz);

    return DB::table('crm_campaigns')->insertGetId([
        'business_id' => $biz, 'name' => $nome.' '.CAMB_TAG, 'campaign_type' => $tipo,
        'subject' => 'Assunto', 'email_body' => 'Corpo', 'sms_body' => null,
        'contact_ids' => '[]', 'additional_info' => '{}',
        'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Nomes que o DataTables da Blade devolve (o nome pode vir com o selo "Enviada" em HTML). */
function cambLista(User $user, string $query = ''): array
{
    $resposta = test()->actingAs($user)->get(CAMB_ROTA.$query, CAMB_AJAX);
    $resposta->assertOk();

    return collect((array) $resposta->json('data'))
        ->map(fn ($r) => (string) ($r['name'] ?? ''))
        ->filter(fn ($n) => str_contains($n, CAMB_TAG))
        ->values()->all();
}

function cambTem(array $nomes, string $nome): bool
{
    return collect($nomes)->contains(fn ($n) => str_contains($n, $nome.' '.CAMB_TAG));
}

it('UC-CAMB-01 · sem permissão de campanha é barrado; com acesso total vê a tela Blade', function () {
    $this->actingAs(cambUsuario('camb_sem_test', []))->get(CAMB_ROTA)->assertForbidden();

    $this->actingAs(cambUsuario('camb_todos_test', ['crm.access_all_campaigns']))
        ->withSession(['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']])
        ->get(CAMB_ROTA)
        ->assertOk()
        ->assertViewIs('crm::campaign.index');
});

it('UC-CAMB-02 · campanha de outro negócio não aparece na lista [T0]', function () {
    $user = cambUsuario('camb_todos_test', ['crm.access_all_campaigns']);
    $vizinho = cambUsuario('camb_vizinho_test', ['crm.access_all_campaigns'], CAMB_OUTRO);
    cambCampanha(CAMB_BIZ, 'Meu negocio', $user);
    cambCampanha(CAMB_OUTRO, 'Negocio vizinho', $vizinho);

    $nomes = cambLista($user);
    expect(cambTem($nomes, 'Meu negocio'))->toBeTrue();
    $this->assertFalse(cambTem($nomes, 'Negocio vizinho'), 'vazou campanha de outro business');
});

it('UC-CAMB-03 · quem só pode ver as próprias vê só as criadas por ele', function () {
    $propria = cambUsuario('camb_propria_test', ['crm.access_own_campaigns']);
    $colega = cambUsuario('camb_colega_test', ['crm.access_all_campaigns']);
    cambCampanha(CAMB_BIZ, 'Criada por mim', $propria);
    cambCampanha(CAMB_BIZ, 'Criada pelo colega', $colega);

    $nomes = cambLista($propria);
    expect(cambTem($nomes, 'Criada por mim'))->toBeTrue();
    $this->assertFalse(cambTem($nomes, 'Criada pelo colega'), 'quem só vê as próprias viu a do colega');

    expect(cambTem(cambLista($colega), 'Criada pelo colega'))->toBeTrue();
});

it('UC-CAMB-04 · o filtro de tipo separa SMS de e-mail', function () {
    $user = cambUsuario('camb_todos_test', ['crm.access_all_campaigns']);
    cambCampanha(CAMB_BIZ, 'Campanha sms', $user, 'sms');
    cambCampanha(CAMB_BIZ, 'Campanha email', $user, 'email');

    $sms = cambLista($user, '?campaign_type=sms');
    expect(cambTem($sms, 'Campanha sms'))->toBeTrue();
    $this->assertFalse(cambTem($sms, 'Campanha email'), 'o filtro sms trouxe campanha de e-mail');
});

it('UC-CAMB-05 · ver campanha de outro negócio ou de colega (só as próprias) dá 404 [T0]', function () {
    $user = cambUsuario('camb_todos_test', ['crm.access_all_campaigns']);
    $vizinho = cambUsuario('camb_vizinho_test', ['crm.access_all_campaigns'], CAMB_OUTRO);
    $propria = cambUsuario('camb_propria_test', ['crm.access_own_campaigns']);
    $deFora = cambCampanha(CAMB_OUTRO, 'Show vizinho', $vizinho);
    $doColega = cambCampanha(CAMB_BIZ, 'Show colega', $user);

    $this->actingAs($user)->get(CAMB_ROTA.'/'.$deFora, CAMB_AJAX)->assertNotFound();
    $this->actingAs($propria)->get(CAMB_ROTA.'/'.$doColega, CAMB_AJAX)->assertNotFound();
    $this->actingAs($user)->get(CAMB_ROTA.'/'.$doColega, CAMB_AJAX)->assertOk();
});

it('UC-CAMB-06 · excluir não alcança campanha de outro negócio [T0]', function () {
    $user = cambUsuario('camb_todos_test', ['crm.access_all_campaigns']);
    $vizinho = cambUsuario('camb_vizinho_test', ['crm.access_all_campaigns'], CAMB_OUTRO);
    $deFora = cambCampanha(CAMB_OUTRO, 'Excluir vizinho', $vizinho);
    $minha = cambCampanha(CAMB_BIZ, 'Excluir minha', $user);

    $this->actingAs($user)->delete(CAMB_ROTA.'/'.$deFora, [], CAMB_AJAX)->assertOk();
    $this->assertTrue(DB::table('crm_campaigns')->where('id', $deFora)->exists(), 'excluiu campanha de outro business');

    $this->actingAs($user)->delete(CAMB_ROTA.'/'.$minha, [], CAMB_AJAX)->assertOk();
    $this->assertFalse(DB::table('crm_campaigns')->where('id', $minha)->exists(), 'não excluiu a própria campanha');
});
