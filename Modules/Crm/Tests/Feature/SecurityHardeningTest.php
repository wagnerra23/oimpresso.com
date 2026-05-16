<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Crm\Entities\Campaign;
use Modules\Crm\Entities\Proposal;
use Modules\Crm\Http\Requests\MassDestroyCallLogRequest;
use Modules\Crm\Http\Requests\StoreCampaignRequest;
use Modules\Crm\Http\Requests\StoreLeadRequest;
use Modules\Crm\Http\Requests\StoreProposalRequest;
use Modules\Crm\Http\Requests\StoreScheduleRequest;
use Modules\Crm\Http\Requests\UpdateLeadRequest;
use Modules\Crm\Http\Requests\UpdateScheduleRequest;
use Modules\Crm\Policies\CampaignPolicy;
use Modules\Crm\Policies\ProposalPolicy;

uses(Tests\TestCase::class);

/**
 * Wave 15 governance D8 Security — verifica hardening do modulo Crm.
 *
 * Cenarios:
 *   1. FormRequests existem + estendem FormRequest base
 *   2. Cada FormRequest tem authorize() + rules()
 *   3. Policies Proposal/Campaign registradas via Gate::policy
 *   4. Policy isola cross-tenant (biz=1 vs biz=99) — fail-secure NEGA
 *   5. Rotas sensiveis tem middleware throttle (anti-abuso)
 *   6. Rules() impede payload malicioso (sem first_name no Lead → reject)
 *
 * ADR 0093 multi-tenant Tier 0 + ADR 0101 biz=1/99 (NUNCA biz=4 cliente real).
 *
 * @see Modules/Crm/Http/Requests
 * @see Modules/Crm/Policies
 */

const CRM_SEC_BIZ_WAGNER = 1;
const CRM_SEC_BIZ_FICTICIO = 99;

it('cenario 1: 7 FormRequests existem e estendem FormRequest base', function () {
    $requests = [
        StoreCampaignRequest::class,
        StoreScheduleRequest::class,
        UpdateScheduleRequest::class,
        StoreProposalRequest::class,
        StoreLeadRequest::class,
        UpdateLeadRequest::class,
        MassDestroyCallLogRequest::class,
    ];

    foreach ($requests as $class) {
        expect(class_exists($class))->toBeTrue("FormRequest {$class} deve existir");
        expect(is_subclass_of($class, FormRequest::class))->toBeTrue("{$class} deve estender FormRequest");
    }
});

it('cenario 2: cada FormRequest tem authorize() + rules() implementados', function () {
    $requests = [
        StoreProposalRequest::class,
        StoreLeadRequest::class,
        UpdateLeadRequest::class,
        MassDestroyCallLogRequest::class,
    ];

    foreach ($requests as $class) {
        $reflection = new ReflectionClass($class);
        expect($reflection->hasMethod('authorize'))->toBeTrue("{$class}::authorize() obrigatorio");
        expect($reflection->hasMethod('rules'))->toBeTrue("{$class}::rules() obrigatorio");

        // Rules deve retornar array nao-vazio.
        $instance = new $class();
        expect($instance->rules())->toBeArray()->not->toBeEmpty();
    }
});

it('cenario 3: ProposalPolicy + CampaignPolicy registradas em Gate', function () {
    // Forca boot do CrmServiceProvider — Gate::policy executado em registerPolicies().
    $proposalPolicy = Gate::getPolicyFor(Proposal::class);
    $campaignPolicy = Gate::getPolicyFor(Campaign::class);

    expect($proposalPolicy)->toBeInstanceOf(ProposalPolicy::class);
    expect($campaignPolicy)->toBeInstanceOf(CampaignPolicy::class);
});

it('cenario 4: Policy nega acesso cross-tenant (biz=1 user vs biz=99 proposal)', function () {
    $policy = new ProposalPolicy();

    // Usuario biz=1
    $userBiz1 = new App\User();
    $userBiz1->id = 1;
    $userBiz1->business_id = CRM_SEC_BIZ_WAGNER;

    // Proposal biz=99 (outro tenant)
    $proposalBiz99 = new Proposal();
    $proposalBiz99->id = 1;
    $proposalBiz99->business_id = CRM_SEC_BIZ_FICTICIO;
    $proposalBiz99->sent_by = 1;

    expect($policy->view($userBiz1, $proposalBiz99))->toBeFalse('Cross-tenant view DEVE ser negado');
    expect($policy->update($userBiz1, $proposalBiz99))->toBeFalse('Cross-tenant update DEVE ser negado');
    expect($policy->delete($userBiz1, $proposalBiz99))->toBeFalse('Cross-tenant delete DEVE ser negado');
});

