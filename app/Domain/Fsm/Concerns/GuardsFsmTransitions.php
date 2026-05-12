<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Concerns;

use App\Domain\Fsm\Observers\TransactionFsmObserver;

/**
 * US-SELL-032 — Trait que registra TransactionFsmObserver no model.
 *
 * Models FSM-managed (Transaction, Repair JobSheet futuro, McpTask futuro)
 * adicionam `use GuardsFsmTransitions;`. Eloquent boot() registra o
 * observer automaticamente.
 *
 * Uso:
 *   class Transaction extends Model {
 *       use \App\Domain\Fsm\Concerns\GuardsFsmTransitions;
 *       // ...
 *   }
 */
trait GuardsFsmTransitions
{
    public static function bootGuardsFsmTransitions(): void
    {
        static::observe(TransactionFsmObserver::class);
    }
}
