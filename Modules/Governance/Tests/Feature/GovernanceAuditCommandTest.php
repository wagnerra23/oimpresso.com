<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckerRegistry;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

uses(Tests\TestCase::class);

/**
 * R-GOV-0216 — anti-regressão orchestrator GovernanceAuditCommand ADR 0216 PR 1.
 *
 * Nota técnica: Pest 4 + Laravel reset container entre tests não permite usar
 * beforeEach(fn() => app()->instance(...)) — facade root not set. Cada test
 * chama resetRegistry() inline pra garantir registry fresh.
 */

function makeOkChecker(string $name): DriftChecker
{
    return new class($name) implements DriftChecker {
        public function __construct(private string $n) {}
        public function name(): string { return $this->n; }
        public function description(): string { return 'ok'; }
        public function tags(): array { return ['test']; }
        public function severity(): string { return 'low'; }
        public function enforcement(): string { return 'advisory'; }
        public function cadence(): string { return 'daily'; }
        public function check(array $opts = []): DriftCheckResult
        {
            return DriftCheckResult::clean($this->n);
        }
    };
}

function makeDriftedChecker(string $name, int $findingsCount = 1, string $enforcement = 'warn'): DriftChecker
{
    return new class($name, $findingsCount, $enforcement) implements DriftChecker {
        public function __construct(private string $n, private int $count, private string $enf) {}
        public function name(): string { return $this->n; }
        public function description(): string { return 'drifted'; }
        public function tags(): array { return ['test']; }
        public function severity(): string { return 'high'; }
        public function enforcement(): string { return $this->enf; }
        public function cadence(): string { return 'daily'; }
        public function check(array $opts = []): DriftCheckResult
        {
            $findings = [];
            for ($i = 0; $i < $this->count; $i++) {
                $findings[] = new DriftFinding(
                    target: "target_{$i}",
                    target_type: 'file',
                    severity: 'high',
                    message: "drift #{$i}",
                );
            }

            return DriftCheckResult::drifted($this->n, $findings);
        }
    };
}

function resetRegistry(): DriftCheckerRegistry
{
    app()->forgetInstance(DriftCheckerRegistry::class);
    $r = new DriftCheckerRegistry();
    app()->instance(DriftCheckerRegistry::class, $r);

    return $r;
}

it('R-GOV-0216-AUDIT-001 — registry vazio retorna INVALID exit code', function () {
    resetRegistry();
    $exit = Artisan::call('governance:audit', ['--no-persist' => true]);
    expect($exit)->toBe(2); // self::INVALID
});

it('R-GOV-0216-AUDIT-002 — checker clean retorna SUCCESS', function () {
    resetRegistry()->register(makeOkChecker('clean_one'));
    $exit = Artisan::call('governance:audit', ['--no-persist' => true]);
    expect($exit)->toBe(0);
});

it('R-GOV-0216-AUDIT-003 — drift sem --fail-on-drift retorna SUCCESS', function () {
    resetRegistry()->register(makeDriftedChecker('drifted_one', 3));
    $exit = Artisan::call('governance:audit', ['--no-persist' => true]);
    expect($exit)->toBe(0);
});

it('R-GOV-0216-AUDIT-004 — drift com --fail-on-drift retorna FAILURE', function () {
    resetRegistry()->register(makeDriftedChecker('drifted_two', 1));
    $exit = Artisan::call('governance:audit', [
        '--fail-on-drift' => true,
        '--no-persist' => true,
    ]);
    expect($exit)->toBe(1);
});

it('R-GOV-0216-AUDIT-005 — --check=<name> seleciona 1 checker', function () {
    $registry = resetRegistry();
    $registry->register(makeOkChecker('a'));
    $registry->register(makeDriftedChecker('b'));

    $exit = Artisan::call('governance:audit', [
        '--check' => 'b',
        '--fail-on-drift' => true,
        '--no-persist' => true,
    ]);
    expect($exit)->toBe(1);

    $exit = Artisan::call('governance:audit', [
        '--check' => 'a',
        '--fail-on-drift' => true,
        '--no-persist' => true,
    ]);
    expect($exit)->toBe(0);
});

it('R-GOV-0216-AUDIT-006 — --check inexistente retorna INVALID', function () {
    resetRegistry()->register(makeOkChecker('exists'));
    $exit = Artisan::call('governance:audit', [
        '--check' => 'does_not_exist',
        '--no-persist' => true,
    ]);
    expect($exit)->toBe(2);
});

it('R-GOV-0216-AUDIT-007 — --json produz output JSON parseável', function () {
    resetRegistry()->register(makeDriftedChecker('json_test', 2));

    Artisan::call('governance:audit', [
        '--json' => true,
        '--no-persist' => true,
    ]);
    $output = Artisan::output();
    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveKeys(['summary', 'results']);
    expect($decoded['summary']['total_checkers'])->toBe(1);
    expect($decoded['summary']['drifted'])->toBe(1);
    expect($decoded['summary']['total_drift_findings'])->toBe(2);
});

it('R-GOV-0216-AUDIT-008 — drift_framework_enabled=false skip silenciosamente', function () {
    $original = config('governance.drift_framework_enabled');
    config(['governance.drift_framework_enabled' => false]);
    resetRegistry()->register(makeDriftedChecker('would_drift'));

    $exit = Artisan::call('governance:audit', [
        '--fail-on-drift' => true,
        '--no-persist' => true,
    ]);
    expect($exit)->toBe(0);

    config(['governance.drift_framework_enabled' => $original]);
});

it('R-GOV-0216-AUDIT-009 — --fail-on=block ignora advisory findings', function () {
    resetRegistry()->register(makeDriftedChecker('advisory_only', 5, 'advisory'));

    $exit = Artisan::call('governance:audit', [
        '--fail-on' => 'block',
        '--no-persist' => true,
    ]);
    expect($exit)->toBe(0);
});

it('R-GOV-0216-AUDIT-010 — comando registrado em Artisan::all()', function () {
    $commands = collect(Artisan::all())->keys();
    expect($commands)->toContain('governance:audit');
});
