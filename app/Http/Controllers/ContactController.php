<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\Notifications\CustomerNotification;
use App\PurchaseLine;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\NotificationUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use DB;
use Excel;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;
use App\Events\ContactCreatedOrModified;
use App\Http\Requests\Cliente\StoreContactRequest;
use App\Http\Requests\Cliente\UpdateContactRequest;
use Inertia\Inertia;

class ContactController extends Controller
{
    /**
     * W1-B3 — Helper MWART pra checar feature flag `cliente_*` por business.
     * Vazia em business_ids = todos os businesses; lista = só os listados.
     * Ver `config/mwart.php` + ADR 0104.
     */
    private function shouldRenderInertiaCliente(string $flag, int $business_id): bool
    {
        if (! config("mwart.{$flag}.enabled")) {
            return false;
        }
        $allowedBizIds = config("mwart.{$flag}.business_ids", []);
        if (empty($allowedBizIds)) {
            return true;
        }
        return in_array($business_id, $allowedBizIds, true);
    }

    /**
     * Detecta XHR LEGACY (DataTable Yajra, jQuery AJAX, autocomplete) — exclui
     * Inertia partial reload que ALSO seta X-Requested-With: XMLHttpRequest.
     *
     * Bug pré-existente 2026-05-21: ativar MWART_CLIENTE_INDEX=true expôs collision —
     * Inertia partial reload pra carregar Inertia::defer caía nos branches
     * `if (request()->ajax())` retornando DataTable JSON cru → Inertia client erra
     * "All Inertia requests must receive a valid Inertia response".
     *
     * Fix: usar este helper em vez de `request()->ajax()` direto em TODOS os
     * branches que retornam JSON (DataTable/autocomplete/contact lookup). Inertia
     * envia header `X-Inertia: true` que diferencia das XHR legacy.
     *
     * Ver: PR #1299 fix/contact-controller-inertia-ajax-collision (2026-05-21).
     */
    private function isLegacyAjax(): bool
    {
        return request()->ajax() && ! request()->hasHeader('X-Inertia');
    }

    /**
     * Wave C US-CRM-065 — Paginador de vendas do contato pra SalesTab React.
     * Multi-tenant Tier 0 (ADR 0093): business_id scope obrigatório em TODO query.
     * Espelha shape esperado pelo `SalesPaginator` em resources/js/Pages/Cliente/_show/SalesTab.tsx.
     */
    private function buildClienteSalesPaginator(int $contactId, int $businessId, Request $req): array
    {
        $startDate = $req->query('customer_sales_start');
        $endDate = $req->query('customer_sales_end');
        $status = $req->query('customer_sales_status');
        $q = trim((string) $req->query('customer_sales_q', ''));
        $page = max(1, (int) $req->query('customer_sales_page', 1));

        // FIX 2026-05-21: `transactions.total_paid` NÃO existe no schema UltimatePOS.
        // Pagamentos vivem em `transaction_payments.amount` (1:N). Subquery scalar inline.
        $totalPaidExpr = '(SELECT COALESCE(SUM(amount), 0) FROM transaction_payments WHERE transaction_payments.transaction_id = transactions.id)';
        $query = Transaction::where('transactions.business_id', $businessId)
            ->where('contact_id', $contactId)
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

        return [
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
        ];
    }

