<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsUserSalesTarget;
use Modules\Essentials\Entities\EssentialsUserShift;
use Modules\Essentials\Entities\Shift;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Follow-up #4474 — gates EXPLÍCITOS de tenant em consumers Essentials (folha/RH)
 * de entities BelongsToBusinessViaParent (parent=user/shift). O backstop de
 * scope NÃO cobre INSERT/updateOrCreate — só SELECT.
 *
 * IDOR provado (auth + Admin#biz, sem gate de tenant no id cru do body):
 *   - SalesTargetController::saveSalesTarget → EssentialsUserSalesTarget::create
 *     com user_id CRU do body → cria meta/comissão no colaborador de OUTRO biz
 *   - ShiftController::postAssignUsers → Shift validado mas SEM abort + user_id
 *     cru → grava EssentialsUserShift cross-tenant (shift OU user de outro biz)
 *
 * Fix:
 *   - SalesTarget: User::where('business_id',$biz)->findOrFail($userId) antes
 *   - Shift: Shift::where('business_id',$biz)->findOrFail($shiftId) + abort_unless
 *     cada user_id do body ∈ users do business
 *
 * Admin#1 no acting user → Gate::before (AuthServiceProvider) autoriza todas as
 * cláusulas de permissão dos controllers (can/is_admin), isolando o teste no gate
 * de TENANT, não no de permissão.
 *
 * ADR 0093 Tier 0 IRREVOGÁVEL. ADR 0101: biz=1 (Wagner WR2) vs biz=99. NUNCA biz=4.
 */

const STS_BIZ_WAGNER = 1;
const STS_BIZ_FICTICIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0101).');
    }
    foreach (['essentials_shifts', 'essentials_user_shifts', 'essentials_user_sales_targets'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode migrate Modules/Essentials.");
        }
    }

    $business = Business::find(STS_BIZ_WAGNER);
    if (! $business) {
        $this->markTestSkipped('business_id=1 não encontrado — semear DB.');
    }
    $user = User::where('business_id', STS_BIZ_WAGNER)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=1.');
    }
    $this->wUser = $user;

    // Admin#1 → Gate::before autoriza as cláusulas de permissão. Idempotente +
    // rollback via DatabaseTransactions.
    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.STS_BIZ_WAGNER, 'guard_name' => 'web'],
        ['business_id' => STS_BIZ_WAGNER]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    if (! Business::find(STS_BIZ_FICTICIO)) {
        Business::forceCreate([
            'id' => STS_BIZ_FICTICIO,
            'name' => 'Test Biz Adversario#99 (Essentials IDOR)',
            'currency_id' => 1,
            'start_date' => now()->toDateString(),
            'default_profit_percent' => 0,
            'owner_id' => $user->id,
            'stop_selling_before' => 0,
            'weighing_scale_setting' => '',
            'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0,
        ]);
    }

    // NÃO setar session manualmente: as rotas /essentials rodam SetSessionData
    // DEPOIS do auth, então o middleware reconstrói user.business_id a partir do
    // usuário autenticado (padrão comprovado do EssentialsTestCase/TodoTest).
    // Setar user.business_id à mão aqui deixava o bloco não-stale → SetSessionData
    // pulava a reconstrução e o business_id chegava nulo no controller → 404
    // (gate não achava nem o próprio user/shift biz=1). flush + actingAs limpa.
    session()->flush();
    $this->actingAs($this->wUser);
});

function stsForeignUser(): ?User
{
    return User::where('business_id', '!=', STS_BIZ_WAGNER)->first();
}

function stsMakeShift(int $bizId): Shift
{
    // INSERT não é filtrado pelo scope; business_id explícito cria cross-tenant.
    return Shift::create([
        'business_id' => $bizId,
        'name' => 'STS-IDOR-'.$bizId.'-'.uniqid(),
        'type' => 'fixed_shift',
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
    ]);
}

it('SalesTarget cross-tenant: save-sales-target com user_id de outro biz → 404 e NÃO cria', function () {
    $foreign = stsForeignUser();
    if (! $foreign) {
        $this->markTestSkipped('Sem user em business != 1 (seed só tem biz=1).');
    }

    $antes = EssentialsUserSalesTarget::withoutGlobalScopes()->where('user_id', $foreign->id)->count();

    $resp = $this->post('/essentials/save-sales-target', [
        'user_id' => $foreign->id,
        'sales_amount_start' => ['0' => '100'],
        'sales_amount_end' => ['0' => '200'],
        'commission' => ['0' => '5'],
    ]);

    $resp->assertNotFound();
    $depois = EssentialsUserSalesTarget::withoutGlobalScopes()->where('user_id', $foreign->id)->count();
    expect($depois)->toBe($antes); // gate barrou o INSERT cross-tenant
});

it('SalesTarget positivo: save-sales-target no próprio user biz=1 → NÃO é 404 (gate passa)', function () {
    $resp = $this->post('/essentials/save-sales-target', [
        'user_id' => $this->wUser->id,
        'sales_amount_start' => [],
        'sales_amount_end' => [],
        'commission' => [],
    ]);

    if ($resp->status() === 404) {
        // APP_DEBUG=true no CI: o body do 404 nomeia a exceção
        // (ModelNotFound[App\User] = gate/business_id · NotFoundHttp = rota).
        test()->fail('404 BODY: '.substr(preg_replace('/\s+/', ' ', strip_tags((string) $resp->getContent())), 0, 300));
    }

    // Passa o gate de tenant (não 404). DatabaseTransactions desfaz qualquer escrita.
    expect($resp->status())->not->toBe(404);
    $resp->assertRedirect();
});

it('Shift cross-tenant (shift): assign-users em shift de outro biz → 404', function () {
    $shiftFicticio = stsMakeShift(STS_BIZ_FICTICIO);

    $resp = $this->post('/essentials/shift/assign-users', [
        'shift_id' => $shiftFicticio->id,
        'user_shift' => [],
    ]);

    $resp->assertNotFound();
    // Nenhuma atribuição criada pro shift cross-tenant.
    expect(EssentialsUserShift::withoutGlobalScopes()->where('essentials_shift_id', $shiftFicticio->id)->count())->toBe(0);
});

it('Shift cross-tenant (user): assign-users com user_id de outro biz num shift próprio → 403', function () {
    $foreign = stsForeignUser();
    if (! $foreign) {
        $this->markTestSkipped('Sem user em business != 1 (seed só tem biz=1).');
    }
    $shiftProprio = stsMakeShift(STS_BIZ_WAGNER);

    $resp = $this->post('/essentials/shift/assign-users', [
        'shift_id' => $shiftProprio->id,
        'user_shift' => [
            (string) $foreign->id => ['is_added' => 1, 'start_date' => null, 'end_date' => null],
        ],
    ]);

    $resp->assertForbidden();
    expect(EssentialsUserShift::withoutGlobalScopes()
        ->where('essentials_shift_id', $shiftProprio->id)
        ->where('user_id', $foreign->id)
        ->count())->toBe(0);
});
