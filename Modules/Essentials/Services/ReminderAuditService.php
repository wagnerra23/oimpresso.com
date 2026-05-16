<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Log;
use Modules\Essentials\Entities\Reminder;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * ReminderAuditService — wrap append-only de auditoria pra CRUD de lembretes.
 *
 * **Por que existe (Wave 12 D7 LGPD recovery — 2026-05-16):**
 * Reminder.name é texto livre — usuários colocam nome de cliente, telefone, CPF
 * pra lembrar de ligação/cobrança/aniversário. Sem redaction, esse PII vaza em
 * stream de observabilidade (OTel collector, logs PSR-3 storage/logs/laravel.log).
 *
 * Este Service oferece logs estruturados com **PiiRedactor** aplicado em `name`
 * + ressaltos com OTel span (`essentials.reminder.*`).
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * `$businessId` sempre explícito pelo caller. NUNCA assumir session() em job/queue.
 *
 * **Append-only:** Spatie LogsActivity em Reminder persiste mudanças no DB (`activity_log`
 * table multi-tenant via FK ao subject). Este Service complementa com stream PSR-3
 * pra que dashboards/observabilidade externos não recebam PII bruta.
 *
 * @see Modules\Essentials\Entities\Reminder
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 * @see Modules\Essentials\Services\LeaveAuditService (pattern irmão)
 * @see Modules\Essentials\Config\retention.php
 */
class ReminderAuditService
{
    public function __construct(
        private readonly PiiRedactor $redactor,
    ) {
    }

    /**
     * Loga criação de reminder redactando `name`.
     *
     * @param Reminder $reminder Já carregado scoped (caller garante business_id)
     * @param int $businessId Tier 0 multi-tenant explícito
     */
    public function logCreated(Reminder $reminder, int $businessId): void
    {
        OtelHelper::spanBiz('essentials.reminder.created', function () use ($reminder, $businessId) {
            $nameSafe = $this->redactor->redact((string) ($reminder->name ?? ''));

            Log::channel(config('logging.essentials_audit_channel', 'stack'))->info('essentials.reminder.created', [
                'business_id' => $businessId,
                'reminder_id' => $reminder->id,
                'user_id'     => $reminder->user_id,
                'name_safe'   => $nameSafe,
                'date'        => $reminder->date,
                'repeat'      => $reminder->repeat,
                'logged_at'   => now()->toIso8601String(),
            ]);
        }, [
            'reminder_id' => $reminder->id,
        ]);
    }

    /**
     * Loga atualização de reminder com diff redactado.
     *
     * @param Reminder $reminder Já atualizado
     * @param array<string, mixed> $original Atributos pre-update (Eloquent ->getOriginal())
     */
    public function logUpdated(Reminder $reminder, array $original, int $businessId): void
    {
        OtelHelper::spanBiz('essentials.reminder.updated', function () use ($reminder, $original, $businessId) {
            $nameBefore = $this->redactor->redact((string) ($original['name'] ?? ''));
            $nameAfter  = $this->redactor->redact((string) ($reminder->name ?? ''));

            Log::channel(config('logging.essentials_audit_channel', 'stack'))->info('essentials.reminder.updated', [
                'business_id' => $businessId,
                'reminder_id' => $reminder->id,
                'user_id'     => $reminder->user_id,
                'name_before' => $nameBefore,
                'name_after'  => $nameAfter,
                'logged_at'   => now()->toIso8601String(),
            ]);
        }, [
            'reminder_id' => $reminder->id,
        ]);
    }

    /**
     * Loga deleção de reminder.
     */
    public function logDeleted(Reminder $reminder, int $businessId): void
    {
        OtelHelper::spanBiz('essentials.reminder.deleted', function () use ($reminder, $businessId) {
            $nameSafe = $this->redactor->redact((string) ($reminder->name ?? ''));

            Log::channel(config('logging.essentials_audit_channel', 'stack'))->info('essentials.reminder.deleted', [
                'business_id' => $businessId,
                'reminder_id' => $reminder->id,
                'user_id'     => $reminder->user_id,
                'name_safe'   => $nameSafe,
                'logged_at'   => now()->toIso8601String(),
            ]);
        }, [
            'reminder_id' => $reminder->id,
        ]);
    }
}
