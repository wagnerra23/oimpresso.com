<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Baseline da Blade `/crm/proposals` (Propostas) ANTES da tela React — thread Crm/14, F2 do MWART.
 *
 * Trava o que a Blade lista e mostra hoje; a F3 (Page atrás da flag) tem de manter cada linha.
 * As linhas vêm do mapa de paridade, não do código:
 *   memory/requisitos/Crm/propostas-parity.md (linhas 1, 2, 3 e 7)
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/ProposalController::index()
 * @see Modules/Crm/Http/Controllers/ProposalController::show()
 */
const PRB_TAG = '[prb14]';
const PRB_BIZ = 98;
const PRB_OUTRO = 99;
const PRB_ROTA = '/crm/proposals';
const PRB_AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_proposals requer schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'roles', 'crm_proposals', 'crm_proposal_templates'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    prbLimpa();
});

afterEach(fn () => prbLimpa());

function prbLimpa(): void
{
    DB::table('crm_proposals')->where('subject', 'like', '%'.PRB_TAG.'%')->delete();
    DB::table('crm_proposal_templates')->where('subject', 'like', '%'.PRB_TAG.'%')->delete();
    DB::table('contacts')->where('name', 'like', '%'.PRB_TAG.'%')->delete();
}

/** Mesmo conserto do CrmPainelContratoTest::paiNegocio (`id` é guarded; owner_id obrigatório). */
function prbNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function prbUsuario(string $username, array $permissoes = [], bool $admin = false, int $biz = PRB_BIZ): User
{
    prbNegocio($biz);
    // `user_type`/`allow_login` explícitos: o CheckUserLogin barra o model recém-criado sem eles.
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Prb', 'last_name' => 'Teste',
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

/** Proposta crua enviada por `$quem` no negócio `$biz`. */
function prbProposta(int $biz, string $assunto, User $quem): int
{
    prbNegocio($biz);
    $contato = DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Contato '.PRB_TAG, 'mobile' => '0',
        'created_by' => $quem->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('crm_proposals')->insertGetId([
        'business_id' => $biz, 'contact_id' => $contato, 'sent_by' => $quem->id,
        'subject' => $assunto.' '.PRB_TAG, 'body' => '<p>corpo</p>', 'cc' => null, 'bcc' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Assuntos marcados que o DataTables da Blade devolve para `$user`. */
function prbAssuntos(User $user): array
{
    $resposta = test()->actingAs($user)->get(PRB_ROTA, PRB_AJAX);
    $resposta->assertOk();

    return collect((array) $resposta->json('data'))
        ->pluck('subject')
        ->filter(fn ($s) => str_contains((string) $s, PRB_TAG))
        ->values()->all();
}

it('BASE-PROP-01 · a lista traz só propostas do negócio da sessão [T0]', function () {
    $admin = prbUsuario('prb_admin_test', [], true);
    $vizinho = prbUsuario('prb_vizinho_test', [], true, PRB_OUTRO);
    prbProposta(PRB_BIZ, 'Fachada loja', $admin);
    prbProposta(PRB_OUTRO, 'Proposta do vizinho', $vizinho);

    $assuntos = prbAssuntos($admin);
    expect($assuntos)->toContain('Fachada loja '.PRB_TAG);
    $this->assertNotContains('Proposta do vizinho '.PRB_TAG, $assuntos, 'vazou proposta de outro business');
});

it('BASE-PROP-02 · quem não é Admin# vê só as que enviou; o Admin# vê as do colega', function () {
    $comum = prbUsuario('prb_comum_test');
    $colega = prbUsuario('prb_colega_test');
    $admin = prbUsuario('prb_admin_test', [], true);
    prbProposta(PRB_BIZ, 'Enviada por mim', $comum);
    prbProposta(PRB_BIZ, 'Enviada pelo colega', $colega);

    $meus = prbAssuntos($comum);
    expect($meus)->toContain('Enviada por mim '.PRB_TAG);
    $this->assertNotContains('Enviada pelo colega '.PRB_TAG, $meus, 'quem não é Admin# viu proposta do colega');

    expect(prbAssuntos($admin))->toContain('Enviada pelo colega '.PRB_TAG);
});

it('BASE-PROP-03 · o detalhe abre a proposta do negócio e não abre a do vizinho [T0]', function () {
    $admin = prbUsuario('prb_admin_test', [], true);
    $vizinho = prbUsuario('prb_vizinho_test', [], true, PRB_OUTRO);
    $minha = prbProposta(PRB_BIZ, 'Detalhe meu', $admin);
    $dele = prbProposta(PRB_OUTRO, 'Detalhe vizinho', $vizinho);

    $this->actingAs($admin)->get(PRB_ROTA.'/'.$minha, PRB_AJAX)
        ->assertOk()
        ->assertViewIs('crm::proposal.show')
        ->assertSee('Detalhe meu '.PRB_TAG, false);

    // Hoje a Blade cai em erro com o registro nulo; o que se trava é o conteúdo não sair.
    $this->actingAs($admin)->get(PRB_ROTA.'/'.$dele, PRB_AJAX)
        ->assertDontSee('Detalhe vizinho '.PRB_TAG, false);
});

it('BASE-PROP-04 · a Blade mostra Enviar só com modelo cadastrado e crm.access_proposal', function () {
    $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];
    $enviar = 'send-proposal';
    // Nenhum dos dois é Admin#: o Gate::before dá toda permissão de negócio ao papel Admin#.
    $comPermissao = prbUsuario('prb_envia_test', ['crm.access_proposal']);
    $semPermissao = prbUsuario('prb_comum_test');

    $this->actingAs($comPermissao)->withSession($sessao)->get(PRB_ROTA)
        ->assertOk()->assertViewIs('crm::proposal.index')->assertDontSee($enviar, false);

    DB::table('crm_proposal_templates')->insert([
        'business_id' => PRB_BIZ, 'subject' => 'Modelo '.PRB_TAG, 'body' => '<p>m</p>',
        'created_by' => $comPermissao->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($comPermissao)->withSession($sessao)->get(PRB_ROTA)->assertOk()->assertSee($enviar, false);
    $this->actingAs($semPermissao)->withSession($sessao)->get(PRB_ROTA)->assertOk()->assertDontSee($enviar, false);
});
