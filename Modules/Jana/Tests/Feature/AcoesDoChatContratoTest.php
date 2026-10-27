<?php

declare(strict_types=1);

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Jana\Entities\AcaoAprovacao;
use Modules\Jana\Services\AcoesDoChat\AcaoPropostaException;
use Modules\Jana\Services\AcoesDoChat\CriarTarefaAcao;
use Modules\Jana\Services\AcoesDoChat\PropostaAcaoService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da ADR 0440 — a Jana propõe, o usuário confirma, e só então grava.
 * UC-JCHAT-18..23 em `resources/js/Pages/Jana/Chat.casos.md`, derivados da ADR e da
 * US-COPI-151, nunca do código. Tenant 98; o adversário cross-tenant é o 99.
 */
const JACAO_BIZ_ALHEIO = 99;

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

    $this->biz = (int) $business->id;
    $this->tUser = $user;
    $this->actingAs($user);
    // `business` vai como o MODELO, como o SetSessionData grava: o `Util::activityLog`
    // lê `->time_zone` dele, e um array de teste quebraria ali.
    session(['user.business_id' => $this->biz, 'user.id' => $user->id, 'business' => $business]);
});

function jacaoPropor(string $titulo): AcaoAprovacao
{
    return jacaoSvc()->propor(test()->biz, test()->tUser, null, 'criar-tarefa', [
        'titulo' => $titulo, 'data' => now()->addDay()->format('Y-m-d'), 'prioridade' => 'high',
    ]);
}

function jacaoTarefas(string $titulo): int
{
    return DB::table('essentials_to_dos')->where('task', $titulo)->count();
}

/** Executa a decisão e devolve o código da recusa (0 = aceitou). */
function jacaoCodigo(callable $decisao): int
{
    try {
        $decisao();

        return 0;
    } catch (AcaoPropostaException $e) {
        return $e->getCode();
    }
}

function jacaoSvc(): PropostaAcaoService
{
    return app(PropostaAcaoService::class);
}

it('UC-JCHAT-18 · a proposta guarda o resumo e não grava a tarefa', function () {
    $titulo = 'JACAO18 '.uniqid();
    $p = jacaoPropor($titulo);

    expect($p->status)->toBe('proposta');
    expect($p->origem)->toBe('chat');
    expect((int) $p->business_id)->toBe($this->biz);
    expect($p->previa)->toContain($titulo);
    expect($p->expira_em)->not->toBeNull();
    expect(jacaoTarefas($titulo))->toBe(0);
});

it('UC-JCHAT-19 · confirmar cria a tarefa como o usuário e deixa trilha', function () {
    $titulo = 'JACAO19 '.uniqid();
    $p = jacaoPropor($titulo);

    expect(jacaoSvc()->confirmar($p->id, $this->biz, $this->tUser)->status)->toBe('executada');

    $tarefa = DB::table('essentials_to_dos')->where('task', $titulo)->first();
    expect($tarefa)->not->toBeNull();
    expect((int) $tarefa->business_id)->toBe($this->biz);
    expect((int) $tarefa->created_by)->toBe((int) $this->tUser->id);
    expect((int) $p->fresh()->resultado['id'])->toBe((int) $tarefa->id);
    expect(DB::table('activity_log')->where('subject_type', AcaoAprovacao::class)->where('subject_id', $p->id)
        ->pluck('description')->all())->toContain('jana_acao_proposta', 'jana_acao_executada');
});

it('UC-JCHAT-20 · [T0] proposta da empresa 98 não executa com a sessão da 99', function () {
    $titulo = 'JACAO20 '.uniqid();
    $p = jacaoPropor($titulo);

    expect(jacaoCodigo(fn () => jacaoSvc()->confirmar($p->id, JACAO_BIZ_ALHEIO, $this->tUser)))->toBe(404);

    expect(jacaoTarefas($titulo))->toBe(0);
    expect($p->fresh()->status)->toBe('proposta');
});

it('UC-JCHAT-21 · cancelar fecha sem gravar, e a proposta decidida não executa depois', function () {
    $titulo = 'JACAO21 '.uniqid();
    $p = jacaoPropor($titulo);

    expect(jacaoSvc()->cancelar($p->id, $this->biz, $this->tUser)->status)->toBe('cancelada');
    expect(jacaoCodigo(fn () => jacaoSvc()->confirmar($p->id, $this->biz, $this->tUser)))->toBe(409);

    expect(jacaoTarefas($titulo))->toBe(0);
});

it('UC-JCHAT-22 · sem permissão na hora de confirmar, nada grava', function () {
    $titulo = 'JACAO22 '.uniqid();
    $p = jacaoPropor($titulo);
    app()->instance(CriarTarefaAcao::class, new class(app(\Modules\Essentials\Services\TodoService::class)) extends CriarTarefaAcao {
        public function permitida(Model $user, int $businessId): bool
        {
            return false;
        }
    });

    expect(jacaoCodigo(fn () => jacaoSvc()->confirmar($p->id, $this->biz, $this->tUser)))->toBe(403);

    expect(jacaoTarefas($titulo))->toBe(0);
    expect($p->fresh()->status)->toBe('proposta');
});

it('UC-JCHAT-23 · proposta vencida não executa', function () {
    $titulo = 'JACAO23 '.uniqid();
    $p = jacaoPropor($titulo);
    $p->update(['expira_em' => now()->subMinute()]);

    expect(jacaoCodigo(fn () => jacaoSvc()->confirmar($p->id, $this->biz, $this->tUser)))->toBe(410);

    expect(jacaoTarefas($titulo))->toBe(0);
});
