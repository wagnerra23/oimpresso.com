<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

use Illuminate\Support\Facades\Log;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * LeaveAuditService — Wave 11 LGPD (D7.a).
 *
 * Centraliza logging de eventos de Leave (solicitação/aprovação/rejeição)
 * com redaction obrigatória de PII brasileira (CPF/CNPJ/Email/Phone/CEP)
 * via `PiiRedactor::redact()` antes de bater no canal de log.
 *
 * Por quê: a tabela `essentials_leaves` carrega `reason` (texto livre) +
 * referência a `user` (que pode ter CPF/email em comments). Logar isso direto
 * em `storage/logs/laravel.log` violaria LGPD Art. 7º (princípio da
 * minimização) — logs rotacionados podem viajar pra observabilidade externa.
 *
 * Compliance: LGPD Art. 6º (finalidade), Art. 7º (bases legais),
 * Art. 46 (segurança), Constituição v2 §4 (Princípio Loop fechado).
 *
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 * @see memory/decisions/0085-fase-3-4-scope-md-completo-actor-resolver-pii-redactor.md
 */
class LeaveAuditService
{
    public function __construct(private readonly PiiRedactor $redactor)
    {
    }

    /**
     * Loga criação de solicitação de leave com payload redactado.
     */
    public function logLeaveCreated(EssentialsLeave $leave, array $rawPayload): void
    {
        $safePayload = $this->redactor->redactArray($rawPayload);

        Log::channel(config('logging.default'))->info('essentials.leave.created', [
            'leave_id'    => $leave->id,
            'business_id' => $leave->business_id,
            'user_id'     => $leave->user_id,
            'status'      => $leave->status,
            'payload'     => $safePayload,
        ]);
    }

    /**
     * Loga mudança de status (approve/reject) com motivo redactado.
     */
    public function logStatusChanged(EssentialsLeave $leave, string $oldStatus, ?string $reason = null): void
    {
        $safeReason = $reason !== null ? $this->redactor->redact($reason) : null;

        Log::channel(config('logging.default'))->info('essentials.leave.status_changed', [
            'leave_id'    => $leave->id,
            'business_id' => $leave->business_id,
            'from'        => $oldStatus,
            'to'          => $leave->status,
            'changed_by'  => $leave->changed_by,
            'reason'      => $safeReason,
        ]);
    }
}
