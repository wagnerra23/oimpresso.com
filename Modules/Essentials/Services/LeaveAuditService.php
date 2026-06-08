<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Log;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * LeaveAuditService — wrap append-only de auditoria pra mudanças de status em
 * solicitações de Leave (atestado/férias/folga).
 *
 * **Por que existe (Wave 12 D7 LGPD recovery — 2026-05-16):**
 * EssentialsLeave já usa Spatie LogsActivity (audit log nativo via activity_log).
 * Este Service adiciona uma camada de log estruturado (PSR-3) com **PiiRedactor**
 * aplicado em campos texto livre (`reason`, `note`) ANTES de subir pro storage.
 *
 * `reason` típico contém PII alta (nome de médico, diagnóstico, CID, nome de
 * familiar internado). Spatie ActivityLog persiste no DB protegido por business_id
 * scope, MAS daemons que streamem activity_log pra observabilidade externa (Otel
 * collector CT 100) precisariam re-redact — facilita logar via wrapper.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Recebe `$businessId` explícito do caller (Controller resolve via session()).
 * Service NUNCA assume scope implícito.
 *
 * **OTel instrumentation:** span `essentials.leave.status_change` com business_id +
 * leave_id + from_status/to_status. Zero-cost se OTel desligado.
 *
 * @see Modules\Essentials\Entities\EssentialsLeave (LogsActivity Spatie)
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 * @see Modules\Crm\Services\CampaignService (pattern referência D7 Wave 9)
 * @see Modules\Essentials\Config\retention.php
 */
class LeaveAuditService
{
    public function __construct(
        private readonly PiiRedactor $redactor,
    ) {
    }

    /**
     * Loga mudança de status redactando reason/note. Spatie ActivityLog continua
     * disparando via Model events — esse log é COMPLEMENTAR (stream observability).
     *
     * Idempotente: chamar 2x com mesmo estado loga 2x (log é histórico, não dedup).
     *
     * @param EssentialsLeave $leave Já carregado scoped (caller garante business_id)
     * @param string $newStatus Status alvo (pending/approved/cancelled)
     * @param int $changedBy ID usuário que disparou
     * @param int $businessId Tier 0 multi-tenant explícito
     */
    public function logStatusChange(
        EssentialsLeave $leave,
        string $newStatus,
        int $changedBy,
        int $businessId,
    ): void {
        OtelHelper::spanBiz('essentials.leave.status_change', function () use ($leave, $newStatus, $changedBy, $businessId) {
            // D7 LGPD: reason pode conter CPF/email/telefone do colaborador + nome
            // médico/diagnóstico. Redact ANTES de qualquer log estruturado.
            $reasonSafe = $this->redactor->redact((string) ($leave->reason ?? ''));

            Log::channel(config('logging.essentials_audit_channel', 'stack'))->info('essentials.leave.status_change', [
                'business_id'  => $businessId,
                'leave_id'     => $leave->id,
                'user_id'      => $leave->user_id,
                'from_status'  => $leave->getOriginal('status'),
                'to_status'    => $newStatus,
                'changed_by'   => $changedBy,
                'reason_safe'  => $reasonSafe,
                'logged_at'    => now()->toIso8601String(),
            ]);
        }, [
            'leave_id'   => $leave->id,
            'new_status' => $newStatus,
        ]);
    }

    /**
     * Loga criação nova de leave redactando reason. Caller (Controller @store)
     * dispara após persistir EssentialsLeave + relacionados.
     */
    public function logCreated(EssentialsLeave $leave, int $businessId): void
    {
        OtelHelper::spanBiz('essentials.leave.created', function () use ($leave, $businessId) {
            $reasonSafe = $this->redactor->redact((string) ($leave->reason ?? ''));

            Log::channel(config('logging.essentials_audit_channel', 'stack'))->info('essentials.leave.created', [
                'business_id'  => $businessId,
                'leave_id'     => $leave->id,
                'user_id'      => $leave->user_id,
                'leave_type'   => $leave->essentials_leave_type_id,
                'start_date'   => $leave->start_date,
                'end_date'     => $leave->end_date,
                'reason_safe'  => $reasonSafe,
                'logged_at'    => now()->toIso8601String(),
            ]);
        }, [
            'leave_id' => $leave->id,
        ]);
    }
}
