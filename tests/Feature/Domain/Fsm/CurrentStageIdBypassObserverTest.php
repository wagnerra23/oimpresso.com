<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * CU-03 (G4) — UPDATE direto em current_stage_id é bloqueado por Observer.
 *
 * Specs FAILING-FIRST:
 *   1. App\Domain\Fsm\Observers\TransactionFsmObserver ainda não existe
 *   2. Registro do observer em booted() de Transaction ainda não existe
 *   3. Flag _fsmAuthorizedTransition no ExecuteStageActionService ainda não existe
 *
 * Pain point Wagner 2026-05-12 (implícito):
 *   "Sem ninguém ter autorizado" — alguém pode estar burlando via Eloquent
 *   $tx->current_stage_id = X; $tx->save() ou via tinker.
 *
 * Surface attack: ExecuteStageActionService é o gateway recomendado mas
 * NÃO o obrigatório. Observer transforma em obrigatório.
 *
 * Ver: memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md §CU-03
 */

class FsmObserverTestSubject extends Model
{
    protected $table = 'fsm_observer_subjects';

    protected $guarded = ['id'];

    public $timestamps = false;

    /**
     * O Observer (a criar) precisa ser bootado também pra esse model de teste.
     * Quando a US-SELL-032 implementar, este método chama o observer canônico.
     */
    protected static function booted(): void
    {
        $observerClass = '\App\Domain\Fsm\Observers\TransactionFsmObserver';
        if (class_exists($observerClass)) {
            static::observe(app($observerClass));
        }
    }
}

beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->increments('id');
        $t->string('username')->unique();
        $t->string('password');
        $t->integer('business_id')->nullable();
        $t->rememberToken();
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('fsm_observer_subjects', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedBigInteger('current_stage_id')->nullable();
    });

    foreach (['permissions', 'roles'] as $tbl) {
        Schema::create($tbl, function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });
    }
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk');
    });
    Schema::create('model_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });

    foreach (glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: [] as $f) {
        (require $f)->up();
    }
});

afterEach(function () {
    foreach (array_reverse(glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: []) as $f) {
        (require $f)->down();
    }
    foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'fsm_observer_subjects', 'users'] as $tbl) {
        Schema::dropIfExists($tbl);
    }
});

function fsmObserverSetup(int $bizId): array
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'key' => 'venda_padrao', 'name' => 'Venda Padrão',
        'default_for_contact_type' => 'any', 'active' => true,
    ]);

    $a = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 's1', 'name' => 'S1', 'sort_order' => 0, 'is_initial' => true,
    ]);
    $b = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 's2', 'name' => 'S2', 'sort_order' => 1,
    ]);

    return compact('process', 'a', 'b');
}

function fsmObserverSubject(int $bizId, int $stageId): FsmObserverTestSubject
{
    $s = new FsmObserverTestSubject;
    $s->business_id = $bizId;
    $s->current_stage_id = $stageId;
    $s->save();
    return $s;
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. UPDATE direto em current_stage_id (sem service) lança UnauthorizedActionException', function () {
    ['a' => $a, 'b' => $b] = fsmObserverSetup(1);
    $subject = fsmObserverSubject(1, $a->id);

    // Tentativa burra: setar direto + save
    $subject->current_stage_id = $b->id;

    expect(fn () => $subject->save())
        ->toThrow(UnauthorizedActionException::class, 'use ExecuteStageActionService');

    // Recarrega do banco — stage permanece original
    expect(FsmObserverTestSubject::find($subject->id)->current_stage_id)->toBe($a->id);
});

it('2. ExecuteStageActionService transitiona normalmente (com flag interna)', function () {
    ['a' => $a, 'b' => $b, 'process' => $p] = fsmObserverSetup(1);
    SaleStageAction::create([
        'stage_id' => $a->id, 'key' => 'avancar', 'label' => 'Avançar', 'target_stage_id' => $b->id,
    ]);

    $subject = fsmObserverSubject(1, $a->id);
    $user = User::forceCreate(['username' => 'u1', 'password' => bcrypt('x'), 'business_id' => 1]);

    // Service usa flag _fsmAuthorizedTransition — Observer libera
    $history = (new ExecuteStageActionService)->execute($subject, 'avancar', $user);

    expect($history->to_stage_id)->toBe($b->id);
    expect($subject->fresh()->current_stage_id)->toBe($b->id);
});

it('3. mass update Eloquent BYPASSA o Observer (limitação técnica conhecida)', function () {
    ['a' => $a, 'b' => $b] = fsmObserverSetup(1);
    $subject = fsmObserverSubject(1, $a->id);

    // Eloquent mass update (Builder::update) NÃO dispara events `updating` por
    // padrão — é otimização do framework. Observer não pega.
    // Mitigação: comando artisan `fsm:scan-drift` periódico (US-SELL-032 v2
    // ou US separada) cruza current_stage_id com sale_stage_history e detecta
    // registros que mudaram fora do gateway canônico.
    // Este spec DOCUMENTA a limitação — não deve falhar.
    FsmObserverTestSubject::where('id', $subject->id)->update(['current_stage_id' => $b->id]);

    // Mass update foi aplicado (bypass confirmado, não bloqueado)
    expect(FsmObserverTestSubject::find($subject->id)->current_stage_id)->toBe($b->id);
});

it('4. flag _fsmAuthorizedTransition explícita libera + gera log WARNING auditável', function () {
    ['a' => $a, 'b' => $b] = fsmObserverSetup(1);
    $subject = fsmObserverSubject(1, $a->id);

    Log::spy();

    // Escape hatch superadmin: flag explícita libera Observer mas registra
    // log estruturado pra auditoria (fsm:scan-drift cruza com sale_stage_history)
    $subject->_fsmAuthorizedTransition = true;
    $subject->current_stage_id = $b->id;
    $subject->save();

    expect(FsmObserverTestSubject::find($subject->id)->current_stage_id)->toBe($b->id);
    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $msg, array $ctx) => str_contains($msg, 'FSM authorized transition'))
        ->once();
});

it('5. update parcial de outros campos NÃO dispara o Observer (back-compat)', function () {
    ['a' => $a] = fsmObserverSetup(1);
    $subject = fsmObserverSubject(1, $a->id);

    // Outros campos podem ser atualizados normalmente — Observer só pega current_stage_id
    $subject->business_id = 1; // re-atribui (no-op)
    expect(fn () => $subject->save())->not->toThrow(UnauthorizedActionException::class);
});
