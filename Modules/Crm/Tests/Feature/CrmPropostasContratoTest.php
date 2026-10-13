<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Contrato da lista `/crm/proposals` (Propostas) — thread Crm/14, F3: Inertia atrás da flag
 * `useV2CrmPropostas`, ligada aqui pelo override `feature-flags.forced_on` (inerte em produção).
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Crm/Resources/js/Pages/Crm/Propostas/Index.casos.md
 * O lado Blade segue travado pelo CrmPropostasBaselineTest (F2).
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/ProposalController::index()
 */
const PRC_TAG = '[prc14]';
const PRC_BIZ = 98;
const PRC_OUTRO = 99;
const PRC_ROTA = '/crm/proposals';
const PRC_FLAG = 'useV2CrmPropostas';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_proposals requer schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'roles', 'crm_proposals', 'crm_proposal_templates', 'media'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    config(['feature-flags.forced_on' => PRC_FLAG]);
    prcLimpa();
});

afterEach(fn () => prcLimpa());

function prcLimpa(): void
{
    DB::table('crm_proposals')->where('subject', 'like', '%'.PRC_TAG.'%')->delete();
    DB::table('crm_proposal_templates')->where('subject', 'like', '%'.PRC_TAG.'%')->delete();
    DB::table('contacts')->where('name', 'like', '%'.PRC_TAG.'%')->delete();
}

