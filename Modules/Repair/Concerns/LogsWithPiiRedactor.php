<?php

declare(strict_types=1);

namespace Modules\Repair\Concerns;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * Trait helper — Wave 17 D7.a LGPD hardening (2026-05-16).
 *
 * Substitui `\Log::emergency('File:'.$e->getFile()...)` raw nos Controllers Repair
 * (vazamento potencial PII via $e->getMessage() quando exception captura contexto
 * de update OS com nome cliente, defects livre, telefone, CPF/CNPJ).
 *
 * Wrap idiomático: `$this->logSafeEmergency('contexto', $e)`.
 *
 * Defesa em profundidade (ADR 0094 §6) — complementa LogsActivity em Entities
 * (Wave S Batch 2) + sale_stage_history FSM canon (ADR 0143).
 *
 * Aplicado em: RepairController, JobSheetController, RepairSettingsController,
 * RepairStatusController, DeviceModelController, CustomerRepairStatusController.
 */
trait LogsWithPiiRedactor
{
    /**
     * Loga exception em nível emergency com PII redactada.
     *
     * @param  string  $context  Contexto identificável (ex: 'job_sheet.update', 'repair_status.destroy')
     * @param  \Throwable  $e  Exception capturada
     */
    protected function logSafeEmergency(string $context, \Throwable $e): void
    {
        $redactor = app(PiiRedactor::class);

        $safeMessage = $redactor->redact($e->getMessage());

        Log::emergency(
            '[repair.'.$context.'] File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$safeMessage,
            [
                'business_id' => session('user.business_id'),
                'exception_class' => get_class($e),
            ],
        );
    }
}
