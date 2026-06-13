<?php

declare(strict_types=1);

use App\Domain\Fsm\Jobs\ExpireStaleReservationsJob;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\StockReservation;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Domain\Fsm\SideEffects\ConsumirEstoque;
use App\Domain\Fsm\SideEffects\LiberarReserva;
use App\Domain\Fsm\SideEffects\ReservarEstoque;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-SELL-013 — Reservas de estoque + 3 SideEffect classes (ADR 0129).
 *
 * Default biz=1 (Wagner), cross-tenant adversário biz=99 (BusinessIdGuard).
 */

class StockResTestSubject extends Model
{
    protected $table = 'fsm_test_subjects';

    protected $guarded = ['id'];

    public $timestamps = false;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
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

    Schema::create('variation_location_details', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('product_id')->unsigned();
        $t->integer('product_variation_id')->unsigned()->default(0);
        $t->integer('variation_id')->unsigned();
        $t->integer('location_id')->unsigned();
        $t->decimal('qty_available', 22, 4)->default(0);
        $t->timestamps();
    });

    // products: mantida VAZIA — BomResolver consulta no fallback combo (App\Product,
    // sem global scope). Vazia ⇒ produto não-combo ⇒ caminho "produto simples"
    // (ReservarEstoque cria 1 reserva por item). Tabela precisa existir mesmo vazia.
    Schema::create('products', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('business_id')->nullable();
        $t->string('name')->nullable();
        $t->string('type')->default('single');
        $t->timestamps();
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

    foreach (glob(database_path('migrations/2026_05_11_120*_create_sale_*.php')) ?: [] as $f) {
        (require $f)->up();
    }
    (require database_path('migrations/2026_05_11_130001_create_stock_reservations_table.php'))->up();
    // product_bom: BomResolver::resolve() consulta esta tabela ANTES do fallback
    // combo. Sem ela, ReservarEstoque quebra (no such table: product_bom).
    (require database_path('migrations/2026_05_12_080001_create_product_bom_table.php'))->up();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        (require database_path('migrations/2026_05_12_080001_create_product_bom_table.php'))->down();
        (require database_path('migrations/2026_05_11_130001_create_stock_reservations_table.php'))->down();
        foreach (array_reverse(glob(database_path('migrations/2026_05_11_120*_create_sale_*.php')) ?: []) as $f) {
            (require $f)->down();
        }
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'products', 'variation_location_details', 'fsm_test_subjects', 'users'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function stockMakeSubject(int $bizId): StockResTestSubject
{
    $s = new StockResTestSubject;
    $s->business_id = $bizId;
    $s->save();
    return $s;
}

function stockMakeVld(int $varId, int $locId, float $qty): int
{
    return DB::table('variation_location_details')->insertGetId([
        'product_id' => 100, 'variation_id' => $varId, 'location_id' => $locId,
        'qty_available' => $qty, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function stockUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'sru' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

it('1. ReservarEstoque cria reservations active com expires_at', function () {
    $subject = stockMakeSubject(1);

    (new ReservarEstoque)->execute($subject, [
        'items' => [
            ['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 6.0],
            ['product_id' => 101, 'variation_id' => 201, 'location_id' => 1, 'qty' => 2.5],
        ],
        'expires_in_days' => 15,
    ]);

    $rows = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->get();
    expect($rows)->toHaveCount(2);
    expect($rows->first()->status)->toBe(StockReservation::STATUS_ACTIVE);
    expect((float) $rows->first()->qty_reserved)->toBe(6.0);
    expect(abs($rows->first()->expires_at->diffInDays(now())))->toBeGreaterThanOrEqual(14);
});

it('2. ConsumirEstoque marca consumed + decrementa qty_available', function () {
    $subject = stockMakeSubject(1);
    stockMakeVld(200, 1, 10.0);

    (new ReservarEstoque)->execute($subject, [
        'items' => [['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 6.0]],
    ]);

    (new ConsumirEstoque)->execute($subject);

    $r = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($r->status)->toBe(StockReservation::STATUS_CONSUMED);

    $vld = DB::table('variation_location_details')->first();
    expect((float) $vld->qty_available)->toBe(4.0);
});

it('3. ConsumirEstoque guard: qty_available NUNCA fica negativo (clamp em 0)', function () {
    $subject = stockMakeSubject(1);
    stockMakeVld(200, 1, 2.0);

    (new ReservarEstoque)->execute($subject, [
        'items' => [['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 5.0]],
    ]);

    (new ConsumirEstoque)->execute($subject);

    $vld = DB::table('variation_location_details')->first();
    expect((float) $vld->qty_available)->toBe(0.0);
});

it('4. LiberarReserva marca released sem mexer qty_available', function () {
    $subject = stockMakeSubject(1);
    stockMakeVld(200, 1, 10.0);

    (new ReservarEstoque)->execute($subject, [
        'items' => [['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 6.0]],
    ]);

    (new LiberarReserva)->execute($subject);

    $r = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($r->status)->toBe(StockReservation::STATUS_RELEASED);

    $vld = DB::table('variation_location_details')->first();
    expect((float) $vld->qty_available)->toBe(10.0);
});

it('5. ExpireStaleReservationsJob expira só ACTIVE com expires_at vencido', function () {
    $subject = stockMakeSubject(1);

    StockReservation::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'product_id' => 100, 'variation_id' => 200, 'location_id' => 1,
        'qty_reserved' => 1.0, 'status' => StockReservation::STATUS_ACTIVE,
        'expires_at' => Carbon::now()->subDay(),
    ]);
    StockReservation::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'product_id' => 100, 'variation_id' => 200, 'location_id' => 1,
        'qty_reserved' => 1.0, 'status' => StockReservation::STATUS_ACTIVE,
        'expires_at' => Carbon::now()->addDay(),
    ]);
    StockReservation::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'product_id' => 100, 'variation_id' => 200, 'location_id' => 1,
        'qty_reserved' => 1.0, 'status' => StockReservation::STATUS_CONSUMED,
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $count = (new ExpireStaleReservationsJob)->handle();

    expect($count)->toBe(1);
    $expired = StockReservation::withoutGlobalScope(ScopeByBusiness::class)
        ->where('status', StockReservation::STATUS_EXPIRED)->count();
    expect($expired)->toBe(1);
});

it('6. multi-tenant: biz=1 não vê reservation biz=99', function () {
    $sBiz1 = stockMakeSubject(1);
    $sBiz99 = stockMakeSubject(99);

    (new ReservarEstoque)->execute($sBiz1, [
        'items' => [['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 1.0]],
    ]);
    (new ReservarEstoque)->execute($sBiz99, [
        'items' => [['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 1.0]],
    ]);

    $this->actingAs(stockUser(1));
    session(['user.business_id' => 1]);

    $visible = StockReservation::all();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->business_id)->toBe(1);
});

it('7. ConsumirEstoque idempotente: 2ª chamada não re-decrementa', function () {
    $subject = stockMakeSubject(1);
    stockMakeVld(200, 1, 10.0);

    (new ReservarEstoque)->execute($subject, [
        'items' => [['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 3.0]],
    ]);

    (new ConsumirEstoque)->execute($subject);
    (new ConsumirEstoque)->execute($subject);

    $vld = DB::table('variation_location_details')->first();
    expect((float) $vld->qty_available)->toBe(7.0);
});

it('8. integração FSM: ExecuteStageActionService dispara ReservarEstoque via side_effect_class', function () {
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1, 'key' => 'venda_padrao', 'name' => 'Venda',
        'default_for_contact_type' => 'any', 'active' => true,
    ]);
    $rascunho = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 'rascunho', 'name' => 'Rascunho',
        'sort_order' => 0, 'is_initial' => true,
    ]);
    $aprovada = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 'aprovada', 'name' => 'Aprovada',
        'sort_order' => 1,
    ]);
    SaleStageAction::create([
        'stage_id' => $rascunho->id, 'key' => 'aprovar', 'label' => 'Aprovar OS',
        'target_stage_id' => $aprovada->id,
        'side_effect_class' => ReservarEstoque::class,
        'side_effect_payload' => ['items' => [
            ['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 4.0],
        ]],
    ]);

    $subject = stockMakeSubject(1);
    $subject->current_stage_id = $rascunho->id;
    $subject->save();

    (new ExecuteStageActionService)->execute($subject, 'aprovar', stockUser(1));

    $rows = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->get();
    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()->qty_reserved)->toBe(4.0);
    expect($subject->fresh()->current_stage_id)->toBe($aprovada->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// Invariantes do fluxo (DOC-RAIZ-ESTOQUE §7) — guards de estado e hold ≠ baixa.
// Lacunas não cobertas pelos casos 1-8: reserva não baixa saldo (INV-4),
// idempotência da reserva, e imutabilidade dos estados terminais
// (consumed/released/expired) — toda mutação guarda em status=ACTIVE.
// ─────────────────────────────────────────────────────────────────────────────

it('9. INV-4: ReservarEstoque NÃO decrementa qty_available (hold ≠ baixa)', function () {
    $subject = stockMakeSubject(1);
    stockMakeVld(200, 1, 10.0);

    (new ReservarEstoque)->execute($subject, [
        'items' => [['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 6.0]],
    ]);

    // Reserva criada active, mas saldo físico intacto — só ConsumirEstoque baixa (INV-4).
    $r = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($r->status)->toBe(StockReservation::STATUS_ACTIVE);
    expect((float) $r->qty_reserved)->toBe(6.0);

    $vld = DB::table('variation_location_details')->first();
    expect((float) $vld->qty_available)->toBe(10.0);
});

it('10. ReservarEstoque idempotente: 2ª reserva do mesmo item não duplica nem soma', function () {
    $subject = stockMakeSubject(1);
    stockMakeVld(200, 1, 10.0);

    $payload = ['items' => [['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 6.0]]];
    (new ReservarEstoque)->execute($subject, $payload);
    (new ReservarEstoque)->execute($subject, $payload);

    $rows = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->get();
    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()->qty_reserved)->toBe(6.0);
});

it('11. ConsumirEstoque ignora reserva RELEASED: liberar→consumir não baixa saldo', function () {
    $subject = stockMakeSubject(1);
    stockMakeVld(200, 1, 10.0);

    (new ReservarEstoque)->execute($subject, [
        'items' => [['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 6.0]],
    ]);
    (new LiberarReserva)->execute($subject);   // active → released
    (new ConsumirEstoque)->execute($subject);  // só consome ACTIVE → no-op

    $r = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($r->status)->toBe(StockReservation::STATUS_RELEASED);

    $vld = DB::table('variation_location_details')->first();
    expect((float) $vld->qty_available)->toBe(10.0);
});

it('12. ConsumirEstoque ignora reserva EXPIRED: expirar→consumir não baixa saldo', function () {
    $subject = stockMakeSubject(1);
    stockMakeVld(200, 1, 10.0);

    // expires_in_days negativo força expires_at no passado → job expira.
    (new ReservarEstoque)->execute($subject, [
        'items' => [['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 6.0]],
        'expires_in_days' => -1,
    ]);
    expect((new ExpireStaleReservationsJob)->handle())->toBe(1); // active → expired
    (new ConsumirEstoque)->execute($subject);                    // só consome ACTIVE → no-op

    $r = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($r->status)->toBe(StockReservation::STATUS_EXPIRED);

    $vld = DB::table('variation_location_details')->first();
    expect((float) $vld->qty_available)->toBe(10.0);
});

it('13. estado terminal imutável: LiberarReserva após ConsumirEstoque não reverte a baixa', function () {
    $subject = stockMakeSubject(1);
    stockMakeVld(200, 1, 10.0);

    (new ReservarEstoque)->execute($subject, [
        'items' => [['product_id' => 100, 'variation_id' => 200, 'location_id' => 1, 'qty' => 6.0]],
    ]);
    (new ConsumirEstoque)->execute($subject);  // active → consumed, saldo 10 → 4
    (new LiberarReserva)->execute($subject);   // só libera ACTIVE → não toca consumed

    $r = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($r->status)->toBe(StockReservation::STATUS_CONSUMED);

    $vld = DB::table('variation_location_details')->first();
    expect((float) $vld->qty_available)->toBe(4.0); // baixa NÃO revertida
});
