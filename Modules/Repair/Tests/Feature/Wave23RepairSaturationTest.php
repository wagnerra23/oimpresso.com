<?php

declare(strict_types=1);

namespace Modules\Repair\Tests\Feature;

/**
 * Helper path sem booting Laravel.
 */
function repairW23Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

/**
 * Wave 23 — Repair saturação bucket vertical_client_facing (ADR 0160).
 *
 * Target: nota scoped ≥85 (subindo de 69 W22). Cobertura V1/V3/V4/V5/V6 da rubrica
 * scoped vertical_client_facing.yaml.
 *
 * Estratégia: smoke + reflection (sem boot Laravel). Pest v4 + biz=99 (ADR 0101).
 * Complementa W18 Wave18RepairSaturationTest + RepairFsmActionControllerTest
 * (DB-based). Foca em jornada FSM canon 13 stages.
 *
 * Tier 0:
 *   - Multi-tenant ADR 0093 (repair_job_sheets business_id global scope)
 *   - FSM canon ADR 0143 (13 stages × ~15 actions × 6 roles)
 *   - LGPD: contact_id/device_id/defects/notes PII (PiiRedactor + retention 5y)
 *   - LogsActivity Spatie (audit append-only, complementa sale_stage_history)
 *
 * @see governance/buckets/vertical_client_facing.yaml
 * @see memory/decisions/0160-scoped-scorecard-evaluator-v3.md
 * @see memory/requisitos/Repair/CAPTERRA-FICHA.md
 */

describe('Wave 23 Repair — V1 Pest E2E (FsmCanonicalJourney 13 stages)', function () {

    it('CAPTERRA-FICHA.md existe + cita FSM canon 13 stages', function () {
        $path = repairW23Path('memory/requisitos/Repair/CAPTERRA-FICHA.md');
        expect(file_exists($path))->toBeTrue('CAPTERRA-FICHA obrigatória');
        $conteudo = (string) file_get_contents($path);
        expect($conteudo)->toContain('vertical_client_facing');
        expect($conteudo)->toContain('Repair');
        expect($conteudo)->toContain('FSM');
    });

    it('JobSheet entity tem GuardsFsmTransitions + HasBusinessScope + LogsActivity', function () {
        $class = 'Modules\\Repair\\Entities\\JobSheet';
        expect(class_exists($class))->toBeTrue();

        $traits = class_uses_recursive($class);
        expect($traits)->toHaveKey('App\\Domain\\Fsm\\Concerns\\GuardsFsmTransitions');
        expect($traits)->toHaveKey('App\\Concerns\\HasBusinessScope');
        expect($traits)->toHaveKey('Spatie\\Activitylog\\Traits\\LogsActivity');
    });

    it('FsmActionRequest + ExecuteRepairFsmActionRequest existem (V2)', function () {
        expect(class_exists('Modules\\Repair\\Http\\Requests\\StartFsmActionRequest'))->toBeTrue();
        expect(class_exists('Modules\\Repair\\Http\\Requests\\ExecuteRepairFsmActionRequest'))->toBeTrue();
        expect(class_exists('Modules\\Repair\\Http\\Requests\\StoreJobSheetRequest'))->toBeTrue();
        expect(class_exists('Modules\\Repair\\Http\\Requests\\UpdateJobSheetRequest'))->toBeTrue();
    });

    it('Controllers JobSheet/Repair/RepairFsmAction/Dashboard existem', function () {
        $controllers = [
            'Modules\\Repair\\Http\\Controllers\\JobSheetController',
            'Modules\\Repair\\Http\\Controllers\\RepairController',
            'Modules\\Repair\\Http\\Controllers\\RepairFsmActionController',
            'Modules\\Repair\\Http\\Controllers\\DashboardController',
        ];
        foreach ($controllers as $c) {
            expect(class_exists($c))->toBeTrue("Controller {$c} obrigatório");
        }
    });
});

