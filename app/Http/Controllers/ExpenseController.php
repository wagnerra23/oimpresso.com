<?php

namespace App\Http\Controllers;

use App\Account;
use App\AccountTransaction;
use App\BusinessLocation;
use App\Contact;
use App\ExpenseCategory;
use App\TaxRate;
use App\Transaction;
use App\User;
use App\Utils\CashRegisterUtil;
use App\Utils\ModuleUtil;
use App\Services\FeatureFlagService;
use App\Utils\TransactionUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Yajra\DataTables\Facades\DataTables;
use App\Events\ExpenseCreatedOrModified;

class ExpenseController extends Controller
{
    /**
     * Flag do caminho React (thread financeiro/13). Convenção `useV2<Modulo><Tela>`.
     * Sem a chave no GrowthBook o FeatureFlagService cai no fallbackDefaults, que não a lista:
     * default OFF. Não ligar antes da US-MWART-017/018 (drawer, ações e nova despesa).
     *
     * @see memory/requisitos/Financeiro/RUNBOOK-despesas.md
     */
    private const FLAG_V2 = 'useV2FinanceiroDespesas';

    /**
     * Constructor
     *
     * @param  TransactionUtil  $transactionUtil
     * @return void
     */
    public function __construct(TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, CashRegisterUtil $cashRegisterUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->dummyPaymentLine = ['method' => 'cash', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => '', ];
        $this->cashRegisterUtil = $cashRegisterUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('all_expense.access') && ! auth()->user()->can('view_own_expense')) {
            abort(403, 'Unauthorized action.');
        }

