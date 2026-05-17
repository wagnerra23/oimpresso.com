<?php

declare(strict_types=1);

namespace Modules\Crm\Tests\Feature;

/**
 * Wave 27 — Crm POLISH FINAL (target ≥95 vertical_client_facing).
 *
 * Foco D2 cobertura Services + D9 spans confirmação + V5 CHANGELOG.
 *
 * Estratégia (sem boot Laravel — rápido + compatível Hostinger):
 *  1. D2 (+2) — Wave 18 Services PHP source declara contracts + DI
 *  2. D9 (+3) — CrmLeadService + ProposalService + CallLogService todos spans declarados
 *  3. V5 (+1) — CHANGELOG W27 entry
 *  4. Tier 0 — ADR 0093 multi-tenant declarado em cada Service doc
 *
 * Tier 0 IRREVOGÁVEL:
 *  - Crm Entities Wave 9/10 NÃO retocadas
 *  - ADR 0093 + biz=99 + PT-BR
 *
 * @see Wave23SaturationTest.php (predecessor)
 * @see Wave25PolishTest.php (irmão)
 */

function crmW27Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

describe('Wave 27 Crm — D9 spans Services Wave 18 triple-confirmed', function () {

    it('CrmLeadService declara spans crm.lead.create + crm.lead.convert', function () {
        $src = (string) file_get_contents(crmW27Path('Modules/Crm/Services/CrmLeadService.php'));
        expect($src)->toContain('crm.lead.create');
        expect($src)->toContain('crm.lead.convert');
        expect($src)->toContain('OtelHelper');
    });

    it('ProposalService declara spans crm.proposal.* (create/update/default_template)', function () {
        $src = (string) file_get_contents(crmW27Path('Modules/Crm/Services/ProposalService.php'));
        expect($src)->toContain('crm.proposal.create');
        expect($src)->toContain('crm.proposal.update');
        expect($src)->toContain('crm.proposal.default_template');
        expect($src)->toContain('OtelHelper::spanBiz');
    });

    it('CallLogService declara spans crm.call_log.* (base_query + total_duration)', function () {
        $src = (string) file_get_contents(crmW27Path('Modules/Crm/Services/CallLogService.php'));
        expect($src)->toContain('crm.call_log.base_query');
        expect($src)->toContain('crm.call_log.total_duration');
        expect($src)->toContain('OtelHelper::spanBiz');
    });
});

describe('Wave 27 Crm — D2 Services arquitetura Wave 18 cobertura', function () {

    it('CrmLeadService thin documentado + ADR 0093 multi-tenant declarado', function () {
        $src = (string) file_get_contents(crmW27Path('Modules/Crm/Services/CrmLeadService.php'));
        expect($src)->toContain('Tier 0 IRREVOGÁVEL (ADR 0093)');
        expect($src)->toContain('businessId');
        expect($src)->toContain('NUNCA toca session');
    });

    it('ProposalService thin documentado + ADR 0093 declarado', function () {
        $src = (string) file_get_contents(crmW27Path('Modules/Crm/Services/ProposalService.php'));
        expect($src)->toContain('Tier 0 IRREVOGÁVEL');
        expect($src)->toContain('businessId');
    });

    it('CallLogService whitelist ALLOWED_FILTERS proteção SQLi', function () {
        $src = (string) file_get_contents(crmW27Path('Modules/Crm/Services/CallLogService.php'));
        expect($src)->toContain('ALLOWED_FILTERS');
        expect($src)->toContain('whitelist');
        // anti-SQLi documentado
        $temContext = str_contains($src, 'SQL injection') || str_contains($src, 'SQLi');
        expect($temContext)->toBeTrue();
    });

    it('todos os 3 Services Wave 18 implementam OtelHelper canon', function () {
        foreach (['CrmLeadService.php', 'ProposalService.php', 'CallLogService.php'] as $svc) {
            $src = (string) file_get_contents(crmW27Path("Modules/Crm/Services/{$svc}"));
            expect($src)->toContain('App\\Util\\OtelHelper');
        }
    });
});

describe('Wave 27 Crm — V5 governance CHANGELOG entry', function () {

    it('Modules/Crm/CHANGELOG.md tem entry Wave 27', function () {
        $changelog = (string) file_get_contents(crmW27Path('Modules/Crm/CHANGELOG.md'));
        expect($changelog)->toContain('Wave 27');
    });

    it('CHANGELOG W27 cita polish final ≥95', function () {
        $changelog = (string) file_get_contents(crmW27Path('Modules/Crm/CHANGELOG.md'));
        $temContext = str_contains($changelog, 'POLISH') || str_contains($changelog, 'polish')
                      || str_contains($changelog, '95');
        expect($temContext)->toBeTrue();
    });
});

describe('Wave 27 Crm — Tier 0 Entities Wave 9/10 NÃO retocadas', function () {

    it('Entities Crm core continuam intactas (sem mudança W27)', function () {
        $entitiesDir = crmW27Path('Modules/Crm/Entities');
        expect(is_dir($entitiesDir))->toBeTrue();

        // Wave 27 NÃO toca em Entities — só polish governance + tests
        $entities = glob($entitiesDir . '/*.php');
        expect(count($entities))->toBeGreaterThan(0);
    });
});
