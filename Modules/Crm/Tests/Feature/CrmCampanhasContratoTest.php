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

/**
 * Contrato da lista `/crm/campaigns` em React, atrás da flag `useV2CrmCampanhas` — thread Crm/11, F3.
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Crm/Resources/js/Pages/Crm/Campanhas/Index.casos.md
 *   memory/requisitos/Crm/RUNBOOK-campanhas.md §2-5 + campanhas-parity.md
 *
 * A flag é forçada por stub do FeatureFlagService (mesmo desenho do ComputadoresEmpresasIndexContratoTest).
 * Nunca envia: a tela nova não chama `send-campaign-notification`, e o `Notification::fake()` segura e-mail.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/CampaignController::index()
 */
const CRMCAM_TAG = '[crmcam11]';
const CRMCAM_BIZ = 98;
const CRMCAM_OUTRO = 99;
const CRMCAM_ROTA = '/crm/campaigns';
const CRMCAM_COMPONENTE = 'Crm/Campanhas/Index';
const CRMCAM_MOEDA = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];

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
    crmcamFlag(true);
    crmcamLimpa();
});

afterEach(function () {
    crmcamLimpa();
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
});

function crmcamFlag(bool $ligada): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class($ligada) extends \App\Services\FeatureFlagService
    {
        public function __construct(private bool $ligada) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2CrmCampanhas' && $this->ligada;
        }
    });
}

function crmcamLimpa(): void
{
    if (Schema::hasTable('crm_campaigns')) {
        DB::table('crm_campaigns')->where('name', 'like', '%'.CRMCAM_TAG.'%')->delete();
    }
}

/** Mesmo conserto do CrmAcompanhamentosContratoTest::acoNegocio — `id` é guarded. */
function crmcamNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function crmcamUsuario(string $username, array $permissoes, int $biz = CRMCAM_BIZ): User
{
    crmcamNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Crmcam', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));

    return $user;
}

