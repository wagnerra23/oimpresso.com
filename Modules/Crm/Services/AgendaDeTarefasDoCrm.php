<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Contact;
use App\Contracts\Crm\AgendaDeTarefas;
use App\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\Schedule;

/**
 * Implementação do contrato `App\Contracts\Crm\AgendaDeTarefas` sobre o follow-up do CRM
 * (`crm_schedules` + `crm_schedule_users` + `crm_followup_invoices`).
 *
 * Usa o Model `Schedule` (activity log LGPD + scope). Chamada de job, sem session: o
 * `business_id` vem explícito e o usuário/contato são conferidos contra ele (ADR 0093).
 */
class AgendaDeTarefasDoCrm implements AgendaDeTarefas
{
    public function criarTarefa(
        int $businessId,
        int $userId,
        string $titulo,
        string $descricao,
        ?int $contactId,
        ?int $transactionId,
        CarbonInterface $quando,
    ): ?int {
        if (! User::query()->whereKey($userId)->where('business_id', $businessId)->exists()) {
            return null;
        }
        if ($contactId !== null
            && ! Contact::withoutGlobalScopes() // SUPERADMIN: job sem session; business_id explícito
                ->whereKey($contactId)->where('business_id', $businessId)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($businessId, $userId, $titulo, $descricao, $contactId, $transactionId, $quando) {
            $schedule = Schedule::create([
                'business_id' => $businessId,
                'contact_id' => $contactId,
                'title' => $titulo,
                'status' => 'scheduled',
                'start_datetime' => $quando,
                'end_datetime' => $quando->copy()->addDay(),
                'description' => $descricao,
                'schedule_type' => 'call',
                'allow_notification' => 0,
                'created_by' => $userId,
            ]);
            $schedule->users()->sync([$userId]);
            if ($transactionId !== null) {
                $schedule->invoices()->sync([$transactionId]);
            }

            return (int) $schedule->id;
        });
    }
}
