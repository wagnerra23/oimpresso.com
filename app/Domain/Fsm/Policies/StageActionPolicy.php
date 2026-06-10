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
    /**
     * Permissão module-level que destrava a execução FSM por tipo de subject
     * (ADR 0265 — fio usável): roles Spatie (mecanico#biz/gerente#biz) valem como
     * RESTRIÇÃO ADICIONAL quando atribuídas, nunca como muro default — o seeder
     * criava as roles mas ninguém as recebia, e o dono do negócio via "ação oculta
     * por falta de permissão" na própria OS. Quem tem a permission de update do
     * módulo (ou superadmin) executa o fluxo.
     *
     * @var array<class-string, string>
     */
    private const SUBJECT_UPDATE_PERMISSION = [
        \Modules\OficinaAuto\Entities\ServiceOrder::class => 'oficinaauto.service_order.update',
    ];

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

        if ($this->grantsByPermission($user, $subject)) {
            return true;
        }

        $roleNames = $action->roles->pluck('role_name')->all();
        if (empty($roleNames)) {
            return true;
        }

        return $user !== null && $user->hasAnyRole($roleNames);
    }

    /**
     * Permission module-level concede execução pro subject? (compartilhado com
     * ExecuteStageActionService — policy e service NUNCA podem divergir, senão a
     * UI mostra can_execute=true e o execute() responde 403.)
     */
    public function grantsByPermission(?User $user, Model $subject): bool
    {
        if ($user === null) {
            return false;
        }

        $permission = self::SUBJECT_UPDATE_PERMISSION[get_class($subject)] ?? null;
        if ($permission === null) {
            return false;
        }

        return $user->can('superadmin') || $user->can($permission);
    }
}