    /**
     * Fix 2026-06-08 — Endpoint JSON das vendas do contato pro SalesTab self-fetch.
     *
     * Bug: no paradigma drawer 760px (ADR 0179, MWART_CLIENTE_INDEX) o SalesTab
     * dentro de OssTab recebia `sales=undefined` e NÃO tinha carga inicial — só
     * buscava ao filtrar/paginar. Resultado: skeleton infinito, "as vendas não
     * aparecem no cadastro de cliente". No Show.tsx full-page funcionava porque o
     * `<Deferred data="sales">` dispara o Inertia::defer; o drawer não tem esse
     * wrapper. Este endpoint dá ao SalesTab uma fonte AJAX (espelha o padrão
     * self-fetch de PaymentsTab `/contacts/payments/{id}` e anexos).
     *
     * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): contato resolvido DENTRO do
     * business (404 cross-tenant) + buildClienteSalesPaginator já força
     * business_id em todo query.
     *
     * GET /cliente/{id}/sales-json  (?customer_sales_start/_end/_status/_q/_page)
     */
    public function salesJson($id)
    {
        if (! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own')
            && ! auth()->user()->can('supplier.view') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // 404 cross-tenant: o contato precisa existir DENTRO do business.
        \App\Contact::where('business_id', $business_id)->findOrFail($id);

        return response()->json(
            $this->buildClienteSalesPaginator((int) $id, (int) $business_id, request())
        );
    }

    /**
     * Fix 2026-06-08 — Endpoint JSON dos pagamentos do contato pro PaymentsTab self-fetch.
     *
     * O legado GET /contacts/payments/{id} devolve Blade HTML (modal DataTable),
     * deixando a aba "Pagamentos" do drawer presa em "Aguardando wiring". Este
     * entrega JSON limpo no shape do `PaymentRow` (PaymentsTab.tsx).
     *
     * Multi-tenant Tier 0 (ADR 0093): contato resolvido DENTRO do business +
     * scope business_id em transaction_payments. PII §LGPD: bank_account_number
     * mascarado (nunca em claro).
     *
     * GET /cliente/{id}/payments-json
     */
    public function paymentsJson($id)
    {
        if (! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own')
            && ! auth()->user()->can('supplier.view') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        \App\Contact::where('business_id', $business_id)->findOrFail($id);

        $payments = TransactionPayment::leftJoin('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
            ->leftJoin('transaction_payments as parent_payment', 'transaction_payments.parent_id', '=', 'parent_payment.id')
            ->where('transaction_payments.business_id', $business_id)
            ->whereNull('transaction_payments.parent_id')
            ->where('transaction_payments.payment_for', $id)
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
            ->groupBy('transaction_payments.id')
            ->orderByDesc('transaction_payments.paid_on')
            ->get()
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'paid_on' => $p->paid_on ? \Illuminate\Support\Carbon::parse($p->paid_on)->toIso8601String() : null,
                'payment_ref_no' => (string) ($p->payment_ref_no ?? ''),
                'parent_payment_ref_no' => $p->parent_payment_ref_no,
                'amount' => (float) $p->amount,
                'is_return' => (int) $p->is_return,
                'method' => (string) ($p->method ?? ''),
                'invoice_no' => $p->invoice_no,
                'ref_no' => $p->ref_no,
                'transaction_id' => $p->transaction_id !== null ? (int) $p->transaction_id : null,
                'transaction_type' => $p->transaction_type,
                'cheque_number' => $p->cheque_number,
                'card_transaction_number' => $p->card_transaction_number,
                // PII (ADR 0093 §LGPD): nunca devolver número de conta em claro.
                'bank_account_number' => $p->bank_account_number
                    ? '****' . substr((string) $p->bank_account_number, -4)
                    : null,
                'parent_id' => $p->parent_id !== null ? (int) $p->parent_id : null,
            ])
            ->all();

        return response()->json(['payments' => $payments]);
    }

    /**
     * Fix 2026-06-08 — Endpoint JSON dos pontos (reward points) pro RewardPointsTab
     * self-fetch. Espelha o defer 'reward_points' do show() (mesmo shape/limite).
     * Tier 0: contato DENTRO do business + scope business_id no histórico.
     *
     * GET /cliente/{id}/rewards-json
     */
    public function rewardsJson($id)
    {
        if (! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own')
            && ! auth()->user()->can('supplier.view') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        \App\Contact::where('business_id', $business_id)->findOrFail($id);
        $contact = $this->contactUtil->getContactInfo($business_id, $id);

        $enabled = request()->session()->get('business.enable_rp') == 1
            && in_array($contact->type, ['customer', 'both'], true);

        if (! $enabled) {
            return response()->json(['enabled' => false, 'rp_name' => '', 'summary' => null, 'history' => []]);
        }

        return response()->json([
            'enabled' => true,
            'rp_name' => (string) (request()->session()->get('business.rp_name') ?? 'Pontos'),
            'summary' => [
                'total_earned' => (int) ($contact->total_rp ?? 0),
                'total_used' => (int) ($contact->total_rp_used ?? 0),
                'total_expired' => (int) ($contact->total_rp_expired ?? 0),
                'balance' => (int) (((int) ($contact->total_rp ?? 0)) - ((int) ($contact->total_rp_used ?? 0)) - ((int) ($contact->total_rp_expired ?? 0))),
            ],
            'history' => Transaction::where('transactions.business_id', $business_id)
                ->where('transactions.contact_id', $id)
                ->where(function ($q) {
                    $q->where('transactions.rp_earned', '>', 0)
                      ->orWhere('transactions.rp_redeemed', '>', 0);
                })
                ->orderByDesc('transactions.transaction_date')
                ->limit(100)
                ->get(['id', 'invoice_no', 'transaction_date', 'final_total', 'rp_earned', 'rp_redeemed', 'rp_redeemed_amount'])
                ->map(fn ($tx) => [
                    'id' => (int) $tx->id,
                    'invoice_no' => (string) ($tx->invoice_no ?? ''),
                    'transaction_date' => optional($tx->transaction_date)->toIso8601String(),
                    'final_total' => (float) $tx->final_total,
                    'rp_earned' => (int) ($tx->rp_earned ?? 0),
                    'rp_redeemed' => (int) ($tx->rp_redeemed ?? 0),
                    'rp_redeemed_amount' => (float) ($tx->rp_redeemed_amount ?? 0),
                ])
                ->all(),
        ]);
    }

    /**
     * Fix 2026-06-08 — Endpoint JSON das assinaturas pro SubscriptionsTab self-fetch.
     * Espelha o defer 'subscriptions' do show() (recur is_recurring=1, pai da série).
     * Tier 0: contato DENTRO do business + scope business_id.
     *
     * GET /cliente/{id}/subscriptions-json
     */
    public function subscriptionsJson($id)
    {
        if (! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own')
            && ! auth()->user()->can('supplier.view') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        \App\Contact::where('business_id', $business_id)->findOrFail($id);

        return response()->json(
            Transaction::where('transactions.business_id', $business_id)
                ->where('transactions.contact_id', $id)
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
                ])
                ->map(fn ($s) => [
                    'id' => (int) $s->id,
                    'subscription_no' => (string) ($s->subscription_no ?? ''),
                    'transaction_date' => optional($s->transaction_date)->toIso8601String(),
                    'recur_interval' => (int) ($s->recur_interval ?? 0),
                    'recur_interval_type' => (string) ($s->recur_interval_type ?? ''),
                    'recur_repetitions' => (int) ($s->recur_repetitions ?? 0),
                    'recur_stopped_on' => optional($s->recur_stopped_on)->toIso8601String(),
                    'location_name' => $s->location_name,
                    'generated_count' => (int) Transaction::where('business_id', $business_id)
                        ->where('recur_parent_id', $s->id)
                        ->count(),
                ])
                ->all()
        );
    }

    /**
     * Wave Onda 1 PR D 2026-05-26 — Paginador de veículos do contato (frota Martinho).
     *
     * Multi-tenant Tier 0 (ADR 0093): business_id scope obrigatório.
     * Reutiliza schema `vehicles` do Modules/OficinaAuto (ADR 0137) — coluna
     * `contact_id` já liga veículo ao cliente. Sem migration nova.
     *
     * Caller (show()) é responsável por gate `oficinaauto_enabled` —
     * este helper NÃO checa o módulo (pra facilitar reuso futuro em outros contextos).
     *
     * Query string: `vehicles_q` (LIKE placa/secondary_plate/chassis), `vehicles_page`.
     */
    private function buildClienteVehiclesPaginator(int $contactId, int $businessId, Request $req): array
    {
        $q = trim((string) $req->query('vehicles_q', ''));
        $page = max(1, (int) $req->query('vehicles_page', 1));

        $query = \Modules\OficinaAuto\Entities\Vehicle::where('business_id', $businessId)
            ->where('contact_id', $contactId);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('plate', 'like', "%{$q}%")
                    ->orWhere('secondary_plate', 'like', "%{$q}%")
                    ->orWhere('chassis', 'like', "%{$q}%");
            });
        }

        $paginator = $query->orderByDesc('id')->paginate(20, ['*'], 'vehicles_page', $page);

        return [
            'data' => $paginator->getCollection()->map(fn ($v) => [
                'id' => (int) $v->id,
                'plate' => (string) $v->plate,
                'secondary_plate' => $v->secondary_plate,
                'chassis' => $v->chassis,
                'manufacture_year' => $v->manufacture_year,
                'model_year' => $v->model_year,
                'renavam' => $v->renavam,
                'vehicle_type' => (string) ($v->vehicle_type ?? ''),
                'current_status' => (string) ($v->current_status ?? ''),
                'color' => $v->color,
                'fuel_type' => $v->fuel_type,
                'mileage_at_entry' => $v->mileage_at_entry,
                'notes' => $v->notes,
            ])->all(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * W1-B3 — Mascara CNPJ/CPF pra display client-side.
     * NUNCA enviar plain digits — ADR 0093 PII §LGPD Art.7.
     */
    private function maskTaxNumber(?string $taxNumber): ?string
    {
        if (empty($taxNumber)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $taxNumber);
        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        }
        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits);
        }
        return $taxNumber;
    }

    protected $commonUtil;

    protected $contactUtil;

    protected $transactionUtil;

    protected $moduleUtil;

    protected $notificationUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(
        Util $commonUtil,
        ModuleUtil $moduleUtil,
        TransactionUtil $transactionUtil,
        NotificationUtil $notificationUtil,
        ContactUtil $contactUtil
    ) {
        $this->commonUtil = $commonUtil;
        $this->contactUtil = $contactUtil;
        $this->moduleUtil = $moduleUtil;
        $this->transactionUtil = $transactionUtil;
        $this->notificationUtil = $notificationUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        $type = request()->get('type');

        // ADR 0188 (multi-type 2026-05-24) + ADR 0246 (Outros 2026-06-03) + Wagner 2026-05-25:
        // whitelist canon alinhada com $inertiaTypes abaixo. Antes era ['supplier', 'customer']
        // UPOS legacy → /contacts?type=all/employee/representative caía em redirect()->back()
        // (bug: usuário em /sells clicava em "Contatos" sidebar → voltava pra /sells).
        // ADR 0246: adicionado 'other' — sem isso, ?type=other rejeitado e cai default 'customer'
        // (bug Wagner reportou 2026-06-04 pós-deploy PR #2205: aba Outros mostrava Clientes).
        $types = ['supplier', 'customer', 'employee', 'representative', 'other', 'all'];

        if (empty($type) || ! in_array($type, $types)) {
            return redirect()->back();
        }

        if ($this->isLegacyAjax()) {
            if ($type == 'supplier') {
                return $this->indexSupplier();
            } elseif ($type == 'customer') {
                return $this->indexCustomer();
            } else {
                // ADR 0188 — papéis novos (employee/representative/all) caem no branch
                // Inertia abaixo. AJAX legacy não suporta esses tipos.
                exit('Not Found');
            }
        }

        $reward_enabled = (request()->session()->get('business.enable_rp') == 1 && in_array($type, ['customer'])) ? true : false;

        $users = User::forDropdown($business_id);

        $customer_groups = [];
        if ($type == 'customer') {
            $customer_groups = CustomerGroup::forDropdown($business_id);
        }

        // W1-B3 MWART branch — Inertia render quando flag cliente_index liga.
        // ADR 0188 — Slot 2 PT-01 multi-type: aceita 4 papéis + 'all'. Permissões
        // Spatie permanecem mapeadas pra 'customer.*' (UPOS legacy) por simplicidade
        // operacional — Wagner expande pra 'supplier.*' etc em ondas futuras se time
        // pedir granularidade por papel.
        // ADR 0188 + ADR 0246 — 5 papéis canônicos + 'all' agregado.
        $inertiaTypes = ['customer', 'supplier', 'employee', 'representative', 'other', 'all'];
        if (in_array($type, $inertiaTypes, true) && $this->shouldRenderInertiaCliente('cliente_index', (int) $business_id)) {
            return Inertia::render('Cliente/Index', [
                'activeType' => $type,
                'kpis' => Inertia::defer(fn () => $this->buildClienteIndexKpis((int) $business_id, $type)),
                // ADR 0189 v3.1 + LEARNINGS AP18 (2026-05-25): counters por papel canon
                // pro PageHeader Zona C subnav. Backend devolve count(*) por tipo;
                // frontend NUNCA recalcula via rows.filter (rows traz só tipo ativo,
                // server-side filtered → outros tipos retornariam 0 — bug visível).
                'tab_counts' => Inertia::defer(fn () => $this->buildClienteIndexTabCounts((int) $business_id)),
                'customers' => Inertia::defer(fn () => $this->buildClienteIndexCustomers((int) $business_id, $type)),
                'permissions' => [
                    'create' => auth()->user()->can('customer.create'),
                    'view' => auth()->user()->can('customer.view') || auth()->user()->can('customer.view_own'),
                    'import' => auth()->user()->can('customer.create'),
                    // Exclusão (soft delete) — destroy() já valida o mesmo can()
                    // + bloqueia se houver transação + escopo business_id (Tier 0).
                    // OR supplier.delete cobre a aba Fornecedores. Wagner 2026-06-08.
                    'delete' => auth()->user()->can('customer.delete') || auth()->user()->can('supplier.delete'),
                ],
                // Tabela de preço REAL pro drawer Comercial (ADR 0093 scope).
                // [{id,name}] das customer_groups do business — substitui o
                // dropdown fake hardcoded (padrao/varejo/atacado/parceiro).
                // biz=164 Martinho: MECÂNICA / FABRICANTE / FINAL.
                'priceGroups' => CustomerGroup::where('business_id', $business_id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($g) => ['id' => (int) $g->id, 'name' => (string) $g->name])
                    ->all(),
                // Wagner 2026-05-27 — gate sub-tab "Placas" do OssTab drawer.
                // Daniela @ Martinho: ve placas no drawer apenas se OficinaAuto
                // ativo. biz=4 Larissa vestuario nao ve (graceful default false).
                'oficinaauto_enabled' => (bool) $this->moduleUtil->isModuleInstalled('OficinaAuto'),
            ]);
        }

        return view('contact.index')
            ->with(compact('type', 'reward_enabled', 'customer_groups', 'users'));
    }

    /**
     * ADR 0188 — Aplica filtro por papel canônico em Builder · prefere flags `is_X`
     * aditivas (migration 2026_05_24_200000) com fallback `type` enum UPOS legacy
     * pra ambientes pré-migration ou se a coluna for dropada por rollback.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $q
     * @return mixed Builder com filter aplicado (encadeável).
     */
    private function applyContactTypeFilter($q, string $type)
    {
        $flagColumn = [
            'customer' => 'is_customer',
            'supplier' => 'is_supplier',
            'employee' => 'is_employee',
            'representative' => 'is_representative',
            // ADR 0246 (2026-06-03) — categoria "Outros" canônica
            'other' => 'is_other',
        ];

        if ($type === 'all') {
            return $q; // Sem filtro · todos papéis
        }

        $flag = $flagColumn[$type] ?? null;
        if ($flag === null) {
            // Fallback defensivo · tipo inválido cai pra customer (já validado em /cliente route).
            return $q->where('contacts.type', 'customer');
        }

        // Prefere flag se a coluna existir (post-migration).
        if (\Illuminate\Support\Facades\Schema::hasColumn('contacts', $flag)) {
            return $q->where("contacts.{$flag}", 1);
        }

        // Fallback legacy: type enum UPOS. Mapeia 'customer' ↔ 'both' (UPOS legacy convention).
        if ($type === 'customer') {
            return $q->whereIn('contacts.type', ['customer', 'both']);
        }

        return $q->where('contacts.type', $type);
    }

    /**
     * W1-B3 — Constrói KPIs da listagem de clientes pra Inertia/React.
     * Multi-tenant: scoped por `business_id` ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
     */
    private function buildClienteIndexKpis(int $business_id, string $type = 'customer'): array
    {
        $base = Contact::where('contacts.business_id', $business_id);
        // ADR 0188 — filtra via flag aditiva `is_X` se a coluna existir (migration
        // rodou). Fallback `type` enum legacy UPOS pra ambientes pré-migration.
        $base = $this->applyContactTypeFilter($base, $type);

        $total = (clone $base)->count();
        $com_os_aberta = (clone $base)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('transactions')
                  ->whereColumn('transactions.contact_id', 'contacts.id')
                  ->where('transactions.type', 'sell')
                  ->whereIn('transactions.payment_status', ['due', 'partial']);
            })
            ->count();
        $com_atraso = (clone $base)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('transactions')
                  ->whereColumn('transactions.contact_id', 'contacts.id')
                  ->where('transactions.type', 'sell')
                  ->whereIn('transactions.payment_status', ['due', 'partial'])
                  ->whereNotNull('transactions.due_date')
                  ->where('transactions.due_date', '<', now());
            })
            ->count();
        // FIX 2026-05-21: `transactions.total_paid` NÃO existe no schema UltimatePOS.
        // Pagamentos estão em `transaction_payments.amount` (1:N). Subquery scalar.
        $totalPaidSub = '(SELECT COALESCE(SUM(amount), 0) FROM transaction_payments WHERE transaction_payments.transaction_id = transactions.id)';
        $valor_total_aberto = (float) Transaction::where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->whereIn('transactions.payment_status', ['due', 'partial'])
            ->sum(DB::raw("final_total - {$totalPaidSub}"));

        // Onda 3 (2026-06-12) — counts reais server-side dos 3 KPIs que vinham
        // estimados client-side sobre a página (50 rows): VIPs · Sem compra 90d · Novos.
        // Fecha o "número sem prova" do placar (charter Goal "Onda 3 plug backend").
        $vips = \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'vip')
            ? (clone $base)->where('contacts.vip', 1)->count()
            : 0;

        $novos_mes = (clone $base)
            ->where('contacts.created_at', '>=', now()->startOfMonth())
            ->count();

        // "Sem compra 90d" (risco churn) = JÁ comprou (venda não-draft) mas NADA nos
        // últimos 90d. Alinha com a FrescorPill (last_purchase_at = MAX(transaction_date
        // WHERE status != 'draft')) — não conta nunca-comprou (esses são "sem histórico",
        // não churn). Subquery scoped por business_id (Tier 0 explícito · ADR 0093).
        $sem_compra_90d = (clone $base)
            ->whereExists(function ($q) use ($business_id) {
                $q->select(DB::raw(1))->from('transactions')
                  ->whereColumn('transactions.contact_id', 'contacts.id')
                  ->where('transactions.business_id', $business_id)
                  ->where('transactions.type', 'sell')
                  ->where('transactions.status', '!=', 'draft');
            })
            ->whereNotExists(function ($q) use ($business_id) {
                $q->select(DB::raw(1))->from('transactions')
                  ->whereColumn('transactions.contact_id', 'contacts.id')
                  ->where('transactions.business_id', $business_id)
                  ->where('transactions.type', 'sell')
                  ->where('transactions.status', '!=', 'draft')
                  ->where('transactions.transaction_date', '>=', now()->subDays(90));
            })
            ->count();

        return [
            'total' => (int) $total,
            'com_os_aberta' => (int) $com_os_aberta,
            'com_atraso' => (int) $com_atraso,
            'valor_total_aberto' => $valor_total_aberto,
            'vips' => (int) $vips,
            'sem_compra_90d' => (int) $sem_compra_90d,
            'novos_mes' => (int) $novos_mes,
        ];
    }

    /**
     * ADR 0189 PageHeader canon v3.1 + LEARNINGS AP18 (2026-05-25):
     * counters por papel pro Zona C subnav. Backend devolve 5 counts canônicos
     * (all + 4 papeis ADR 0188), frontend NUNCA recalcula via rows.filter
     * — rows traz só tipo ativo (server-side filtered) → outros retornariam 0.
     *
     * Multi-tenant Tier 0: scoped por `business_id` ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
     * Perf: 5 queries COUNT — OK com índice `(business_id, is_X)` ou `(business_id, type)`.
     *
     * @return array{all:int,customer:int,supplier:int,employee:int,representative:int}
     */
    private function buildClienteIndexTabCounts(int $business_id): array
    {
        $base = Contact::where('contacts.business_id', $business_id);

        $counts = [
            'all' => (int) (clone $base)->count(),
        ];

        // ADR 0246 — inclui 'other' (5º papel) nos counters da subnav.
        foreach (['customer', 'supplier', 'employee', 'representative', 'other'] as $tipo) {
            $q = clone $base;
            $counts[$tipo] = (int) $this->applyContactTypeFilter($q, $tipo)->count();
        }

        return $counts;
    }

    /**
     * W1-B3 — Constrói lista paginada de clientes pra Inertia/React.
     * Multi-tenant scope obrigatório ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
     */
    private function buildClienteIndexCustomers(int $business_id, string $type = 'customer'): array
    {
        $perPage = (int) request()->input('per_page', 50);
        $perPage = max(10, min($perPage, 100));

        // Wave G (ADR 0179) — payload expandido com campos cadastrais p/ tabela turbinada.
        // SELECT defensivo: usa hasColumn pra cada campo Wave B (migration aditiva
        // idempotente — em ambientes onde migration ainda não rodou, evita SQL error).
        // Campos canon UPOS (sempre presentes): id, name, tax_number, contact_id, mobile,
        // city, state, address_line_1, balance. Campos Wave B adicionados via migration
        // 2026_05_22_000000: tipo, fantasia, tags, segmento, vip, favorito_users, etc.
        $hasWaveBCols = \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'tipo');
        $selectCols = [
            'contacts.id',
            'contacts.name',
            'contacts.tax_number',
            'contacts.contact_id',
            'contacts.mobile',
            'contacts.landline',
            'contacts.alternate_number',
            'contacts.email',
            'contacts.city',
            'contacts.state',
            'contacts.balance',
            'contacts.credit_limit',
            'contacts.pay_term_number',
            // Tabela de preço REAL (FK customer_groups) — canon UPOS, sempre presente.
            // Drawer ComercialTab lê pra popular o dropdown de tabela de preço.
            'contacts.customer_group_id',
            'contacts.contact_status',
            // is_default = consumidor final / fornecedor padrão (walk-in). Canon
            // UPOS sempre presente. Front esconde "Excluir" pra ele (destroy()
            // protege server-side, mas evita o no-op confuso). Wagner 2026-06-08.
            'contacts.is_default',
            'contacts.created_at',
            // Endereço completo — sem isso, router.reload({only:['rows']}) pós lookup
            // CNPJ/CEP zera campos no EnderecoTab (undefined → setState('')) e a UI
            // some apesar do DB ter os dados. Wagner 2026-05-27.
            'contacts.zip_code',
            'contacts.address_line_1',
            'contacts.address_line_2',
            // Endereço de entrega (shipping_address) — coluna UPOS desde 2020.
            // Sem isso, o drawer EnderecoTab reabre com o campo vazio mesmo salvo
            // (mesma classe de bug do zip_code/address_line acima). Wagner 2026-06-02.
            'contacts.shipping_address',
        ];
        // `neighborhood` e `numero` (BR canon) — migrations aditivas recentes
        // (2026_05_22_120000 e 2026_05_22_180000). hasColumn graceful pra
        // ambientes pré-migration.
        if (\Illuminate\Support\Facades\Schema::hasColumn('contacts', 'neighborhood')) {
            $selectCols[] = 'contacts.neighborhood';
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('contacts', 'numero')) {
            $selectCols[] = 'contacts.numero';
        }
        // Wave 2026-05-21 (canon BR restaurado pós-regressão UPOS 6.7 — ADR 0178).
        // Sem isso, drawer IdentificacaoTab (Index Cliente) abre com CNPJ/IE/RG
        // vazios mesmo com dado no banco — payload rows não enviava as chaves
        // `cpf_cnpj_masked`/`ie`/`rg` que o drawer espera (Wagner 2026-05-27).
        $hasCanonBrCols = \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'cpf_cnpj');
        if ($hasCanonBrCols) {
            $selectCols[] = 'contacts.cpf_cnpj';
            $selectCols[] = 'contacts.inscricao_estadual';
            $selectCols[] = 'contacts.rg';
        }
        // Wave drawer 2026-05-22 — campos cadastrais drawer (nascimento + cargo +
        // tel2 + site_url + canal_preferido + tabela_preco_padrao + pgto_padrao +
        // obs_comercial). Sem isso, ContatoTab/ComercialTab/IdentificacaoTab abrem
        // com placeholders. Wagner 2026-05-27 reportou "drawer não traz dados".
        $hasDrawerCols = \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'cargo');
        if ($hasDrawerCols) {
            $selectCols[] = 'contacts.nascimento';
            $selectCols[] = 'contacts.cargo';
            // `ie` (Wave drawer 2026-05-22) — alias Cowork de `inscricao_estadual`
            // (Wave canon BR 2026-05-21). DUAS colunas coexistem (intencional —
            // Wave C decide canon). ClienteAutosaveController PATCH grava SÓ em
            // `ie`, então é a fonte de verdade pro drawer. Fallback `inscricao_estadual`
            // cobre cadastros pre-drawer Wave (Wave 2026-05-21).
            $selectCols[] = 'contacts.ie';
            $selectCols[] = 'contacts.tel2';
            $selectCols[] = 'contacts.site_url';
            $selectCols[] = 'contacts.canal_preferido';
            $selectCols[] = 'contacts.tabela_preco_padrao';
            $selectCols[] = 'contacts.pgto_padrao';
            $selectCols[] = 'contacts.obs_comercial';
        }
        // Wave emails extras 2026-05-26 (Onda 1 PR B' Daniela) — emails
        // diferenciados (comercial / NF-e). ContatoTab espera.
        $hasEmailsExtras = \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'email_billing');
        if ($hasEmailsExtras) {
            $selectCols[] = 'contacts.email_billing';
            $selectCols[] = 'contacts.email_nfe';
        }
        // Wave SEFAZ 2026-05-23 — campos derivados ConsultaCadastro (badge alertas
        // IdentificacaoTab). Graceful pra ambiente pré-Wave.
        $hasSefazCols = \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'sefaz_cad_sit');
        if ($hasSefazCols) {
            $selectCols[] = 'contacts.ind_ie_dest';
            $selectCols[] = 'contacts.sefaz_cad_sit';
            $selectCols[] = 'contacts.sefaz_cad_ind_cred_nfe';
            $selectCols[] = 'contacts.sefaz_cad_consultado_em';
        }
        // ADR 0195 Bucket A — `bloqueado` (bool) flag separada de contact_status.
        // Migration 2026_05_27_120000_extend_contacts_bucket_a_legacy_absorption.
        $hasBucketACols = \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'bloqueado');
        if ($hasBucketACols) {
            $selectCols[] = 'contacts.bloqueado';
        }
        // Daniela 2026-05-27 — `contato` (nome do responsavel principal PJ).
        // Migration 2026_05_27_180000_add_contato_to_contacts.
        $hasContatoCol = \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'contato');
        if ($hasContatoCol) {
            $selectCols[] = 'contacts.contato';
        }
        // `mensagem_venda` (TEXT) — migrado de PESSOAS.MENSAGEM_PARA_VENDA Delphi.
        // Exibido como alerta ao vendedor no POS + editavel no drawer Comercial.
        // Migration 2026_05_29_120000_add_mensagem_venda_to_contacts.
        $hasMensagemVendaCol = \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'mensagem_venda');
        if ($hasMensagemVendaCol) {
            $selectCols[] = 'contacts.mensagem_venda';
        }
        if ($hasWaveBCols) {
            $selectCols = array_merge($selectCols, [
                'contacts.tipo',
                'contacts.fantasia',
                'contacts.tags',
                'contacts.segmento',
                'contacts.vip',
            ]);
        }

        // ADR 0188 Onda 4 + ADR 0246 — incluir flags multi-papel se a migration rodou.
        // Graceful degradation: hasColumn check evita erro SQL em ambiente
        // pre-migration. Front recebe null/false → Drawer trata como unchecked.
        $hasOnda4Cols = \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'is_customer');
        if ($hasOnda4Cols) {
            $selectCols = array_merge($selectCols, [
                'contacts.is_customer',
                'contacts.is_supplier',
                'contacts.is_employee',
                'contacts.is_representative',
            ]);
            // ADR 0246 — flag is_other adicionada em migration separada (2026_06_03).
            if (\Illuminate\Support\Facades\Schema::hasColumn('contacts', 'is_other')) {
                $selectCols[] = 'contacts.is_other';
            }
        }

        // ADR 0188 — filtra por papel (`is_X`) se a coluna existir, fallback `type` enum.
        $contactsQuery = Contact::where('contacts.business_id', $business_id);
        $contactsQuery = $this->applyContactTypeFilter($contactsQuery, $type);

        // Fix 2026-05-26 — search server-side. Antes o frontend filtrava `rows`
        // em memória sobre a página paginada (default 50) — busca por nome só
        // encontrava clientes da página atual. Agora `q` bate no banco via LIKE
        // em colunas indexáveis (name/tax_number/mobile/fantasia). Multi-tenant
        // Tier 0 OK — scope `business_id` já aplicado acima ([ADR 0093]).
        $q = trim((string) request()->input('q', ''));
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $hasFantasia = $hasWaveBCols; // fantasia veio na Wave B junto com tipo.
            $contactsQuery->where(function ($w) use ($like, $hasFantasia) {
                $w->where('contacts.name', 'like', $like)
                    ->orWhere('contacts.tax_number', 'like', $like)
                    ->orWhere('contacts.mobile', 'like', $like)
                    ->orWhere('contacts.contact_id', 'like', $like);
                if ($hasFantasia) {
                    $w->orWhere('contacts.fantasia', 'like', $like);
                }
            });
        }

        // Sort server-side (ligado 2026-06-12 · bug Wagner "alfabético → lixo de
        // símbolo primeiro"). Default job-aligned = RECENTES (id desc) em vez de
        // alfabético (que jogava ".COM"/"@"/"&"/"+" no topo). Whitelist anti-injeção;
        // colunas agregadas (count/sum/max de transactions) via leftJoinSub 1:1.
        $sortInput = (string) request()->input('sort', 'recent');
        $hasDir = request()->filled('dir');
        $dir = strtolower((string) request()->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $AGG = ['total_os', 'valor_aberto', 'last_os_at'];
        $DIRECT = ['recent' => 'contacts.id', 'name' => 'contacts.name'];

        if (in_array($sortInput, $AGG, true)) {
            $totalPaidSub = '(SELECT COALESCE(SUM(tp.amount), 0) FROM transaction_payments tp WHERE tp.transaction_id = transactions.id)';
            $aggSub = Transaction::query()
                ->where('business_id', $business_id)
                ->where('type', 'sell')
                ->groupBy('contact_id')
                ->select(
                    'contact_id',
                    DB::raw('COUNT(*) AS total_os'),
                    DB::raw('MAX(transaction_date) AS last_os_at'),
                    DB::raw("SUM(CASE WHEN payment_status IN ('due','partial') THEN (final_total - {$totalPaidSub}) ELSE 0 END) AS valor_aberto"),
                );
            // $sortInput é whitelisted (in_array $AGG) → seguro no orderByRaw.
            $contactsQuery->leftJoinSub($aggSub, 'cli_agg', 'cli_agg.contact_id', '=', 'contacts.id')
                ->orderByRaw("cli_agg.{$sortInput} IS NULL")   // sem histórico por último
                ->orderBy("cli_agg.{$sortInput}", $dir);
        } else {
            $col = $DIRECT[$sortInput] ?? $DIRECT['recent'];
            // Default dir: recent=desc (mais novo), name=asc (alfabético natural) salvo dir explícito.
            if (! $hasDir) {
                $dir = $col === 'contacts.name' ? 'asc' : 'desc';
            }
            $contactsQuery->orderBy($col, $dir);
        }

        $contacts = $contactsQuery
            ->select($selectCols)
            ->orderBy('contacts.id', 'desc')   // tie-breaker determinístico (paginação estável)
            ->paginate($perPage)
            ->withQueryString();

        $contactIds = $contacts->pluck('id')->all();

        // Wagner 2026-05-27 — header drawer botao contador "X placas". Lazy: so
        // count se tabela vehicles existe (gate OficinaAuto). Map contact_id => count.
        $vehiclesCountMap = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('vehicles') && ! empty($contactIds)) {
            $vehiclesCountMap = \Modules\OficinaAuto\Entities\Vehicle::where('business_id', $business_id)
                ->whereIn('contact_id', $contactIds)
                ->selectRaw('contact_id, COUNT(*) as cnt')
                ->groupBy('contact_id')
                ->pluck('cnt', 'contact_id')
                ->toArray();
        }

        // Wagner 2026-06-01 — header drawer botao contador "📎 N anexos". Conta
        // arquivos (media) anexados aos document-notes do contato. business_id
        // scope (Tier 0 ADR 0093) nas DUAS queries. 2 passos (sem has()/relacao,
        // larastan-clean igual vehiclesCountMap): mapa note=>contato, depois media
        // por nota. model_type guarda a classe cheia (sem morphMap no projeto).
        $documentsCountMap = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('document_and_notes') && ! empty($contactIds)) {
            $noteToContact = \App\DocumentAndNote::where('business_id', $business_id)
                ->where('notable_type', \App\Contact::class)
                ->whereIn('notable_id', $contactIds)
                ->pluck('notable_id', 'id');

            if ($noteToContact->isNotEmpty()) {
                $mediaPerNote = \App\Media::where('business_id', $business_id)
                    ->where('model_type', \App\DocumentAndNote::class)
                    ->whereIn('model_id', $noteToContact->keys())
                    ->selectRaw('model_id, COUNT(*) as cnt')
                    ->groupBy('model_id')
                    ->pluck('cnt', 'model_id');

                foreach ($mediaPerNote as $noteId => $cnt) {
                    $cid = $noteToContact[$noteId] ?? null;
                    if ($cid !== null) {
                        $documentsCountMap[$cid] = ($documentsCountMap[$cid] ?? 0) + (int) $cnt;
                    }
                }
            }
        }

        $stats = Transaction::where('transactions.business_id', $business_id)
            ->whereIn('transactions.contact_id', $contactIds)
            ->where('transactions.type', 'sell')
            ->select(
                'contact_id',
                DB::raw('COUNT(*) AS total_os'),
                DB::raw('SUM(CASE WHEN payment_status IN (\'due\',\'partial\') THEN 1 ELSE 0 END) AS os_abertas'),
                DB::raw('SUM(CASE WHEN payment_status IN (\'due\',\'partial\') AND due_date IS NOT NULL AND due_date < NOW() THEN 1 ELSE 0 END) AS os_atrasadas'),
                // FIX 2026-05-21: subquery em transaction_payments (total_paid NÃO existe no schema).
                DB::raw('SUM(CASE WHEN payment_status IN (\'due\',\'partial\') THEN (final_total - (SELECT COALESCE(SUM(tp.amount), 0) FROM transaction_payments tp WHERE tp.transaction_id = transactions.id)) ELSE 0 END) AS valor_aberto'),
                DB::raw('MAX(transaction_date) AS last_os_at'),
                // Wave G — última compra real (qualquer status, não só due/partial) pra FrescorPill.
                // Distingue de last_os_at que é última OS aberta. Wave G FrescorPill calcula
                // dias desde last_purchase pra classificar fresc/recente/distante/frio.
                DB::raw('MAX(CASE WHEN status != \'draft\' THEN transaction_date ELSE NULL END) AS last_purchase_at'),
            )
            ->groupBy('contact_id')
            ->get()
            ->keyBy('contact_id');

        $rows = $contacts->getCollection()->map(function ($contact) use ($stats, $hasWaveBCols, $hasCanonBrCols, $hasDrawerCols, $hasEmailsExtras, $hasSefazCols, $hasBucketACols, $hasContatoCol, $hasMensagemVendaCol, $vehiclesCountMap, $documentsCountMap) {
            $row = $stats->get($contact->id);
            $totalOs = $row ? (int) $row->total_os : 0;
            $abertas = $row ? (int) $row->os_abertas : 0;
            $atrasadas = $row ? (int) $row->os_atrasadas : 0;
            $valorAberto = $row ? (float) $row->valor_aberto : 0.0;
            $lastOsAt = $row ? $row->last_os_at : null;
            $lastPurchaseAt = $row ? $row->last_purchase_at : null;
            $status = $atrasadas > 0 ? 'late' : ($abertas > 0 ? 'active' : 'idle');

            // Wave G — saldo devedor convenção CRM (positivo = cliente nos deve).
            // Combina valor_aberto (OS due/partial) - contacts.balance (adiantamento +).
            // UPOS: contacts.balance >0 = cliente tem crédito conosco (positivo p/
            // ele). Convertendo p/ frame "devedor do CRM": subtraímos.
            $balance = (float) ($contact->balance ?? 0);
            $saldoDevedor = $valorAberto - $balance;

            // Wave G — payload base sempre presente.
            $payload = [
                'id' => (int) $contact->id,
                'name' => (string) $contact->name,
                'tax_number_masked' => $this->maskTaxNumber($contact->tax_number),
                'contact_id' => $contact->contact_id,
                'mobile' => $contact->mobile,
                'total_os' => $totalOs,
                'os_abertas' => $abertas,
                'os_atrasadas' => $atrasadas,
                'valor_aberto' => $valorAberto,
                'status' => $status,
                'last_os_at' => $lastOsAt,
                // Consumidor/fornecedor padrão (walk-in) — front esconde "Excluir".
                'is_default' => (bool) $contact->is_default,
                // Wave G novos campos cadastrais.
                // avatar_hash_seed = name (HSL hash determinístico Pages/Cliente/_components/Avatar.tsx).
                // Frontend usa name por default mas seed explícito permite estabilidade
                // mesmo se name mudar futuramente.
                'avatar_hash_seed' => (string) $contact->name,
                'cidade' => $contact->city,
                'uf' => $contact->state,
                'saldo_devedor' => round($saldoDevedor, 2),
                'last_purchase_at' => $lastPurchaseAt,
                // Z-2.1: subtitle drawer "Pessoa jurídica · cadastrado há Xd".
                'created_at' => optional($contact->created_at)->toIso8601String(),
                // Endereço canon EN — sem esses campos no payload, EnderecoTab
                // useEffect reseta state local pra '' quando router.reload({only:['rows']})
                // dispara pós lookup CNPJ/CEP (`contact.zip_code ?? ''` com undefined → '').
                // city/state já presentes acima como cidade/uf alias PT-BR — mas
                // EnderecoTab prefere canon EN. Wagner 2026-05-27.
                'zip_code' => $contact->zip_code,
                'address_line_1' => $contact->address_line_1,
                'address_line_2' => $contact->address_line_2,
                'neighborhood' => $contact->neighborhood ?? null,
                'numero' => $contact->numero ?? null,
                'city' => $contact->city,
                'state' => $contact->state,
                'shipping_address' => $contact->shipping_address,
            ];

            // Wave G — campos opcionais quando migration Wave B rodou.
            // Compatibilidade: ambientes em produção podem ter rodado migration
            // Wave B ainda não (graceful — null em vez de erro SQL no select).
            if ($hasWaveBCols) {
                $payload['tipo'] = $contact->tipo;
                $payload['fantasia'] = $contact->fantasia;
                $payload['tags'] = is_array($contact->tags) ? $contact->tags : [];
                $payload['segmento'] = $contact->segmento;
                $payload['vip'] = (bool) $contact->vip;
            } else {
                $payload['tipo'] = null;
                $payload['fantasia'] = null;
                $payload['tags'] = [];
                $payload['segmento'] = null;
                $payload['vip'] = false;
            }

            // ADR 0188 Onda 4 + ADR 0246 — 5 flags multi-papel pro Drawer 760 seção "Papéis".
            // Bool no payload (front cast direto · MySQL int 0/1 → React bool).
            $payload['is_customer'] = (bool) ($contact->is_customer ?? false);
            $payload['is_supplier'] = (bool) ($contact->is_supplier ?? false);
            $payload['is_employee'] = (bool) ($contact->is_employee ?? false);
            $payload['is_representative'] = (bool) ($contact->is_representative ?? false);
            // ADR 0246 — 5ª flag "Outros" (categoria default pra cadastros sem papel comercial).
            $payload['is_other'] = (bool) ($contact->is_other ?? false);

            // Canon BR (Wave 2026-05-21 ADR 0178) — campos que IdentificacaoTab
            // do drawer espera. Fallback `cpf_cnpj` → `tax_number` cobre legacy
            // UPOS (cadastros pré-restauração BR). PII mascarado, NUNCA plain.
            $cpfCnpjRaw = $hasCanonBrCols ? ($contact->cpf_cnpj ?? null) : null;
            $payload['cpf_cnpj_masked'] = $this->maskTaxNumber($cpfCnpjRaw ?? $contact->tax_number);
            // IE: prioriza `contacts.ie` (Wave drawer — onde autosave grava) com
            // fallback `inscricao_estadual` (Wave canon BR — cadastros pre-drawer).
            $payload['ie'] = ($hasDrawerCols ? ($contact->ie ?? null) : null)
                ?? ($hasCanonBrCols ? ($contact->inscricao_estadual ?? null) : null);
            $payload['rg'] = $hasCanonBrCols ? ($contact->rg ?? null) : null;

            // Wave drawer 2026-05-22 — campos cadastrais drawer.
            $payload['nascimento'] = $hasDrawerCols ? ($contact->nascimento ?? null) : null;
            $payload['cargo'] = $hasDrawerCols ? ($contact->cargo ?? null) : null;

            // ── ContatoTab: telefones + emails + site + canal ────────────────
            // UPOS canon (sempre presentes).
            $payload['landline'] = $contact->landline ?? null;
            $payload['alternate_number'] = $contact->alternate_number ?? null;
            $payload['email'] = $contact->email ?? null;
            // Wave drawer + Wave emails extras. Aliases PT-BR (`site`/`canal`)
            // pra ContatoTab que declara essas chaves diretamente; canon EN
            // (`site_url`/`canal_preferido`) preservados pra compat shapeContactResponse.
            $payload['tel2'] = $hasDrawerCols ? ($contact->tel2 ?? null) : null;
            $payload['site_url'] = $hasDrawerCols ? ($contact->site_url ?? null) : null;
            $payload['site'] = $payload['site_url'];
            $payload['canal_preferido'] = $hasDrawerCols ? ($contact->canal_preferido ?? null) : null;
            $payload['canal'] = $payload['canal_preferido'];
            $payload['email_billing'] = $hasEmailsExtras ? ($contact->email_billing ?? null) : null;
            $payload['email_nfe'] = $hasEmailsExtras ? ($contact->email_nfe ?? null) : null;

            // ── ComercialTab: limite, prazo, tabela, pgto, obs ───────────────
            // UPOS canon + Wave drawer enums. Aliases PT-BR pro tab que declara
            // `limite_credito`/`prazo_padrao_dias` em vez de `credit_limit`/`pay_term_number`.
            $payload['credit_limit'] = $contact->credit_limit !== null ? (float) $contact->credit_limit : null;
            $payload['limite_credito'] = $payload['credit_limit'];
            $payload['pay_term_number'] = $contact->pay_term_number !== null ? (int) $contact->pay_term_number : null;
            $payload['prazo_padrao_dias'] = $payload['pay_term_number'];
            // Tabela de preço REAL (FK customer_groups) — canon UPOS. Drawer
            // ComercialTab popula o dropdown com priceGroups e grava aqui.
            $payload['customer_group_id'] = $contact->customer_group_id !== null
                ? (int) $contact->customer_group_id : null;
            $payload['tabela_preco_padrao'] = $hasDrawerCols ? ($contact->tabela_preco_padrao ?? null) : null;
            $payload['pgto_padrao'] = $hasDrawerCols ? ($contact->pgto_padrao ?? null) : null;
            // mensagem_venda (alerta ao vendedor no POS · editavel no drawer).
            $payload['mensagem_venda'] = $hasMensagemVendaCol ? ($contact->mensagem_venda ?? null) : null;
            $payload['obs_comercial'] = $hasDrawerCols ? ($contact->obs_comercial ?? null) : null;

            // ── ClassificacaoTab: contact_status enum UPOS ───────────────────
            // NOTA: `status` (linha ~559) é DERIVADO do payment_status das OS
            // (late/active/idle pra FrescorPill) — NÃO renomear pra evitar
            // regressão visual. `contact_status` (ativo/inativo/bloqueado) é
            // separado e o tab pode escolher entre os 2 ao migrar.
            $payload['contact_status'] = $contact->contact_status ?? null;

            // ── IdentificacaoTab: SEFAZ ConsultaCadastro (Wave 2026-05-23) ───
            // Badge alertas NFe (rejeicao 478/487/770) — IdentificacaoTab lê via
            // shapeContactResponse pos lookup; ler no payload inicial mantem
            // consistencia (drawer abre com warning sem precisar re-lookup).
            $payload['ind_ie_dest'] = $hasSefazCols && $contact->ind_ie_dest !== null
                ? (int) $contact->ind_ie_dest : null;
            $payload['sefaz_cad_sit'] = $hasSefazCols ? ($contact->sefaz_cad_sit ?? null) : null;
            $payload['sefaz_cad_ind_cred_nfe'] = $hasSefazCols && $contact->sefaz_cad_ind_cred_nfe !== null
                ? (int) $contact->sefaz_cad_ind_cred_nfe : null;
            $payload['sefaz_cad_consultado_em'] = $hasSefazCols ? ($contact->sefaz_cad_consultado_em ?? null) : null;

            // ── Bucket A: bloqueado (ADR 0195) ───────────────────────────────
            $payload['bloqueado'] = $hasBucketACols ? (bool) ($contact->bloqueado ?? false) : false;

            // ── Daniela 2026-05-27: contato (nome do responsavel principal PJ)
            $payload['contato'] = $hasContatoCol ? ($contact->contato ?? null) : null;

            // ── Wagner 2026-05-27: contador de veiculos pro header drawer (botao "🚛 N placas")
            $payload['vehicles_count'] = (int) ($vehiclesCountMap[$contact->id] ?? 0);

            // ── Wagner 2026-06-01: contador de anexos pro header drawer (botao "📎 N anexos")
            $payload['documents_count'] = (int) ($documentsCountMap[$contact->id] ?? 0);

            return $payload;
        })->all();

        return [
            'data' => $rows,
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
                'from' => $contacts->firstItem(),
                'to' => $contacts->lastItem(),
                'sort' => 'name',
                'dir' => 'asc',
            ],
        ];
    }

    /**
     * Wave G (ADR 0179) — Export CSV da listagem de clientes.
     *
     * Endpoint: GET /cliente/export
     *
     * - Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): scope business_id obrigatório.
     * - PII LGPD: tax_number sai mascarado no CSV (CPF.***.***.000-XX), nunca plain.
     * - BOM UTF-8 \xEF\xBB\xBF p/ Excel-BR abrir acentuação OK.
     * - Streamed via chunk(500) — evita memory blow em biz=4 Larissa (30+ clientes/dia
     *   ROTA LIVRE, exports periódicos contábeis).
     * - Permission: customer.view (lista) — sem permission de export separada porque
     *   listar = ver = exportar (mesmo recorte de dados).
     *
     * Não acopla com BizzImport/BizzExport — esse é um CSV simples PT-BR p/ Excel/
     * Sheets, sem header machine-readable.
     */
    public function clienteExport(Request $request)
    {
        if (! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own')) {
            abort(403, 'Sem permissão pra exportar clientes.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        if (! $businessId) {
            abort(403, 'Sessão sem business_id.');
        }

        $filename = 'clientes-' . now()->format('Y-m-d-His') . '.csv';

        // Checa colunas Wave B (graceful — em ambiente onde migration não rodou,
        // colunas ficam vazias no CSV em vez de erro SQL).
        $hasWaveBCols = \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'tipo');

        return response()->stream(function () use ($businessId, $hasWaveBCols) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 — Excel-BR abre acentuação OK (Larissa biz=4 usa Excel local).
            fwrite($out, "\xEF\xBB\xBF");

            // Cabeçalho PT-BR. Separador ; (CSV Brasil padrão), não vírgula.
            fputcsv($out, [
                'Nome',
                'Tipo',
                'Documento',
                'Email',
                'Telefone',
                'Cidade',
                'UF',
                'Segmento',
                'Tags',
                'VIP',
            ], ';');

            Contact::where('business_id', $businessId)
                ->whereIn('type', ['customer', 'both'])
                ->orderBy('name', 'asc')
                ->chunk(500, function ($contacts) use ($out, $hasWaveBCols) {
                    foreach ($contacts as $c) {
                        $tags = $hasWaveBCols && is_array($c->tags ?? null) ? implode(',', $c->tags) : '';
                        fputcsv($out, [
                            $c->name ?? '',
                            $hasWaveBCols ? ($c->tipo ?? '') : '',
                            // PII LGPD — documento mascarado, NUNCA plain.
                            $this->maskTaxNumber($c->tax_number) ?? '',
                            $c->email ?? '',
                            $c->mobile ?? '',
                            $c->city ?? '',
                            $c->state ?? '',
                            $hasWaveBCols ? ($c->segmento ?? '') : '',
                            $tags,
                            $hasWaveBCols && $c->vip ? 'Sim' : 'Não',
                        ], ';');
                    }
                });

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Returns the database object for supplier
     *
     * @return \Illuminate\Http\Response
     */
    private function indexSupplier()
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $contact = $this->contactUtil->getContactQuery($business_id, 'supplier');

        if (request()->has('has_purchase_due')) {
            $contact->havingRaw('(total_purchase - purchase_paid) > 0');
        }

        if (request()->has('has_purchase_return')) {
            $contact->havingRaw('total_purchase_return > 0');
        }

        if (request()->has('has_advance_balance')) {
            $contact->where('balance', '>', 0);
        }

        if (request()->has('has_opening_balance')) {
            $contact->havingRaw('opening_balance > 0');
        }

        if (! empty(request()->input('contact_status'))) {
            $contact->where('contacts.contact_status', request()->input('contact_status'));
        }

        if (! empty(request()->input('assigned_to'))) {
            $contact->join('user_contact_access AS uc', 'contacts.id', 'uc.contact_id')
                ->where('uc.user_id', request()->input('assigned_to'));
        }

        return Datatables::of($contact)
            ->addColumn('address', '{{implode(", ", array_filter([$address_line_1, $address_line_2, $city, $state, $country, $zip_code]))}}')
            ->addColumn(
                'due',
                '<span class="contact_due" data-orig-value="{{$total_purchase - $purchase_paid - $total_ledger_discount}}" data-highlight=false>@format_currency($total_purchase - $purchase_paid - $total_ledger_discount)</span>'
            )
            ->addColumn(
                'return_due',
                '<span class="return_due" data-orig-value="{{$total_purchase_return - $purchase_return_paid}}" data-highlight=false>@format_currency($total_purchase_return - $purchase_return_paid)'
            )
            ->addColumn(
                'action',
                function ($row) {
                    $html = '<div class="btn-group">
                    <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max  dropdown-toggle" 
                        data-toggle="dropdown" aria-expanded="false">'.
                        __('messages.actions').
                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=purchase" class="pay_purchase_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.pay').'</a></li>';

                    $return_due = $row->total_purchase_return - $row->purchase_return_paid;
                    if ($return_due > 0) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=purchase_return" class="pay_purchase_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.receive_purchase_return_due').'</a></li>';
                    }

                    if (auth()->user()->can('supplier.view') || auth()->user()->can('supplier.view_own')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'"><i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'</a></li>';
                    }
                    if (auth()->user()->can('supplier.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'edit'], [$row->id]).'" class="edit_contact_button"><i class="glyphicon glyphicon-edit"></i>'.__('messages.edit').'</a></li>';
                    }
                    if (auth()->user()->can('supplier.delete')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'destroy'], [$row->id]).'" class="delete_contact_button"><i class="glyphicon glyphicon-trash"></i>'.__('messages.delete').'</a></li>';
                    }

                    if (auth()->user()->can('customer.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'updateStatus'], [$row->id]).'"class="update_contact_status"><i class="fas fa-power-off"></i>';

                        if ($row->contact_status == 'active') {
                            $html .= __('messages.deactivate');
                        } else {
                            $html .= __('messages.activate');
                        }

                        $html .= '</a></li>';
                    }

                    $html .= '<li class="divider"></li>';
                    if (auth()->user()->can('supplier.view')) {
                        $html .= '
                                <li>
                                    <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=ledger">
                                        <i class="fas fa-scroll" aria-hidden="true"></i>
                                        '.__('lang_v1.ledger').'
                                    </a>
                                </li>';

                        if (in_array($row->type, ['both', 'supplier'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=purchase">
                                    <i class="fas fa-arrow-circle-down" aria-hidden="true"></i>
                                    '.__('purchase.purchases').'
                                </a>
                            </li>
                            <li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=stock_report">
                                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                                    '.__('report.stock_report').'
                                </a>
                            </li>';
                        }

                        if (in_array($row->type, ['both', 'customer'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=sales">
                                    <i class="fas fa-arrow-circle-up" aria-hidden="true"></i>
                                    '.__('sale.sells').'
                                </a>
                            </li>';
                        }

                        $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=documents_and_notes">
                                    <i class="fas fa-paperclip" aria-hidden="true"></i>
                                     '.__('lang_v1.documents_and_notes').'
                                </a>
                            </li>';
                    }
                    $html .= '</ul></div>';

                    return $html;
                }
            )
            ->editColumn('opening_balance', function ($row) {
                $html = '<span data-orig-value="'.$row->opening_balance.'">'.$this->transactionUtil->num_f($row->opening_balance, true).'</span>';

                return $html;
            })
            ->editColumn('balance', function ($row) {
                $html = '<span data-orig-value="'.$row->balance.'">'.$this->transactionUtil->num_f($row->balance, true).'</span>';

                return $html;
            })
            ->editColumn('pay_term', '
                @if(!empty($pay_term_type) && !empty($pay_term_number))
                    {{$pay_term_number}}
                    @lang("lang_v1.".$pay_term_type)
                @endif
            ')
            ->editColumn('name', function ($row) {
                if ($row->contact_status == 'inactive') {
                    return $row->name.' <small class="label pull-right bg-red no-print">'.__('lang_v1.inactive').'</small>';
                } else {
                    return $row->name;
                }
            })
            ->editColumn('created_at', '{{@format_date($created_at)}}')
            ->removeColumn('opening_balance_paid')
            ->removeColumn('type')
            ->removeColumn('id')
            ->removeColumn('total_purchase')
            ->removeColumn('purchase_paid')
            ->removeColumn('total_purchase_return')
            ->removeColumn('purchase_return_paid')
            ->filterColumn('address', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('address_line_1', 'like', "%{$keyword}%")
                    ->orWhere('address_line_2', 'like', "%{$keyword}%")
                    ->orWhere('city', 'like', "%{$keyword}%")
                    ->orWhere('state', 'like', "%{$keyword}%")
                    ->orWhere('country', 'like', "%{$keyword}%")
                    ->orWhere('zip_code', 'like', "%{$keyword}%")
                    ->orWhereRaw("CONCAT(COALESCE(address_line_1, ''), ', ', COALESCE(address_line_2, ''), ', ', COALESCE(city, ''), ', ', COALESCE(state, ''), ', ', COALESCE(country, '') ) like ?", ["%{$keyword}%"]);
                });
            })
            ->rawColumns(['action', 'opening_balance', 'pay_term', 'due', 'return_due', 'name', 'balance'])
            ->make(true);
    }

    /**
     * Returns the database object for customer
     *
     * @return \Illuminate\Http\Response
     */
    private function indexCustomer()
    {
        if (! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->contactUtil->is_admin(auth()->user());

        $query = $this->contactUtil->getContactQuery($business_id, 'customer');

        if (request()->has('has_sell_due')) {
            $query->havingRaw('(total_invoice - invoice_received) > 0');
        }

        if (request()->has('has_sell_return')) {
            $query->havingRaw('total_sell_return > 0');
        }

        if (request()->has('has_advance_balance')) {
            $query->where('balance', '>', 0);
        }

        if (request()->has('has_opening_balance')) {
            $query->havingRaw('opening_balance > 0');
        }

        if (! empty(request()->input('assigned_to'))) {
            $query->join('user_contact_access AS uc', 'contacts.id', 'uc.contact_id')
                ->where('uc.user_id', request()->input('assigned_to'));
        }

        $has_no_sell_from = request()->input('has_no_sell_from', null);

        if (
            (! $is_admin && auth()->user()->can('customer_with_no_sell_one_month')) ||
            ($has_no_sell_from == 'one_month' && (auth()->user()->can('customer_with_no_sell_one_month') || auth()->user()->can('customer_irrespective_of_sell')))
            ) {
            $from_transaction_date = \Carbon::now()->subDays(30)->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if (
            (! $is_admin && auth()->user()->can('customer_with_no_sell_three_month')) ||
            ($has_no_sell_from == 'three_months' && (auth()->user()->can('customer_with_no_sell_three_month') || auth()->user()->can('customer_irrespective_of_sell')))
        ) {
            $from_transaction_date = \Carbon::now()->subMonths(3)->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if (
            (! $is_admin && auth()->user()->can('customer_with_no_sell_six_month')) ||
            ($has_no_sell_from == 'six_months' && (auth()->user()->can('customer_with_no_sell_six_month') || auth()->user()->can('customer_irrespective_of_sell')))
        ) {
            $from_transaction_date = \Carbon::now()->subMonths(6)->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if ((! $is_admin && auth()->user()->can('customer_with_no_sell_one_year')) ||
            ($has_no_sell_from == 'one_year' && (auth()->user()->can('customer_with_no_sell_one_year') || auth()->user()->can('customer_irrespective_of_sell')))
        ) {
            $from_transaction_date = \Carbon::now()->subYear()->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if (! empty(request()->input('customer_group_id'))) {
            $query->where('contacts.customer_group_id', request()->input('customer_group_id'));
        }

        if (! empty(request()->input('contact_status'))) {
            $query->where('contacts.contact_status', request()->input('contact_status'));
        }

        $contacts = Datatables::of($query)
            ->addColumn('address', '{{implode(", ", array_filter([$address_line_1, $address_line_2, $city, $state, $country, $zip_code]))}}')
            ->addColumn(
                'due',
                '<span class="contact_due" data-orig-value="{{$total_invoice - $invoice_received - $total_ledger_discount}}" data-highlight=true>@format_currency($total_invoice - $invoice_received - $total_ledger_discount)</span>'
            )
            ->addColumn(
                'return_due',
                '<span class="return_due" data-orig-value="{{$total_sell_return - $sell_return_paid}}" data-highlight=false>@format_currency($total_sell_return - $sell_return_paid)</span>'
            )
            ->addColumn(
                'action',
                function ($row) {
                    $html = '<div class="btn-group">
                    <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max dropdown-toggle" 
                        data-toggle="dropdown" aria-expanded="false">'.
                        __('messages.actions').
                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=sell" class="pay_sale_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.pay').'</a></li>';
                    $return_due = $row->total_sell_return - $row->sell_return_paid;
                    if ($return_due > 0) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=sell_return" class="pay_purchase_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.pay_sell_return_due').'</a></li>';
                    }

                    if (auth()->user()->can('customer.view') || auth()->user()->can('customer.view_own')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'"><i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'</a></li>';
                    }
                    if (auth()->user()->can('customer.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'edit'], [$row->id]).'" class="edit_contact_button"><i class="glyphicon glyphicon-edit"></i>'.__('messages.edit').'</a></li>';
                    }
                    if (! $row->is_default && auth()->user()->can('customer.delete')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'destroy'], [$row->id]).'" class="delete_contact_button"><i class="glyphicon glyphicon-trash"></i>'.__('messages.delete').'</a></li>';
                    }

                    if (auth()->user()->can('customer.update')) {
                    if(!$row->is_default){
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'updateStatus'], [$row->id]).'"class="update_contact_status"><i class="fas fa-power-off"></i>';

                        if ($row->contact_status == 'active') {
                            $html .= __('messages.deactivate');
                        } else {
                            $html .= __('messages.activate');
                        }
                        $html .= '</a></li>';
                    }
                       
                    }

                    $html .= '<li class="divider"></li>';
                    if (auth()->user()->can('customer.view')) {
                        $html .= '
                                <li>
                                    <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=ledger">
                                        <i class="fas fa-scroll" aria-hidden="true"></i>
                                        '.__('lang_v1.ledger').'
                                    </a>
                                </li>';

                        if (in_array($row->type, ['both', 'supplier'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=purchase">
                                    <i class="fas fa-arrow-circle-down" aria-hidden="true"></i>
                                    '.__('purchase.purchases').'
                                </a>
                            </li>
                            <li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=stock_report">
                                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                                    '.__('report.stock_report').'
                                </a>
                            </li>';
                        }

                        if (in_array($row->type, ['both', 'customer'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=sales">
                                    <i class="fas fa-arrow-circle-up" aria-hidden="true"></i>
                                    '.__('sale.sells').'
                                </a>
                            </li>';
                        }

                        $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=documents_and_notes">
                                    <i class="fas fa-paperclip" aria-hidden="true"></i>
                                     '.__('lang_v1.documents_and_notes').'
                                </a>
                            </li>';
                    }
                    $html .= '</ul></div>';

                    return $html;
                }
            )
            ->editColumn('opening_balance', function ($row) {
                $html = '<span data-orig-value="'.$row->opening_balance.'">'.$this->transactionUtil->num_f($row->opening_balance, true).'</span>';

                return $html;
            })
            ->editColumn('balance', function ($row) {
                $html = '<span data-orig-value="'.$row->balance.'">'.$this->transactionUtil->num_f($row->balance, true).'</span>';

                return $html;
            })
            ->editColumn('credit_limit', function ($row) {
                $html = __('lang_v1.no_limit');
                if (! is_null($row->credit_limit)) {
                    $html = '<span data-orig-value="'.$row->credit_limit.'">'.$this->transactionUtil->num_f($row->credit_limit, true).'</span>';
                }

                return $html;
            })
            ->editColumn('pay_term', '
                @if(!empty($pay_term_type) && !empty($pay_term_number))
                    {{$pay_term_number}}
                    @lang("lang_v1.".$pay_term_type)
                @endif
            ')
            ->editColumn('name', function ($row) {
                $name = $row->name;
                if ($row->contact_status == 'inactive') {
                    $name = $row->name.' <small class="label pull-right bg-red no-print">'.__('lang_v1.inactive').'</small>';
                }

                if (! empty($row->converted_by)) {
                    $name .= '<span class="label bg-info label-round no-print" data-toggle="tooltip" title="Converted from leads"><i class="fas fa-sync-alt"></i></span>';
                }

                return $name;
            })
            ->editColumn('total_rp', '{{$total_rp ?? 0}}')
            ->editColumn('created_at', '{{@format_date($created_at)}}')
            ->removeColumn('total_invoice')
            ->removeColumn('opening_balance_paid')
            ->removeColumn('invoice_received')
            ->removeColumn('state')
            ->removeColumn('country')
            ->removeColumn('city')
            ->removeColumn('type')
            ->removeColumn('id')
            ->removeColumn('is_default')
            ->removeColumn('total_sell_return')
            ->removeColumn('sell_return_paid')
            ->filterColumn('address', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('address_line_1', 'like', "%{$keyword}%")
                    ->orWhere('address_line_2', 'like', "%{$keyword}%")
                    ->orWhere('city', 'like', "%{$keyword}%")
                    ->orWhere('state', 'like', "%{$keyword}%")
                    ->orWhere('country', 'like', "%{$keyword}%")
                    ->orWhere('zip_code', 'like', "%{$keyword}%")
                    ->orWhereRaw("CONCAT(COALESCE(address_line_1, ''), ', ', COALESCE(address_line_2, ''), ', ', COALESCE(city, ''), ', ', COALESCE(state, ''), ', ', COALESCE(country, '') ) like ?", ["%{$keyword}%"]);
                });
            });
        $reward_enabled = (request()->session()->get('business.enable_rp') == 1) ? true : false;
        if (! $reward_enabled) {
            $contacts->removeColumn('total_rp');
        }

        return $contacts->rawColumns(['action', 'opening_balance', 'credit_limit', 'pay_term', 'due', 'return_due', 'name', 'balance'])
                        ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        }

        $types = [];
        if (auth()->user()->can('supplier.create') || auth()->user()->can('supplier.view_own')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create') || auth()->user()->can('customer.view_own')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create') || auth()->user()->can('supplier.view_own') || auth()->user()->can('customer.view_own')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }

        $customer_groups = CustomerGroup::forDropdown($business_id);
        $selected_type = request()->type;

        $module_form_parts = $this->moduleUtil->getModuleData('contact_form_part');

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        // Pre-fill via query param: vem do cadastro inline em Sells/Create
        // (CustomerSearchAutocomplete "Cadastrar 'NOME'"). Pedido Wagner 2026-05-10.
        // Sanitiza pra evitar XSS — view escapa com {{ }} também (dupla camada).
        $prefill_name = trim((string) request()->query('prefill_name', ''));
        if (mb_strlen($prefill_name) > 100) {
            $prefill_name = mb_substr($prefill_name, 0, 100);
        }

        // W1-B3 MWART branch — Inertia render quando flag cliente_create liga.
        if ($this->shouldRenderInertiaCliente('cliente_create', (int) $business_id)) {
            return Inertia::render('Cliente/Create', [
                'types' => $types,
                'customer_groups' => $customer_groups instanceof \Illuminate\Support\Collection
                    ? $customer_groups->map(fn ($v, $k) => ['id' => $k, 'name' => $v])->values()->all()
                    : (is_array($customer_groups) ? array_map(fn ($v, $k) => ['id' => $k, 'name' => $v], $customer_groups, array_keys($customer_groups)) : []),
                'selected_type' => $selected_type,
                'prefill_name' => $prefill_name,
                'permissions' => [
                    'create_customer' => auth()->user()->can('customer.create'),
                    'create_supplier' => auth()->user()->can('supplier.create'),
                ],
            ]);
        }

        return view('contact.create')
            ->with(compact('types', 'customer_groups', 'selected_type', 'module_form_parts', 'users', 'prefill_name'));
    }

    /**
     * Página standalone de cadastro de contato com layout completo (CSS/JS inclusos).
     * Usada pelo link "Cadastrar como novo cliente" da tela Sells/Create.tsx (v2 Inertia).
     * contact/create.blade.php é fragmento de modal — sem layout próprio não tem CSS.
     */
    public function createPage()
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        }

        $types = [];
        if (auth()->user()->can('supplier.create') || auth()->user()->can('supplier.view_own')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create') || auth()->user()->can('customer.view_own')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create') || auth()->user()->can('supplier.view_own') || auth()->user()->can('customer.view_own')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }

        $customer_groups = CustomerGroup::forDropdown($business_id);
        $selected_type = request()->type;
        $module_form_parts = $this->moduleUtil->getModuleData('contact_form_part');
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        $prefill_name = trim((string) request()->query('prefill_name', ''));
        if (mb_strlen($prefill_name) > 100) {
            $prefill_name = mb_substr($prefill_name, 0, 100);
        }

        return view('contact.create-page')
            ->with(compact('types', 'customer_groups', 'selected_type', 'module_form_parts', 'users', 'prefill_name'));
    }

    /**
     * Wagner 2026-06-01 — anexos do cliente pro drawer (Operações → Documentos).
     * Lista os arquivos (media) anexados aos document-notes do contato como JSON,
     * fechando o gap Wave D (o painel não carregava os anexos existentes — só
     * mostrava "Anexos (0)" até subir um arquivo na sessão). Mesma fonte do
     * contador `documents_count` do header (media via document_and_notes).
     *
     * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): scope business_id em TODAS as
     * queries (contato, notas e media). Sem relação/has() — larastan-clean igual
     * documentsCountMap: 2 queries simples + accessors do Media pro download.
     *
     * GET /cliente/{id}/anexos → { documents: DocumentItem[] }
     */
    public function anexos($id)
    {
        $business_id = request()->session()->get('user.business_id');

        // 404 cross-tenant: o contato precisa existir DENTRO do business.
        \App\Contact::where('business_id', $business_id)->findOrFail($id);

        $noteIds = \App\DocumentAndNote::where('business_id', $business_id)
            ->where('notable_type', \App\Contact::class)
            ->where('notable_id', $id)
            ->pluck('id');

        if ($noteIds->isEmpty()) {
            return response()->json(['documents' => []]);
        }

        $media = \App\Media::where('business_id', $business_id)
            ->where('model_type', \App\DocumentAndNote::class)
            ->whereIn('model_id', $noteIds)
            ->orderByDesc('id')
            ->get();

        $documents = $media->map(function ($m) {
            return [
                'id' => (int) $m->id,
                'file_name' => $m->file_name,
                'display_name' => $m->display_name,
                'description' => $m->description,
                'file_size' => null,
                'mime_type' => null,
                'uploaded_by_name' => null,
                'created_at' => optional($m->created_at)->toISOString(),
                'download_url' => $m->display_url,
            ];
        })->all();

        return response()->json(['documents' => $documents]);
    }

    /**
     * Wagner 2026-06-01 — upload de anexo do cliente (drawer Operações → Documentos).
     * Cria 1 document-note + anexa o arquivo (media) ao contato. Habilita o
     * "anexar" que estava read-only (botão oculto + endpoint legado /post-document-upload
     * não persistia o vínculo). Pareado com GET (listar) e DELETE (excluir).
     *
     * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): contato resolvido DENTRO do
     * business; o document-note herda business_id; Media::uploadMedia grava com
     * o mesmo scope.
     *
     * POST /cliente/{id}/anexos  (campo `file`)
     */
    public function storeAnexo(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|file|max:25600', // 25 MB
        ]);

        $business_id = request()->session()->get('user.business_id');
        $user_id = request()->session()->get('user.id');

        $contact = \App\Contact::where('business_id', $business_id)->findOrFail($id);

        $file = $request->file('file');
        $heading = $file instanceof \Illuminate\Http\UploadedFile
            ? $file->getClientOriginalName()
            : 'anexo';

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $note = $contact->documentsAndnote()->create([
                'business_id' => $business_id,
                'created_by' => $user_id,
                'heading' => $heading,
            ]);

            \App\Media::uploadMedia($business_id, $note, $request, 'file', true);

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Log::error('storeAnexo cliente '.$id.': '.$e->getMessage());

            return response()->json(['message' => __('messages.something_went_wrong')], 500);
        }

        $media = \App\Media::where('business_id', $business_id)
            ->where('model_type', \App\DocumentAndNote::class)
            ->where('model_id', $note->id)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'success' => true,
            'document' => $media ? [
                'id' => (int) $media->id,
                'file_name' => $media->file_name,
                'display_name' => $media->display_name,
                'description' => $media->description,
                'file_size' => null,
                'mime_type' => null,
                'uploaded_by_name' => null,
                'created_at' => optional($media->created_at)->toISOString(),
                'download_url' => $media->display_url,
            ] : null,
        ], 201);
    }

    /**
     * Wagner 2026-06-01 — exclui um anexo (media) do cliente. Valida que o media
     * pertence a um document-note DESTE contato (Tier 0 business_id scope) antes
     * de excluir. Remove o document-note se ficar órfão (sem media e sem texto).
     *
     * DELETE /cliente/{id}/anexos/{mediaId}
     */
    public function destroyAnexo($id, $mediaId)
    {
        $business_id = request()->session()->get('user.business_id');

        \App\Contact::where('business_id', $business_id)->findOrFail($id);

        $noteIds = \App\DocumentAndNote::where('business_id', $business_id)
            ->where('notable_type', \App\Contact::class)
            ->where('notable_id', $id)
            ->pluck('id');

        $media = \App\Media::where('business_id', $business_id)
            ->where('model_type', \App\DocumentAndNote::class)
            ->whereIn('model_id', $noteIds)
            ->where('id', $mediaId)
            ->firstOrFail();

        $noteId = $media->model_id;
        $media->delete();

        // Remove o document-note se ficou órfão (sem outras medias e sem texto).
        $remaining = \App\Media::where('business_id', $business_id)
            ->where('model_type', \App\DocumentAndNote::class)
            ->where('model_id', $noteId)
            ->count();
        if ($remaining === 0) {
            \App\DocumentAndNote::where('business_id', $business_id)
                ->where('id', $noteId)
                ->whereNull('description')
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Slice 7 — type-hint StoreContactRequest wira App\Rules\BR\CpfCnpj +
     * regras canon BR (indicador_ie 1/2/9, regime simples/presumido/real/mei).
     * Authorize já roda no FormRequest; abort(403) abaixo é defensividade legacy.
     *
     * @param  \App\Http\Requests\Cliente\StoreContactRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreContactRequest $request)
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse();
            }

            $input = $request->only(['type', 'supplier_business_name',
                'prefix', 'first_name', 'middle_name', 'last_name', 'tax_number', 'pay_term_number', 'pay_term_type', 'mobile', 'landline', 'alternate_number', 'city', 'state', 'country', 'address_line_1', 'address_line_2', 'customer_group_id', 'zip_code', 'contact_id', 'custom_field1', 'custom_field2', 'custom_field3', 'custom_field4', 'custom_field5', 'custom_field6', 'custom_field7', 'custom_field8', 'custom_field9', 'custom_field10', 'email', 'shipping_address', 'position', 'dob', 'shipping_custom_field_details', 'assigned_to_users',
                // Campos BR restaurados — migration 2026_05_21_140000 (regressão UPOS 6.7).
                'cpf_cnpj', 'rg', 'inscricao_estadual', 'inscricao_municipal', 'indicador_ie', 'nome_fantasia', 'consumidor_final', 'contribuinte', 'regime', 'suframa',
                // Onda 1 PR B' (Daniela @ Martinho) — emails extras (migration 2026_05_26_140000).
                'email_billing', 'email_nfe',
            ]);

            $name_array = [];

            if (! empty($input['prefix'])) {
                $name_array[] = $input['prefix'];
            }
            if (! empty($input['first_name'])) {
                $name_array[] = $input['first_name'];
            }
            if (! empty($input['middle_name'])) {
                $name_array[] = $input['middle_name'];
            }
            if (! empty($input['last_name'])) {
                $name_array[] = $input['last_name'];
            }

            $input['contact_type'] = $request->input('contact_type_radio');

            $input['name'] = trim(implode(' ', $name_array));

            unset($input['prefix'], $input['first_name'], $input['middle_name'], $input['last_name']);

            if (! empty($request->input('is_export'))) {
                $input['is_export'] = true;
                $input['export_custom_field_1'] = $request->input('export_custom_field_1');
                $input['export_custom_field_2'] = $request->input('export_custom_field_2');
                $input['export_custom_field_3'] = $request->input('export_custom_field_3');
                $input['export_custom_field_4'] = $request->input('export_custom_field_4');
                $input['export_custom_field_5'] = $request->input('export_custom_field_5');
                $input['export_custom_field_6'] = $request->input('export_custom_field_6');
            }

            if (! empty($input['dob'])) {
                $input['dob'] = $this->commonUtil->uf_date($input['dob']);
            }

            $input['business_id'] = $business_id;
            $input['created_by'] = $request->session()->get('user.id');

            $input['credit_limit'] = $request->input('credit_limit') != '' ? $this->commonUtil->num_uf($request->input('credit_limit')) : null;
            $input['opening_balance'] = $this->commonUtil->num_uf($request->input('opening_balance'));

            DB::beginTransaction();
            $output = $this->contactUtil->createNewContact($input);

            event(new ContactCreatedOrModified($input, 'added'));

            $this->moduleUtil->getModuleData('after_contact_saved', ['contact' => $output['data'], 'input' => $request->input()]);

            $this->contactUtil->activityLog($output['data'], 'added');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        // Inertia-aware response (bug fix 2026-05-25 -- Wagner reportou modal
        // "All Inertia requests must receive a valid Inertia response, however
        // a plain JSON response was received" ao salvar cliente novo via
        // /contacts/create). Tela Inertia espera redirect com headers proprios;
        // retornar array vira JSON puro e Inertia client lanca modal de erro.
        //
        // Detecta via X-Inertia header e converte pra Inertia-friendly:
        //   sucesso -> redirect()->route('contacts.index') com flash
        //   erro    -> back()->withInput()->withErrors([...])
        //
        // Legacy AJAX/cURL sem header X-Inertia mantem JSON UPOS pra
        // back-compat com integracoes externas.
        if ($request->header('X-Inertia')) {
            if (! empty($output['success'])) {
                return redirect()
                    ->route('contacts.index')
                    ->with('status', $output['msg'] ?? __('contact.added_success'));
            }

            return back()
                ->withInput()
                ->withErrors(['msg' => $output['msg'] ?? __('messages.something_went_wrong')]);
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $contact = $this->contactUtil->getContactInfo($business_id, $id);

        $is_selected_contacts = User::isSelectedContacts(auth()->user()->id);
        $user_contacts = [];
        if ($is_selected_contacts) {
            $user_contacts = auth()->user()->contactAccess->pluck('id')->toArray();
        }

        if (! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            if ($contact->created_by != auth()->user()->id & ! in_array($contact->id, $user_contacts)) {
                abort(403, 'Unauthorized action.');
            }
        }
        if (! auth()->user()->can('customer.view') && auth()->user()->can('customer.view_own')) {
            if ($contact->created_by != auth()->user()->id & ! in_array($contact->id, $user_contacts)) {
                abort(403, 'Unauthorized action.');
            }
        }

        $reward_enabled = (request()->session()->get('business.enable_rp') == 1 && in_array($contact->type, ['customer', 'both'])) ? true : false;

        $contact_dropdown = Contact::contactDropdown($business_id, false, false);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        //get contact view type : ledger, notes etc.
        $view_type = request()->get('view');
        if (is_null($view_type)) {
            $view_type = 'ledger';
        }

        $contact_view_tabs = $this->moduleUtil->getModuleData('get_contact_view_tabs');

        $activities = Activity::forSubject($contact)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        // Wave B ADR 0179 — paradigma drawer 760px substitui Show.tsx full-page.
        // Quando cliente_index liga, redireciona 302 -> Index com deeplink
        // ?contact_id={id}&tab=identificacao. Tem prioridade sobre cliente_show
        // branch abaixo (cliente_show vira fallback legacy emergencial).
        // Multi-tenant: $contact ja foi resolvido com Contact::find($id) acima
        // sob session('user.business_id') -- chegou aqui = cross-tenant safe.
        if (config('mwart.cliente_index.enabled')) {
            $tab = (string) (request()->query('tab') ?: 'identificacao');
            return redirect()->to("/cliente?contact_id={$contact->id}&tab={$tab}");
        }

        // W1-B3 MWART branch — Inertia render quando flag cliente_show liga.
        if ($this->shouldRenderInertiaCliente('cliente_show', (int) $business_id)) {
            $req = request();
            $tab = (string) ($req->query('tab') ?? 'ledger');
            $contact_type = (string) ($contact->type ?? 'customer');
            $user = auth()->user();
            $can_customer_update = $user->can('customer.update');
            $can_supplier_update = $user->can('supplier.update');

            return Inertia::render('Cliente/Show', [
                'contact' => [
                    'id' => (int) $contact->id,
                    'name' => (string) $contact->name,
                    'supplier_business_name' => $contact->supplier_business_name ?? null,
                    'type' => $contact_type,
                    'is_active' => (bool) ($contact->contact_status === 'active'),
                    'tax_number_masked' => $this->maskTaxNumber($contact->tax_number ?? null),
                    'mobile' => $contact->mobile ?? null,
                    'landline' => $contact->landline ?? null,
                    'email' => $contact->email ?? null,
                    'city' => $contact->city ?? null,
                    'state' => $contact->state ?? null,
                    'address_line_1' => $contact->address_line_1 ?? null,
                    // Dados Fiscais BR (migration 2026_05_21_140000). cpf_cnpj formatado
                    // via maskTaxNumber (canon Show.charter Automation Anti-hook) — NAO
                    // entrega plain pro frontend.
                    'cpf_cnpj_masked' => $this->maskTaxNumber($contact->cpf_cnpj ?? null),
                    'inscricao_estadual' => $contact->inscricao_estadual ?? null,
                    'inscricao_municipal' => $contact->inscricao_municipal ?? null,
                    'indicador_ie' => $contact->indicador_ie ?? null,
                    'nome_fantasia' => $contact->nome_fantasia ?? null,
                    'consumidor_final' => (bool) ($contact->consumidor_final ?? false),
                    'contribuinte' => (bool) ($contact->contribuinte ?? true),
                    'regime' => $contact->regime ?? null,
                    'suframa' => $contact->suframa ?? null,
                ],
                'initialTab' => in_array($tab, ['ledger', 'sales', 'payments', 'documents', 'activities', 'persons', 'subscriptions', 'rewards', 'vehicles'], true) ? $tab : 'ledger',
                'modules' => [
                    // Onda 1 PR D 2026-05-26 — frontend gate pra tab Veículos.
                    // Daniela (Martinho cliente piloto) precisa enxergar frota do cliente
                    // direto do cadastro. Schema `vehicles` (ADR 0137) já tem contact_id.
                    'oficinaauto_enabled' => (bool) $this->moduleUtil->isModuleInstalled('OficinaAuto'),
                ],
                // Defer só quando módulo instalado pra evitar referenciar Modules\OficinaAuto\Entities\Vehicle
                // em business que não tem o módulo (autoload do nWidart só registra Modules ativos).
                'vehicles' => $this->moduleUtil->isModuleInstalled('OficinaAuto')
                    ? Inertia::defer(fn () => $this->buildClienteVehiclesPaginator((int) $contact->id, (int) $business_id, request()))
                    : null,
                'stats' => Inertia::defer(fn () => [
                    'total_invoice' => (float) ($contact->total_invoice ?? 0),
                    'invoice_due' => (float) (($contact->total_invoice ?? 0) - ($contact->invoice_paid ?? 0)),
                    'total_purchase' => (float) ($contact->total_purchase ?? 0),
                    'purchase_due' => (float) (($contact->total_purchase ?? 0) - ($contact->purchase_paid ?? 0)),
                    'opening_balance' => (float) ($contact->opening_balance ?? 0),
                ]),
                'transactions' => Inertia::defer(fn () => Transaction::where('business_id', $business_id)
                    ->where('contact_id', $contact->id)
                    ->whereIn('type', ['sell', 'purchase'])
                    ->orderByDesc('transaction_date')
                    ->limit(20)
                    ->get(['id', 'invoice_no', 'transaction_date', 'final_total', 'payment_status'])
                    ->map(fn ($tx) => [
                        'id' => (int) $tx->id,
                        'invoice_no' => (string) $tx->invoice_no,
                        'transaction_date' => optional($tx->transaction_date)->toIso8601String(),
                        'final_total' => (float) $tx->final_total,
                        'payment_status' => (string) $tx->payment_status,
                    ])
                    ->all()),
                // Inertia::defer roda em request separado (partial reload only:['sales']) — usa request() corrente, NÃO $req capturado.
                'sales' => Inertia::defer(fn () => $this->buildClienteSalesPaginator((int) $contact->id, (int) $business_id, request())),
                'locations' => $business_locations->map(fn ($name, $id) => ['id' => (int) $id, 'name' => (string) $name])->values()->all(),
                // Onda Final.A — Contact picker header: lista clientes (customer+both) ativos do biz pro dropdown trocar contato sem voltar.
                // Defer porque pode ser custoso em business com muitos contatos. Multi-tenant Tier 0 (ADR 0093).
                'contact_dropdown' => Inertia::defer(fn () => Contact::where('contacts.business_id', $business_id)
                    ->whereIn('contacts.type', ['customer', 'both'])
                    ->where('contacts.contact_status', 'active')
                    ->orderBy('name')
                    ->limit(500)
                    ->get(['id', 'name', 'contact_id', 'supplier_business_name'])
                    ->map(fn ($c) => [
                        'id' => (int) $c->id,
                        'name' => (string) $c->name,
                        'contact_id' => $c->contact_id,
                        'supplier_business_name' => $c->supplier_business_name,
                    ])
                    ->all()),
                // Onda Final.B — Tab Atividades: activity log Spatie\Activitylog do contact.
                // Multi-tenant Tier 0 (ADR 0093): Activity::forSubject já scope por subject_id.
                'activities' => Inertia::defer(fn () => Activity::forSubject($contact)
                    ->with(['causer'])
                    ->latest()
                    ->limit(100)
                    ->get()
                    ->map(fn ($a) => [
                        'id' => (int) $a->id,
                        'created_at' => optional($a->created_at)->toIso8601String(),
                        'description' => (string) ($a->description ?? ''),
                        'description_label' => (string) __('lang_v1.' . ($a->description ?? '')),
                        'causer_name' => $a->causer->user_full_name ?? null,
                        'from_api' => $a->getExtraProperty('from_api') ?? null,
                        'is_automatic' => (bool) $a->getExtraProperty('is_automatic'),
                        'update_note' => is_string($a->getExtraProperty('update_note')) ? $a->getExtraProperty('update_note') : null,
                    ])
                    ->all()),
                // Onda Final.C — Tab Pessoas de contato: usuários CRM com crm_contact_id = $contact->id.
                // Feature do Modules/Crm — fica vazio se CRM module não habilitado pra biz. Multi-tenant Tier 0.
                'contact_persons' => Inertia::defer(fn () => User::where('business_id', $business_id)
                    ->where('crm_contact_id', $contact->id)
                    ->orderBy('first_name')
                    ->limit(200)
                    ->get(['id', 'username', 'email', 'surname', 'first_name', 'last_name', 'crm_department', 'crm_designation'])
                    ->map(fn ($u) => [
                        'id' => (int) $u->id,
                        'username' => (string) ($u->username ?? ''),
                        'email' => $u->email,
                        'full_name' => trim(($u->surname ?? '') . ' ' . ($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                        'department' => $u->crm_department,
                        'designation' => $u->crm_designation,
                    ])
                    ->all()),
                // Onda Final.E — Tab Reward Points: pontos fidelidade do contact.
                // Condicional business.enable_rp (passa null se desligado).
                'reward_points' => Inertia::defer(fn () => ($req->session()->get('business.enable_rp') == 1 && in_array($contact->type, ['customer', 'both'], true))
                    ? [
                        'enabled' => true,
                        'rp_name' => (string) ($req->session()->get('business.rp_name') ?? 'Pontos'),
                        'summary' => [
                            'total_earned' => (int) ($contact->total_rp ?? 0),
                            'total_used' => (int) ($contact->total_rp_used ?? 0),
                            'total_expired' => (int) ($contact->total_rp_expired ?? 0),
                            'balance' => (int) (((int) ($contact->total_rp ?? 0)) - ((int) ($contact->total_rp_used ?? 0)) - ((int) ($contact->total_rp_expired ?? 0))),
                        ],
                        'history' => Transaction::where('transactions.business_id', $business_id)
                            ->where('transactions.contact_id', $contact->id)
                            ->where(function ($q) {
                                $q->where('transactions.rp_earned', '>', 0)
                                  ->orWhere('transactions.rp_redeemed', '>', 0);
                            })
                            ->orderByDesc('transactions.transaction_date')
                            ->limit(100)
                            ->get(['id', 'invoice_no', 'transaction_date', 'final_total', 'rp_earned', 'rp_redeemed', 'rp_redeemed_amount'])
                            ->map(fn ($tx) => [
                                'id' => (int) $tx->id,
                                'invoice_no' => (string) ($tx->invoice_no ?? ''),
                                'transaction_date' => optional($tx->transaction_date)->toIso8601String(),
                                'final_total' => (float) $tx->final_total,
                                'rp_earned' => (int) ($tx->rp_earned ?? 0),
                                'rp_redeemed' => (int) ($tx->rp_redeemed ?? 0),
                                'rp_redeemed_amount' => (float) ($tx->rp_redeemed_amount ?? 0),
                            ])
                            ->all(),
                    ]
                    : ['enabled' => false, 'rp_name' => '', 'summary' => null, 'history' => []]),
                // Onda Final.D — Tab Assinaturas: transactions is_recurring=1 do contact (recur_parent_id NULL = pai da série).
                // Multi-tenant Tier 0 (ADR 0093): business_id + contact_id scope obrigatório.
                'subscriptions' => Inertia::defer(fn () => Transaction::where('transactions.business_id', $business_id)
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
                    ])
                    ->map(fn ($s) => [
                        'id' => (int) $s->id,
                        'subscription_no' => (string) ($s->subscription_no ?? ''),
                        'transaction_date' => optional($s->transaction_date)->toIso8601String(),
                        'recur_interval' => (int) ($s->recur_interval ?? 0),
                        'recur_interval_type' => (string) ($s->recur_interval_type ?? ''),
                        'recur_repetitions' => (int) ($s->recur_repetitions ?? 0),
                        'recur_stopped_on' => optional($s->recur_stopped_on)->toIso8601String(),
                        'location_name' => $s->location_name,
                        'generated_count' => (int) Transaction::where('business_id', $business_id)
                            ->where('recur_parent_id', $s->id)
                            ->count(),
                    ])
                    ->all()),
                'permissions' => [
                    'update' => $can_customer_update || $can_supplier_update,
                    'pay_due' => $user->can('purchase.payments') || $user->can('sell.payments'),
                    'delete' => $user->can('customer.delete') || $user->can('supplier.delete'),
                    'toggle_status' => $can_customer_update || $can_supplier_update,
                    'add_discount' => $user->can('discount.access'),
                    'upload' => $can_customer_update || $can_supplier_update,
                    'delete_document' => $can_customer_update || $can_supplier_update,
                    'edit_note' => $can_customer_update || $can_supplier_update,
                    'view_sell' => $user->can('view_own_sell_only') || $user->can('sell.view') || $user->can('direct_sell.view'),
                ],
            ]);
        }

        return view('contact.show')
             ->with(compact('contact', 'reward_enabled', 'contact_dropdown', 'business_locations', 'view_type', 'contact_view_tabs', 'activities'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('supplier.update') && ! auth()->user()->can('customer.update') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        if ($this->isLegacyAjax()) {
            $business_id = request()->session()->get('user.business_id');
            $contact = Contact::where('business_id', $business_id)->find($id);

            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse();
            }

            $types = [];
            if (auth()->user()->can('supplier.create')) {
                $types['supplier'] = __('report.supplier');
            }
            if (auth()->user()->can('customer.create')) {
                $types['customer'] = __('report.customer');
            }
            if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
                $types['both'] = __('lang_v1.both_supplier_customer');
            }

            $customer_groups = CustomerGroup::forDropdown($business_id);

            $ob_transaction = Transaction::where('contact_id', $id)
                                            ->where('type', 'opening_balance')
                                            ->first();
            $opening_balance = ! empty($ob_transaction->final_total) ? $ob_transaction->final_total : 0;

            //Deduct paid amount from opening balance.
            if (! empty($opening_balance)) {
                $opening_balance_paid = $this->transactionUtil->getTotalAmountPaid($ob_transaction->id);
                if (! empty($opening_balance_paid)) {
                    $opening_balance = $opening_balance - $opening_balance_paid;
                }

                $opening_balance = $this->commonUtil->num_f($opening_balance);
            }

            //Added check because $users is of no use if enable_contact_assign if false
            $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

            return view('contact.edit')
                ->with(compact('contact', 'types', 'customer_groups', 'opening_balance', 'users'));
        }

        // W1-B3 MWART branch (non-ajax full page) — Inertia render quando flag cliente_edit liga.
        $business_id = request()->session()->get('user.business_id');
        if ($this->shouldRenderInertiaCliente('cliente_edit', (int) $business_id)) {
            $contact = Contact::where('business_id', $business_id)->findOrFail($id);

            $types = [];
            if (auth()->user()->can('supplier.create')) {
                $types['supplier'] = __('report.supplier');
            }
            if (auth()->user()->can('customer.create')) {
                $types['customer'] = __('report.customer');
            }
            if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
                $types['both'] = __('lang_v1.both_supplier_customer');
            }
            $customer_groups = CustomerGroup::forDropdown($business_id);

            $ob_transaction = Transaction::where('contact_id', $id)
                ->where('type', 'opening_balance')
                ->first();
            $opening_balance = ! empty($ob_transaction->final_total) ? $ob_transaction->final_total : 0;
            if (! empty($opening_balance)) {
                $opening_balance_paid = $this->transactionUtil->getTotalAmountPaid($ob_transaction->id);
                if (! empty($opening_balance_paid)) {
                    $opening_balance = $opening_balance - $opening_balance_paid;
                }
                $opening_balance = $this->commonUtil->num_f($opening_balance);
            }

            return Inertia::render('Cliente/Edit', [
                'contact' => [
                    'id' => (int) $contact->id,
                    'type' => (string) ($contact->type ?? 'customer'),
                    'contact_type' => $contact->contact_type ?? null,
                    'name' => (string) $contact->name,
                    'prefix' => $contact->prefix ?? null,
                    'first_name' => (string) ($contact->first_name ?? $contact->name),
                    'middle_name' => $contact->middle_name ?? null,
                    'last_name' => $contact->last_name ?? null,
                    'supplier_business_name' => $contact->supplier_business_name ?? null,
                    'tax_number' => $contact->tax_number ?? null,
                    'mobile' => $contact->mobile ?? null,
                    'landline' => $contact->landline ?? null,
                    'email' => $contact->email ?? null,
                    'address_line_1' => $contact->address_line_1 ?? null,
                    'city' => $contact->city ?? null,
                    'state' => $contact->state ?? null,
                    'zip_code' => $contact->zip_code ?? null,
                    'shipping_address' => $contact->shipping_address ?? null,
                    'customer_group_id' => $contact->customer_group_id ?? null,
                    'credit_limit' => $contact->credit_limit ?? null,
                    // Dados Fiscais BR (migration 2026_05_21_140000). Diferente do Show()
                    // que entrega cpf_cnpj MASCARADO, Edit precisa do valor PLAIN pra
                    // permitir edição. Exposição PII gated por can('customer.update') /
                    // can('supplier.update') no início do método.
                    'cpf_cnpj' => $contact->cpf_cnpj ?? null,
                    'rg' => $contact->rg ?? null,
                    'inscricao_estadual' => $contact->inscricao_estadual ?? null,
                    'inscricao_municipal' => $contact->inscricao_municipal ?? null,
                    'indicador_ie' => $contact->indicador_ie ?? null,
                    'nome_fantasia' => $contact->nome_fantasia ?? null,
                    'consumidor_final' => $contact->consumidor_final !== null ? (bool) $contact->consumidor_final : null,
                    'contribuinte' => $contact->contribuinte !== null ? (bool) $contact->contribuinte : null,
                    'regime' => $contact->regime ?? null,
                    'suframa' => $contact->suframa ?? null,
                ],
                'types' => $types,
                'customer_groups' => $customer_groups instanceof \Illuminate\Support\Collection
                    ? $customer_groups->map(fn ($v, $k) => ['id' => $k, 'name' => $v])->values()->all()
                    : [],
                'opening_balance' => (string) $opening_balance,
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * Slice 7 — type-hint UpdateContactRequest wira App\Rules\BR\CpfCnpj.
     * Cobre o path legacy (isLegacyAjax) e o futuro Inertia — validação roda
     * antes da action, antes do branch isLegacyAjax. Authorize duplo (FormRequest
     * + abort manual) por defensividade durante migração Wave 1.
     *
     * @param  \App\Http\Requests\Cliente\UpdateContactRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateContactRequest $request, $id)
    {
        if (! auth()->user()->can('supplier.update') && ! auth()->user()->can('customer.update') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        // Bug fix 2026-05-26 — pré-fix, TODO o corpo do update() vivia dentro de
        // `if ($this->isLegacyAjax()) { ... }`. Quando o Edit.tsx mandava PUT via
        // Inertia (X-Inertia header presente, isLegacyAjax() = false), o método caía
        // fora do bloco e retornava void → Inertia client lançava "All Inertia requests
        // must receive a valid Inertia response". Mesma classe de bug que store() ganhou
        // fix 2026-05-25.
        //
        // Solução: extrair processamento pra helper `processContactUpdate()` + 2 branches
        // explícitos (legacy AJAX retorna JSON UPOS; Inertia redireciona com flash,
        // espelhando o pattern de store() linhas 1291-1301).
        if ($this->isLegacyAjax()) {
            return $this->processContactUpdate($request, $id);
        }

        $result = $this->processContactUpdate($request, $id);

        // expiredResponse() do moduleUtil já é Response — devolve direto sem mexer.
        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return $result;
        }

        if (! empty($result['success'])) {
            return redirect()
                ->route('contacts.show', $id)
                ->with('status', $result['msg'] ?? __('contact.updated_success'));
        }

        return back()
            ->withInput()
            ->withErrors(['msg' => $result['msg'] ?? __('messages.something_went_wrong')]);
    }

    /**
     * Helper extraído do update() pra desacoplar processamento da response.
     *
     * Retorna ['success' => bool, 'msg' => string, 'data' => Contact|null] (UPOS canon)
     * OU Response (caso `moduleUtil->expiredResponse()` em business sem subscription).
     *
     * Caller é responsável por interpretar e responder Inertia/JSON conforme contexto.
     *
     * @return array|\Symfony\Component\HttpFoundation\Response
     */
    private function processContactUpdate(UpdateContactRequest $request, int $id)
    {
        try {
            $input = $request->only(['type', 'supplier_business_name', 'prefix', 'first_name', 'middle_name', 'last_name', 'tax_number', 'pay_term_number', 'pay_term_type', 'mobile', 'address_line_1', 'address_line_2', 'zip_code', 'dob', 'alternate_number', 'city', 'state', 'country', 'landline', 'customer_group_id', 'contact_id', 'custom_field1', 'custom_field2', 'custom_field3', 'custom_field4', 'custom_field5', 'custom_field6', 'custom_field7', 'custom_field8', 'custom_field9', 'custom_field10', 'email', 'shipping_address', 'position', 'shipping_custom_field_details', 'export_custom_field_1', 'export_custom_field_2', 'export_custom_field_3', 'export_custom_field_4', 'export_custom_field_5',
                'export_custom_field_6', 'assigned_to_users',
                // Campos BR restaurados — migration 2026_05_21_140000 (regressão UPOS 6.7).
                'cpf_cnpj', 'rg', 'inscricao_estadual', 'inscricao_municipal', 'indicador_ie', 'nome_fantasia', 'consumidor_final', 'contribuinte', 'regime', 'suframa',
            ]);

            $name_array = [];

            if (! empty($input['prefix'])) {
                $name_array[] = $input['prefix'];
            }
            if (! empty($input['first_name'])) {
                $name_array[] = $input['first_name'];
            }
            if (! empty($input['middle_name'])) {
                $name_array[] = $input['middle_name'];
            }
            if (! empty($input['last_name'])) {
                $name_array[] = $input['last_name'];
            }

            $input['contact_type'] = $request->input('contact_type_radio');

            $input['name'] = trim(implode(' ', $name_array));

            unset($input['prefix'], $input['first_name'], $input['middle_name'], $input['last_name']);

            $input['is_export'] = ! empty($request->input('is_export')) ? 1 : 0;

            if (! $input['is_export']) {
                unset($input['export_custom_field_1'], $input['export_custom_field_2'], $input['export_custom_field_3'], $input['export_custom_field_4'], $input['export_custom_field_5'], $input['export_custom_field_6']);
            }

            if (! empty($input['dob'])) {
                $input['dob'] = $this->commonUtil->uf_date($input['dob']);
            }

            $input['credit_limit'] = $request->input('credit_limit') != '' ? $this->commonUtil->num_uf($request->input('credit_limit')) : null;

            $business_id = $request->session()->get('user.business_id');

            $input['opening_balance'] = $this->commonUtil->num_uf($request->input('opening_balance'));

            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse();
            }

            $output = $this->contactUtil->updateContact($input, $id, $business_id);

            event(new ContactCreatedOrModified($output['data'], 'updated'));

            $this->contactUtil->activityLog($output['data'], 'edited');
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('supplier.delete') && ! auth()->user()->can('customer.delete') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        if ($this->isLegacyAjax()) {
            try {
                $business_id = request()->user()->business_id;

                //Check if any transaction related to this contact exists
                $count = Transaction::where('business_id', $business_id)
                                    ->where('contact_id', $id)
                                    ->count();
                if ($count == 0) {
                    $contact = Contact::where('business_id', $business_id)->findOrFail($id);
                    if (! $contact->is_default) {
                        $log_properities = [
                            'id' => $contact->id,
                            'name' => $contact->name,
                            'supplier_business_name' => $contact->supplier_business_name,
                        ];
                        $this->contactUtil->activityLog($contact, 'contact_deleted', $log_properities);

                        //Disable login for associated users
                        User::where('crm_contact_id', $contact->id)
                            ->update(['allow_login' => 0]);

                        $contact->delete();

                        event(new ContactCreatedOrModified($contact, 'deleted'));
                    }
                    $output = ['success' => true,
                        'msg' => __('contact.deleted_success'),
                    ];
                } else {
                    $output = ['success' => false,
                        'msg' => __('lang_v1.you_cannot_delete_this_contact'),
                    ];
                }
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Retrieves list of customers, if filter is passed then filter it accordingly.
     *
     * @param  string  $q
     * @return JSON
     */
    public function getCustomers()
    {
        if ($this->isLegacyAjax()) {
            $term = request()->input('q', '');

            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $contacts = Contact::where('contacts.business_id', $business_id)
                            ->leftjoin('customer_groups as cg', 'cg.id', '=', 'contacts.customer_group_id')
                            ->active();

            if (! request()->has('all_contact')) {
                $contacts->onlyCustomers();
            }

            if (! empty($term)) {
                $contacts->where(function ($query) use ($term) {
                    $query->where('contacts.name', 'like', '%'.$term.'%')
                            ->orWhere('supplier_business_name', 'like', '%'.$term.'%')
                            ->orWhere('mobile', 'like', '%'.$term.'%')
                            ->orWhere('contacts.contact_id', 'like', '%'.$term.'%');
                });
            }

            $contacts->select(
                'contacts.id',
                DB::raw("IF(contacts.contact_id IS NULL OR contacts.contact_id='', contacts.name, CONCAT(contacts.name, ' (', contacts.contact_id, ')')) AS text"),
                'mobile',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'country',
                'zip_code',
                'shipping_address',
                'pay_term_number',
                'pay_term_type',
                'balance',
                'supplier_business_name',
                // mensagem_venda — alerta ao vendedor no POS quando o cliente é
                // selecionado (migrado de PESSOAS.MENSAGEM_PARA_VENDA Delphi).
                'contacts.mensagem_venda',
                'cg.amount as discount_percent',
                'cg.price_calculation_type',
                'cg.selling_price_group_id',
                'shipping_custom_field_details',
                'is_export',
                'export_custom_field_1',
                'export_custom_field_2',
                'export_custom_field_3',
                'export_custom_field_4',
                'export_custom_field_5',
                'export_custom_field_6'
            );

            if (request()->session()->get('business.enable_rp') == 1) {
                $contacts->addSelect('total_rp');
            }
            $contacts = $contacts->get();

            // ADR 0251 — catálogo de veículos do cliente p/ o seletor de veículo na
            // venda direta de oficina (Sells/Create). Query separada + map (mesmo
            // padrão do vehiclesCountMap) porque o select() acima é custom + leftjoin.
            //
            // BLINDAGEM: este endpoint é COMPARTILHADO com o Blade legado. Guard
            // Schema::hasTable degrada gracioso quando OficinaAuto não está instalado
            // (sem catálogo, o seletor some) em vez de quebrar a busca de cliente.
            // Vehicle tem global scope por business_id (ADR 0093) — Tier 0 automático.
            $vehiclesByContact = collect();
            if (\Illuminate\Support\Facades\Schema::hasTable('vehicles') && $contacts->isNotEmpty()) {
                $contactIds = $contacts->pluck('id')->all();
                $vehiclesByContact = \Modules\OficinaAuto\Entities\Vehicle::query()
                    ->whereIn('contact_id', $contactIds)
                    ->orderByDesc('id')
                    ->get(['id', 'contact_id', 'plate', 'secondary_plate', 'vehicle_type'])
                    ->groupBy('contact_id');
            }

            // Mapeia pra array (em vez de setar propriedade dinâmica no Model — Larastan
            // acusaria App\Contact::$vehicles undefined) anexando vehicles[] por contato.
            $payload = $contacts->map(function ($c) use ($vehiclesByContact) {
                $arr = $c->toArray();
                $arr['vehicles'] = collect($vehiclesByContact->get($c->id) ?? [])
                    ->map(fn ($v) => [
                        'id'              => (int) $v->id,
                        'plate'           => $v->plate,
                        'secondary_plate' => $v->secondary_plate,
                        'vehicle_type'    => $v->vehicle_type,
                    ])
                    ->values()
                    ->all();

                return $arr;
            });

            return json_encode($payload);
        }
    }

    /**
     * Checks if the given contact id already exist for the current business.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkContactId(Request $request)
    {
        $contact_id = $request->input('contact_id');

        $valid = 'true';
        if (! empty($contact_id)) {
            $business_id = $request->session()->get('user.business_id');
            $hidden_id = $request->input('hidden_id');

            $query = Contact::where('business_id', $business_id)
                            ->where('contact_id', $contact_id);
            if (! empty($hidden_id)) {
                $query->where('id', '!=', $hidden_id);
            }
            $count = $query->count();
            if ($count > 0) {
                $valid = 'false';
            }
        }
        echo $valid;
        exit;
    }

    /**
     * Shows import option for contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function getImportContacts()
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $zip_loaded = extension_loaded('zip') ? true : false;
        $business_id = request()->session()->get('user.business_id');

        // W1-B3 MWART branch — Inertia render quando flag cliente_import liga.
        if ($this->shouldRenderInertiaCliente('cliente_import', (int) $business_id)) {
            $notification = null;
            if ($zip_loaded === false) {
                $notification = [
                    'success' => 0,
                    'msg' => 'Please install/enable PHP Zip archive for import',
                ];
            }

            return Inertia::render('Cliente/Import', [
                'zip_available' => $zip_loaded,
                'notification' => $notification,
            ]);
        }

        //Check if zip extension it loaded or not.
        if ($zip_loaded === false) {
            $output = ['success' => 0,
                'msg' => 'Please install/enable PHP Zip archive for import',
            ];

            return view('contact.import')
                ->with('notification', $output);
        } else {
            return view('contact.import');
        }
    }

    /**
     * Imports contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function postImportContacts(Request $request)
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $notAllowed = $this->commonUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            //Set maximum php execution time
            ini_set('max_execution_time', 0);

            if ($request->hasFile('contacts_csv')) {
                $file = $request->file('contacts_csv');
                $parsed_array = Excel::toArray([], $file);
                //Remove header row
                $imported_data = array_splice($parsed_array[0], 1);

                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');

                $formated_data = [];

                $is_valid = true;
                $error_msg = '';

                DB::beginTransaction();
                foreach ($imported_data as $key => $value) {
                    //Check if 27 no. of columns exists
                    if (count($value) != 27) {
                        $is_valid = false;
                        $error_msg = 'Number of columns mismatch';
                        break;
                    }

                    $row_no = $key + 1;
                    $contact_array = [];

                    //Check contact type
                    $contact_type = '';
                    $contact_types = [
                        1 => 'customer',
                        2 => 'supplier',
                        3 => 'both',
                    ];
                    if (! empty($value[0])) {
                        $contact_type = strtolower(trim($value[0]));
                        if (in_array($contact_type, [1, 2, 3])) {
                            $contact_array['type'] = $contact_types[$contact_type];
                            $contact_type = $contact_types[$contact_type];
                        } else {
                            $is_valid = false;
                            $error_msg = "Invalid contact type $contact_type in row no. $row_no";
                            break;
                        }
                    } else {
                        $is_valid = false;
                        $error_msg = "Contact type is required in row no. $row_no";
                        break;
                    }

                    $contact_array['prefix'] = $value[1];
                    //Check contact name
                    if (! empty($value[2])) {
                        $contact_array['first_name'] = $value[2];
                    } else {
                        $is_valid = false;
                        $error_msg = "First name is required in row no. $row_no";
                        break;
                    }
                    $contact_array['middle_name'] = $value[3];
                    $contact_array['last_name'] = $value[4];
                    $contact_array['name'] = implode(' ', [$contact_array['prefix'], $contact_array['first_name'], $contact_array['middle_name'], $contact_array['last_name']]);

                    //Check business name
                    if (! empty(trim($value[5]))) {
                        $contact_array['supplier_business_name'] = $value[5];
                    }

                    //Check supplier fields
                    if (in_array($contact_type, ['supplier', 'both'])) {
                        //Check pay term
                        if (trim($value[9]) != '') {
                            $contact_array['pay_term_number'] = trim($value[9]);
                        } else {
                            $is_valid = false;
                            $error_msg = "Pay term is required in row no. $row_no";
                            break;
                        }

                        //Check pay period
                        $pay_term_type = strtolower(trim($value[10]));
                        if (in_array($pay_term_type, ['days', 'months'])) {
                            $contact_array['pay_term_type'] = $pay_term_type;
                        } else {
                            $is_valid = false;
                            $error_msg = "Pay term period is required in row no. $row_no";
                            break;
                        }
                    }

                    //Check contact ID
                    if (! empty(trim($value[6]))) {
                        $count = Contact::where('business_id', $business_id)
                                    ->where('contact_id', $value[6])
                                    ->count();

                        if ($count == 0) {
                            $contact_array['contact_id'] = $value[6];
                        } else {
                            $is_valid = false;
                            $error_msg = "Contact ID already exists in row no. $row_no";
                            break;
                        }
                    }

                    //Tax number
                    if (! empty(trim($value[7]))) {
                        $contact_array['tax_number'] = $value[7];
                    }

                    //Check opening balance
                    if (! empty(trim($value[8])) && $value[8] != 0) {
                        $contact_array['opening_balance'] = trim($value[8]);
                    }

                    //Check credit limit
                    if (trim($value[11]) != '' && in_array($contact_type, ['customer', 'both'])) {
                        $contact_array['credit_limit'] = trim($value[11]);
                    }

                    //Check email
                    if (! empty(trim($value[12]))) {
                        if (filter_var(trim($value[12]), FILTER_VALIDATE_EMAIL)) {
                            $contact_array['email'] = $value[12];
                        } else {
                            $is_valid = false;
                            $error_msg = "Invalid email id in row no. $row_no";
                            break;
                        }
                    }

                    //Mobile number
                    if (! empty(trim($value[13]))) {
                        $contact_array['mobile'] = $value[13];
                    } else {
                        $is_valid = false;
                        $error_msg = "Mobile number is required in row no. $row_no";
                        break;
                    }

                    //Alt contact number
                    $contact_array['alternate_number'] = $value[14];

                    //Landline
                    $contact_array['landline'] = $value[15];

                    //City
                    $contact_array['city'] = $value[16];

                    //State
                    $contact_array['state'] = $value[17];

                    //Country
                    $contact_array['country'] = $value[18];

                    //address_line_1
                    $contact_array['address_line_1'] = $value[19];
                    //address_line_2
                    $contact_array['address_line_2'] = $value[20];
                    $contact_array['zip_code'] = $value[21];
                    $contact_array['dob'] = $value[22];

                    //Cust fields
                    $contact_array['custom_field1'] = $value[23];
                    $contact_array['custom_field2'] = $value[24];
                    $contact_array['custom_field3'] = $value[25];
                    $contact_array['custom_field4'] = $value[26];

                    $formated_data[] = $contact_array;
                }
                if (! $is_valid) {
                    throw new \Exception($error_msg);
                }

                if (! empty($formated_data)) {
                    foreach ($formated_data as $contact_data) {
                        $ref_count = $this->transactionUtil->setAndGetReferenceCount('contacts');
                        //Set contact id if empty
                        if (empty($contact_data['contact_id'])) {
                            $contact_data['contact_id'] = $this->commonUtil->generateReferenceNumber('contacts', $ref_count);
                        }

                        $opening_balance = 0;
                        if (isset($contact_data['opening_balance'])) {
                            $opening_balance = $contact_data['opening_balance'];
                            unset($contact_data['opening_balance']);
                        }

                        $contact_data['business_id'] = $business_id;
                        $contact_data['created_by'] = $user_id;

                        $contact = Contact::create($contact_data);

                        if (! empty($opening_balance)) {
                            $this->transactionUtil->createOpeningBalanceTransaction($business_id, $contact->id, $opening_balance, $user_id, false);
                        }

                        $this->transactionUtil->activityLog($contact, 'imported');
                    }
                }

                $output = ['success' => 1,
                    'msg' => __('product.file_imported_successfully'),
                ];

                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            return redirect()->route('contacts.import')->with('notification', $output);
        }
        $type = ! empty($contact->type) && $contact->type != 'both' ? $contact->type : 'supplier';

        return redirect()->action([\App\Http\Controllers\ContactController::class, 'index'], ['type' => $type])->with('status', $output);
    }

    /**
     * Shows ledger for contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function getLedger()
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('customer.view') && ! auth()->user()->can('supplier.view_own') && ! auth()->user()->can('customer.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $contact_id = request()->input('contact_id');

        $is_admin = $this->contactUtil->is_admin(auth()->user());

        $start_date = request()->start_date;
        $end_date = request()->end_date;
        $format = request()->format;
        $location_id = request()->location_id;

        $contact = Contact::find($contact_id);

        $is_selected_contacts = User::isSelectedContacts(auth()->user()->id);
        $user_contacts = [];
        if ($is_selected_contacts) {
            $user_contacts = auth()->user()->contactAccess->pluck('id')->toArray();
        }

        if (! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            if ($contact->created_by != auth()->user()->id & ! in_array($contact->id, $user_contacts)) {
                abort(403, 'Unauthorized action.');
            }
        }
        if (! auth()->user()->can('customer.view') && auth()->user()->can('customer.view_own')) {
            if ($contact->created_by != auth()->user()->id & ! in_array($contact->id, $user_contacts)) {
                abort(403, 'Unauthorized action.');
            }
        }

        $line_details = $format == 'format_3' ? true : false;

        $ledger_details = $this->transactionUtil->getLedgerDetails($contact_id, $start_date, $end_date, $format, $location_id, $line_details);

        $location = null;
        if (! empty($location_id)) {
            $location = BusinessLocation::where('business_id', $business_id)->find($location_id);
        }
        if (request()->input('action') == 'pdf') {
            $output_file_name = 'Ledger-'.str_replace(' ', '-', $contact->name).'-'.$start_date.'-'.$end_date.'.pdf';
            $for_pdf = true;
            if ($format == 'format_2') {
                $html = view('contact.ledger_format_2')
                        ->with(compact('ledger_details', 'contact', 'for_pdf', 'location'))->render();
            } elseif ($format == 'format_3') {
                $html = view('contact.ledger_format_3')
                    ->with(compact('ledger_details', 'contact', 'location', 'is_admin', 'for_pdf'))->render();
            } else {
                $html = view('contact.ledger')
                    ->with(compact('ledger_details', 'contact', 'for_pdf', 'location'))->render();
            }

            $mpdf = $this->getMpdf();
            $mpdf->WriteHTML($html);
            $mpdf->Output($output_file_name, 'I');
        }

        // W1-B3 MWART branch — Inertia render quando flag cliente_ledger liga.
        if ($this->shouldRenderInertiaCliente('cliente_ledger', (int) $business_id) && request()->input('action') !== 'pdf') {
            $lines = collect($ledger_details['ledger'] ?? [])->map(function ($line) {
                return [
                    'date' => $line['date'] ?? null,
                    'ref_no' => $line['ref_no'] ?? '',
                    'description' => $line['type'] ?? $line['description'] ?? '',
                    'debit' => (float) ($line['debit'] ?? 0),
                    'credit' => (float) ($line['credit'] ?? 0),
                    'balance' => (float) ($line['balance'] ?? 0),
                    'payment_method' => $line['payment_method'] ?? null,
                    'doc_type' => (string) ($line['doc_type'] ?? 'invoice'),
                ];
            })->all();

            return Inertia::render('Cliente/Ledger', [
                'contact' => [
                    'id' => (int) $contact->id,
                    'name' => (string) $contact->name,
                    'tax_number_masked' => $this->maskTaxNumber($contact->tax_number ?? null),
                    'mobile' => $contact->mobile ?? null,
                    'email' => $contact->email ?? null,
                    'opening_balance' => (float) ($contact->opening_balance ?? 0),
                    'current_balance' => (float) ($ledger_details['balance_due'] ?? 0),
                ],
                'ledger' => [
                    'lines' => $lines,
                    'total_debit' => (float) ($ledger_details['total_debit'] ?? array_sum(array_column($lines, 'debit'))),
                    'total_credit' => (float) ($ledger_details['total_credit'] ?? array_sum(array_column($lines, 'credit'))),
                    'balance' => (float) ($ledger_details['balance_due'] ?? 0),
                ],
                'filters' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'format' => $format,
                    'location_id' => $location_id ? (int) $location_id : null,
                ],
                'locations' => BusinessLocation::where('business_id', $business_id)
                    ->get(['id', 'name'])
                    ->map(fn ($l) => ['id' => (int) $l->id, 'name' => (string) $l->name])
                    ->all(),
            ]);
        }

        if ($format == 'format_2') {
            return view('contact.ledger_format_2')
             ->with(compact('ledger_details', 'contact', 'location'));
        } elseif ($format == 'format_3') {
            return view('contact.ledger_format_3')
             ->with(compact('ledger_details', 'contact', 'location', 'is_admin'));
        } else {
            return view('contact.ledger')
             ->with(compact('ledger_details', 'contact', 'location', 'is_admin'));
        }
    }

    public function postCustomersApi(Request $request)
    {
        try {
            $api_token = $request->header('API-TOKEN');

            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $business = Business::find($api_settings->business_id);

            $data = $request->only(['name', 'email']);

            $customer = Contact::where('business_id', $api_settings->business_id)
                                ->where('email', $data['email'])
                                ->whereIn('type', ['customer', 'both'])
                                ->first();

            if (empty($customer)) {
                $data['type'] = 'customer';
                $data['business_id'] = $api_settings->business_id;
                $data['created_by'] = $business->owner_id;
                $data['mobile'] = 0;

                $ref_count = $this->commonUtil->setAndGetReferenceCount('contacts', $business->id);

                $data['contact_id'] = $this->commonUtil->generateReferenceNumber('contacts', $ref_count, $business->id);

                $customer = Contact::create($data);
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($customer);
    }

    /**
     * Function to send ledger notification
     */
    public function sendLedger(Request $request)
    {
        $notAllowed = $this->notificationUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $data = $request->only(['to_email', 'subject', 'email_body', 'cc', 'bcc', 'ledger_format']);
            $emails_array = array_map('trim', explode(',', $data['to_email']));

            $contact_id = $request->input('contact_id');
            $business_id = request()->session()->get('business.id');

            $start_date = request()->input('start_date');
            $end_date = request()->input('end_date');
            $location_id = request()->input('location_id');

            $contact = Contact::find($contact_id);

            $ledger_details = $this->transactionUtil->getLedgerDetails($contact_id, $start_date, $end_date, $data['ledger_format'], $location_id);

            $orig_data = [
                'email_body' => $data['email_body'],
                'subject' => $data['subject'],
            ];

            $tag_replaced_data = $this->notificationUtil->replaceTags($business_id, $orig_data, null, $contact);
            $data['email_body'] = $tag_replaced_data['email_body'];
            $data['subject'] = $tag_replaced_data['subject'];

            //replace balance_due
            $data['email_body'] = str_replace('{balance_due}', $this->notificationUtil->num_f($ledger_details['balance_due']), $data['email_body']);

            $data['email_settings'] = request()->session()->get('business.email_settings');

            $for_pdf = true;
            if ($data['ledger_format'] == 'format_2') {
                $html = view('contact.ledger_format_2')
                        ->with(compact('ledger_details', 'contact', 'for_pdf'))->render();
            } else {
                $html = view('contact.ledger')
                        ->with(compact('ledger_details', 'contact', 'for_pdf'))->render();
            }

            $mpdf = $this->getMpdf();
            $mpdf->WriteHTML($html);

            $path = config('constants.mpdf_temp_path');
            if (! file_exists($path)) {
                mkdir($path, 0777, true);
            }

            $file = $path.'/'.time().'_ledger.pdf';
            $mpdf->Output($file, 'F');

            $data['attachment'] = $file;
            $data['attachment_name'] = 'ledger.pdf';
            \Notification::route('mail', $emails_array)
                    ->notify(new CustomerNotification($data));

            if (file_exists($file)) {
                unlink($file);
            }

            $output = ['success' => 1, 'msg' => __('lang_v1.notification_sent_successfully')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => 'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage(),
            ];
        }

        return $output;
    }

    /**
     * Function to get product stock details for a supplier
     */
    public function getSupplierStockReport($supplier_id)
    {
        //TODO: current stock not calculating stock transferred from other location
        $pl_query_string = $this->commonUtil->get_pl_quantity_sum_string();
        $query = PurchaseLine::join('transactions as t', 't.id', '=', 'purchase_lines.transaction_id')
                        ->join('products as p', 'p.id', '=', 'purchase_lines.product_id')
                        ->join('variations as v', 'v.id', '=', 'purchase_lines.variation_id')
                        ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
                        ->join('units as u', 'p.unit_id', '=', 'u.id')
                        ->whereIn('t.type', ['purchase', 'purchase_return'])
                        ->where('t.contact_id', $supplier_id)
                        ->select(
                            'p.name as product_name',
                            'v.name as variation_name',
                            'pv.name as product_variation_name',
                            'p.type as product_type',
                            'u.short_name as product_unit',
                            'v.sub_sku',
                            DB::raw('SUM(quantity) as purchase_quantity'),
                            DB::raw('SUM(quantity_returned) as total_quantity_returned'),
                            DB::raw("SUM((SELECT SUM(TSL.quantity - TSL.quantity_returned) FROM transaction_sell_lines_purchase_lines as TSLPL 
                              JOIN transaction_sell_lines AS TSL ON TSLPL.sell_line_id=TSL.id
                              JOIN transactions AS sell ON sell.id=TSL.transaction_id
                              WHERE sell.status='final' AND sell.type='sell'
                              AND TSLPL.purchase_line_id=purchase_lines.id)) as total_quantity_sold"),
                            DB::raw("SUM((SELECT SUM(TSL.quantity - TSL.quantity_returned) FROM transaction_sell_lines_purchase_lines as TSLPL 
                              JOIN transaction_sell_lines AS TSL ON TSLPL.sell_line_id=TSL.id
                              JOIN transactions AS sell ON sell.id=TSL.transaction_id
                              WHERE sell.status='final' AND sell.type='sell_transfer'
                              AND TSLPL.purchase_line_id=purchase_lines.id)) as total_quantity_transfered"),
                            DB::raw("SUM( COALESCE(quantity - ($pl_query_string), 0) * purchase_price_inc_tax) as stock_price"),
                            DB::raw("SUM( COALESCE(quantity - ($pl_query_string), 0)) as current_stock")
                        )->groupBy('purchase_lines.variation_id');

        if (! empty(request()->location_id)) {
            $query->where('t.location_id', request()->location_id);
        }

        $product_stocks = Datatables::of($query)
                            ->editColumn('product_name', function ($row) {
                                $name = $row->product_name;
                                if ($row->product_type == 'variable') {
                                    $name .= ' - '.$row->product_variation_name.'-'.$row->variation_name;
                                }

                                return $name.' ('.$row->sub_sku.')';
                            })
                            ->editColumn('purchase_quantity', function ($row) {
                                $purchase_quantity = 0;
                                if ($row->purchase_quantity) {
                                    $purchase_quantity = (float) $row->purchase_quantity;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$purchase_quantity.'" data-unit="'.$row->product_unit.'" >'.$purchase_quantity.'</span> '.$row->product_unit;
                            })
                            ->editColumn('total_quantity_sold', function ($row) {
                                $total_quantity_sold = 0;
                                if ($row->total_quantity_sold) {
                                    $total_quantity_sold = (float) $row->total_quantity_sold;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$total_quantity_sold.'" data-unit="'.$row->product_unit.'" >'.$total_quantity_sold.'</span> '.$row->product_unit;
                            })
                            ->editColumn('total_quantity_transfered', function ($row) {
                                $total_quantity_transfered = 0;
                                if ($row->total_quantity_transfered) {
                                    $total_quantity_transfered = (float) $row->total_quantity_transfered;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$total_quantity_transfered.'" data-unit="'.$row->product_unit.'" >'.$total_quantity_transfered.'</span> '.$row->product_unit;
                            })
                            ->editColumn('stock_price', function ($row) {
                                $stock_price = 0;
                                if ($row->stock_price) {
                                    $stock_price = (float) $row->stock_price;
                                }

                                return '<span class="display_currency" data-currency_symbol=true >'.$stock_price.'</span> ';
                            })
                            ->editColumn('current_stock', function ($row) {
                                $current_stock = 0;
                                if ($row->current_stock) {
                                    $current_stock = (float) $row->current_stock;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$current_stock.'" data-unit="'.$row->product_unit.'" >'.$current_stock.'</span> '.$row->product_unit;
                            });

        return $product_stocks->rawColumns(['current_stock', 'stock_price', 'total_quantity_sold', 'purchase_quantity', 'total_quantity_transfered'])->make(true);
    }

    public function updateStatus($id)
    {
        if (! auth()->user()->can('supplier.update') && ! auth()->user()->can('customer.update')) {
            abort(403, 'Unauthorized action.');
        }

        if ($this->isLegacyAjax()) {
            $business_id = request()->session()->get('user.business_id');
            $contact = Contact::where('business_id', $business_id)->find($id);
            $contact->contact_status = $contact->contact_status == 'active' ? 'inactive' : 'active';
            $contact->save();

            $output = ['success' => true,
                'msg' => __('contact.updated_success'),
            ];

            return $output;
        }
    }

    /**
     * Display contact locations on map
     */
    public function contactMap()
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $query = Contact::where('business_id', $business_id)
                        ->active()
                        ->whereNotNull('position');

        if (! empty(request()->input('contacts'))) {
            $query->whereIn('id', request()->input('contacts'));
        }
        $contacts = $query->get();

        $all_contacts = Contact::where('business_id', $business_id)
                        ->active()
                        ->get();

        // W1-B3 MWART branch — Inertia render quando flag cliente_map liga.
        if ($this->shouldRenderInertiaCliente('cliente_map', (int) $business_id)) {
            $mapContacts = $contacts->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'position' => $c->position,
                'city' => $c->city ?? null,
                'state' => $c->state ?? null,
                'mobile' => $c->mobile ?? null,
            ])->all();
            $mapAll = $all_contacts->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'position' => $c->position,
                'city' => $c->city ?? null,
                'state' => $c->state ?? null,
                'mobile' => $c->mobile ?? null,
            ])->all();

            return Inertia::render('Cliente/Map', [
                'contacts' => $mapContacts,
                'all_contacts' => $mapAll,
            ]);
        }

        return view('contact.contact_map')
             ->with(compact('contacts', 'all_contacts'));
    }

    public function getContactPayments($contact_id)
    {
        $business_id = request()->session()->get('user.business_id');
        if ($this->isLegacyAjax()) {
            $payments = TransactionPayment::leftjoin('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
            ->leftjoin('transaction_payments as parent_payment', 'transaction_payments.parent_id', '=', 'parent_payment.id')
            ->where('transaction_payments.business_id', $business_id)
            ->whereNull('transaction_payments.parent_id')
            ->with(['child_payments', 'child_payments.transaction'])
            ->where('transaction_payments.payment_for', $contact_id)
                ->select(
                    'transaction_payments.id',
                    'transaction_payments.amount',
                    'transaction_payments.is_return',
                    'transaction_payments.method',
                    'transaction_payments.paid_on',
                    'transaction_payments.payment_ref_no',
                    'transaction_payments.parent_id',
                    'transaction_payments.transaction_no',
                    't.invoice_no',
                    't.ref_no',
                    't.type as transaction_type',
                    't.return_parent_id',
                    't.id as transaction_id',
                    'transaction_payments.cheque_number',
                    'transaction_payments.card_transaction_number',
                    'transaction_payments.bank_account_number',
                    'transaction_payments.id as DT_RowId',
                    'parent_payment.payment_ref_no as parent_payment_ref_no'
                )
                ->groupBy('transaction_payments.id')
                ->orderByDesc('transaction_payments.paid_on')
                ->paginate();

            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

            return view('contact.partials.contact_payments_tab')
                    ->with(compact('payments', 'payment_types'));
        }
    }

    public function getContactDue($contact_id)
    {
        if ($this->isLegacyAjax()) {
            $business_id = request()->session()->get('user.business_id');
            $due = $this->transactionUtil->getContactDue($contact_id, $business_id);

            $output = $due != 0 ? $this->transactionUtil->num_f($due, true) : '';

            return $output;
        }
    }

    public function checkMobile(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        $mobile_number = trim((string) $request->input('mobile_number'));

        if ($mobile_number === '') {
            return ['is_mobile_exists' => false, 'msg' => ''];
        }

        $query = Contact::where('business_id', $business_id)
                        ->where('mobile', 'like', "%{$mobile_number}");

        if (! empty($request->input('contact_id'))) {
            $query->where('id', '!=', $request->input('contact_id'));
        }

        $contacts = $query->pluck('name')->toArray();

        return [
            'is_mobile_exists' => ! empty($contacts),
            'msg' => __('lang_v1.mobile_already_registered', ['contacts' => implode(', ', $contacts), 'mobile' => $mobile_number]),
        ];
    }
}