        // `! inertia()`: o Inertia v3 manda `X-Requested-With` em toda visita; sem esta perna o
        // partial reload da Page cairia no JSON do DataTables (mesma pegadinha do RUNBOOK-locais §10).
        // Para quem chama o DataTables (esta Blade e o relatório de despesas por representante) nada muda.
        if (request()->ajax() && ! request()->inertia()) {
            $business_id = request()->session()->get('user.business_id');

            $expenses = $this->consultaDespesas((int) $business_id);

            return Datatables::of($expenses)
                ->addColumn(
                    'action',
                    '<div class="btn-group">
                        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max dropdown-toggle" 
                            data-toggle="dropdown" aria-expanded="false"> @lang("messages.actions")<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                </span>
                        </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">
                    @if(auth()->user()->can("expense.edit"))
                        <li><a href="{{action(\'App\Http\Controllers\ExpenseController@edit\', [$id])}}"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</a></li>
                    @endif
                    @if($document)
                        <li><a href="{{ url(\'uploads/documents/\' . $document)}}" 
                        download=""><i class="fa fa-download" aria-hidden="true"></i> @lang("purchase.download_document")</a></li>
                        @if(isFileImage($document))
                            <li><a href="#" data-href="{{ url(\'uploads/documents/\' . $document)}}" class="view_uploaded_document"><i class="fas fa-file-image" aria-hidden="true"></i>@lang("lang_v1.view_document")</a></li>
                        @endif
                    @endif
                    @if(auth()->user()->can("expense.delete"))
                        <li>
                        <a href="#" data-href="{{action(\'App\Http\Controllers\ExpenseController@destroy\', [$id])}}" class="delete_expense"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</a></li>
                    @endif
                    <li class="divider"></li> 
                    @if($payment_status != "paid")
                        <li><a href="{{action([\App\Http\Controllers\TransactionPaymentController::class, \'addPayment\'], [$id])}}" class="add_payment_modal"><i class="fas fa-money-bill-alt" aria-hidden="true"></i> @lang("purchase.add_payment")</a></li>
                    @endif
                    <li><a href="{{action([\App\Http\Controllers\TransactionPaymentController::class, \'show\'], [$id])}}" class="view_payment_modal"><i class="fas fa-money-bill-alt" aria-hidden="true" ></i> @lang("purchase.view_payments")</a></li>
                    </ul></div>'
                )
                ->removeColumn('id')
                ->editColumn(
                    'final_total',
                    '<span class="display_currency final-total" data-currency_symbol="true" data-orig-value="@if($type=="expense_refund"){{-1 * $final_total}}@else{{$final_total}}@endif">@if($type=="expense_refund") - @endif @format_currency($final_total)</span>'
                )
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn(
                    'payment_status',
                    '<a href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, \'show\'], [$id])}}" class="view_payment_modal payment-status" data-orig-value="{{$payment_status}}" data-status-name="{{__(\'lang_v1.\' . $payment_status)}}"><span class="label @payment_status($payment_status)">{{__(\'lang_v1.\' . $payment_status)}}
                        </span></a>'
                )
                ->addColumn('payment_due', function ($row) {
                    $due = $row->final_total - $row->amount_paid;

                    if ($row->type == 'expense_refund') {
                        $due = -1 * $due;
                    }

                    return '<span class="display_currency payment_due" data-currency_symbol="true" data-orig-value="'.$due.'">'.$this->transactionUtil->num_f($due, true).'</span>';
                })
                ->addColumn('recur_details', function ($row) {
                    $details = '<small>';
                    if ($row->is_recurring == 1) {
                        $type = $row->recur_interval == 1 ? Str::singular(__('lang_v1.'.$row->recur_interval_type)) : __('lang_v1.'.$row->recur_interval_type);
                        $recur_interval = $row->recur_interval.$type;

                        $details .= __('lang_v1.recur_interval').': '.$recur_interval;
                        if (! empty($row->recur_repetitions)) {
                            $details .= ', '.__('lang_v1.no_of_repetitions').': '.$row->recur_repetitions;
                        }
                        if ($row->recur_interval_type == 'months' && ! empty($row->subscription_repeat_on)) {
                            $details .= '<br><small class="text-muted">'.
                            __('lang_v1.repeat_on').': '.str_ordinal($row->subscription_repeat_on);
                        }
                    } elseif (! empty($row->recur_parent_id)) {
                        $details .= __('lang_v1.recurred_from').': '.$row->recurring_parent->ref_no;
                    }
                    $details .= '</small>';

                    return $details;
                })
                ->editColumn('ref_no', function ($row) {
                    $ref_no = $row->ref_no;
                    if (! empty($row->is_recurring)) {
                        $ref_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="'.__('lang_v1.recurring_expense').'"><i class="fas fa-recycle"></i></small>';
                    }

                    if (! empty($row->recur_parent_id)) {
                        $ref_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="'.__('lang_v1.generated_recurring_expense').'"><i class="fas fa-recycle"></i></small>';
                    }

                    if ($row->type == 'expense_refund') {
                        $ref_no .= ' &nbsp;<small class="label bg-gray">'.__('lang_v1.refund').'</small>';
                    }

                    return $ref_no;
                })
                ->rawColumns(['final_total', 'action', 'payment_status', 'payment_due', 'ref_no', 'recur_details'])
                ->make(true);
        }

        $business_id = request()->session()->get('user.business_id');

        // US-MWART-015/016: com a flag ligada a lista é a Page React; `?classico=1` mantém a Blade.
        // Flag desligada (default, sem a chave no GrowthBook) = a Blade segue servindo.
        if (! request()->boolean('classico') && app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            return Inertia::render('Financeiro/Despesas/Index', [
                'filtros' => $this->filtrosDespesas((int) $business_id),
                'opcoes' => Inertia::defer(fn () => $this->opcoesDespesas((int) $business_id)),
                'despesas' => Inertia::defer(fn () => $this->despesasPaginadas((int) $business_id)),
                'pode' => [
                    'add' => auth()->user()->can('expense.add'),
                    'edit' => auth()->user()->can('expense.edit'),
                    'delete' => auth()->user()->can('expense.delete'),
                ],
            ]);
        }

        $categories = ExpenseCategory::where('business_id', $business_id)
                            ->whereNull('parent_id')
                            ->pluck('name', 'id');

        $users = User::forDropdown($business_id, false, true, true);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        $contacts = Contact::contactDropdown($business_id, false, false);

        $sub_categories = ExpenseCategory::where('business_id', $business_id)
                        ->whereNotNull('parent_id')
                        ->pluck('name', 'id')
                        ->toArray();

