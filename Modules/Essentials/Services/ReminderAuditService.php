<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

use Illuminate\Support\Facades\Log;
use Modules\Essentials\Entities\Reminder;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * ReminderAuditService — Wave 11 LGPD (D7.a).
 *
 * Loga eventos de Reminder (essentials calendário) com PII brasileira
 * redactada via `PiiRedactor::redact()`. Reminders são texto livre por
 * usuário e podem inadvertidamente conter telefone, email, CPF ou endereço
 * (ex: "ligar pra Maria 11 99999-0000 sobre proposta").
 *
 * Compliance: LGPD Art. 7º (minimização), Constituição v2 §4 Princípio 7
 * (Transparência) — logs auditáveis mas sem dados pessoais identificáveis.
 *
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */
class ReminderAuditService
{
    public function __construct(private readonly PiiRedactor $redactor)
    {
    }

    /**
     * Loga criação/edição de reminder com nome+descrição redactados.
     */
    public function logReminderUpserted(Reminder $reminder, string $event = 'created'): void
    {
        $safeName = $this->redactor->redact((string) ($reminder->name ?? ''));
        $safeDescription = $this->redactor->redact((string) ($reminder->description ?? ''));

        Log::channel(config('logging.default'))->info("essentials.reminder.{$event}", [
            'reminder_id' => $reminder->id,
            'business_id' => $reminder->business_id,
            'user_id'     => $reminder->user_id,
            'date'        => $reminder->date,
            'repeat'      => $reminder->repeat,
            'name'        => $safeName,
            'description' => $safeDescription,
        ]);
    }

    /**
     * Loga remoção. Sem payload (apenas IDs).
     */
    public function logReminderDeleted(int $reminderId, int $businessId, int $userId): void
    {
        Log::channel(config('logging.default'))->info('essentials.reminder.deleted', [
            'reminder_id' => $reminderId,
            'business_id' => $businessId,
            'user_id'     => $userId,
        ]);
    }
}
