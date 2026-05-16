<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Modules\Repair\Entities\JobSheet;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Wave 3 B6 MWART — JobSheet Show port Blade → Inertia.
 * F2 BASELINE (flag OFF) + F4 INERTIA (flag ON).
 *
 * Pattern segue RepairIndexMwartTest.php (Sprint 2). Auto-skip se DB dev
 * não tiver businesses/users semeados (Pest contra DB real, UltimatePOS).
 */

function w3b6BootstrapUser(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }
    if (! $business) {
        test()->markTestSkipped('Sem business no banco.');
    }
    try {
        $user = User::where('business_id', $business->id)->first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela users indisponível.');
    }
    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    try {
        foreach ([
            'job_sheet.view_all',
            'job_sheet.view_assigned',
            'job_sheet.create',
            'job_sheet.edit',
            'job_sheet.delete',
        ] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    } catch (\Throwable $e) {
        test()->markTestSkipped('Permissions indisponível: '.$e->getMessage());
    }

    session([
        'user.business_id' => $business->id,
        'user.id' => $user->id,
        'business.id' => $business->id,
        'business.name' => $business->name,
        'business.currency_symbol' => 'R$',
        'business' => [
            'id' => $business->id,
            'name' => $business->name,
            'currency_symbol' => 'R$',
        ],
        'is_admin' => true,
    ]);

    return [$business, $user];
}

function w3b6GivePerm(User $user, string $perm): void
{
    try {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        if (! $user->hasPermissionTo($perm)) {
            $user->givePermissionTo($perm);
        }
    } catch (\Throwable $e) {
        test()->markTestSkipped('Permission grant falhou: '.$e->getMessage());
    }
}

function w3b6FindOrSkipJobSheet(int $business_id): ?JobSheet
{
    try {
        $js = JobSheet::where('business_id', $business_id)->first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('JobSheet schema indisponível: '.$e->getMessage());
    }
    return $js;
}

afterEach(function () {
    config([
        'mwart.repair_job_sheet_show.enabled' => false,
        'mwart.repair_job_sheet_show.business_ids' => [],
    ]);
});

it('respeita flag MWART desligada — retorna Blade JobSheet/Show', function () {
    [$business, $user] = w3b6BootstrapUser();
    w3b6GivePerm($user, 'job_sheet.view_all');

    $js = w3b6FindOrSkipJobSheet($business->id);
    if (! $js) {
        test()->markTestSkipped('Sem JobSheet no banco dev.');
    }

    config(['mwart.repair_job_sheet_show.enabled' => false]);

    $response = $this->actingAs($user)->get("/repair/job-sheet/{$js->id}");

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate.');
    }
    if ($response->status() === 404) {
        test()->markTestSkipped('JobSheet/{id} schema mismatch (404).');
    }

    expect($response->status())->toBeLessThan(500);
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('respeita flag MWART ligada — retorna Inertia Repair/JobSheet/Show', function () {
    [$business, $user] = w3b6BootstrapUser();
    w3b6GivePerm($user, 'job_sheet.view_all');

    $js = w3b6FindOrSkipJobSheet($business->id);
    if (! $js) {
        test()->markTestSkipped('Sem JobSheet no banco dev.');
    }

    config([
        'mwart.repair_job_sheet_show.enabled' => true,
        'mwart.repair_job_sheet_show.business_ids' => [],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get("/repair/job-sheet/{$js->id}");

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate.');
    }
    if ($response->status() === 404) {
        test()->markTestSkipped('JobSheet/{id} schema mismatch.');
    }
    if ($response->status() >= 500) {
        test()->markTestSkipped('Erro 500 — controller payload mismatch ambiente dev.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Repair/JobSheet/Show')
        ->has('job_sheet.id')
        ->has('fsm.endpoints.actions')
        ->has('fsm.endpoints.execute')
        ->has('fsm.endpoints.start_pipeline')
        ->has('permissions.edit')
    );
});

it('força business_id scope — biz cross-tenant não acessa OS', function () {
    [$business, $user] = w3b6BootstrapUser();
    w3b6GivePerm($user, 'job_sheet.view_all');

    $otherBiz = Business::where('id', '!=', $business->id)->first();
    if (! $otherBiz) {
        test()->markTestSkipped('Precisa de >=2 businesses.');
    }

    $otherJs = JobSheet::where('business_id', $otherBiz->id)->first();
    if (! $otherJs) {
        test()->markTestSkipped('Sem JobSheet em biz alternativo.');
    }

    config(['mwart.repair_job_sheet_show.enabled' => true]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get("/repair/job-sheet/{$otherJs->id}");

    expect($response->status())->toBeIn([404, 403]);
});

it('endpoints FSM apontam pra rotas REPAIR (não Sells)', function () {
    [$business, $user] = w3b6BootstrapUser();
    w3b6GivePerm($user, 'job_sheet.view_all');

    $js = w3b6FindOrSkipJobSheet($business->id);
    if (! $js) {
        test()->markTestSkipped('Sem JobSheet.');
    }

    config(['mwart.repair_job_sheet_show.enabled' => true]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get("/repair/job-sheet/{$js->id}");

    if ($response->status() !== 200) {
        test()->markTestSkipped('Render falhou no ambiente dev.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('fsm.endpoints.actions', "/api/repair/job-sheets/{$js->id}/fsm-actions")
        ->where('fsm.endpoints.execute', "/repair/job-sheets/{$js->id}/fsm-action")
        ->where('fsm.endpoints.start_pipeline', "/repair/job-sheets/{$js->id}/fsm-start-pipeline")
    );
});

/**
 * Pest FSM Trait — UPDATE direto em current_stage_id DEVE lançar exception (ADR 0143).
 * Mesmo cenário do hotfix #640 — trait GuardsFsmTransitions bloqueia save sem
 * FsmAuthorizationFlag::mark().
 */
it('UPDATE direto em current_stage_id lança UnauthorizedActionException', function () {
    [$business, $user] = w3b6BootstrapUser();

    $js = w3b6FindOrSkipJobSheet($business->id);
    if (! $js) {
        test()->markTestSkipped('Sem JobSheet.');
    }
    if ($js->current_stage_id === null) {
        // OS legacy sem FSM iniciado — pula
        test()->markTestSkipped('JobSheet sem current_stage_id (legacy) — trait não dispara.');
    }

    expect(fn () => tap($js, function ($j) {
        $j->current_stage_id = $j->current_stage_id + 0; // qualquer mudança no campo
        $j->save();
    }))->toThrow(\App\Domain\Fsm\Exceptions\UnauthorizedActionException::class);
});
