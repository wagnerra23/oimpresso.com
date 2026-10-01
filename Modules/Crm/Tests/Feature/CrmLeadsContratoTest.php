<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Contrato da lista `/crm/leads` (Leads) — thread Crm/02, Blade → Inertia, detalhe em drawer.
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Crm/Resources/js/Pages/Crm/Leads/Index.casos.md
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/LeadController::index()
 */
const LEAD_TAG = '[lead02]';
const LEAD_BIZ = 98;
const LEAD_OUTRO = 99;
const LEAD_ROTA = '/crm/leads';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: contacts/crm_lead_users requerem schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'crm_lead_users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    leadLimpa();
});

afterEach(fn () => leadLimpa());

function leadLimpa(): void
{
    if (Schema::hasTable('contacts')) {
        // crm_lead_users cai junto (FK on delete cascade).
        DB::table('contacts')->where('name', 'like', '%'.LEAD_TAG.'%')->delete();
    }
}

function leadUsuario(string $username, array $permissoes, int $biz = LEAD_BIZ): User
{
    Business::firstOrCreate(['id' => $biz], ['name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1]);
    // `user_type` e `allow_login` explícitos: o `CheckUserLogin` da rota barra quem não tem os
    // dois (medido no CI da thread Crm/03).
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Lead', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));

    return $user;
}

/** Contato cru (lead por padrão), criado e atribuído a `$dono`. */
function leadContato(int $biz, string $nome, User $dono, string $tipo = 'lead'): int
{
    Business::firstOrCreate(['id' => $biz], ['name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1]);
    $id = DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => $tipo, 'name' => $nome.' '.LEAD_TAG, 'mobile' => '0',
        'contact_id' => 'L'.random_int(100000, 999999),
        'created_by' => $dono->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('crm_lead_users')->insert(['contact_id' => $id, 'user_id' => $dono->id]);

    return $id;
}

/** Pedido parcial como o browser faz (X-Inertia + X-Requested-With). */
function leadParcial(User $user, string $prop, string $query = '')
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resposta = test()->actingAs($user)->get(LEAD_ROTA.$query, [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $prop,
        'X-Inertia-Partial-Component' => 'Crm/Leads/Index',
    ]);
    $resposta->assertOk();

    return $resposta;
}

/** Nomes (com a tag) da prop deferida `leads`. */
function leadLista(User $user, string $query = ''): array
{
    return collect((array) leadParcial($user, 'leads', $query)->json('props.leads.data'))
        ->filter(fn ($r) => str_contains((string) ($r['nome'] ?? ''), LEAD_TAG))
        ->pluck('nome')->all();
}

it('UC-CRMLD-01 · a lista responde Inertia com o componente Crm/Leads/Index', function () {
    $this->actingAs(leadUsuario('lead_todos_test', ['crm.access_all_leads']))
        ->get(LEAD_ROTA)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Leads/Index', false)->has('filtros'));
});

it('UC-CRMLD-02 · visita Inertia não cai no DataTables, e o ajax da tela clássica segue recebendo JSON', function () {
    $user = leadUsuario('lead_todos_test', ['crm.access_all_leads']);
    leadContato(LEAD_BIZ, 'Grafica Aurora', $user);

    expect(leadLista($user))->toContain('Grafica Aurora '.LEAD_TAG);

    $json = $this->actingAs($user)->get(LEAD_ROTA.'?lead_view=list_view', ['X-Requested-With' => 'XMLHttpRequest']);
    $json->assertOk();
    $this->assertArrayHasKey('data', (array) $json->json(), 'o DataTables da tela clássica deve continuar recebendo JSON');
    $this->assertNull($json->headers->get('X-Inertia'), 'ajax sem X-Inertia não pode virar resposta Inertia');
});

it('UC-CRMLD-03 · lead de outro negócio não aparece [T0]', function () {
    $user = leadUsuario('lead_todos_test', ['crm.access_all_leads']);
    $vizinho = leadUsuario('lead_vizinho_test', ['crm.access_all_leads'], LEAD_OUTRO);
    leadContato(LEAD_BIZ, 'Meu lead', $user);
    leadContato(LEAD_OUTRO, 'Lead vizinho', $vizinho);

    $nomes = leadLista($user);
    expect($nomes)->toContain('Meu lead '.LEAD_TAG);
    $this->assertNotContains('Lead vizinho '.LEAD_TAG, $nomes, 'vazou lead de outro business');
});

it('UC-CRMLD-04 · quem só pode ver os próprios vê só os atribuídos a ele', function () {
    $proprio = leadUsuario('lead_proprio_test', ['crm.access_own_leads']);
    $colega = leadUsuario('lead_colega_test', ['crm.access_all_leads']);
    leadContato(LEAD_BIZ, 'Atribuido a mim', $proprio);
    leadContato(LEAD_BIZ, 'Atribuido ao colega', $colega);

    $nomes = leadLista($proprio);
    expect($nomes)->toContain('Atribuido a mim '.LEAD_TAG);
    $this->assertNotContains('Atribuido ao colega '.LEAD_TAG, $nomes, 'quem só vê os próprios viu o do colega');

    expect(leadLista($colega))->toContain('Atribuido ao colega '.LEAD_TAG);
});

it('UC-CRMLD-05 · sem permissão de leads é barrado', function () {
    $this->actingAs(leadUsuario('lead_sem_test', []))->get(LEAD_ROTA)->assertForbidden();
    $this->actingAs(leadUsuario('lead_todos_test', ['crm.access_all_leads']))->get(LEAD_ROTA)->assertOk();
});

it('UC-CRMLD-06 · o drawer traz o lead pedido e nada fora do escopo da lista [T0]', function () {
    $user = leadUsuario('lead_todos_test', ['crm.access_all_leads']);
    $vizinho = leadUsuario('lead_vizinho_test', ['crm.access_all_leads'], LEAD_OUTRO);
    $meu = leadContato(LEAD_BIZ, 'Lead do drawer', $user);
    $deFora = leadContato(LEAD_OUTRO, 'Lead de fora', $vizinho);
    $cliente = leadContato(LEAD_BIZ, 'Cliente nao lead', $user, 'customer');

    $detalhe = leadParcial($user, 'lead', '?lead='.$meu)->json('props.lead');
    expect($detalhe['id'] ?? null)->toBe($meu);
    expect($detalhe['nome'] ?? '')->toContain('Lead do drawer');

    $this->assertNull(leadParcial($user, 'lead', '?lead='.$deFora)->json('props.lead'), 'o drawer abriu lead de outro business');
    $this->assertNull(leadParcial($user, 'lead', '?lead='.$cliente)->json('props.lead'), 'o drawer abriu contato que não é lead');
});

it('UC-CRMLD-07 · ?classico=1 e o kanban devolvem a tela Blade', function () {
    $user = leadUsuario('lead_todos_test', ['crm.access_all_leads']);
    $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];

    $this->actingAs($user)->withSession($sessao)->get(LEAD_ROTA.'?classico=1')
        ->assertOk()->assertViewIs('crm::lead.index');
    $this->actingAs($user)->withSession($sessao)->get(LEAD_ROTA.'?lead_view=kanban')
        ->assertOk()->assertViewIs('crm::lead.index');
});
