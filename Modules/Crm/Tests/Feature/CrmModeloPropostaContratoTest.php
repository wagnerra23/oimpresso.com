<?php

declare(strict_types=1);

use App\Business;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Contrato da tela `/crm/proposal-template` (Modelo de proposta) — Onda 1 do RUNBOOK-modelo-proposta:
 * Inertia atrás da flag `useV2CrmModeloProposta`, ligada aqui pelo override `feature-flags.forced_on`.
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Crm/Resources/js/Pages/Crm/ModeloProposta/Index.casos.md
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/ProposalTemplateController::index()
 */
const MOD_TAG = '[crmmod]';
const MOD_BIZ = 98;
const MOD_OUTRO = 99;
const MOD_ROTA = '/crm/proposal-template';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_proposal_templates requer schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'crm_proposal_templates', 'media'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    config(['feature-flags.forced_on' => 'useV2CrmModeloProposta']);
    modLimpa();
});

afterEach(fn () => modLimpa());

/** Apaga só o que o teste criou (marcador no assunto) e o modelo das empresas fictícias. */
function modLimpa(): void
{
    DB::table('crm_proposal_templates')->where('subject', 'like', '%'.MOD_TAG.'%')->delete();
}

function modNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function modUsuario(string $username, array $permissoes = [], int $biz = MOD_BIZ): User
{
    modNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Mod', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));
    $user->syncRoles([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

/** Garante que a empresa fique sem modelo próprio durante o teste, e cria o do teste. */
function modModelo(int $biz, string $assunto, User $quem, string $corpo = '<p>corpo</p>'): void
{
    modNegocio($biz);
    if (DB::table('crm_proposal_templates')->where('business_id', $biz)->where('subject', 'not like', '%'.MOD_TAG.'%')->exists()) {
        test()->markTestSkipped("A empresa {$biz} já tem um modelo que não é do teste; não é apagado.");
    }
    DB::table('crm_proposal_templates')->insert([
        'business_id' => $biz, 'subject' => $assunto.' '.MOD_TAG, 'body' => $corpo,
        'cc' => 'cc@test.local', 'bcc' => null, 'created_by' => $quem->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-CRMMOD-01 · com a flag ligada responde Inertia; com ?classico=1 responde a Blade', function () {
    $user = modUsuario('crmmod_leitor_test');

    $this->actingAs($user)->get(MOD_ROTA)->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/ModeloProposta/Index', false)
            ->has('modelo')->has('pode_editar')->has('pode_mandar'));

    $this->actingAs($user)->get(MOD_ROTA.'?classico=1')->assertOk()->assertViewIs('crm::proposal_template.index');
});

it('UC-CRMMOD-02 · o modelo exibido é o da minha empresa, nunca o da vizinha', function () {
    $user = modUsuario('crmmod_leitor_test');
    $vizinho = modUsuario('crmmod_vizinho_test', [], MOD_OUTRO);
    modModelo(MOD_OUTRO, 'Modelo da vizinha', $vizinho);
    modModelo(MOD_BIZ, 'Meu modelo', $user);

    $this->actingAs($user)->get(MOD_ROTA)->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->where('modelo.assunto', 'Meu modelo '.MOD_TAG));
});

it('UC-CRMMOD-03 · o corpo chega como texto, com quebra de linha e sem nenhuma tag', function () {
    $user = modUsuario('crmmod_leitor_test');
    modModelo(MOD_BIZ, 'Corpo html', $user, '<p>Olá, <strong>contato</strong>.</p><p>Segue a proposta.<br>Abraço</p>');

    $this->actingAs($user)->get(MOD_ROTA)->assertOk()
        ->assertInertia(function (AssertableInertia $p) {
            $corpo = (string) $p->toArray()['props']['modelo']['corpo'];
            expect($corpo)->not->toContain('<');
            expect($corpo)->toContain('Olá, contato.');
            expect($corpo)->toContain('Segue a proposta.');
            expect(substr_count($corpo, PHP_EOL))->toBeGreaterThanOrEqual(2);
        });
});

it('UC-CRMMOD-04 · sem modelo, a prop é nula e só quem pode cadastrar recebe pode_editar', function () {
    if (DB::table('crm_proposal_templates')->where('business_id', MOD_BIZ)->exists()) {
        $this->markTestSkipped('A empresa 98 já tem modelo fora do teste; não é apagado.');
    }
    $comPermissao = modUsuario('crmmod_editor_test', ['crm.add_proposal_template']);
    $semPermissao = modUsuario('crmmod_leitor_test');

    $this->actingAs($comPermissao)->get(MOD_ROTA)->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->where('modelo', null)->where('pode_editar', true)->where('pode_mandar', false));
    $this->actingAs($semPermissao)->get(MOD_ROTA)->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->where('modelo', null)->where('pode_editar', false));
});

it('UC-CRMMOD-05 · sem o módulo CRM na assinatura, a tela responde 403', function () {
    $user = modUsuario('crmmod_leitor_test');
    $semModulo = Mockery::mock(ModuleUtil::class)->makePartial();
    $semModulo->shouldReceive('hasThePermissionInSubscription')->andReturn(false);
    app()->instance(ModuleUtil::class, $semModulo);

    $this->actingAs($user)->get(MOD_ROTA)->assertForbidden();
});
