<?php

declare(strict_types=1);

namespace Modules\Fisio\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Sinal de alerta marcado pelo paciente no check-in do portal (US-FISIO-007b, UC-FPP-13).
 * Vai na hora para o profissional do programa (e para o responsável pelo paciente), pelo sino
 * do sistema (canal `database`). Só avisa: não sugere conduta (ADR 0437 §5). O texto exibido
 * sai de `Modules\Fisio\Http\Controllers\DataController::parse_notification()`.
 */
final class SinalDeAlertaNotification extends Notification
{
    /**
     * @param  list<string>  $redFlags  sinais da lista fechada `config('fisio.red_flags')`
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $patientId,
        public readonly int $checkinId,
        public readonly array $redFlags,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'business_id' => $this->businessId,
            'patient_id' => $this->patientId,
            'checkin_id' => $this->checkinId,
            'red_flags' => $this->redFlags,
        ];
    }
}