        return view('expense.index')
            ->with(compact('categories', 'business_locations', 'users', 'contacts', 'sub_categories'));
    }

    /** Filtros da Page: os da requisição; sem período, o ano fiscal (o default da Blade, `dateRangeSettings`). */
    private function filtrosDespesas(int $business_id): array
    {
        if (empty(request()->start_date) || empty(request()->end_date)) {
            $ano = app(\App\Utils\BusinessUtil::class)->getCurrentFinancialYear($business_id);
            request()->merge(['start_date' => $ano['start'], 'end_date' => $ano['end']]);
        }

        return collect(['location_id', 'expense_for', 'contact_id', 'expense_category_id',
            'expense_sub_category_id', 'payment_status', 'start_date', 'end_date'])
            ->mapWithKeys(fn ($k) => [$k => (string) request()->input($k, '')])
            ->all();
    }

    /** As mesmas listas que a Blade monta para os filtros. */
    private function opcoesDespesas(int $business_id): array
    {
        return [
            'locais' => BusinessLocation::forDropdown($business_id, true),
            'usuarios' => User::forDropdown($business_id, false, true, true),
            'contatos' => Contact::contactDropdown($business_id, false, false),
            'categorias' => ExpenseCategory::where('business_id', $business_id)->whereNull('parent_id')->pluck('name', 'id'),
            'subcategorias' => ExpenseCategory::where('business_id', $business_id)->whereNotNull('parent_id')->pluck('name', 'id'),
            // Nova despesa (US-MWART-018): a Blade filtra a subcategoria pela categoria via AJAX; aqui vai o mapa.
            'subcategoria_pai' => ExpenseCategory::where('business_id', $business_id)->whereNotNull('parent_id')->pluck('parent_id', 'id'),
            'impostos' => TaxRate::forBusinessDropdown($business_id, true, true)['tax_rates'],
        ];
    }

    /**
     * Página de 25, como o DataTable. Total e devido com o MESMO sinal e a MESMA conta do ramo
     * DataTables (`final-total` / `payment_due`): estorno negativo, devido = total − pagamentos.
     */
    private function despesasPaginadas(int $business_id): array
    {
        $this->filtrosDespesas($business_id);
        $pagina = $this->consultaDespesas($business_id)
            ->orderBy('transaction_date', 'desc')
            ->paginate(25)
            ->withQueryString();

        $linhas = collect($pagina->items())->map(function ($r) {
            $sinal = $r->type == 'expense_refund' ? -1 : 1;

            return [
                'id' => $r->id,
                'data' => $this->transactionUtil->format_date($r->transaction_date, true),
                'ref' => $r->ref_no,
                'estorno' => $r->type == 'expense_refund',
                'recorrente' => ! empty($r->is_recurring),
                'gerada_de' => ! empty($r->recur_parent_id) ? optional($r->recurring_parent)->ref_no : null,
                'categoria' => $r->category,
                'subcategoria' => $r->sub_category,
                'local' => $r->location_name,
                'status' => $r->payment_status,
                'imposto' => $r->tax,
                'total' => $sinal * (float) $r->final_total,
                'devido' => $sinal * ((float) $r->final_total - (float) $r->amount_paid),
                'para' => trim((string) $r->expense_for),
                'contato' => $r->contact_name,
                'nota' => $r->additional_notes,
                'criado_por' => trim((string) $r->added_by),
                'documento' => $r->document,
            ];
        });

        // O formato do paginador do Laravel (from/to/links) é o que o DataTable compartilhado lê.
        return array_merge($pagina->toArray(), ['data' => $linhas->all()]);
    }

    /**
     * A consulta da lista de despesas, com os filtros da requisição e o escopo do usuário.
     * Fonte única do ramo DataTables (Blade e relatório por representante) e do payload Inertia
     * (Financeiro/Despesas/Index): as duas telas não podem divergir no que listam.
     */
    private function consultaDespesas(int $business_id)
    {
        $expenses = Transaction::leftJoin('expense_categories AS ec', 'transactions.expense_category_id', '=', 'ec.id')
                    ->leftJoin('expense_categories AS esc', 'transactions.expense_sub_category_id', '=', 'esc.id')
                    ->join(
                        'business_locations AS bl',
                        'transactions.location_id',
                        '=',
                        'bl.id'
                    )
                    ->leftJoin('tax_rates as tr', 'transactions.tax_id', '=', 'tr.id')
                    ->leftJoin('users AS U', 'transactions.expense_for', '=', 'U.id')
                    ->leftJoin('users AS usr', 'transactions.created_by', '=', 'usr.id')
                    ->leftJoin('contacts AS c', 'transactions.contact_id', '=', 'c.id')
                    ->leftJoin(
                        'transaction_payments AS TP',
                        'transactions.id',
                        '=',
                        'TP.transaction_id'
                    )
                    ->where('transactions.business_id', $business_id)
                    ->whereIn('transactions.type', ['expense', 'expense_refund'])
                    ->select(
                        'transactions.id',
                        'transactions.document',
                        'transaction_date',
                        'ref_no',
                        'ec.name as category',
                        'esc.name as sub_category',
                        'payment_status',
                        'additional_notes',
                        'final_total',
                        'transactions.is_recurring',
                        'transactions.recur_interval',
                        'transactions.recur_interval_type',
                        'transactions.recur_repetitions',
                        'transactions.subscription_repeat_on',
                        'bl.name as location_name',
                        DB::raw("CONCAT(COALESCE(U.surname, ''),' ',COALESCE(U.first_name, ''),' ',COALESCE(U.last_name,'')) as expense_for"),
                        DB::raw("CONCAT(tr.name ,' (', tr.amount ,' )') as tax"),
                        DB::raw('SUM(TP.amount) as amount_paid'),
                        DB::raw("CONCAT(COALESCE(usr.surname, ''),' ',COALESCE(usr.first_name, ''),' ',COALESCE(usr.last_name,'')) as added_by"),
                        'transactions.recur_parent_id',
                        'c.name as contact_name',
                        'transactions.type'
                    )
                    ->with(['recurring_parent'])
                    ->groupBy('transactions.id');

        //Add condition for expense for,used in sales representative expense report & list of expense
        if (request()->has('expense_for')) {
            $expense_for = request()->get('expense_for');
            if (! empty($expense_for)) {
                $expenses->where('transactions.expense_for', $expense_for);
            }
        }

        if (request()->has('contact_id')) {
            $contact_id = request()->get('contact_id');
            if (! empty($contact_id)) {
                $expenses->where('transactions.contact_id', $contact_id);
            }
        }

        //Add condition for location,used in sales representative expense report & list of expense
        if (request()->has('location_id')) {
            $location_id = request()->get('location_id');
            if (! empty($location_id)) {
                $expenses->where('transactions.location_id', $location_id);
            }
        }

        //Add condition for expense category, used in list of expense,
        if (request()->has('expense_category_id')) {
            $expense_category_id = request()->get('expense_category_id');
            if (! empty($expense_category_id)) {
                $expenses->where('transactions.expense_category_id', $expense_category_id);
            }
        }

        //Add condition for expense sub category, used in list of expense,
        if (request()->has('expense_sub_category_id')) {
            $expense_sub_category_id = request()->get('expense_sub_category_id');
            if (! empty($expense_sub_category_id)) {
                $expenses->where('transactions.expense_sub_category_id', $expense_sub_category_id);
            }
        }

        //Add condition for start and end date filter, uses in sales representative expense report & list of expense
        if (! empty(request()->start_date) && ! empty(request()->end_date)) {
            $start = request()->start_date;
            $end = request()->end_date;
            $expenses->whereDate('transaction_date', '>=', $start)
                    ->whereDate('transaction_date', '<=', $end);
        }

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $expenses->whereIn('transactions.location_id', $permitted_locations);
        }

        //Add condition for payment status for the list of expense
        if (request()->has('payment_status')) {
            $payment_status = request()->get('payment_status');
            if (! empty($payment_status)) {
                $expenses->where('transactions.payment_status', $payment_status);
            }
        }

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);
        if (! $is_admin && ! auth()->user()->can('all_expense.access')) {
            $user_id = auth()->user()->id;
            $expenses->where(function ($query) use ($user_id) {
                $query->where('transactions.created_by', $user_id)
                    ->orWhere('transactions.expense_for', $user_id);
            });
        }

        return $expenses;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('expense.add')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\ExpenseController::class, 'index']));
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false, true);

        $bl_attributes = $business_locations['attributes'];
        $business_locations = $business_locations['locations'];

        $expense_categories = ExpenseCategory::where('business_id', $business_id)
                                ->whereNull('parent_id')
                                ->pluck('name', 'id');
        $users = User::forDropdown($business_id, true, true);

        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $payment_line = $this->dummyPaymentLine;

        $payment_types = $this->transactionUtil->payment_types(null, false, $business_id);

        $contacts = Contact::contactDropdown($business_id, false, false);

        //Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false, true);
        }

        if (request()->ajax()) {
            return view('expense.add_expense_modal')
                ->with(compact('expense_categories', 'business_locations', 'users', 'taxes', 'payment_line', 'payment_types', 'accounts', 'bl_attributes', 'contacts'));
        }

        return view('expense.create')
            ->with(compact('expense_categories', 'business_locations', 'users', 'taxes', 'payment_line', 'payment_types', 'accounts', 'bl_attributes', 'contacts'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('expense.add')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            //Check if subscribed or not
            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\ExpenseController::class, 'index']));
            }

            //Validate document size
            $request->validate([
                'document' => 'file|max:'.(config('constants.document_size_limit') / 1000),
            ]);

            $user_id = $request->session()->get('user.id');

            DB::beginTransaction();

            $expense = $this->transactionUtil->createExpense($request, $business_id, $user_id);

            if (request()->ajax()) {
                $payments = ! empty($request->input('payment')) ? $request->input('payment') : [];
                $this->cashRegisterUtil->addSellPayments($expense, $payments);
            }

            $this->transactionUtil->activityLog($expense, 'added');

            event(new ExpenseCreatedOrModified($expense));

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('expense.expense_add_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        if (request()->ajax()) {
            return $output;
        }

        return redirect('expenses')->with('status', $output);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('expense.edit')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\ExpenseController::class, 'index']));
        }

        $business_locations = BusinessLocation::forDropdown($business_id);

        $expense_categories = ExpenseCategory::where('business_id', $business_id)
                                ->whereNull('parent_id')
                                ->pluck('name', 'id');
        $expense = Transaction::where('business_id', $business_id)
                                ->where('id', $id)
                                ->first();

        $users = User::forDropdown($business_id, true, true);

        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $contacts = Contact::contactDropdown($business_id, false, false);

        //Sub-category
        $sub_categories = [];

        if (! empty($expense->expense_category_id)) {
            $sub_categories = ExpenseCategory::where('business_id', $business_id)
                        ->where('parent_id', $expense->expense_category_id)
                        ->pluck('name', 'id')
                        ->toArray();
        }

        return view('expense.edit')
            ->with(compact('expense', 'expense_categories', 'business_locations', 'users', 'taxes', 'contacts', 'sub_categories'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('expense.edit')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            //Validate document size
            $request->validate([
                'document' => 'file|max:'.(config('constants.document_size_limit') / 1000),
            ]);

            $business_id = $request->session()->get('user.business_id');

            //Check if subscribed or not
            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\ExpenseController::class, 'index']));
            }

            $expense = $this->transactionUtil->updateExpense($request, $id, $business_id);

            $this->transactionUtil->activityLog($expense, 'edited');

            event(new ExpenseCreatedOrModified($expense));

            $output = ['success' => 1,
                'msg' => __('expense.expense_update_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('expenses')->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('expense.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $expense = Transaction::where('business_id', $business_id)
                                        ->where(function ($q) {
                                            $q->where('type', 'expense')
                                                ->orWhere('type', 'expense_refund');
                                        })
                                        ->where('id', $id)
                                        ->first();

                //Delete Cash register transactions
                $expense->cash_register_payments()->delete();

                $expense->delete();

                //Delete account transactions
                AccountTransaction::where('transaction_id', $expense->id)->delete();

                event(new ExpenseCreatedOrModified($expense, true));

                $output = ['success' => true,
                    'msg' => __('expense.expense_delete_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }
}