function crmcamCampanha(int $biz, string $nome, User $criador, string $tipo = 'email', array $extra = []): int
{
    crmcamNegocio($biz);

    return DB::table('crm_campaigns')->insertGetId(array_merge([
        'business_id' => $biz, 'name' => $nome.' '.CRMCAM_TAG, 'campaign_type' => $tipo,
        'subject' => 'Assunto', 'email_body' => 'Corpo', 'sms_body' => 'Corpo sms',
        'contact_ids' => '[]', 'additional_info' => '{}',
        'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ], $extra));
}

/** Pede uma prop da tela como o browser pede (X-Inertia + X-Requested-With, partial reload). */
function crmcamProp(User $user, string $prop, string $query = ''): mixed
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resposta = test()->actingAs($user)->get(CRMCAM_ROTA.$query, [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $prop,
        'X-Inertia-Partial-Component' => CRMCAM_COMPONENTE,
    ]);
    $resposta->assertOk();

    return $resposta->json('props.'.$prop);
}

function crmcamNomes(User $user, string $query = ''): array
{
    return collect((array) (crmcamProp($user, 'campanhas', $query)['data'] ?? []))
        ->pluck('nome')
        ->filter(fn ($n) => str_contains((string) $n, CRMCAM_TAG))
        ->values()->all();
}

it('UC-CRMCAM-01 · flag desligada serve a Blade; ligada serve a tela React', function () {
    $user = crmcamUsuario('crmcam_todos_test', ['crm.access_all_campaigns']);

    crmcamFlag(false);
    $this->actingAs($user)->withSession(CRMCAM_MOEDA)->get(CRMCAM_ROTA)->assertOk()->assertViewIs('crm::campaign.index');

    crmcamFlag(true);
    $this->actingAs($user)->get(CRMCAM_ROTA)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component(CRMCAM_COMPONENTE, false)->has('filtros'));
});

it('UC-CRMCAM-02 · visita Inertia não cai no DataTables, e o ajax da tela clássica segue recebendo JSON', function () {
    $user = crmcamUsuario('crmcam_todos_test', ['crm.access_all_campaigns']);
    crmcamCampanha(CRMCAM_BIZ, 'Aniversariantes', $user);

    expect(crmcamNomes($user))->toContain('Aniversariantes '.CRMCAM_TAG);

    $json = $this->actingAs($user)->get(CRMCAM_ROTA, ['X-Requested-With' => 'XMLHttpRequest']);
    $json->assertOk();
    $this->assertArrayHasKey('data', (array) $json->json(), 'o DataTables da tela clássica deve continuar recebendo JSON');
    $this->assertNull($json->headers->get('X-Inertia'), 'ajax sem X-Inertia não pode virar resposta Inertia');
});

it('UC-CRMCAM-03 · campanha de outro negócio não aparece [T0]', function () {
    $user = crmcamUsuario('crmcam_todos_test', ['crm.access_all_campaigns']);
    $vizinho = crmcamUsuario('crmcam_vizinho_test', ['crm.access_all_campaigns'], CRMCAM_OUTRO);
    crmcamCampanha(CRMCAM_BIZ, 'Meu negocio', $user);
    crmcamCampanha(CRMCAM_OUTRO, 'Negocio vizinho', $vizinho);

    $nomes = crmcamNomes($user);
    expect($nomes)->toContain('Meu negocio '.CRMCAM_TAG);
    $this->assertNotContains('Negocio vizinho '.CRMCAM_TAG, $nomes, 'vazou campanha de outro business');
});

it('UC-CRMCAM-04 · quem só pode ver as próprias vê só as criadas por ele', function () {
    $propria = crmcamUsuario('crmcam_propria_test', ['crm.access_own_campaigns']);
    $colega = crmcamUsuario('crmcam_colega_test', ['crm.access_all_campaigns']);
    crmcamCampanha(CRMCAM_BIZ, 'Criada por mim', $propria);
    crmcamCampanha(CRMCAM_BIZ, 'Criada pelo colega', $colega);

    $nomes = crmcamNomes($propria);
    expect($nomes)->toContain('Criada por mim '.CRMCAM_TAG);
    $this->assertNotContains('Criada pelo colega '.CRMCAM_TAG, $nomes, 'quem só vê as próprias viu a do colega');
});

it('UC-CRMCAM-05 · o filtro de tipo e a busca por nome recortam a lista', function () {
    $user = crmcamUsuario('crmcam_todos_test', ['crm.access_all_campaigns']);
    crmcamCampanha(CRMCAM_BIZ, 'Promo sms', $user, 'sms');
    crmcamCampanha(CRMCAM_BIZ, 'Promo email', $user, 'email');

    $sms = crmcamNomes($user, '?campaign_type=sms');
    expect($sms)->toContain('Promo sms '.CRMCAM_TAG);
    $this->assertNotContains('Promo email '.CRMCAM_TAG, $sms, 'o filtro sms trouxe campanha de e-mail');

    $busca = crmcamNomes($user, '?q='.urlencode('Promo email'));
    expect($busca)->toContain('Promo email '.CRMCAM_TAG);
    $this->assertNotContains('Promo sms '.CRMCAM_TAG, $busca, 'a busca por nome trouxe outra campanha');
});

it('UC-CRMCAM-06 · o drawer abre a própria campanha com o corpo em texto e não abre a de outro negócio [T0]', function () {
    $user = crmcamUsuario('crmcam_todos_test', ['crm.access_all_campaigns']);
    $vizinho = crmcamUsuario('crmcam_vizinho_test', ['crm.access_all_campaigns'], CRMCAM_OUTRO);
    $minha = crmcamCampanha(CRMCAM_BIZ, 'Com html', $user, 'email', [
        'email_body' => '<p>Olá <script>alert(1)</script><b>cliente</b></p>', 'contact_ids' => '[1,2,3]',
    ]);
    $deFora = crmcamCampanha(CRMCAM_OUTRO, 'Do vizinho', $vizinho);

    $campanha = crmcamProp($user, 'campanha', '?campanha='.$minha);
    expect($campanha['nome'])->toBe('Com html '.CRMCAM_TAG);
    expect($campanha['contatos'])->toBe(3);
    expect($campanha['corpo'])->not->toContain('<');

    $this->assertNull(crmcamProp($user, 'campanha', '?campanha='.$deFora), 'o drawer abriu campanha de outro business');
});

it('UC-CRMCAM-07 · com a flag ligada, ?classico=1 devolve a Blade, que hospeda criar, editar, excluir e enviar', function () {
    $this->actingAs(crmcamUsuario('crmcam_todos_test', ['crm.access_all_campaigns']))
        ->withSession(CRMCAM_MOEDA)
        ->get(CRMCAM_ROTA.'?classico=1')
        ->assertOk()
        ->assertViewIs('crm::campaign.index');
});
