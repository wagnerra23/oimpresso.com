<?php

declare(strict_types=1);

// @covers-us US-SELL-011

use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageActionRole;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-SELL-011 — Service canônico FSM (ADR 0129 §Service).
 *
 * Cobre as 6 responsabilidades + cross-tenant guard + terminal block + RBAC.
 * Default biz=1 (Wagner), cross-tenant adversário biz=99 (BusinessIdGuard).
 */

class FsmTestSubject extends Model
{
    protected $table = 'fsm_test_subjects';

    protected $guarded = ['id'];

    public $timestamps = false;
}

class FsmTestEvent
{
    public function __construct(
        public Model $subject,
        public SaleStageAction $action,
        public ?User $user,
    ) {}
}

beforeEach(function () {
    // SELF-SCHEMA (cria E dropa users/roles/permissions/sale_* no tearDown) — SQLITE-ONLY.
    // Contra MySQL real o afterEach DROPA tabelas vivas: incidente staging 2026-06-10
    // (sale_stage_actions/sale_stage_action_roles/sale_stage_history dropadas do
    // oimpresso_staging; restauradas via up() + re-seed). CI (sqlite) continua cobrindo.
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Self-schema test — SQLite-only (vs MySQL real dropa tabelas vivas; incidente staging 2026-06-10)');
    }

    Schema::create('users', function (Blueprint $t) {
        $t->increments('id');
        $t->string('username')->unique();
        $t->string('password');
        $t->integer('business_id')->nullable();
        $t->rememberToken();
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('fsm_test_subjects', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedBigInteger('current_stage_id')->nullable();
    });

    Schema::create('permissions', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('roles', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('model_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk');
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });

    foreach (glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: [] as $f) {
        (require $f)->up();
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (array_reverse(glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: []) as $f) {
            (require $f)->down();
        }
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'fsm_test_subjects', 'users'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function fsmSetupProcess(int $bizId, bool $terminalDest = false): array
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'key' => 'venda_padrao',
        'name' => 'Venda Padrão',
        'default_for_contact_type' => 'any',
        'active' => true,
    ]);

    $rascunho = SaleProcessStage::create([
        'process_id' => $process->id,
        'key' => 'rascunho', 'name' => 'Rascunho', 'sort_order' => 0, 'is_initial' => true,
    ]);
    $faturada = SaleProcessStage::create([
        'process_id' => $process->id,
        'key' => 'faturada', 'name' => 'Faturada', 'sort_order' => 1, 'is_terminal' => $terminalDest,
    ]);

    return compact('process', 'rascunho', 'faturada');
}

function fsmCreateSubject(int $bizId, ?int $stageId): FsmTestSubject
{
    $s = new FsmTestSubject;
    $s->business_id = $bizId;
    $s->current_stage_id = $stageId;
    $s->save();
    return $s;
}

function fsmUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'u' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

it('1. transição válida atualiza current_stage_id e loga history', function () {
    ['rascunho' => $r, 'faturada' => $f] = fsmSetupProcess(1);
    $action = SaleStageAction::create([
        'stage_id' => $r->id, 'key' => 'faturar', 'label' => 'Faturar',
        'target_stage_id' => $f->id,
    ]);
    $subject = fsmCreateSubject(1, $r->id);

    $history = (new ExecuteStageActionService)->execute($subject, 'faturar', fsmUser(1));

    expect($history)->toBeInstanceOf(SaleStageHistory::class);
    expect($history->from_stage_id)->toBe($r->id);
    expect($history->to_stage_id)->toBe($f->id);
    expect($history->action_id)->toBe($action->id);
    expect($subject->fresh()->current_stage_id)->toBe($f->id);
});

it('2. action inválida pra stage atual lança InvalidActionForCurrentStageException', function () {
    ['rascunho' => $r] = fsmSetupProcess(1);
    $subject = fsmCreateSubject(1, $r->id);

    (new ExecuteStageActionService)->execute($subject, 'inexistente', fsmUser(1));
})->throws(InvalidActionForCurrentStageException::class);

