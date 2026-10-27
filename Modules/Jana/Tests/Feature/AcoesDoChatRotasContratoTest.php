<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Jana\Services\AcoesDoChat\PropostaAcaoService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Rotas do cartão de ação do chat (ADR 0440). UC-JCHAT-24..26 em
 * `resources/js/Pages/Jana/Chat.casos.md`. O serviço por trás já tem contrato
 * próprio (`AcoesDoChatContratoTest`); aqui se prova o que a rota acrescenta:
 * a flag, o business da SESSÃO e o status HTTP de cada recusa. Tenant 98.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL.');
    }
    $business = $this->seededTenant();
    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        $this->markTestSkipped("Sem user em business_id={$business->id}.");
    }
    foreach (['jana.access', 'jana.chat'] as $perm) {
        Permission::findOrCreate($perm, 'web');
        $user->givePermissionTo($perm);
    }
    $role = Role::firstOrCreate(['name' => 'Admin#'.$business->id, 'guard_name' => 'web'], ['business_id' => $business->id]);
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Notification::fake();
    config(['copiloto.chat_acoes.enabled' => true]);

    $this->biz = (int) $business->id;
    $this->tUser = $user;
    $this->actingAs($user);
    // `business` vai como o MODELO, como o SetSessionData grava: o `Util::activityLog`
    // lê `->time_zone` dele.
    session(['user.business_id' => $this->biz, 'user.id' => $user->id, 'business' => $business]);
});

function jrotaPropor(string $titulo, ?int $conversa = null)
{
    return app(PropostaAcaoService::class)->propor(test()->biz, test()->tUser, $conversa, 'criar-tarefa', [
        'titulo' => $titulo, 'data' => now()->format('Y-m-d'),
    ]);
}

it('UC-JCHAT-24 · com a flag desligada, listar e decidir respondem 404', function () {
    $p = jrotaPropor('JROTA24 '.uniqid());
    config(['copiloto.chat_acoes.enabled' => false]);

    $this->getJson('/ia/conversas/1/acoes')->assertNotFound();
    $this->postJson("/ia/acoes-chat/{$p->id}/confirmar")->assertNotFound();
    $this->postJson("/ia/acoes-chat/{$p->id}/cancelar")->assertNotFound();
    expect($p->fresh()->status)->toBe('proposta');
});

it('UC-JCHAT-25 · confirmar pela rota grava com o business da sessão; outro business dá 404', function () {
    $titulo = 'JROTA25 '.uniqid();
    $p = jrotaPropor($titulo);

    session(['user.business_id' => 99]);
    $this->postJson("/ia/acoes-chat/{$p->id}/confirmar")->assertNotFound()->assertJsonPath('erro', 'Proposta não encontrada.');
    expect(DB::table('essentials_to_dos')->where('task', $titulo)->count())->toBe(0);

    session(['user.business_id' => $this->biz]);
    $this->postJson("/ia/acoes-chat/{$p->id}/confirmar")
        ->assertOk()->assertJsonPath('acao.status', 'executada')->assertJsonPath('acao.resultado.url', fn ($u) => str_starts_with((string) $u, '/essentials/todo/'));
    expect(DB::table('essentials_to_dos')->where('task', $titulo)->where('business_id', $this->biz)->count())->toBe(1);
});

it('UC-JCHAT-26 · a conversa lista só as minhas propostas, com resumo e status', function () {
    $minha = jrotaPropor('JROTA26 '.uniqid(), 424242);
    app(PropostaAcaoService::class)->cancelar($minha->id, $this->biz, $this->tUser);
    $aberta = jrotaPropor('JROTA26b '.uniqid(), 424242);

    $res = $this->getJson('/ia/conversas/424242/acoes')->assertOk();

    expect(collect($res->json('acoes'))->pluck('status', 'id')->all())
        ->toBe([$minha->id => 'cancelada', $aberta->id => 'proposta']);
    expect($res->json('acoes.1.resumo'))->toBe($aberta->previa);
});
