<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Concerns;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * Trait helper — Wave 17 D7.a LGPD hardening (2026-05-16).
 *
 * Substitui `\Log::emergency('File:'.$e->getFile()...)` raw (vazamento potencial PII
 * via $e->getMessage() quando exception envolve dados de cliente — ex: ref_no com
 * email, lot_number serializando $contact, additional_notes copiando observação
 * livre digitada na UI).
 *
 * Wrap idiomático: `$this->logSafeEmergency('contexto', $e)`.
 *
 * Defesa em profundidade (ADR 0094 §6) — complementa PiiRedactor já aplicado em
 * `ProductionService::logProductionEvent()` (Wave 14).
 *
 * Reaproveitado em todos os Controllers Manufacturing que tratam exception em CRUD
 * de produção/receita/settings.
 */
trait LogsWithPiiRedactor
{
    /**
     * Loga exception em nível emergency com PII redactada.
     *
     * @param  string  $context  Contexto identificável (ex: 'recipe.store', 'production.update')
     * @param  \Throwable  $e  Exception capturada
     */
    protected function logSafeEmergency(string $context, \Throwable $e): void
    {
        $redactor = app(PiiRedactor::class);

        $safeMessage = $redactor->redact($e->getMessage());

        Log::emergency(
            '[manufacturing.'.$context.'] File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$safeMessage,
            [
                'business_id' => session('user.business_id'),
                'exception_class' => get_class($e),
            ],
        );
    }
}