it('3. RBAC OK: user com role permitida executa sem erro', function () {
    ['rascunho' => $r, 'faturada' => $f] = fsmSetupProcess(1);
    $action = SaleStageAction::create([
        'stage_id' => $r->id, 'key' => 'faturar', 'label' => 'Faturar', 'target_stage_id' => $f->id,
    ]);
    SaleStageActionRole::create(['action_id' => $action->id, 'role_name' => 'caixa']);

    Role::create(['name' => 'caixa', 'guard_name' => 'web']);
    $user = fsmUser(1);
    $user->assignRole('caixa');

    $subject = fsmCreateSubject(1, $r->id);

    $history = (new ExecuteStageActionService)->execute($subject, 'faturar', $user);

    expect($history->user_id)->toBe($user->id);
});

it('4. RBAC falha: user sem nenhuma role exigida lança UnauthorizedActionException', function () {
    ['rascunho' => $r, 'faturada' => $f] = fsmSetupProcess(1);
    $action = SaleStageAction::create([
        'stage_id' => $r->id, 'key' => 'faturar', 'label' => 'Faturar', 'target_stage_id' => $f->id,
    ]);
    SaleStageActionRole::create(['action_id' => $action->id, 'role_name' => 'gerente']);
    Role::create(['name' => 'gerente', 'guard_name' => 'web']);

    $subject = fsmCreateSubject(1, $r->id);

    (new ExecuteStageActionService)->execute($subject, 'faturar', fsmUser(1));
})->throws(UnauthorizedActionException::class);

it('5. cross-tenant: subject biz=99 + process biz=1 lança UnauthorizedActionException', function () {
    ['rascunho' => $r, 'faturada' => $f] = fsmSetupProcess(1);
    SaleStageAction::create([
        'stage_id' => $r->id, 'key' => 'faturar', 'label' => 'Faturar', 'target_stage_id' => $f->id,
    ]);
    $subject = fsmCreateSubject(99, $r->id);

    (new ExecuteStageActionService)->execute($subject, 'faturar', fsmUser(1));
})->throws(UnauthorizedActionException::class);

it('6. terminal state bloqueia novas transições', function () {
    ['faturada' => $f] = fsmSetupProcess(1, terminalDest: true);
    SaleStageAction::create([
        'stage_id' => $f->id, 'key' => 'reabrir', 'label' => 'Reabrir', 'target_stage_id' => null,
    ]);
    $subject = fsmCreateSubject(1, $f->id);

    (new ExecuteStageActionService)->execute($subject, 'reabrir', fsmUser(1));
})->throws(InvalidActionForCurrentStageException::class, 'terminal');

it('7. history logada mesmo quando target_stage_id é null (action que não transita)', function () {
    ['rascunho' => $r] = fsmSetupProcess(1);
    SaleStageAction::create([
        'stage_id' => $r->id, 'key' => 'reemitir_2via', 'label' => 'Reemitir 2ª via',
        'target_stage_id' => null,
    ]);
    $subject = fsmCreateSubject(1, $r->id);

    $history = (new ExecuteStageActionService)->execute($subject, 'reemitir_2via', fsmUser(1));

    expect($history)->toBeInstanceOf(SaleStageHistory::class);
    expect($history->from_stage_id)->toBe($r->id);
    expect($history->to_stage_id)->toBeNull();
    expect($subject->fresh()->current_stage_id)->toBe($r->id);
});

it('8. event_class é disparado pós-execução', function () {
    Event::fake([FsmTestEvent::class]);

    ['rascunho' => $r, 'faturada' => $f] = fsmSetupProcess(1);
    SaleStageAction::create([
        'stage_id' => $r->id, 'key' => 'faturar', 'label' => 'Faturar',
        'target_stage_id' => $f->id, 'event_class' => FsmTestEvent::class,
    ]);
    $subject = fsmCreateSubject(1, $r->id);

    (new ExecuteStageActionService)->execute($subject, 'faturar', fsmUser(1));

    Event::assertDispatched(FsmTestEvent::class, fn ($e) => $e->subject->id === $subject->id);
});
