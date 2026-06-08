<?php

declare(strict_types=1);

namespace Modules\Superadmin\Support;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * Trait RedactsPiiInLogs — LGPD Tier 0 D7.a (Wave 11 Superadmin).
 *
 * Superadmin é o ÚNICO módulo legitimamente cross-tenant (Wagner-only).
 * Toda \Log::emergency() em catch (\Exception) recebe getMessage() que pode
 * conter PII de QUALQUER tenant (email, CPF, CNPJ, telefone) — vazamento
 * cross-tenant em log = incidente LGPD grave.
 *
 * Pattern:
 *   try { ... }
 *   catch (\Exception $e) {
 *       $this->logEmergencyRedacted($e, 'Controller@method');
 *   }
 *
 * Aplica `PiiRedactor::redact()` no Message e no context opcional.
 * File:Line ficam intactos (paths são código, não PII).
 *
 * Constituição Art. 4 (Compliance LGPD Art. 7º) + ADR 0093 (Multi-tenant Tier 0).
 */
trait RedactsPiiInLogs
{
    protected function logEmergencyRedacted(\Throwable $e, string $context = ''): void
    {
        $redactor = app(PiiRedactor::class);
        $message = $redactor->redact((string) $e->getMessage());
        $ctx = $context !== '' ? '['.$redactor->redact($context).'] ' : '';

        Log::emergency($ctx.'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$message);
    }

    /**
     * Log info redactado — útil pra fluxos cross-tenant que logam payload bruto
     * (ex: PesaPalController logando transaction_id + status).
     */
    protected function logInfoRedacted(string $message, array $context = []): void
    {
        $redactor = app(PiiRedactor::class);
        Log::info($redactor->redact($message), $redactor->redactArray($context));
    }
}
