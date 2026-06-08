<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Policies;

use App\Domain\Fsm\Models\SaleStageAction;
use App\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy reutilizável (Controller/View/Job) — sem invocar side-effect/event/log.
 * ExecuteStageActionService chama internamente; UI usa pra esconder botão.
 */
class StageActionPolicy
{
    public function canExecute(?User $user, Model $subject, string $actionKey): bool
    {
        $currentStageId = $subject->current_stage_id ?? null;

        $action = SaleStageAction::with(['stage.process', 'roles'])
            ->where('stage_id', $currentStageId)
            ->where('key', $actionKey)
            ->first();

        if (! $action || $action->stage->is_terminal) {
            return false;
        }

        if (($subject->business_id ?? null) !== $action->stage->process->business_id) {
            return false;
        }

        $roleNames = $action->roles->pluck('role_name')->all();
        if (empty($roleNames)) {
            return true;
        }

        return $user !== null && $user->hasAnyRole($roleNames);
    }
}
