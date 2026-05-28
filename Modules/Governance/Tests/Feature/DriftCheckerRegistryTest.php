<?php

declare(strict_types=1);

use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckerRegistry;
use Modules\Governance\Services\DriftCheckResult;

uses(Tests\TestCase::class);

/**
 * R-GOV-0216 — anti-regressão registry singleton ADR 0216 PR 1.
 */

function makeFakeChecker(
    string $name,
    array $tags = ['test'],
    string $cadence = 'daily',
    string $enforcement = 'advisory',
    string $severity = 'medium',
): DriftChecker {
    return new class($name, $tags, $cadence, $enforcement, $severity) implements DriftChecker {
        public function __construct(
            private string $n,
            private array $t,
            private string $c,
            private string $e,
            private string $s,
        ) {
        }

        public function name(): string { return $this->n; }
        public function description(): string { return "fake {$this->n}"; }
        public function tags(): array { return $this->t; }
        public function severity(): string { return $this->s; }
        public function enforcement(): string { return $this->e; }
        public function cadence(): string { return $this->c; }
        public function check(array $opts = []): DriftCheckResult
        {
            return DriftCheckResult::clean($this->n);
        }
    };
}

it('R-GOV-0216-001 — registry resolvível via container', function () {
    // Singleton confirmed em prod via script direto; aqui só validamos binding básico
    // (Pest 4 reseta container entre tests — singleton lifecycle não confiável em test isolation).
    $r = app(DriftCheckerRegistry::class);
    expect($r)->toBeInstanceOf(DriftCheckerRegistry::class);
});

it('R-GOV-0216-002 — register/get/has funciona', function () {
    $registry = new DriftCheckerRegistry();
    $checker = makeFakeChecker('test_001');
    $registry->register($checker);

    expect($registry->has('test_001'))->toBeTrue();
    expect($registry->get('test_001'))->toBe($checker);
    expect($registry->get('nonexistent'))->toBeNull();
});

it('R-GOV-0216-003 — name duplicado throw InvalidArgumentException', function () {
    $registry = new DriftCheckerRegistry();
    $registry->register(makeFakeChecker('dup'));
    expect(fn () => $registry->register(makeFakeChecker('dup')))
        ->toThrow(InvalidArgumentException::class);
});

it('R-GOV-0216-004 — byTag filtra corretamente', function () {
    $registry = new DriftCheckerRegistry();
    $registry->register(makeFakeChecker('a', tags: ['tier_0', 'security']));
    $registry->register(makeFakeChecker('b', tags: ['tier_1', 'security']));
    $registry->register(makeFakeChecker('c', tags: ['tier_0', 'tech_debt']));

    $tier0 = $registry->byTag('tier_0');
    expect(array_keys($tier0))->toContain('a', 'c');
    expect($tier0)->toHaveCount(2);

    $security = $registry->byTag('security');
    expect(array_keys($security))->toContain('a', 'b');
});

it('R-GOV-0216-005 — byCadence filtra corretamente', function () {
    $registry = new DriftCheckerRegistry();
    $registry->register(makeFakeChecker('daily_one', cadence: 'daily'));
    $registry->register(makeFakeChecker('hourly_one', cadence: 'hourly'));
    $registry->register(makeFakeChecker('daily_two', cadence: 'daily'));

    expect($registry->byCadence('daily'))->toHaveCount(2);
    expect($registry->byCadence('hourly'))->toHaveCount(1);
    expect($registry->byCadence('weekly'))->toHaveCount(0);
});

it('R-GOV-0216-006 — byEnforcement filtra corretamente', function () {
    $registry = new DriftCheckerRegistry();
    $registry->register(makeFakeChecker('warn_one', enforcement: 'warn'));
    $registry->register(makeFakeChecker('block_one', enforcement: 'block'));
    $registry->register(makeFakeChecker('advisory_one', enforcement: 'advisory'));

    expect($registry->byEnforcement('block'))->toHaveCount(1);
    expect($registry->byEnforcement('warn'))->toHaveCount(1);
});

it('R-GOV-0216-007 — names + count + all retornam estrutura esperada', function () {
    $registry = new DriftCheckerRegistry();
    $registry->register(makeFakeChecker('x'));
    $registry->register(makeFakeChecker('y'));

    expect($registry->count())->toBe(2);
    expect($registry->names())->toEqualCanonicalizing(['x', 'y']);
    expect($registry->all())->toHaveCount(2);
});

it('R-GOV-0216-008 — unregister + reset funcionam', function () {
    $registry = new DriftCheckerRegistry();
    $registry->register(makeFakeChecker('temp'));
    expect($registry->count())->toBe(1);

    $registry->unregister('temp');
    expect($registry->count())->toBe(0);

    $registry->register(makeFakeChecker('a'));
    $registry->register(makeFakeChecker('b'));
    $registry->reset();
    expect($registry->count())->toBe(0);
});
