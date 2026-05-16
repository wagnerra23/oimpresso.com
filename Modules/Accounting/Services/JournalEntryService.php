<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Entities\JournalEntry;
use Modules\Accounting\Entities\PaymentDetail;
use Modules\Accounting\Services\Privacy\AccountingAuditLogger;

/**
 * Service thin — extrai lógica de criação/reversão de lançamentos contábeis
 * do JournalEntryController (Wave J D4.a — fat-controller → service testável).
 *
 * Multi-tenant Tier 0 (ADR 0093): caller passa $businessId explicitamente
 * (jobs async não enxergam session). Para uso web normal, Controller passa
 * session('business.id').
 *
 * @see Modules/Accounting/Http/Controllers/JournalEntryController.php
 * @see memory/requisitos/Accounting/BRIEFING.md
 */
class JournalEntryService
{
    public function __construct(
        private ?AccountingAuditLogger $auditLogger = null,
    ) {
        // Fallback resolve container — caller pode injetar (testes) ou usar default.
        $this->auditLogger = $auditLogger ?? app(AccountingAuditLogger::class);
    }

    /**
     * Cria entradas contábeis balanceadas (debit + credit) dentro de uma transação DB.
     *
     * @param  array<string,mixed>  $payload  ['location_id','currency_id','payment_type_id','date','journal_entry_data'=>[['debit','credit','amount','notes'],...]]
     * @param  int                  $userId   Created_by — caller passa Auth::id() (web) ou job context
     * @return array<int,string>              Lista de transaction_numbers gerados
     *
     * @throws \Throwable Rollback automático em erro
     */
    public function criarEntradaBalanceada(array $payload, int $userId): array
    {
        $transactionNumbers = [];

        foreach ($payload['journal_entry_data'] as $row) {
            DB::transaction(function () use ($payload, $row, $userId, &$transactionNumbers) {
                $paymentDetail = new PaymentDetail();
                $paymentDetail->created_by_id = $userId;
                $paymentDetail->payment_type_id = $payload['payment_type_id'] ?? null;
                $paymentDetail->transaction_type = 'journal_manual_entry';
                $paymentDetail->save();

                $transactionNumber = function_exists('get_uniqid') ? get_uniqid() : uniqid('je_', true);
                $transactionNumbers[] = $transactionNumber;

                $dateParts = explode('-', $payload['date']);

                // Lado débito
                $this->persistirLado(
                    chartOfAccountId: (int) $row['debit'],
                    amount: (float) $row['amount'],
                    isDebit: true,
                    payload: $payload,
                    transactionNumber: $transactionNumber,
                    paymentDetailId: $paymentDetail->id,
                    userId: $userId,
                    dateParts: $dateParts,
                    notes: $row['notes'] ?? null,
                );

                // Lado crédito
                $this->persistirLado(
                    chartOfAccountId: (int) $row['credit'],
                    amount: (float) $row['amount'],
                    isDebit: false,
                    payload: $payload,
                    transactionNumber: $transactionNumber,
                    paymentDetailId: $paymentDetail->id,
                    userId: $userId,
                    dateParts: $dateParts,
                    notes: $row['notes'] ?? null,
                );
            });
        }

        return $transactionNumbers;
    }

    /**
     * Reverte um lançamento — marca original como reversed=1 e cria contrapartida.
     * Preserva audit-trail (não DELETE).
     *
     * @param  string  $oldTransactionNumber
     * @param  int     $businessId           Multi-tenant scope (ADR 0093)
     * @param  int     $userId
     * @return string                        Novo transaction_number gerado
     */
    public function reverter(string $oldTransactionNumber, int $businessId, int $userId): string
    {
        $entries = JournalEntry::leftJoin('business_locations', 'business_locations.id', 'journal_entries.location_id')
            ->where('transaction_number', $oldTransactionNumber)
            ->where('business_locations.business_id', $businessId);

        $entries->update(['reversed' => 1, 'reversible' => 0]);

        $newTransactionNumber = uniqid();

        foreach ($entries->get() as $key) {
            $entry = new JournalEntry();
            $entry->created_by_id = $userId;
            $entry->transaction_number = $newTransactionNumber;
            $entry->payment_detail_id = $key->payment_detail_id;
            $entry->location_id = $key->location_id;
            $entry->currency_id = $key->currency_id;
            $entry->chart_of_account_id = $key->chart_of_account_id;
            $entry->transaction_type = $key->transaction_type;
            $entry->date = date('Y-m-d');
            $dateParts = explode('-', date('Y-m-d'));
            $entry->month = $dateParts[1];
            $entry->year = $dateParts[0];

            // Swap: original débito → reversão crédito (e vice-versa)
            if (empty($key->debit)) {
                $entry->debit = $key->credit;
            } else {
                $entry->credit = $key->debit;
            }
            $entry->reference = $key->reference;
            $entry->manual_entry = $key->manual_entry;
            $entry->notes = $key->notes;
            $entry->save();

            // LGPD D7.a — audit reversão com payload sanitizado (notes/reference
            // podem conter CPF/CNPJ/email de cliente/fornecedor).
            // Wave 11 sessão 2026-05-16.
            $this->auditLogger->log(
                subject: $entry,
                event: 'journal_entry.reversed',
                properties: [
                    'original_transaction_number' => $oldTransactionNumber,
                    'new_transaction_number' => $newTransactionNumber,
                    'business_id' => $businessId,
                    'reference' => $key->reference,
                    'notes' => $key->notes,
                ],
            );
        }

        return $newTransactionNumber;
    }

    /**
     * Helper interno — persiste UM lado (débito ou crédito) do lançamento.
     */
    private function persistirLado(
        int $chartOfAccountId,
        float $amount,
        bool $isDebit,
        array $payload,
        string $transactionNumber,
        int $paymentDetailId,
        int $userId,
        array $dateParts,
        ?string $notes,
    ): void {
        $entry = new JournalEntry();
        $entry->created_by_id = $userId;
        $entry->payment_detail_id = $paymentDetailId;
        $entry->transaction_number = $transactionNumber;
        $entry->location_id = $payload['location_id'];
        $entry->currency_id = $payload['currency_id'];
        $entry->chart_of_account_id = $chartOfAccountId;
        $entry->transaction_type = 'manual_entry';
        $entry->date = $payload['date'];
        $entry->month = $dateParts[1];
        $entry->year = $dateParts[0];
        $entry->manual_entry = 1;
        $entry->notes = $notes;

        if ($isDebit) {
            $entry->debit = $amount;
        } else {
            $entry->credit = $amount;
        }

        $entry->save();

        // LGPD D7.a — payload sanitizado via PiiRedactor antes do audit log
        // (notes pode conter CPF/CNPJ/email de cliente/fornecedor — ver
        // AccountingAuditLogger). Wave 11 sessão 2026-05-16.
        $this->auditLogger->log(
            subject: $entry,
            event: 'journal_entry.balanced_created',
            properties: [
                'id' => $entry->id,
                'transaction_number' => $transactionNumber,
                'side' => $isDebit ? 'debit' : 'credit',
                'amount' => $amount,
                'notes' => $notes,
            ],
        );
    }
}
