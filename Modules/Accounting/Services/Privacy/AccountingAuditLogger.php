<?php

declare(strict_types=1);

namespace Modules\Accounting\Services\Privacy;

use Illuminate\Database\Eloquent\Model;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * Wrapper LGPD-aware de `activity()->log(...)` (Spatie Activitylog) para
 * lançamentos contábeis Accounting (Wave 11 — D7 LGPD push 0→6/10).
 *
 * Por quê:
 *  - O campo `notes` / `reference` / `description` de JournalEntry e
 *    AccountTransaction é livre — usuários frequentemente escrevem
 *    "Pagamento NF 123 — CPF 999.888.777-66 — Fornecedor X".
 *  - Logado direto via Spatie sem sanitização, isso vira PII em
 *    `activity_log.properties` (JSON), violando LGPD Art. 6 II/III
 *    (necessidade + adequação) — não há base legal pra reter CPF
 *    indefinidamente em audit trail interno.
 *
 * Estratégia:
 *  - Antes de chamar `activity()->log()`, passa o payload por
 *    `PiiRedactor::redactArray()` (modo placeholder default).
 *  - Preserva estrutura/chaves; substitui valores PII por
 *    `[REDACTED:CPF]`, `[REDACTED:CNPJ]`, `[REDACTED:EMAIL]`,
 *    `[REDACTED:PHONE]`, `[REDACTED:CEP]`.
 *  - Identificadores de negócio (transaction_number, gl_code) ficam
 *    intactos — são chave legítima de auditoria.
 *
 * Referências:
 *  - LGPD Art. 6 II/III (princípios), Art. 7 (bases legais)
 *  - ADR 0093 (multi-tenant — caller passa biz contextual via subject)
 *  - PiiRedactor: Modules/Jana/Services/Privacy/PiiRedactor.php
 *
 * Uso:
 * ```php
 * app(AccountingAuditLogger::class)->log(
 *     subject: $journalEntry,
 *     event: 'journal_entry.created',
 *     properties: ['notes' => $request->additional_notes, 'amount' => 500.00],
 * );
 * ```
 *
 * @see \Modules\Jana\Services\Privacy\PiiRedactor
 */
class AccountingAuditLogger
{
    public function __construct(
        private readonly PiiRedactor $redactor,
    ) {
    }

    /**
     * Registra evento Spatie Activitylog com payload sanitizado de PII.
     *
     * @param  Model                $subject     Model audited (JournalEntry, AccountTransaction, etc)
     * @param  string               $event       Slug do evento ('journal_entry.created', 'journal_entry.reversed')
     * @param  array<string,mixed>  $properties  Payload — será sanitizado recursivamente
     */
    public function log(Model $subject, string $event, array $properties = []): void
    {
        $sanitized = $this->redactor->redactArray($properties);

        activity()
            ->on($subject)
            ->withProperties($sanitized)
            ->log($event);
    }

    /**
     * Sanitiza array sem persistir (útil quando caller já vai chamar activity()
     * próprio e só quer redactar payload).
     *
     * @param  array<string,mixed>  $properties
     * @return array<string,mixed>
     */
    public function sanitize(array $properties): array
    {
        return $this->redactor->redactArray($properties);
    }
}