/** Mesmo conserto do CrmPainelContratoTest::paiNegocio (`id` é guarded; owner_id obrigatório). */
function prcNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function prcUsuario(string $username, array $permissoes = [], bool $admin = false, int $biz = PRC_BIZ): User
{
    prcNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Prc', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));
    if ($admin) {
        $role = Role::firstOrCreate(['name' => 'Admin#'.$biz, 'guard_name' => 'web'], ['business_id' => $biz]);
        $user->syncRoles([$role]);
    } else {
        $user->syncRoles([]);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function prcProposta(int $biz, string $assunto, User $quem, string $corpo = '<p>corpo</p>'): int
{
    prcNegocio($biz);
    $contato = DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Contato '.PRC_TAG, 'mobile' => '0',
        'created_by' => $quem->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('crm_proposals')->insertGetId([
        'business_id' => $biz, 'contact_id' => $contato, 'sent_by' => $quem->id,
        'subject' => $assunto.' '.PRC_TAG, 'body' => $corpo, 'cc' => 'cc@test.local', 'bcc' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Props pedidas como o browser pede (X-Inertia + X-Requested-With), parcial em `$parcial`. */
function prcProps(User $user, string $parcial, string $query = ''): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resposta = test()->actingAs($user)->get(PRC_ROTA.$query, [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $parcial,
        'X-Inertia-Partial-Component' => 'Crm/Propostas/Index',
    ]);
    $resposta->assertOk();

    return (array) $resposta->json('props');
}

function prcAssuntos(User $user): array
{
    return collect((array) (prcProps($user, 'propostas')['propostas']['data'] ?? []))
        ->pluck('assunto')
        ->filter(fn ($s) => str_contains((string) $s, PRC_TAG))
        ->values()->all();
}

function prcDetalhe(User $user, int $id): ?array
{
    return prcProps($user, 'proposta', '?proposta='.$id)['proposta'] ?? null;
}

it('UC-CRMPRO-01 · com a flag ligada, a lista responde Inertia com Crm/Propostas/Index', function () {
    $this->actingAs(prcUsuario('prc_admin_test', [], true))
        ->get(PRC_ROTA)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Propostas/Index', false)->has('filtros')->has('pode_enviar'));
});

it('UC-CRMPRO-02 · visita Inertia não cai no DataTables, e o ajax da tela clássica segue recebendo JSON', function () {
    $admin = prcUsuario('prc_admin_test', [], true);
    prcProposta(PRC_BIZ, 'Lista inertia', $admin);

    expect(prcAssuntos($admin))->toContain('Lista inertia '.PRC_TAG);

    $json = $this->actingAs($admin)->get(PRC_ROTA, ['X-Requested-With' => 'XMLHttpRequest']);
    $json->assertOk();
    $this->assertArrayHasKey('data', (array) $json->json(), 'o DataTables da tela clássica deve continuar recebendo JSON');
    $this->assertNull($json->headers->get('X-Inertia'), 'ajax sem X-Inertia não pode virar resposta Inertia');
});

it('UC-CRMPRO-03 · proposta de outro negócio não aparece na lista nem abre no drawer [T0]', function () {
    $admin = prcUsuario('prc_admin_test', [], true);
    $vizinho = prcUsuario('prc_vizinho_test', [], true, PRC_OUTRO);
    $minha = prcProposta(PRC_BIZ, 'Minha proposta', $admin);
    $dele = prcProposta(PRC_OUTRO, 'Proposta vizinha', $vizinho);

    $assuntos = prcAssuntos($admin);
    expect($assuntos)->toContain('Minha proposta '.PRC_TAG);
    $this->assertNotContains('Proposta vizinha '.PRC_TAG, $assuntos, 'vazou proposta de outro business');

    expect(prcDetalhe($admin, $minha)['assunto'] ?? null)->toBe('Minha proposta '.PRC_TAG);
    $this->assertNull(prcDetalhe($admin, $dele), 'o drawer abriu proposta de outro business');
});

it('UC-CRMPRO-04 · quem não é Admin# vê só as que enviou, na lista e no drawer', function () {
    $comum = prcUsuario('prc_comum_test');
    $colega = prcUsuario('prc_colega_test');
    $admin = prcUsuario('prc_admin_test', [], true);
    prcProposta(PRC_BIZ, 'Enviada por mim', $comum);
    $doColega = prcProposta(PRC_BIZ, 'Enviada pelo colega', $colega);

    $meus = prcAssuntos($comum);
    expect($meus)->toContain('Enviada por mim '.PRC_TAG);
    $this->assertNotContains('Enviada pelo colega '.PRC_TAG, $meus, 'quem não é Admin# viu proposta do colega');
    $this->assertNull(prcDetalhe($comum, $doColega), 'o drawer abriu a proposta do colega para quem não é Admin#');

    expect(prcAssuntos($admin))->toContain('Enviada pelo colega '.PRC_TAG);
    expect(prcDetalhe($admin, $doColega)['assunto'] ?? null)->toBe('Enviada pelo colega '.PRC_TAG);
});

it('UC-CRMPRO-05 · Mandar só com modelo cadastrado e crm.access_proposal', function () {
    // Nenhum dos dois é Admin#: o Gate::before dá toda permissão de negócio ao papel Admin#.
    $comPermissao = prcUsuario('prc_envia_test', ['crm.access_proposal']);
    $semPermissao = prcUsuario('prc_comum_test');

    expect(prcProps($comPermissao, 'pode_enviar')['pode_enviar'])->toBeFalse();

    DB::table('crm_proposal_templates')->insert([
        'business_id' => PRC_BIZ, 'subject' => 'Modelo '.PRC_TAG, 'body' => '<p>m</p>',
        'created_by' => $comPermissao->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(prcProps($comPermissao, 'pode_enviar')['pode_enviar'])->toBeTrue();
    expect(prcProps($semPermissao, 'pode_enviar')['pode_enviar'])->toBeFalse();
});

it('UC-CRMPRO-06 · o drawer recebe o corpo como texto, sem nenhuma tag', function () {
    $admin = prcUsuario('prc_admin_test', [], true);
    $id = prcProposta(PRC_BIZ, 'Corpo html', $admin, '<p>Primeiro &amp; segundo</p><script>alert(1)</script><p>Fim</p>');

    $corpo = (string) (prcDetalhe($admin, $id)['corpo'] ?? '');
    $this->assertStringContainsString('Primeiro & segundo', $corpo, 'o texto do parágrafo sumiu');
    $this->assertStringContainsString('Fim', $corpo, 'o segundo parágrafo sumiu');
    $this->assertStringNotContainsString('<', $corpo, 'o corpo chegou com marcação HTML');
});

it('UC-CRMPRO-07 · flag desligada, ou ?classico=1 com ela ligada, seguem na Blade', function () {
    $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];
    $admin = prcUsuario('prc_admin_test', [], true);

    $this->actingAs($admin)->withSession($sessao)->get(PRC_ROTA.'?classico=1')
        ->assertOk()->assertViewIs('crm::proposal.index');

    config(['feature-flags.forced_on' => '']);
    $this->actingAs($admin)->withSession($sessao)->get(PRC_ROTA)
        ->assertOk()->assertViewIs('crm::proposal.index');
});
