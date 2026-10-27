<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use Modules\Jana\Ai\Agents\ChatCopilotoAgent;
use Modules\Jana\Ai\Tools\Acoes\ProporTarefaTool;
use Modules\Jana\Entities\AcaoAprovacao;
use Modules\Jana\Entities\Conversa;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * A tool que deixa a Jana PROPOR uma tarefa (ADR 0440). UC-JCHAT-27..29 em
 * `resources/js/Pages/Jana/Chat.casos.md`. Tenant 98; adversário 99.
 * O dono das tools de LEITURA segue sendo `ChatCopilotoAgentToolsTest` — aqui só
 * o que a ação acrescenta.
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
    $role = Role::firstOrCreate(['name' => 'Admin#'.$business->id, 'guard_name' => 'web'], ['business_id' => $business->id]);
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->biz = (int) $business->id;
    $this->tUser = $user;
    $this->actingAs($user);
    session(['user.business_id' => $this->biz, 'user.id' => $user->id, 'business' => $business]);
});

function jtoolConversa(int $biz, int $userId): Conversa
{
    $c = new Conversa(['business_id' => $biz, 'user_id' => $userId, 'titulo' => 't']);
    $c->id = 777001;

    return $c;
}

/** @return list<object> */
function jtoolAcoes(Conversa $c): array
{
    return array_values(array_filter(
        iterator_to_array((new ChatCopilotoAgent($c))->tools(), false),
        fn ($t) => $t instanceof ProporTarefaTool,
    ));
}

it('UC-JCHAT-27 · a tool de propor só existe com a flag de ação ligada, e declara a permissão', function () {
    $c = jtoolConversa($this->biz, (int) $this->tUser->id);

    expect(jtoolAcoes($c))->toBe([]);
    expect((string) (new ChatCopilotoAgent($c))->instructions())->not->toContain('AÇÕES');

    config(['copiloto.chat_acoes.enabled' => true]);

    $acoes = jtoolAcoes($c);
    expect($acoes)->toHaveCount(1);
    expect($acoes[0]->permission())->toBe('essentials.add_todos');
    expect((string) (new ChatCopilotoAgent($c))->instructions())->toContain('Nunca diga que já criou');
});

it('UC-JCHAT-28 · chamar a tool cria a proposta da conversa, e nenhuma tarefa', function () {
    config(['copiloto.chat_acoes.enabled' => true]);
    $titulo = 'JTOOL28 '.uniqid();
    $tool = jtoolAcoes(jtoolConversa($this->biz, (int) $this->tUser->id))[0];

    $saida = json_decode((string) $tool->handle(new Request(['titulo' => $titulo, 'prioridade' => 'urgent'])), true);

    expect($saida['status'])->toBe('aguardando_confirmacao');
    $p = AcaoAprovacao::find($saida['proposta_id']);
    expect((int) $p->business_id)->toBe($this->biz);
    expect((int) $p->conversa_id)->toBe(777001);
    expect($p->status)->toBe('proposta');
    expect(DB::table('essentials_to_dos')->where('task', $titulo)->count())->toBe(0);
});

it('UC-JCHAT-29 · [T0] conversa de outra empresa não recebe tool de ação', function () {
    config(['copiloto.chat_acoes.enabled' => true]);

    expect(jtoolAcoes(jtoolConversa(99, (int) $this->tUser->id)))->toBe([]);
});
