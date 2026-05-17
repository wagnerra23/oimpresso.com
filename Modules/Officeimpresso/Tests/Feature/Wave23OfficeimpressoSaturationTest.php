<?php

declare(strict_types=1);

/**
 * Helper path sem booting Laravel.
 */
function officeimpressoW23Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

/**
 * Wave 23 — Officeimpresso saturação bucket vertical_client_facing (ADR 0160).
 *
 * Target: nota scoped ≥85 (subindo de 60 W22). Cobertura V1/V3/V5/V6 da rubrica
 * scoped vertical_client_facing.yaml.
 *
 * Estratégia: smoke + reflection (sem boot Laravel). Officeimpresso é bridge
 * Delphi desktop → sync com Hostinger; FSM N/A (audit-only LicencaLog append).
 *
 * Tier 0:
 *   - Multi-tenant ADR 0093 (LicencaComputador + LicencaLog business_id scope)
 *   - LicencaLog append-only (audit trail Passport auth + Delphi sync)
 *   - LGPD retention janelas por evento (api_call 1y, admin_action 7y CC Art. 206)
 *
 * @see governance/buckets/vertical_client_facing.yaml
 * @see memory/decisions/0160-scoped-scorecard-evaluator-v3.md
 * @see memory/requisitos/Officeimpresso/CAPTERRA-FICHA.md
 */

describe('Wave 23 Officeimpresso — V1 Pest smoke estrutural (idempotência parse)', function () {

    it('CAPTERRA-FICHA.md existe + cita Delphi bridge + Mubisys', function () {
        $path = officeimpressoW23Path('memory/requisitos/Officeimpresso/CAPTERRA-FICHA.md');
        expect(file_exists($path))->toBeTrue('CAPTERRA-FICHA obrigatória');
        $conteudo = (string) file_get_contents($path);
        expect($conteudo)->toContain('vertical_client_facing');
        expect($conteudo)->toContain('Officeimpresso');
        expect($conteudo)->toContain('Delphi');
    });

    it('LicencaLog + LicencaComputador entities existem', function () {
        expect(class_exists('Modules\\Officeimpresso\\Entities\\LicencaLog'))->toBeTrue();
        expect(class_exists('Modules\\Officeimpresso\\Entities\\Licenca_Computador'))->toBeTrue();
    });

    it('ParseLicencaLogCommand existe + idempotente (offset cursor)', function () {
        $class = 'Modules\\Officeimpresso\\Console\\ParseLicencaLogCommand';
        expect(class_exists($class))->toBeTrue();

        $reflection = new \ReflectionClass($class);
        expect($reflection->hasMethod('handle'))->toBeTrue();
        expect($reflection->hasMethod('classify'))->toBeTrue();
    });

    it('Controllers Licenca/Audit/Client/Officeimpresso existem', function () {
        $controllers = [
            'Modules\\Officeimpresso\\Http\\Controllers\\LicencaComputadorController',
            'Modules\\Officeimpresso\\Http\\Controllers\\LicencaLogController',
            'Modules\\Officeimpresso\\Http\\Controllers\\AuditController',
            'Modules\\Officeimpresso\\Http\\Controllers\\ClientController',
        ];
        foreach ($controllers as $c) {
            expect(class_exists($c))->toBeTrue("Controller {$c} obrigatório bridge Delphi");
        }
    });

    it('FormRequests Store/Revoke Licenca existem (V2 anti-IDOR)', function () {
        expect(class_exists('Modules\\Officeimpresso\\Http\\Requests\\StoreLicencaRequest'))->toBeTrue();
        expect(class_exists('Modules\\Officeimpresso\\Http\\Requests\\RevokeLicencaRequest'))->toBeTrue();
    });
});

describe('Wave 23 Officeimpresso — V4 LGPD retention granular', function () {

    it('Config/retention.php declara janelas por evento (LGPD Art. 16)', function () {
        $config = require officeimpressoW23Path('Modules/Officeimpresso/Config/retention.php');
        expect($config)->toBeArray();
    });

    it('module.json declara retention_days granular por evento', function () {
        $json = json_decode((string) file_get_contents(officeimpressoW23Path('Modules/Officeimpresso/module.json')), true);
        expect($json)->toHaveKey('retention_days');
        expect($json['retention_days'])->toHaveKey('licenca_log_api_call');
        expect($json['retention_days'])->toHaveKey('licenca_log_admin_actions');
        // Admin actions 7 anos (CC Art. 206 prescrição)
        expect($json['retention_days']['licenca_log_admin_actions'])->toBeGreaterThanOrEqual(2555);
        // API call 1 ano (debug Delphi)
        expect($json['retention_days']['licenca_log_api_call'])->toBeLessThanOrEqual(365);
    });

    it('Middleware LogDelphiAccess + LogDesktopAccess existem (audit trail)', function () {
        expect(class_exists('Modules\\Officeimpresso\\Http\\Middleware\\LogDelphiAccess'))->toBeTrue();
        expect(class_exists('Modules\\Officeimpresso\\Http\\Middleware\\LogDesktopAccess'))->toBeTrue();
    });
});

describe('Wave 23 Officeimpresso — V5/V6 Docs canon + bucket', function () {

    it('module.json declara governance.bucket=vertical_client_facing + FSM N/A justificado', function () {
        $json = json_decode((string) file_get_contents(officeimpressoW23Path('Modules/Officeimpresso/module.json')), true);
        expect($json['governance']['bucket'])->toBe('vertical_client_facing');
        expect($json['governance']['fsm_n_a'])->toBeTrue();
        expect($json['governance']['fsm_n_a_reason'])->toContain('Delphi');
        expect($json['governance']['scoped_score_target'])->toBeGreaterThanOrEqual(85);
    });

    it('SPEC.md + PROPOSTA-COMERCIAL + RUNBOOK migração existem (V5)', function () {
        $docs = [
            'memory/requisitos/Officeimpresso/SPEC.md',
            'memory/requisitos/Officeimpresso/PROPOSTA-COMERCIAL-vs-mubsys.md',
            'memory/requisitos/Officeimpresso/RUNBOOK-migracao-react.md',
        ];
        foreach ($docs as $d) {
            expect(file_exists(officeimpressoW23Path($d)))->toBeTrue("Doc canon {$d} obrigatório");
        }
    });

    it('CHANGELOG W23 entry presente', function () {
        $changelog = officeimpressoW23Path('memory/requisitos/Officeimpresso/CHANGELOG.md');
        expect(file_exists($changelog))->toBeTrue();
        $conteudo = (string) file_get_contents($changelog);
        expect($conteudo)->toContain('Wave 23');
        expect($conteudo)->toContain('vertical_client_facing');
    });
});
