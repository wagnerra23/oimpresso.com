<?php

namespace Modules\Accounting\Services;

use App\AccountTransaction;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Entities\JournalEntry;
use Modules\Accounting\Entities\PaymentDetail;
use Modules\Accounting\Services\Privacy\AccountingAuditLogger;

class AccountingService
{
    public function __construct(
        private ?AccountingAuditLogger $auditLogger = null,
    ) {
        // Fallback resolve container (back-compat com instâncias `new AccountingService()` existentes).
        $this->auditLogger = $auditLogger ?? app(AccountingAuditLogger::class);
    }

    public function updateChartAccounts($input, $type, $subtype = null)
    {
        // D9.a OTel Wave 17 — span chamada pública crítica (cria AccountTransaction).
        return OtelHelper::spanBiz('accounting.service.update_chart_accounts', function () use ($input, $type, $subtype) {
            //Get the amount depending on whether a debit or credit is being made
            $amount = $input['debit'] > 0 ? $input['debit'] : $input['credit'];

            $account_transaction_data = [
                'amount' => $amount,
                'account_id' => $input['account_id'],
                'type' => $type,
                'sub_type' => $subtype,
                'operation_date' => $input['journal_date'],
                'created_by' => Auth::id(),
                'note' => $input['description']
            ];

            $account_transaction = AccountTransaction::createAccountTransaction($account_transaction_data);

            $account_transaction->save();

            return $account_transaction;
        }, [
            'type'    => (string) $type,
            'subtype' => (string) ($subtype ?? ''),
        ]);
    }

    public function createJournalEntry(Request $request)
    {
        // D9 OTel — wrap fluxo legacy Controller-driven (Wave 12). Mantém return original.
        return OtelHelper::spanBiz('accounting.service.create_journal_entry', function () use ($request) {
            return $this->doCreateJournalEntry($request);
        }, [
            'transaction_type' => 'manual_entry',
        ]);
    }

    /**
     * Implementação interna — extraída pra OTel wrap não-invasivo.
     */
    private function doCreateJournalEntry(Request $request)
    {
        try {
            DB::beginTransaction();

            $transaction_date = date('Y-m-d', strtotime($request->transaction_date));
            $transaction_number = get_uniqid();

            $payment_detail = new PaymentDetail();
            $payment_detail->created_by_id = Auth::id();
            $payment_detail->payment_type_id = $request->payment_type_id;
            $payment_detail->transaction_type = 'journal_manual_entry';
            $payment_detail->save();

            //debit account
            $journal_entry = new JournalEntry();
            $journal_entry->created_by_id = Auth::id();
            $journal_entry->payment_detail_id = $payment_detail->id;
            $journal_entry->transaction_number = $transaction_number;
            $journal_entry->location_id = $request->location_id;
            $journal_entry->currency_id = $request->currency_id;
            $journal_entry->chart_of_account_id = $request->debit;
            $journal_entry->transaction_type = 'manual_entry';
            $journal_entry->date = $transaction_date;
            $date = explode('-', $transaction_date);
            $journal_entry->month = $date[1];
            $journal_entry->year = $date[0];
            $journal_entry->debit = $request->final_total;
            $journal_entry->manual_entry = 1;
            $journal_entry->notes = $request->additional_notes;
            $journal_entry->save();

            //credit account
            $journal_entry = new JournalEntry();
            $journal_entry->created_by_id = Auth::id();
            $journal_entry->transaction_number = $transaction_number;
            $journal_entry->payment_detail_id = $payment_detail->id;
            $journal_entry->location_id = $request->location_id;
            $journal_entry->currency_id = $request->currency_id;
            $journal_entry->chart_of_account_id = $request->credit;
            $journal_entry->transaction_type = 'manual_entry';
            $journal_entry->date = $transaction_date;
            $date = explode('-', $transaction_date);
            $journal_entry->month = $date[1];
            $journal_entry->year = $date[0];
            $journal_entry->credit = $request->final_total;
            $journal_entry->manual_entry = 1;
            $journal_entry->notes = $request->additional_notes;
            $journal_entry->save();

            // LGPD D7.a — payload sanitizado via PiiRedactor antes do audit log
            // (notes pode conter CPF/CNPJ/email de cliente/fornecedor — ver
            // AccountingAuditLogger). Wave 11 — sessão 2026-05-16.
            $this->auditLogger->log(
                subject: $journal_entry,
                event: 'journal_entry.created',
                properties: [
                    'id' => $journal_entry->id,
                    'transaction_number' => $journal_entry->transaction_number,
                    'notes' => $journal_entry->notes,
                ],
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return (new FlashService())->onException($e)->redirectBackWithInput();
        }
    }
}