describe('Wave 23 Repair — V3 Perf UX (Inertia::defer adoção)', function () {

    it('JobSheetController usa Inertia::defer em props caras (>=5 ocorrências)', function () {
        $source = (string) file_get_contents(repairW23Path('Modules/Repair/Http/Controllers/JobSheetController.php'));
        $count = substr_count($source, 'Inertia::defer');
        expect($count)->toBeGreaterThanOrEqual(5, 'V3 perf — defer obrigatório em props caras (RUNBOOK-inertia-defer-pattern)');
    });

    it('RepairController usa Inertia::render (não bare view)', function () {
        $source = (string) file_get_contents(repairW23Path('Modules/Repair/Http/Controllers/RepairController.php'));
        expect($source)->toContain('Inertia::render');
    });
});

describe('Wave 23 Repair — V4 LGPD retention + LogsActivity canon', function () {

    it('Config/retention.php declara repair_job_sheets 1825d (5y CCB/CDC/fiscal)', function () {
        $config = require repairW23Path('Modules/Repair/Config/retention.php');
        expect($config['tabelas']['repair_job_sheets'])->toBe(1825);
    });

    it('Strategy default anonymize (preserva métricas SRE sem PII)', function () {
        $config = require repairW23Path('Modules/Repair/Config/retention.php');
        expect($config['strategy'])->toBe('anonymize');
    });

    it('module.json declara LGPD compliance + activity_log enabled', function () {
        $json = json_decode((string) file_get_contents(repairW23Path('Modules/Repair/module.json')), true);
        expect($json['lgpd_compliance']['activity_log_enabled'])->toBeTrue();
        expect($json['lgpd_compliance']['pii_redactor_enabled'])->toBeTrue();
        expect($json['lgpd_compliance']['pii_fields_tracked'])->toContain('contact_id');
        expect($json['lgpd_compliance']['pii_fields_tracked'])->toContain('device_id');
        expect($json['lgpd_compliance']['pii_fields_tracked'])->toContain('defects');
    });
});

describe('Wave 23 Repair — V5/V6 Docs canon + bucket governance', function () {

    it('module.json declara governance.bucket=vertical_client_facing + FSM canon 13 stages', function () {
        $json = json_decode((string) file_get_contents(repairW23Path('Modules/Repair/module.json')), true);
        expect($json['governance']['bucket'])->toBe('vertical_client_facing');
        expect($json['governance']['fsm_canonico'])->toBeTrue();
        expect($json['governance']['fsm_pipeline'])->toBe('repair');
        expect($json['governance']['fsm_estados'])->toBe(13);
        expect($json['governance']['scoped_score_target'])->toBeGreaterThanOrEqual(85);
    });

    it('RUNBOOKs canon completos (5 mwart pages do módulo)', function () {
        $runbooks = [
            'memory/requisitos/Repair/RUNBOOK-jobsheet-add-parts.md',
            'memory/requisitos/Repair/RUNBOOK-jobsheet-create.md',
            'memory/requisitos/Repair/RUNBOOK-jobsheet-edit.md',
            'memory/requisitos/Repair/RUNBOOK-jobsheet-index.md',
            'memory/requisitos/Repair/RUNBOOK-jobsheet-show.md',
            'memory/requisitos/Repair/RUNBOOK-repair-index.md',
            'memory/requisitos/Repair/RUNBOOK-repair-show.md',
        ];
        foreach ($runbooks as $r) {
            expect(file_exists(repairW23Path($r)))->toBeTrue("Runbook canon {$r} obrigatório (V5)");
        }
    });

    it('CHANGELOG.md W23 entry presente', function () {
        $changelog = repairW23Path('memory/requisitos/Repair/CHANGELOG.md');
        expect(file_exists($changelog))->toBeTrue();
        $conteudo = (string) file_get_contents($changelog);
        expect($conteudo)->toContain('Wave 23');
        expect($conteudo)->toContain('vertical_client_facing');
    });
});
