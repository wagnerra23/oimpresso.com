<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Contact;
use App\Http\Controllers\Controller;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use App\Utils\TransactionUtil;
use App\DocumentAndNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * ClienteOssDataController -- 7 endpoints JSON read-only que alimentam as
 * sub-tabs operacionais do OssTab no drawer 760px Cliente (ADR 0179).
 *
 * Bug raiz: as 8 sub-tabs nasceram da Show.tsx full-page legada, alimentadas
 * via Inertia::defer no payload do ContactController::show. No drawer Cliente
 * (Cliente/Index.tsx canon ADR 0179) os defers nao existem -- sub-tabs ficavam
 * stuck em "Carregando..." porque OssTab passa undefined em todas as props.
 *
 * Fix arquitetural: cada sub-tab faz self-fetch via fetch() quando monta. Este
 * controller centraliza os 7 endpoints JSON (1 arquivo, N metodos -- mais facil
 * pra manutencao do que 7 controllers separados, mas ainda 1 PR isolado).
 *
 * Endpoints (todos GET, prefixo /cliente/{id}/):
 *   - GET /cliente/{id}/ledger     -> LedgerTab (TransactionUtil::getLedgerDetails)
 *   - GET /cliente/{id}/sales      -> SalesTab (Transaction paginator)
 *   - GET /cliente/{id}/payments   -> PaymentsTab (TransactionPayment scoped)
 *   - GET /cliente/{id}/documents  -> DocumentsTab (DocumentAndNote polymorphic)
 *   - GET /cliente/{id}/activities -> ActivitiesTab (Activity::forSubject)
 *   - GET /cliente/{id}/persons    -> PessoasContatoTab (User com crm_contact_id)
 *   - GET /cliente/{id}/subscriptions -> SubscriptionsTab (Transaction is_recurring=1)
 *   - GET /cliente/{id}/rewards    -> RewardPointsTab (RP summary + history)
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL):
 *   - Toda query: Contact::where('business_id', $bizId)->where('id', $id)->firstOrFail()
 *   - firstOrFail() retorna 404 automatico -- semantica nao vaza existencia
 *   - business_id scope obrigatorio em TODA query relacional
 *
 * PII LGPD:
 *   - bank_account_number / card_transaction_number / cheque_number / tax_number:
 *     mascarados antes de sair. NUNCA plain.
 *   - email/mobile vai plain (UPOS canon, ADR 0093 §LGPD Art.7).
 *
 * Pre-flight LICOES F3:
 *   - T-AP-2: tenant scope explicito (where business_id)
 *   - T-AP-7: usa TransactionUtil real (ja injetado), nao inventa Service
 *   - T-AP-8: session('user.business_id') canon, nao auth()->user()->business_id
 *   - T-AP-9: middleware auth herdado do grupo /cliente em Routes/web.php
 *   - T-AP-11: whereNull('deleted_at') herdado de SoftDeletes em Contact
 *
 * @see resources/js/Pages/Cliente/_drawer/OssTab.tsx (8 sub-tabs)
 * @see resources/js/Pages/Cliente/_show/*.tsx (componentes sub-tabs)
 * @see app/Http/Controllers/ContactController.php::show (padrao Inertia::defer original)
 * @see memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ClienteOssDataController extends Controller
{
    public function __construct(
        private readonly TransactionUtil $transactionUtil,
    ) {
    }

    /**
     * GET /cliente/{id}/ledger
     *
     * Query params (opcionais):
     *   - start_date (YYYY-MM-DD)
     *   - end_date (YYYY-MM-DD)
     *   - format (format_1|format_2|format_3, default format_1)
     *   - location_id (int)
     *
     * Response:
     *   200 {
     *     lines: [{date, ref_no, description, debit, credit, balance, payment_method, doc_type}],
     *     period: {total_debit, total_credit, balance},
     *     all_time: {total_debit, total_credit, balance, opening_balance}
     *   }
     */
    public function ledger(Request $request, int $id): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $contact = $this->resolveContact($businessId, $id);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $format = $request->query('format', 'format_1');
        if (! in_array($format, ['format_1', 'format_2', 'format_3'], true)) {
            $format = 'format_1';
        }
        $locationId = $request->query('location_id');

        $lineDetails = $format === 'format_3';
        $details = $this->transactionUtil->getLedgerDetails(
            $contact->id,
            $startDate,
            $endDate,
            $format,
            $locationId,
            $lineDetails,
        );

        $lines = collect($details['ledger'] ?? [])->map(fn ($line) => [
            'date' => $line['date'] ?? null,
            'ref_no' => (string) ($line['ref_no'] ?? ''),
            'description' => (string) ($line['type'] ?? $line['description'] ?? ''),
            'debit' => (float) ($line['debit'] ?? 0),
            'credit' => (float) ($line['credit'] ?? 0),
            'balance' => (float) ($line['balance'] ?? 0),
            'payment_method' => $line['payment_method'] ?? null,
            'doc_type' => (string) ($line['doc_type'] ?? 'invoice'),
        ])->all();

        return response()->json([
            'lines' => $lines,
            'period' => [
                'total_debit' => (float) ($details['total_debit'] ?? array_sum(array_column($lines, 'debit'))),
                'total_credit' => (float) ($details['total_credit'] ?? array_sum(array_column($lines, 'credit'))),
                'balance' => (float) ($details['balance_due'] ?? 0),
            ],
            'all_time' => [
                'total_debit' => (float) ($details['total_invoice'] ?? 0),
                'total_credit' => (float) ($details['total_paid'] ?? 0),
                'balance' => (float) ($details['balance_due'] ?? 0),
                'opening_balance' => (float) ($contact->opening_balance ?? 0),
            ],
        ]);
    }

    /**
     * GET /cliente/{id}/sales
     *
     * Query params (opcionais):
     *   - customer_sales_start, customer_sales_end, customer_sales_status,
     *     customer_sales_q, customer_sales_page
     *
     * Response: paginator shape {data, total, current_page, last_page, from, to, links}
     */
    public function sales(Request $request, int $id): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $contact = $this->resolveContact($businessId, $id);

        $startDate = $request->query('customer_sales_start');
        $endDate = $request->query('customer_sales_end');
        $status = $request->query('customer_sales_status');
        $q = trim((string) $request->query('customer_sales_q', ''));
        $page = max(1, (int) $request->query('customer_sales_page', 1));

        $totalPaidExpr = '(SELECT COALESCE(SUM(amount), 0) FROM transaction_payments WHERE transaction_payments.transaction_id = transactions.id)';
        $query = Transaction::where('transactions.business_id', $businessId)
            ->where('contact_id', $contact->id)
            ->where('type', 'sell')
            ->where('status', '!=', 'draft')
            ->leftJoin('business_locations as bl', 'transactions.location_id', '=', 'bl.id')
            ->select(
                'transactions.id',
                'transactions.invoice_no',
                'transactions.ref_no',
                'transactions.transaction_date',
                'transactions.final_total',
                DB::raw("{$totalPaidExpr} AS total_paid"),
                'transactions.payment_status',
                'transactions.status',
                'bl.name as location_name',
            );

        if ($startDate) {
            $query->whereDate('transactions.transaction_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('transactions.transaction_date', '<=', $endDate);
        }
        if ($status && in_array($status, ['paid', 'due', 'partial', 'overdue'], true)) {
            $query->where('transactions.payment_status', $status);
        }
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('transactions.invoice_no', 'like', "%{$q}%")
                    ->orWhere('transactions.ref_no', 'like', "%{$q}%");
            });
        }

        $paginator = $query->orderByDesc('transactions.transaction_date')
            ->paginate(20, ['*'], 'customer_sales_page', $page);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($tx) => [
                'id' => (int) $tx->id,
                'invoice_no' => (string) $tx->invoice_no,
                'ref_no' => $tx->ref_no,
                'transaction_date' => optional($tx->transaction_date)->toIso8601String(),
                'final_total' => (float) $tx->final_total,
                'total_paid' => (float) $tx->total_paid,
                'total_due' => (float) (((float) $tx->final_total) - ((float) $tx->total_paid)),
                'payment_status' => (string) $tx->payment_status,
                'status' => (string) $tx->status,
                'location_name' => $tx->location_name,
            ])->all(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'links' => collect($paginator->linkCollection() ?? $paginator->toArray()['links'] ?? [])->map(fn ($l) => [
                'url' => $l['url'] ?? null,
                'label' => (string) ($l['label'] ?? ''),
                'active' => (bool) ($l['active'] ?? false),
            ])->all(),
        ]);
    }

    /**
     * GET /cliente/{id}/payments
     *
     * Response: {payments: [{id, paid_on, payment_ref_no, amount, ...}]}
     */
    public function payments(Request $request, int $id): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $contact = $this->resolveContact($businessId, $id);

        $rows = TransactionPayment::leftjoin('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
            ->leftjoin('transaction_payments as parent_payment', 'transaction_payments.parent_id', '=', 'parent_payment.id')
            ->where('transaction_payments.business_id', $businessId)
            ->whereNull('transaction_payments.parent_id')
            ->where('transaction_payments.payment_for', $contact->id)
            ->select(
                'transaction_payments.id',
                'transaction_payments.amount',
                'transaction_payments.is_return',
                'transaction_payments.method',
                'transaction_payments.paid_on',
                'transaction_payments.payment_ref_no',
                'transaction_payments.parent_id',
                't.invoice_no',
                't.ref_no',
                't.type as transaction_type',
                't.id as transaction_id',
                'transaction_payments.cheque_number',
                'transaction_payments.card_transaction_number',
                'transaction_payments.bank_account_number',
                'parent_payment.payment_ref_no as parent_payment_ref_no',
            )
            ->orderByDesc('transaction_payments.paid_on')
            ->limit(200)
            ->get();

        return response()->json([
            'payments' => $rows->map(fn ($p) => [
                'id' => (int) $p->id,
                'paid_on' => optional($p->paid_on)->toIso8601String(),
                'payment_ref_no' => (string) ($p->payment_ref_no ?? ''),
                'parent_payment_ref_no' => $p->parent_payment_ref_no,
                'amount' => (float) $p->amount,
                'is_return' => (int) ($p->is_return ?? 0),
                'method' => (string) ($p->method ?? 'other'),
                'invoice_no' => $p->invoice_no,
                'ref_no' => $p->ref_no,
                'transaction_id' => $p->transaction_id ? (int) $p->transaction_id : null,
                'transaction_type' => $p->transaction_type,
                // PII -- mascarados (ultimos 4 digitos visiveis).
                'cheque_number' => $this->maskTail($p->cheque_number, 4),
                'card_transaction_number' => $this->maskTail($p->card_transaction_number, 4),
                'bank_account_number' => $this->maskTail($p->bank_account_number, 4),
                'parent_id' => $p->parent_id ? (int) $p->parent_id : null,
            ])->all(),
        ]);
    }

    /**
     * GET /cliente/{id}/documents
     *
     * Response: {documents: [...], notes: [...]}
     *
     * DocumentAndNote eh polymorphic notable_type='App\Contact'. media (1:N)
     * traz arquivo. Quando description vazia + media presente = documento.
     */
    public function documents(Request $request, int $id): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $contact = $this->resolveContact($businessId, $id);

        $rows = DocumentAndNote::where('notable_type', 'App\\Contact')
            ->where('notable_id', $contact->id)
            ->with(['media', 'createdBy'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $documents = [];
        $notes = [];

        foreach ($rows as $row) {
            $userName = $row->createdBy
                ? trim(($row->createdBy->surname ?? '') . ' ' . ($row->createdBy->first_name ?? '') . ' ' . ($row->createdBy->last_name ?? ''))
                : null;

            $hasMedia = $row->media && $row->media->isNotEmpty();
            if ($hasMedia) {
                foreach ($row->media as $media) {
                    if ((int) $media->business_id !== $businessId) {
                        continue; // double-safety: nunca emitir media cross-tenant
                    }
                    $documents[] = [
                        'id' => (int) $row->id,
                        'file_name' => (string) ($media->file_name ?? ''),
                        'display_name' => $media->display_name ?? ($media->file_name ?? null),
                        'description' => $row->description,
                        'file_size' => $media->file_size !== null ? (int) $media->file_size : null,
                        'mime_type' => $media->mime_type ?? null,
                        'uploaded_by_name' => $userName,
                        'created_at' => optional($row->created_at)->toIso8601String(),
                        'download_url' => action(
                            [\App\Http\Controllers\DocumentAndNoteController::class, 'downloadMedia'],
                            ['id' => $media->id],
                        ),
                    ];
                }
            } else {
                $notes[] = [
                    'id' => (int) $row->id,
                    'heading' => $row->heading,
                    'description' => (string) ($row->description ?? ''),
                    'created_by_name' => $userName,
                    'created_at' => optional($row->created_at)->toIso8601String(),
                    'updated_at' => optional($row->updated_at)->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'documents' => $documents,
            'notes' => $notes,
        ]);
    }

    /**
     * GET /cliente/{id}/activities
     *
     * Response: {activities: [...]}
     */
    public function activities(Request $request, int $id): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $contact = $this->resolveContact($businessId, $id);

        $activities = Activity::forSubject($contact)
            ->with(['causer'])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'activities' => $activities->map(fn ($a) => [
                'id' => (int) $a->id,
                'created_at' => optional($a->created_at)->toIso8601String(),
                'description' => (string) ($a->description ?? ''),
                'description_label' => (string) __('lang_v1.' . ($a->description ?? '')),
                'causer_name' => $a->causer->user_full_name ?? null,
                'from_api' => $a->getExtraProperty('from_api') ?? null,
                'is_automatic' => (bool) $a->getExtraProperty('is_automatic'),
                'update_note' => is_string($a->getExtraProperty('update_note')) ? $a->getExtraProperty('update_note') : null,
            ])->all(),
        ]);
    }

    /**
     * GET /cliente/{id}/persons
     *
     * Response: {contact_persons: [...]}
     */
    public function persons(Request $request, int $id): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $contact = $this->resolveContact($businessId, $id);

        $users = User::where('business_id', $businessId)
            ->where('crm_contact_id', $contact->id)
            ->orderBy('first_name')
            ->limit(200)
            ->get(['id', 'username', 'email', 'surname', 'first_name', 'last_name', 'crm_department', 'crm_designation']);

        return response()->json([
            'contact_persons' => $users->map(fn ($u) => [
                'id' => (int) $u->id,
                'username' => (string) ($u->username ?? ''),
                'email' => $u->email,
                'full_name' => trim(($u->surname ?? '') . ' ' . ($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                'department' => $u->crm_department,
                'designation' => $u->crm_designation,
            ])->all(),
        ]);
    }

    /**
     * GET /cliente/{id}/subscriptions
     *
     * Response: {subscriptions: [...]}
     */
    public function subscriptions(Request $request, int $id): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $contact = $this->resolveContact($businessId, $id);

        $rows = Transaction::where('transactions.business_id', $businessId)
            ->where('transactions.contact_id', $contact->id)
            ->where('transactions.is_recurring', 1)
            ->whereNull('transactions.recur_parent_id')
            ->leftJoin('business_locations as bl_sub', 'transactions.location_id', '=', 'bl_sub.id')
            ->orderByDesc('transactions.transaction_date')
            ->limit(100)
            ->get([
                'transactions.id',
                'transactions.subscription_no',
                'transactions.transaction_date',
                'transactions.recur_interval',
                'transactions.recur_interval_type',
                'transactions.recur_repetitions',
                'transactions.recur_stopped_on',
                'bl_sub.name as location_name',
            ]);

        return response()->json([
            'subscriptions' => $rows->map(fn ($s) => [
                'id' => (int) $s->id,
                'subscription_no' => (string) ($s->subscription_no ?? ''),
                'transaction_date' => optional($s->transaction_date)->toIso8601String(),
                'recur_interval' => (int) ($s->recur_interval ?? 0),
                'recur_interval_type' => (string) ($s->recur_interval_type ?? ''),
                'recur_repetitions' => (int) ($s->recur_repetitions ?? 0),
                'recur_stopped_on' => optional($s->recur_stopped_on)->toIso8601String(),
                'location_name' => $s->location_name,
                'generated_count' => (int) Transaction::where('business_id', $businessId)
                    ->where('recur_parent_id', $s->id)
                    ->count(),
            ])->all(),
        ]);
    }

    /**
     * GET /cliente/{id}/rewards
     *
     * Response: {enabled, rp_name, summary, history}
     */
    public function rewards(Request $request, int $id): JsonResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $contact = $this->resolveContact($businessId, $id);

        $enabled = $request->session()->get('business.enable_rp') == 1
            && in_array($contact->type, ['customer', 'both'], true);

        if (! $enabled) {
            return response()->json([
                'enabled' => false,
                'rp_name' => '',
                'summary' => null,
                'history' => [],
            ]);
        }

        $history = Transaction::where('transactions.business_id', $businessId)
            ->where('transactions.contact_id', $contact->id)
            ->where(function ($q) {
                $q->where('transactions.rp_earned', '>', 0)
                  ->orWhere('transactions.rp_redeemed', '>', 0);
            })
            ->orderByDesc('transactions.transaction_date')
            ->limit(100)
            ->get(['id', 'invoice_no', 'transaction_date', 'final_total', 'rp_earned', 'rp_redeemed', 'rp_redeemed_amount']);

        return response()->json([
            'enabled' => true,
            'rp_name' => (string) ($request->session()->get('business.rp_name') ?? 'Pontos'),
            'summary' => [
                'total_earned' => (int) ($contact->total_rp ?? 0),
                'total_used' => (int) ($contact->total_rp_used ?? 0),
                'total_expired' => (int) ($contact->total_rp_expired ?? 0),
                'balance' => (int) (((int) ($contact->total_rp ?? 0)) - ((int) ($contact->total_rp_used ?? 0)) - ((int) ($contact->total_rp_expired ?? 0))),
            ],
            'history' => $history->map(fn ($tx) => [
                'id' => (int) $tx->id,
                'invoice_no' => (string) ($tx->invoice_no ?? ''),
                'transaction_date' => optional($tx->transaction_date)->toIso8601String(),
                'final_total' => (float) $tx->final_total,
                'rp_earned' => (int) ($tx->rp_earned ?? 0),
                'rp_redeemed' => (int) ($tx->rp_redeemed ?? 0),
                'rp_redeemed_amount' => (float) ($tx->rp_redeemed_amount ?? 0),
            ])->all(),
        ]);
    }

    /**
     * Resolver canon multi-tenant Tier 0 (ADR 0093):
     *   firstOrFail() retorna 404 automatico se contact NAO existe NO biz
     *   atual -- nunca vaza existencia cross-tenant. Padrao copiado de
     *   Modules/Crm/Http/Controllers/ClienteAutosaveController.
     */
    private function resolveContact(int $businessId, int $contactId): Contact
    {
        return Contact::where('business_id', $businessId)
            ->where('id', $contactId)
            ->firstOrFail();
    }

    /**
     * Mascara PII -- mantem apenas os $tail ultimos digitos visiveis.
     * Ex: maskTail('1234567890', 4) = '****7890'.
     * NUNCA emitir bank_account/cheque/card plain pro frontend.
     */
    private function maskTail(?string $value, int $tail = 4): ?string
    {
        if (empty($value)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) <= $tail) {
            return str_repeat('*', max(0, strlen($digits)));
        }
        return str_repeat('*', strlen($digits) - $tail) . substr($digits, -$tail);
    }
}