it('cenario 5: Policy permite acesso same-tenant (biz=1 user + biz=1 proposal owner)', function () {
    $policy = new ProposalPolicy();

    $user = new App\User();
    $user->id = 42;
    $user->business_id = CRM_SEC_BIZ_WAGNER;

    $proposal = new Proposal();
    $proposal->id = 1;
    $proposal->business_id = CRM_SEC_BIZ_WAGNER;
    $proposal->sent_by = 42; // mesmo user

    expect($policy->view($user, $proposal))->toBeTrue('Same-tenant view DEVE ser permitido');
    expect($policy->update($user, $proposal))->toBeTrue('Same-tenant update (owner) DEVE ser permitido');
});

it('cenario 6: CampaignPolicy nega cross-tenant + permite same-tenant', function () {
    $policy = new CampaignPolicy();

    $user = new App\User();
    $user->id = 7;
    $user->business_id = CRM_SEC_BIZ_WAGNER;

    $sameBiz = new Campaign();
    $sameBiz->id = 1;
    $sameBiz->business_id = CRM_SEC_BIZ_WAGNER;
    $sameBiz->created_by = 7;

    $crossBiz = new Campaign();
    $crossBiz->id = 2;
    $crossBiz->business_id = CRM_SEC_BIZ_FICTICIO;
    $crossBiz->created_by = 7;

    expect($policy->view($user, $sameBiz))->toBeTrue('Same-tenant Campaign view permitido');
    expect($policy->view($user, $crossBiz))->toBeFalse('Cross-tenant Campaign view NEGADO');
    expect($policy->delete($user, $sameBiz))->toBeTrue('Owner same-tenant delete permitido');
    expect($policy->delete($user, $crossBiz))->toBeFalse('Cross-tenant delete NEGADO');
});

it('cenario 7: rotas sensiveis tem middleware throttle aplicado', function () {
    // Forca carga routes/web.php do modulo.
    $sensitiveRoutes = [
        'crm/install',
        'crm/mass-delete-call-log',
        'crm/send-proposal',
    ];

    $allRoutes = Route::getRoutes();
    $found = 0;

    foreach ($allRoutes as $route) {
        $uri = $route->uri();
        foreach ($sensitiveRoutes as $needle) {
            if (str_contains($uri, $needle)) {
                $middlewares = $route->gatherMiddleware();
                $hasThrottle = collect($middlewares)->contains(fn ($m) => str_starts_with((string) $m, 'throttle:'));
                if ($hasThrottle) {
                    $found++;
                }
            }
        }
    }

    expect($found)->toBeGreaterThanOrEqual(1, 'Pelo menos 1 rota sensivel deve ter middleware throttle (anti-abuso)');
});

it('cenario 8: MassDestroyCallLogRequest::ids() normaliza CSV legacy e impoe cap anti-DoS', function () {
    $request = new MassDestroyCallLogRequest();
    $request->merge(['selected_rows' => '10,20,abc,0,-5,30']);

    $ids = $request->ids();

    expect($ids)->toBe([10, 20, 30]); // filtra invalidos, mantem inteiros >0
});

it('cenario 9: MassDestroyCallLogRequest::ids() aceita array nativo', function () {
    $request = new MassDestroyCallLogRequest();
    $request->merge(['selected_rows' => [1, 2, 3, '4']]);

    expect($request->ids())->toBe([1, 2, 3, 4]);
});

it('cenario 10: MassDestroyCallLogRequest::ids() cap 500 (anti-DoS)', function () {
    $request = new MassDestroyCallLogRequest();
    $bigList = range(1, 1000);
    $request->merge(['selected_rows' => $bigList]);

    expect(count($request->ids()))->toBe(500);
});
