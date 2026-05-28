<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Modules\Governance\Contracts\DriftChecker;

/**
 * Registry singleton de DriftChecker — ADR 0216.
 *
 * Bind em GovernanceServiceProvider::register():
 *   $this->app->singleton(DriftCheckerRegistry::class);
 *
 * Auto-registro de checkers default em boot():
 *   $registry = $this->app->make(DriftCheckerRegistry::class);
 *   $registry->register(new ModuleScopeChecker());
 *   ...
 *
 * Override por config/governance.php 'drift_checkers' (Wagner muda sem deploy).
 */
final class DriftCheckerRegistry
{
    /** @var array<string, DriftChecker> */
    private array $checkers = [];

    public function register(DriftChecker $checker): void
    {
        $name = $checker->name();
        if (isset($this->checkers[$name])) {
            throw new \InvalidArgumentException(
                "DriftChecker '{$name}' já registrado. Cada checker deve ter name() único."
            );
        }
        $this->checkers[$name] = $checker;
    }

    public function get(string $name): ?DriftChecker
    {
        return $this->checkers[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->checkers[$name]);
    }

    /**
     * @return array<string, DriftChecker>
     */
    public function all(): array
    {
        return $this->checkers;
    }

    /**
     * @return array<string>
     */
    public function names(): array
    {
        return array_keys($this->checkers);
    }

    /**
     * @return array<string, DriftChecker>
     */
    public function byTag(string $tag): array
    {
        return array_filter(
            $this->checkers,
            static fn (DriftChecker $c) => in_array($tag, $c->tags(), true)
        );
    }

    /**
     * @return array<string, DriftChecker>
     */
    public function byCadence(string $cadence): array
    {
        return array_filter(
            $this->checkers,
            static fn (DriftChecker $c) => $c->cadence() === $cadence
        );
    }

    /**
     * @return array<string, DriftChecker>
     */
    public function byEnforcement(string $enforcement): array
    {
        return array_filter(
            $this->checkers,
            static fn (DriftChecker $c) => $c->enforcement() === $enforcement
        );
    }

    public function count(): int
    {
        return count($this->checkers);
    }

    public function unregister(string $name): void
    {
        unset($this->checkers[$name]);
    }

    public function reset(): void
    {
        $this->checkers = [];
    }
}
