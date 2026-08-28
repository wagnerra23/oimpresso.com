<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Charts\CommonChart;
use App\Currency;
use App\Media;
use App\Services\Dashboard\GradesDoPainelService;
use App\Transaction;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\RestaurantUtil;
use App\Utils\TransactionUtil;
use App\Utils\ProductUtil;
use App\Utils\Util;
use App\VariationLocationDetails;
use Datatables;
use DB;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;

class HomeController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $businessUtil;

    protected $transactionUtil;

    protected $moduleUtil;

    protected $commonUtil;

    protected $restUtil;
    protected $productUtil;

    /** Grades das abas do painel Inertia (US-DASH-005). Não toca o caminho Blade. */
    protected GradesDoPainelService $grades;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        BusinessUtil $businessUtil,
        TransactionUtil $transactionUtil,
        ModuleUtil $moduleUtil,
        Util $commonUtil,
        RestaurantUtil $restUtil,
        ProductUtil $productUtil,
        GradesDoPainelService $grades,
    ) {
        $this->grades = $grades;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->commonUtil = $commonUtil;
        $this->restUtil = $restUtil;
        $this->productUtil = $productUtil;
    }

    /**
     * Show the application dashboard.
     *
     * F6 Soft wrapper Inertia (US-DASH-001 — 2026-05-21).
     * Default = Inertia React shell minimal (welcome + 4 KPI cards + filtro loja).
     *
     * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): business_id de session.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        if ($user->user_type == 'user_customer') {
            return redirect()->action([\Modules\Crm\Http\Controllers\DashboardController::class, 'index']);
        }

        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->businessUtil->is_admin(auth()->user());

        $can_dashboard_data = (bool) auth()->user()->can('dashboard.data');

        [$pStart, $pEnd, $pPreset] = $this->resolvePeriod($business_id);
        $location_id = request()->query('location_id') ?: null;
        $period = ['from' => $pStart, 'to' => $pEnd, 'preset' => $pPreset];

        $totals = null;
        $deltas = null;
        if ($can_dashboard_data) {
            // US-DASH-004 — periodo escolhido pelo usuario. Default = FY corrente, entao
            // quem nao mexe no filtro ve exatamente o que via antes (zero mudanca de valor).
            [$start, $end] = [$pStart, $pEnd];

            // O filtro de loja existia na UI desde a v2 mas o argumento nunca era passado:
            // trocar de loja devolvia os MESMOS 8 numeros. Provado por controle positivo no
            // CT 100 — getSellTotals(...,999999) = 0,00 contra 200,00 sem filtro.
            $sell_details = $this->transactionUtil->getSellTotals($business_id, $start, $end, $location_id);
            $purchase_details = $this->transactionUtil->getPurchaseTotals($business_id, $start, $end, $location_id);

            $total_ledger_discount = $this->transactionUtil->getTotalLedgerDiscount($business_id, $start, $end);

            $transaction_totals = $this->transactionUtil->getTransactionTotals(
                $business_id,
                ['expense', 'sell_return', 'purchase_return'],
                $start,
                $end,
                $location_id
            );

            $total_sell = (float) ($sell_details['total_sell_inc_tax'] ?? 0);
            $invoice_due = (float) (($sell_details['invoice_due'] ?? 0) - ($total_ledger_discount['total_sell_discount'] ?? 0));
            $total_expense = (float) ($transaction_totals['total_expense'] ?? 0);

            $total_purchase = (float) ($purchase_details['total_purchase_inc_tax'] ?? 0);
            $purchase_due = (float) (($purchase_details['purchase_due'] ?? 0) - ($total_ledger_discount['total_purchase_discount'] ?? 0));
            $total_sell_return = (float) ($transaction_totals['total_sell_return_inc_tax'] ?? 0);
            $total_purchase_return = (float) ($transaction_totals['total_purchase_return_inc_tax'] ?? 0);

            $totals = [
                'total_sell' => $total_sell,
                'net' => $total_sell - $invoice_due - $total_expense,
                'invoice_due' => $invoice_due,
                'total_expense' => $total_expense,
                'total_purchase' => $total_purchase,
                'purchase_due' => $purchase_due,
                'total_sell_return' => $total_sell_return,
                'total_purchase_return' => $total_purchase_return,
            ];

            // Delta vs periodo ANTERIOR de mesma duracao (o protótipo mostra isso com mock;
            // aqui e calculado). Regra do design: metrica onde SUBIR e ruim (a receber,
            // despesas) nao ganha o delta colorido — a cor do DS sai do sinal, nao do sentido.
            $dias = max(1, (int) ((strtotime($end) - strtotime($start)) / 86400) + 1);
            $antStart = date('Y-m-d', strtotime($start . ' -' . $dias . ' days'));
            $antEnd = date('Y-m-d', strtotime($start . ' -1 day'));
            $ant = $this->periodTotals($business_id, $antStart, $antEnd, $location_id);

            $pct = static function (float $atual, float $anterior): ?int {
                if (abs($anterior) < 0.01) {
                    return null; // sem base de comparacao — nao inventa "+100%"
                }

                return (int) round((($atual - $anterior) / abs($anterior)) * 100);
            };

            // So compara janelas COMENSURAVEIS: os presets rolantes tem a mesma duracao e
            // ambos no passado. O FY corrente vai ate 31/12 — comparar um ano pela metade
            // com um ano fechado da -95% e nao significa nada. Sem base justa, sem delta.
            $comparavel = in_array($period['preset'], ['dia', 'semana', 'mes'], true);

            $deltas = $comparavel ? [
                'net' => $pct($totals['net'], $ant['net']),
                'total_sell' => $pct($totals['total_sell'], $ant['total_sell']),
                'invoice_due' => $pct($totals['invoice_due'], $ant['invoice_due']),
                'total_expense' => $pct($totals['total_expense'], $ant['total_expense']),
            ] : null;
        }

        // US-DASH-005 — abas de grade. No Blade legado TODAS as grades vivem dentro do
        // `@if(can('dashboard.data'))` que abre na linha 369 e fecha na 1013 (medido em
        // 2026-08-27), e cada uma tem ainda o seu proprio `@can`. As duas camadas são
        // reproduzidas aqui: sem `dashboard.data` não há aba nenhuma; com ela, cada aba
        // ainda depende da permissão e do setting dela.
        $abas = $can_dashboard_data ? $this->grades->abasPermitidas() : [];
        $aba = $can_dashboard_data ? $this->grades->resolverAba(request()->query('aba')) : null;

        return Inertia::render('Home/Index', [
            'user_name' => (string) request()->session()->get('user.first_name', ''),
            'is_admin' => (bool) $is_admin,
            'can_dashboard_data' => $can_dashboard_data,
            // closure D-14: dropdown por business, não muda com filtro — pula no partial reload
            'all_locations' => fn () => BusinessLocation::forDropdown($business_id)->toArray(),
            'totals' => $totals,
            'period' => $period,
            'deltas' => $deltas,
            // gate MESMO de `totals`: sem `dashboard.data` a prop nem e registrada, entao um
            // partial reload pedindo `charts` nao tem closure pra executar. A FICHA BL-home-index
            // declara "sem dashboard.data o controller devolve a casca" — isto e a casca.
            'charts' => $can_dashboard_data
                ? Inertia::defer(fn () => $this->buildChartsPayload($business_id, $location_id))
                : null,
            'abas' => $abas,
            'aba' => $aba,
            // Só a aba ABERTA consulta o banco, e só quando o Inertia pedir: 8 grades
            // carregadas de uma vez seriam 8 queries por render, contra o alvo de
            // first-paint <= 800ms do charter. `defer` mantém uma query por troca de aba.
            //
            // O cast de `$location_id` acontece AQUI, e não na leitura da query string: a
            // linha que a lê é do caminho dos KPI/charts, e o service tipa `?int`.
            'grade' => $aba === null ? null : Inertia::defer(
                fn () => $this->grades->linhas(
                    $aba,
                    $business_id,
                    $location_id ? (int) $location_id : null,
                    request()->integer('page', 1) ?: 1
                )
            ),
            'endpoints' => [
                'totals' => '/home/get-totals',
                'stock_alert' => '/home/product-stock-alert',
                'purchase_dues' => '/home/purchase-payment-dues',
                'sales_dues' => '/home/sales-payment-dues',
            ],
        ]);
    }

    /**
     * Series dos 2 graficos da Visao geral (US-DASH-002).
     *
     * Fonte: `getSellsCurrentFy` — a MESMA que o Blade legado usa pros charts dele.
     * Uma verdade so: se o legado e a tela nova divergirem, e bug, nao interpretacao.
     *
     * @return array{dia: list<array{label: string, value: float}>, mes: list<array{label: string, value: float}>}
     */
    private function buildChartsPayload(int $business_id, $location_id = null): array
    {
        $fy = $this->businessUtil->getCurrentFinancialYear($business_id);
        $desde = \Carbon::parse($fy['start'])->subDays(30)->format('Y-m-d');
        $sells = $this->transactionUtil->getSellsCurrentFy($business_id, $desde, $fy['end']);

        $porDia = [];
        for ($i = 29; $i >= 0; $i--) {
            $dia = \Carbon::now()->subDays($i)->format('Y-m-d');
            $q = $sells->where('date', $dia);
            if ($location_id) {
                $q = $q->where('location_id', (int) $location_id);
            }
            $porDia[] = [
                'label' => date('d/m', strtotime($dia)),
                'value' => (float) $q->sum('total_sells'),
            ];
        }

        // `yearmonth` vem do SELECT como DATE_FORMAT(...,'%m-%Y') -> "08-2026". Comparar com
        // \Carbon::format('Y-m') ("2026-08") NUNCA casa e o grafico sai 12 barras zeradas.
        // O Blade legado (removido em 2026-08-28) usava date('m-Y', ...) — aqui e o MESMO formato.
        // Rotulo: array literal do prototipo (dash-legacy-page.jsx), nao locale — deterministico.
        $MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        $porMes = [];
        $cursor = \Carbon::parse($fy['start'])->startOfMonth();
        $fim = \Carbon::parse($fy['end'])->startOfMonth();
        while ($cursor->lte($fim)) {
            $q = $sells->where('yearmonth', $cursor->format('m-Y'));
            if ($location_id) {
                $q = $q->where('location_id', (int) $location_id);
            }
            $porMes[] = [
                'label' => $MESES[$cursor->month - 1],
                'value' => (float) $q->sum('total_sells'),
            ];
            $cursor->addMonth();
        }

        return ['dia' => $porDia, 'mes' => $porMes];
    }

    /**
     * Totais de um periodo — o MESMO caminho que o painel usa, chamado tambem pro periodo
     * anterior. Uma formula so: se o painel mudar, o delta muda junto (nao ha 2a verdade).
     *
     * @return array{net: float, total_sell: float, invoice_due: float, total_expense: float}
     */
    private function periodTotals(int $business_id, string $start, string $end, $location_id = null): array
    {
        $sell = $this->transactionUtil->getSellTotals($business_id, $start, $end, $location_id);
        $led = $this->transactionUtil->getTotalLedgerDiscount($business_id, $start, $end);
        $tt = $this->transactionUtil->getTransactionTotals(
            $business_id,
            ['expense'],
            $start,
            $end,
            $location_id
        );

        $totalSell = (float) ($sell['total_sell_inc_tax'] ?? 0);
        $invoiceDue = (float) (($sell['invoice_due'] ?? 0) - ($led['total_sell_discount'] ?? 0));
        $expense = (float) ($tt['total_expense'] ?? 0);

        return [
            'net' => $totalSell - $invoiceDue - $expense,
            'total_sell' => $totalSell,
            'invoice_due' => $invoiceDue,
            'total_expense' => $expense,
        ];
    }

    /**
     * Resolve a janela do painel (US-DASH-004).
     *
     * Default = ano fiscal corrente: quem NAO mexe no filtro ve exatamente os mesmos 8
     * numeros de antes. Presets sao janelas ROLANTES relativas a hoje, iguais as do
     * prototipo: dia = hoje..hoje · semana = hoje-6..hoje · mes = hoje-29..hoje.
     * `from`/`to` explicitos vencem o preset.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolvePeriod(int $business_id): array
    {
        $from = request()->query('from');
        $to = request()->query('to');

        if ($from && $to && strtotime((string) $from) && strtotime((string) $to)) {
            return [date('Y-m-d', strtotime((string) $from)), date('Y-m-d', strtotime((string) $to)), 'custom'];
        }

        $hoje = date('Y-m-d');

        switch (request()->query('preset')) {
            case 'dia':
                return [$hoje, $hoje, 'dia'];
            case 'semana':
                return [date('Y-m-d', strtotime('-6 days')), $hoje, 'semana'];
            case 'mes':
                return [date('Y-m-d', strtotime('-29 days')), $hoje, 'mes'];
        }

        $fy = $this->businessUtil->getCurrentFinancialYear($business_id);

        return [$fy['start'], $fy['end'], 'fy'];
    }

    /**
     * Retrieves purchase and sell details for a given time period.
     *
     * @return \Illuminate\Http\Response
     */
    public function getTotals()
    {
        if (request()->ajax()) {
            $start = request()->start;
            $end = request()->end;
            $location_id = request()->location_id;
            $business_id = request()->session()->get('user.business_id');

            // get user id parameter
            $created_by = request()->user_id;

            $purchase_details = $this->transactionUtil->getPurchaseTotals($business_id, $start, $end, $location_id, $created_by);

            $sell_details = $this->transactionUtil->getSellTotals($business_id, $start, $end, $location_id, $created_by);

            $total_ledger_discount = $this->transactionUtil->getTotalLedgerDiscount($business_id, $start, $end);

            $purchase_details['purchase_due'] = $purchase_details['purchase_due'] - $total_ledger_discount['total_purchase_discount'];

            $transaction_types = [
                'purchase_return', 'sell_return', 'expense',
            ];

            $transaction_totals = $this->transactionUtil->getTransactionTotals(
                $business_id,
                $transaction_types,
                $start,
                $end,
                $location_id,
                $created_by
            );

            $total_purchase_inc_tax = ! empty($purchase_details['total_purchase_inc_tax']) ? $purchase_details['total_purchase_inc_tax'] : 0;
            $total_purchase_return_inc_tax = $transaction_totals['total_purchase_return_inc_tax'];

            $output = $purchase_details;
            $output['total_purchase'] = $total_purchase_inc_tax;
            $output['total_purchase_return'] = $total_purchase_return_inc_tax;
            $output['total_purchase_return_paid'] = $this->transactionUtil->getTotalPurchaseReturnPaid($business_id, $start, $end, $location_id);

            $total_sell_inc_tax = ! empty($sell_details['total_sell_inc_tax']) ? $sell_details['total_sell_inc_tax'] : 0;
            $total_sell_return_inc_tax = ! empty($transaction_totals['total_sell_return_inc_tax']) ? $transaction_totals['total_sell_return_inc_tax'] : 0;
            $output['total_sell_return_paid'] = $this->transactionUtil->getTotalSellReturnPaid($business_id, $start, $end, $location_id);

            $output['total_sell'] = $total_sell_inc_tax;
            $output['total_sell_return'] = $total_sell_return_inc_tax;

            $output['invoice_due'] = $sell_details['invoice_due'] - $total_ledger_discount['total_sell_discount'];
            $output['total_expense'] = $transaction_totals['total_expense'];

            //NET = TOTAL SALES - INVOICE DUE - EXPENSE
            $output['net'] = $output['total_sell'] - $output['invoice_due'] - $output['total_expense'];

            return $output;
        }
    }

    /**
     * Retrieves sell products whose available quntity is less than alert quntity.
     *
     * @return \Illuminate\Http\Response
     */
    public function getProductStockAlert()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $permitted_locations = auth()->user()->permitted_locations();
            $products = $this->productUtil->getProductAlert($business_id, $permitted_locations);

            return Datatables::of($products)
                ->editColumn('product', function ($row) {
                    if ($row->type == 'single') {
                        return $row->product.' ('.$row->sku.')';
                    } else {
                        return $row->product.' - '.$row->product_variation.' - '.$row->variation.' ('.$row->sub_sku.')';
                    }
                })
                ->editColumn('stock', function ($row) {
                    $stock = $row->stock ? $row->stock : 0;

                    return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false>'.(float) $stock.'</span> '.$row->unit;
                })
                ->removeColumn('sku')
                ->removeColumn('sub_sku')
                ->removeColumn('unit')
                ->removeColumn('type')
                ->removeColumn('product_variation')
                ->removeColumn('variation')
                ->rawColumns([2])
                ->make(false);
        }
    }

    /**
     * Retrieves payment dues for the purchases.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPurchasePaymentDues()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $today = \Carbon::now()->format('Y-m-d H:i:s');

            $query = Transaction::join(
                'contacts as c',
                'transactions.contact_id',
                '=',
                'c.id'
            )
                    ->leftJoin(
                        'transaction_payments as tp',
                        'transactions.id',
                        '=',
                        'tp.transaction_id'
                    )
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'purchase')
                    ->where('transactions.payment_status', '!=', 'paid')
                    ->whereRaw("DATEDIFF( DATE_ADD( transaction_date, INTERVAL IF(transactions.pay_term_type = 'days', transactions.pay_term_number, 30 * transactions.pay_term_number) DAY), '$today') <= 7");

            //Check for permitted locations of a user
            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->input('location_id'))) {
                $query->where('transactions.location_id', request()->input('location_id'));
            }

            $dues = $query->select(
                'transactions.id as id',
                'c.name as supplier',
                'c.supplier_business_name',
                'ref_no',
                'final_total',
                DB::raw('SUM(tp.amount) as total_paid')
            )
                        ->groupBy('transactions.id');

            return Datatables::of($dues)
                ->addColumn('due', function ($row) {
                    $total_paid = ! empty($row->total_paid) ? $row->total_paid : 0;
                    $due = $row->final_total - $total_paid;

                    return '<span class="display_currency" data-currency_symbol="true">'.
                    $due.'</span>';
                })
                ->addColumn('action', '@can("purchase.create") <a href="{{action([\App\Http\Controllers\TransactionPaymentController::class, \'addPayment\'], [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent add_payment_modal"><i class="fas fa-money-bill-alt"></i> @lang("purchase.add_payment")</a> @endcan')
                ->removeColumn('supplier_business_name')
                ->editColumn('supplier', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$supplier}}')
                ->editColumn('ref_no', function ($row) {
                    if (auth()->user()->can('purchase.view')) {
                        return  '<a href="#" data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->id]).'"
                                    class="btn-modal" data-container=".view_modal">'.$row->ref_no.'</a>';
                    }

                    return $row->ref_no;
                })
                ->removeColumn('id')
                ->removeColumn('final_total')
                ->removeColumn('total_paid')
                ->rawColumns([0, 1, 2, 3])
                ->make(false);
        }
    }

    /**
     * Retrieves payment dues for the purchases.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSalesPaymentDues()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $today = \Carbon::now()->format('Y-m-d H:i:s');

            $query = Transaction::join(
                'contacts as c',
                'transactions.contact_id',
                '=',
                'c.id'
            )
                    ->leftJoin(
                        'transaction_payments as tp',
                        'transactions.id',
                        '=',
                        'tp.transaction_id'
                    )
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'sell')
                    ->where('transactions.payment_status', '!=', 'paid')
                    ->whereNotNull('transactions.pay_term_number')
                    ->whereNotNull('transactions.pay_term_type')
                    ->whereRaw("DATEDIFF( DATE_ADD( transaction_date, INTERVAL IF(transactions.pay_term_type = 'days', transactions.pay_term_number, 30 * transactions.pay_term_number) DAY), '$today') <= 7");

            //Check for permitted locations of a user
            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->input('location_id'))) {
                $query->where('transactions.location_id', request()->input('location_id'));
            }

            $dues = $query->select(
                'transactions.id as id',
                'c.name as customer',
                'c.supplier_business_name',
                'transactions.invoice_no',
                'final_total',
                DB::raw('SUM(tp.amount) as total_paid')
            )
                        ->groupBy('transactions.id');

            return Datatables::of($dues)
                ->addColumn('due', function ($row) {
                    $total_paid = ! empty($row->total_paid) ? $row->total_paid : 0;
                    $due = $row->final_total - $total_paid;

                    return '<span class="display_currency" data-currency_symbol="true">'.
                    $due.'</span>';
                })
                ->editColumn('invoice_no', function ($row) {
                    if (auth()->user()->can('sell.view')) {
                        return  '<a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]).'"
                                    class="btn-modal" data-container=".view_modal">'.$row->invoice_no.'</a>';
                    }

                    return $row->invoice_no;
                })
                ->addColumn('action', '@if(auth()->user()->can("sell.create") || auth()->user()->can("direct_sell.access")) <a href="{{action([\App\Http\Controllers\TransactionPaymentController::class, \'addPayment\'], [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent add_payment_modal"><i class="fas fa-money-bill-alt"></i> @lang("purchase.add_payment")</a> @endif')
                ->editColumn('customer', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$customer}}')
                ->removeColumn('supplier_business_name')
                ->removeColumn('id')
                ->removeColumn('final_total')
                ->removeColumn('total_paid')
                ->rawColumns([0, 1, 2, 3])
                ->make(false);
        }
    }

    public function loadMoreNotifications()
    {
        $notifications = auth()->user()->notifications()->orderBy('created_at', 'desc')->paginate(10);

        if (request()->input('page') == 1) {
            auth()->user()->unreadNotifications->markAsRead();
        }
        $notifications_data = $this->commonUtil->parseNotifications($notifications);

        return view('layouts.partials.notification_list', compact('notifications_data'));
    }

    /**
     * Function to count total number of unread notifications
     *
     * @return json
     */
    public function getTotalUnreadNotifications()
    {
        $unread_notifications = auth()->user()->unreadNotifications;
        $total_unread = $unread_notifications->count();

        $notification_html = '';
        $modal_notifications = [];
        foreach ($unread_notifications as $unread_notification) {
            if (isset($data['show_popup'])) {
                $modal_notifications[] = $unread_notification;
                $unread_notification->markAsRead();
            }
        }
        if (! empty($modal_notifications)) {
            $notification_html = view('home.notification_modal')->with(['notifications' => $modal_notifications])->render();
        }

        return [
            'total_unread' => $total_unread,
            'notification_html' => $notification_html,
        ];
    }

    public function getCalendar()
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->restUtil->is_admin(auth()->user(), $business_id);
        $is_superadmin = auth()->user()->can('superadmin');
        if (request()->ajax()) {
            $data = [
                'start_date' => request()->start,
                'end_date' => request()->end,
                'user_id' => ($is_admin || $is_superadmin) && ! empty(request()->user_id) ? request()->user_id : auth()->user()->id,
                'location_id' => ! empty(request()->location_id) ? request()->location_id : null,
                'business_id' => $business_id,
                'events' => request()->events ?? [],
                'color' => '#007FFF',
            ];
            $events = [];

            if (in_array('bookings', $data['events'])) {
                $events = $this->restUtil->getBookingsForCalendar($data);
            }

            $module_events = $this->moduleUtil->getModuleData('calendarEvents', $data);

            foreach ($module_events as $module_event) {
                $events = array_merge($events, $module_event);
            }

            return $events;
        }

        $all_locations = BusinessLocation::forDropdown($business_id)->toArray();
        $users = [];
        if ($is_admin) {
            $users = User::forDropdown($business_id, false);
        }

        $event_types = [
            'bookings' => [
                'label' => __('restaurant.bookings'),
                'color' => '#007FFF',
            ],
        ];
        $module_event_types = $this->moduleUtil->getModuleData('eventTypes');
        foreach ($module_event_types as $module_event_type) {
            $event_types = array_merge($event_types, $module_event_type);
        }

        return view('home.calendar')->with(compact('all_locations', 'users', 'event_types'));
    }

    public function showNotification($id)
    {
        $notification = DatabaseNotification::find($id);

        $data = $notification->data;

        $notification->markAsRead();

        return view('home.notification_modal')->with([
            'notifications' => [$notification],
        ]);
    }

    public function attachMediasToGivenModel(Request $request)
    {
        if ($request->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $model_id = $request->input('model_id');
                $model = $request->input('model_type');
                $model_media_type = $request->input('model_media_type');

                DB::beginTransaction();

                //find model to which medias are to be attached
                $model_to_be_attached = $model::where('business_id', $business_id)
                                        ->findOrFail($model_id);

                Media::uploadMedia($business_id, $model_to_be_attached, $request, 'file', false, $model_media_type);

                DB::commit();

                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (Exception $e) {
                DB::rollBack();

                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function getUserLocation($latlng)
    {
        $latlng_array = explode(',', $latlng);

        $response = $this->moduleUtil->getLocationFromCoordinates($latlng_array[0], $latlng_array[1]);

        return ['address' => $response];
    }
}
