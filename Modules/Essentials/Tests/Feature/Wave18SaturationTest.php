<?php

declare(strict_types=1);

use App\Concerns\BelongsToBusinessViaParent;
use App\Concerns\HasBusinessScope;
use Modules\Essentials\Entities\DocumentShare;
use Modules\Essentials\Entities\EssentialsAllowanceAndDeduction;
use Modules\Essentials\Entities\EssentialsAttendance;
use Modules\Essentials\Entities\EssentialsHoliday;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Modules\Essentials\Entities\EssentialsMessage;
use Modules\Essentials\Entities\EssentialsTodoComment;
use Modules\Essentials\Entities\EssentialsUserAllowancesAndDeduction;
use Modules\Essentials\Entities\EssentialsUserSalesTarget;
use Modules\Essentials\Entities\EssentialsUserShift;
use Modules\Essentials\Entities\PayrollGroup;
use Modules\Essentials\Entities\PayrollGroupTransaction;
use Modules\Essentials\Entities\Shift;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Jana\Scopes\ScopeByBusinessViaParent;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 18 SATURATION — multi-tenant Tier 0 + D7 retention para Entities Essentials
 * que ainda não estavam cobertas pela auditoria Wave 12.
 *
 * Cobertura:
 *  - 7 Entities com business_id DIRETO → HasBusinessScope
 *    (EssentialsAttendance, EssentialsHoliday, EssentialsLeaveType, EssentialsMessage,
 *    EssentialsAllowanceAndDeduction, PayrollGroup, Shift)
 *  - 6 Entities filhas → BelongsToBusinessViaParent via FK chain
 *    (EssentialsTodoComment, EssentialsUserShift, EssentialsUserAllowancesAndDeduction,
 *    EssentialsUserSalesTarget, DocumentShare, PayrollGroupTransaction)
 *  - retention.php expanded com 9 entries novas D7
 *
 * 13 Entities × 2 datasets (with vs without scope) = cobertura full multi-tenant.
 *
 * @see Modules/Essentials/Entities/*.php (Wave 18 D1 SATURATION)
 * @see Modules/Essentials/Config/retention.php (Wave 18 D7 SATURATION)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

dataset('entities_business_direto', [
    'EssentialsAttendance' => [EssentialsAttendance::class],
    'EssentialsHoliday' => [EssentialsHoliday::class],
    'EssentialsLeaveType' => [EssentialsLeaveType::class],
    'EssentialsMessage' => [EssentialsMessage::class],
    'EssentialsAllowanceAndDeduction' => [EssentialsAllowanceAndDeduction::class],
    'PayrollGroup' => [PayrollGroup::class],
    'Shift' => [Shift::class],
]);

dataset('entities_via_parent', [
    'EssentialsTodoComment' => [EssentialsTodoComment::class],
    'EssentialsUserShift' => [EssentialsUserShift::class],
    'EssentialsUserAllowancesAndDeduction' => [EssentialsUserAllowancesAndDeduction::class],
    'EssentialsUserSalesTarget' => [EssentialsUserSalesTarget::class],
    'DocumentShare' => [DocumentShare::class],
    'PayrollGroupTransaction' => [PayrollGroupTransaction::class],
]);

it('Entities com business_id direto usam HasBusinessScope (Wave 18 D1 SATURATION)', function (string $fqcn) {
    // Entity sem HasBusinessScope viola ADR 0093 Tier 0 IRREVOGÁVEL
    $traits = class_uses_recursive($fqcn);
    expect($traits)->toContain(HasBusinessScope::class);
})->with('entities_business_direto');

it('Entities com business_id direto registram ScopeByBusiness no boot', function (string $fqcn) {
    // Entity sem ScopeByBusiness aplicado significa que o boot trait não rodou
    $globalScopes = (new $fqcn())->getGlobalScopes();
    expect($globalScopes)->toHaveKey(ScopeByBusiness::class);
})->with('entities_business_direto');

it('Entities filhas usam BelongsToBusinessViaParent (Wave 18 D1 SATURATION)', function (string $fqcn) {
    // Entity filha sem BelongsToBusinessViaParent viola ADR 0093 (pivot/child)
    $traits = class_uses_recursive($fqcn);
    expect($traits)->toContain(BelongsToBusinessViaParent::class);
})->with('entities_via_parent');

it('Entities filhas registram ScopeByBusinessViaParent no boot', function (string $fqcn) {
    // Entity sem ScopeByBusinessViaParent aplicado significa que o boot trait não rodou
    $globalScopes = (new $fqcn())->getGlobalScopes();
    expect($globalScopes)->toHaveKey(ScopeByBusinessViaParent::class);
})->with('entities_via_parent');

it('Entities filhas declaram businessParentRelation property', function (string $fqcn) {
    // Precisa declarar protected string $businessParentRelation pro ScopeByBusinessViaParent saber qual FK seguir
    $reflection = new ReflectionClass($fqcn);
    expect($reflection->hasProperty('businessParentRelation'))->toBeTrue();
})->with('entities_via_parent');

it('EssentialsMessage usa LogsActivity (D7 LGPD audit Wave 18)', function () {
    // EssentialsMessage precisa logar metadata (subject/to_email) — conteúdo pode citar PII
    $traits = class_uses_recursive(EssentialsMessage::class);
    expect($traits)->toContain(LogsActivity::class);
});

it('retention.php expanded Wave 18 cobre 9 entities novas (D7 SATURATION)', function () {
    $config = require base_path('Modules/Essentials/Config/retention.php');

    $newKeys = [
        'essentials_allowance_deduction',
        'essentials_user_allowance_deduction',
        'payroll_group',
        'payroll_group_transaction',
        'essentials_user_sales_target',
        'essentials_user_shift',
        'essentials_todo_comment',
        'shift',
        'essentials_leave_type',
    ];

    foreach ($newKeys as $k) {
        // retention.php Wave 18 deve declarar política pra cada key (D7 LGPD)
        expect($config['entities'])->toHaveKey($k);
    }

    // Folha/RH: 5 anos mínimo (CLT Art. 11 prescricional + RFB fiscal)
    expect($config['entities']['essentials_allowance_deduction'])->toBe(1825);
    expect($config['entities']['payroll_group'])->toBe(1825);
    expect($config['entities']['essentials_user_shift'])->toBe(1825);
});

it('escape valve withoutGlobalScope continua funcionando nas Entities novas', function (string $fqcn) {
    // withoutGlobalScope deve funcionar (escape valve superadmin)
    $query = $fqcn::withoutGlobalScope(ScopeByBusiness::class)->getQuery();
    expect($query)->not->toBeNull();
})->with('entities_business_direto');
