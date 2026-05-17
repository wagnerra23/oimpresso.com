<?php

declare(strict_types=1);

use Modules\Crm\Entities\Campaign;
use Modules\Crm\Entities\CrmCallLog;
use Modules\Crm\Entities\Proposal;
use Modules\Crm\Services\CallLogService;
use Modules\Crm\Services\CrmLeadService;
use Modules\Crm\Services\ProposalService;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 28 Crm POLISH — saturação final ≥95.
 *
 * 2 Pest adicionais (Wave 28 sentry):
 *   1. Services D4 canon trio (ProposalService + CallLogService + CrmLeadService)
 *      mantêm API pública contratada (regression guard pós Wave 18 RETRY).
 *   2. Entities core (Campaign, CrmCallLog, Proposal) preservam LogsActivity
 *      trait — D7 LGPD audit trail (regression guard se alguém remover).
 *
 * Tier 0 IRREVOGÁVEL ({@see ADR 0093}):
 *   - NÃO chama session() — reflection-only, multi-tenant friendly
 *   - NÃO toca biz=4 ROTA LIVRE ({@see ADR 0101})
 *   - PT-BR + Pest CI-friendly (zero DB hit)
 *
 * @see Modules\Crm\Tests\Feature\Wave18SaturationTest (predecessor)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */
describe('Wave 28 Crm Polish — saturação final ≥95', function () {

    it('W28 sentry — trio Services D4 (Proposal/CallLog/Lead) expõem API canônica', function () {
        // Regression guard: se alguém renomear/remover método público, Pest falha
        $proposalSvc = new ProposalService();
        expect(method_exists($proposalSvc, 'createProposal'))->toBeTrue();
        expect(method_exists($proposalSvc, 'updateProposal'))->toBeTrue();

        $callLogSvc = new CallLogService();
        // CallLogService W18 RETRY: baseQuery/applyFilters/restrictToOwner/totalDurationSeconds
        expect(method_exists($callLogSvc, 'baseQuery'))->toBeTrue();
        expect(method_exists($callLogSvc, 'restrictToOwner'))->toBeTrue();

        // CrmLeadService W18 RETRY: createLead/convertToCustomer/acceptedFields/repository
        // (DI: not instantiated raw — uses container)
        $leadRef = new ReflectionClass(CrmLeadService::class);
        expect($leadRef->hasMethod('createLead'))->toBeTrue();
        expect($leadRef->hasMethod('convertToCustomer'))->toBeTrue();
    });

    it('W28 sentry — Entities core preservam LogsActivity (D7 LGPD audit trail)', function () {
        // Regression guard: D7 LGPD compliance — todo Entity Crm com PII rastreia activity
        foreach ([Campaign::class, CrmCallLog::class, Proposal::class] as $entity) {
            $traits = class_uses_recursive($entity);
            $hasLogsActivity = in_array(LogsActivity::class, $traits, true);
            expect($hasLogsActivity)->toBeTrue(
                "Entity {$entity} sem LogsActivity — LGPD audit trail quebrado em W28"
            );
        }
    });
});
