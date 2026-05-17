<?php

namespace App\Http\Controllers;

use App\Account;
use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\InvoiceScheme;
use App\Media;
use App\Product;
use App\SellingPriceGroup;
use App\TaxRate;
use App\Transaction;
use App\TransactionSellLine;
use App\TypesOfService;
use App\Variation;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Warranty;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class SellController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $contactUtil;

    protected $businessUtil;

    protected $transactionUtil;

    protected $productUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ContactUtil $contactUtil, BusinessUtil $businessUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, ProductUtil $productUtil)
    {
        $this->contactUtil = $contactUtil;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->productUtil = $productUtil;

        $this->dummyPaymentLine = ['method' => '', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => '', ];

        $this->shipping_status_colors = [
            'ordered' => 'bg-yellow',
            'packed' => 'bg-info',
            'shipped' => 'bg-navy',
            'delivered' => 'bg-green',
            'cancelled' => 'bg-red',
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['sell.view', 'sell.create', 'direct_sell.access', 'direct_sell.view', 'view_own_sell_only', 'view_commission_agent_sell', 'access_shipping', 'access_own_shipping', 'access_commission_agent_shipping', 'so.view_all', 'so.view_own'])) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $is_woocommerce = $this->moduleUtil->isModuleInstalled('Woocommerce');
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        $is_tables_enabled = $this->transactionUtil->isModuleEnabled('tables');
        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');
        $is_types_service_enabled = $this->moduleUtil->isModuleEnabled('types_of_service');

        // Inertia requests também são AJAX, mas precisam do branch Inertia::render
        // (linha 651). Sem o ! header check, Inertia AJAX pegava o DataTables JSON
        // — quebrava POST /pos -> redirect -> /sells follow-up. Bug 3/N descoberto
        // 2026-05-10 em smoke SEFAZ biz=1 (PR #421/#424).
        if (request()->ajax() && ! request()->header('X-Inertia')) {
            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);
            $with = [];
            $shipping_statuses = $this->transactionUtil->shipping_statuses();

            $sale_type = ! empty(request()->input('sale_type')) ? request()->input('sale_type') : 'sell';

            $sells = $this->transactionUtil->getListSells($business_id, $sale_type);

            // only display sell invoice we add it because project invoive show in sell list
            if($sale_type == 'sell'){
                $sells->whereNull('transactions.sub_type');
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $sells->whereIn('transactions.location_id', $permitted_locations);
            }

            //Add condition for created_by,used in sales representative sales report
            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (! empty($created_by)) {
                    $sells->where('transactions.created_by', $created_by);
                }
            }

            $partial_permissions = ['view_own_sell_only', 'view_commission_agent_sell', 'access_own_shipping', 'access_commission_agent_shipping'];
            if (! auth()->user()->can('direct_sell.view')) {
                $sells->where(function ($q) {
                    if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                        $q->where('transactions.created_by', request()->session()->get('user.id'));
                    }

                    //if user is commission agent display only assigned sells
                    if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                        $q->orWhere('transactions.commission_agent', request()->session()->get('user.id'));
                    }
                });
            }

            $only_shipments = request()->only_shipments == 'true' ? true : false;
            if ($only_shipments) {
                $sells->whereNotNull('transactions.shipping_status');

                if (auth()->user()->hasAnyPermission(['access_pending_shipments_only'])) {
                    $sells->where('transactions.shipping_status', '!=', 'delivered');
                }
            }

            if (! $is_admin && ! $only_shipments && $sale_type != 'sales_order') {
                $payment_status_arr = [];
                if (auth()->user()->can('view_paid_sells_only')) {
                    $payment_status_arr[] = 'paid';
                }

                if (auth()->user()->can('view_due_sells_only')) {
                    $payment_status_arr[] = 'due';
                }

                if (auth()->user()->can('view_partial_sells_only')) {
                    $payment_status_arr[] = 'partial';
                }

                if (empty($payment_status_arr)) {
                    if (auth()->user()->can('view_overdue_sells_only')) {
                        $sells->OverDue();
                    }
                } else {
                    if (auth()->user()->can('view_overdue_sells_only')) {
                        $sells->where(function ($q) use ($payment_status_arr) {
                            $q->whereIn('transactions.payment_status', $payment_status_arr)
                            ->orWhere(function ($qr) {
                                $qr->OverDue();
                            });
                        });
                    } else {
                        $sells->whereIn('transactions.payment_status', $payment_status_arr);
                    }
                }
            }

            if (! empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
                $sells->where('transactions.payment_status', request()->input('payment_status'));
            } elseif (request()->input('payment_status') == 'overdue') {
                $sells->whereIn('transactions.payment_status', ['due', 'partial'])
                    ->whereNotNull('transactions.pay_term_number')
                    ->whereNotNull('transactions.pay_term_type')
                    ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
            }

            //Add condition for location,used in sales representative expense report
            if (request()->has('location_id')) {
                $location_id = request()->get('location_id');
                if (! empty($location_id)) {
                    $sells->where('transactions.location_id', $location_id);
                }
            }

            if (! empty(request()->input('rewards_only')) && request()->input('rewards_only') == true) {
                $sells->where(function ($q) {
                    $q->whereNotNull('transactions.rp_earned')
                    ->orWhere('transactions.rp_redeemed', '>', 0);
                });
            }

            if (! empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $sells->where('contacts.id', $customer_id);
            }
            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $sells->whereDate('transactions.transaction_date', '>=', $start)
                            ->whereDate('transactions.transaction_date', '<=', $end);
            }

            //Check is_direct sell
            if (request()->has('is_direct_sale')) {
                $is_direct_sale = request()->is_direct_sale;
                if ($is_direct_sale == 0) {
                    $sells->where('transactions.is_direct_sale', 0);
                    $sells->whereNull('transactions.sub_type');
                }
            }

            //Add condition for commission_agent,used in sales representative sales with commission report
            if (request()->has('commission_agent')) {
                $commission_agent = request()->get('commission_agent');
                if (! empty($commission_agent)) {
                    $sells->where('transactions.commission_agent', $commission_agent);
                }
            }

            if (! empty(request()->input('source'))) {
                //only exception for woocommerce
                if (request()->input('source') == 'woocommerce') {
                    $sells->whereNotNull('transactions.woocommerce_order_id');
                } else {
                    $sells->where('transactions.source', request()->input('source'));
                }
            }

            if ($is_crm) {
                $sells->addSelect('transactions.crm_is_order_request');

                if (request()->has('crm_is_order_request')) {
                    $sells->where('transactions.crm_is_order_request', 1);
                }
            }

            if (request()->only_subscriptions) {
                $sells->where(function ($q) {
                    $q->whereNotNull('transactions.recur_parent_id')
                        ->orWhere('transactions.is_recurring', 1);
                });
            }

            if (! empty(request()->list_for) && request()->list_for == 'service_staff_report') {
                $sells->whereNotNull('transactions.res_waiter_id');
            }

            if (! empty(request()->res_waiter_id)) {
                $sells->where('transactions.res_waiter_id', request()->res_waiter_id);
            }

            if (! empty(request()->input('sub_type'))) {
                $sells->where('transactions.sub_type', request()->input('sub_type'));
            }

            if (! empty(request()->input('created_by'))) {
                $sells->where('transactions.created_by', request()->input('created_by'));
            }

            if (! empty(request()->input('status'))) {
                $sells->where('transactions.status', request()->input('status'));
            }

            if (! empty(request()->input('sales_cmsn_agnt'))) {
                $sells->where('transactions.commission_agent', request()->input('sales_cmsn_agnt'));
            }

            if (! empty(request()->input('service_staffs'))) {
                $sells->where('transactions.res_waiter_id', request()->input('service_staffs'));
            }

            $only_pending_shipments = request()->only_pending_shipments == 'true' ? true : false;
            if ($only_pending_shipments) {
                $sells->where('transactions.shipping_status', '!=', 'delivered')
                        ->whereNotNull('transactions.shipping_status');
                $only_shipments = true;
            }

            if (! empty(request()->input('shipping_status'))) {
                $sells->where('transactions.shipping_status', request()->input('shipping_status'));
            }

            if (! empty(request()->input('for_dashboard_sales_order'))) {
                $sells->whereIn('transactions.status', ['partial', 'ordered'])
                    ->orHavingRaw('so_qty_remaining > 0');
            }

            if ($sale_type == 'sales_order') {
                if (! auth()->user()->can('so.view_all') && auth()->user()->can('so.view_own')) {
                    $sells->where('transactions.created_by', request()->session()->get('user.id'));
                }
            }

            if (! empty(request()->input('delivery_person'))) {
                $sells->where('transactions.delivery_person', request()->input('delivery_person'));
            }

            $sells->groupBy('transactions.id');

            if (! empty(request()->suspended)) {
                $transaction_sub_type = request()->get('transaction_sub_type');
                if (! empty($transaction_sub_type)) {
                    $sells->where('transactions.sub_type', $transaction_sub_type);
                } else {
                    $sells->where('transactions.sub_type', null);
                }

                $with = ['sell_lines'];

                if ($is_tables_enabled) {
                    $with[] = 'table';
                }

                if ($is_service_staff_enabled) {
                    $with[] = 'service_staff';
                }

                $sales = $sells->where('transactions.is_suspend', 1)
                            ->with($with)
                            ->addSelect('transactions.is_suspend', 'transactions.res_table_id', 'transactions.res_waiter_id', 'transactions.additional_notes')
                            ->get();

                return view('sale_pos.partials.suspended_sales_modal')->with(compact('sales', 'is_tables_enabled', 'is_service_staff_enabled', 'transaction_sub_type'));
            }

            $with[] = 'payment_lines';
            
            if (!empty($with)) {
                foreach ($with as $relation) {
                    if ($relation == 'payment_lines' && !empty(request()->input('payment_method'))) {
                        $sells->whereHas($relation, function ($query) {
                            $query->where('method', request()->input('payment_method'));
                        });
                    } else {
                        $sells->with($relation);
                    }
                }
            }

            //$business_details = $this->businessUtil->getDetails($business_id);
            if ($this->businessUtil->isModuleEnabled('subscription')) {
                $sells->addSelect('transactions.is_recurring', 'transactions.recur_parent_id');
            }
            $sales_order_statuses = Transaction::sales_order_statuses();
            $datatable = Datatables::of($sells)
                ->addColumn(
                    'action',
                    function ($row) use ($only_shipments, $is_admin, $sale_type) {
                        $html = '<div class="btn-group">
                                    <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max dropdown-toggle" 
                                        data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                        </span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                        if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.view') || auth()->user()->can('view_own_sell_only')) {
                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
                        }
                        if (! $only_shipments) {
                            if ($row->is_direct_sale == 0) {
                                if (auth()->user()->can('sell.update')) {
                                    $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellPosController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
                                }
                            } elseif ($row->type == 'sales_order') {
                                if (auth()->user()->can('so.update')) {
                                    $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
                                }
                            } else {
                                if (auth()->user()->can('direct_sell.update')) {
                                    $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
                                }
                            }

                            $delete_link = '<li><a href="'.action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$row->id]).'" class="delete-sale"><i class="fas fa-trash"></i> '.__('messages.delete').'</a></li>';
                            if ($row->is_direct_sale == 0) {
                                if (auth()->user()->can('sell.delete')) {
                                    $html .= $delete_link;
                                }
                            } elseif ($row->type == 'sales_order') {
                                if (auth()->user()->can('so.delete')) {
                                    $html .= $delete_link;
                                }
                            } else {
                                if (auth()->user()->can('direct_sell.delete')) {
                                    $html .= $delete_link;
                                }
                            }
                        }

                        if (config('constants.enable_download_pdf') && auth()->user()->can('print_invoice') && $sale_type != 'sales_order') {
                            $html .= '<li><a href="'.route('sell.downloadPdf', [$row->id]).'" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.download_pdf').'</a></li>';

                            if (! empty($row->shipping_status)) {
                                $html .= '<li><a href="'.route('packing.downloadPdf', [$row->id]).'" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.download_paking_pdf').'</a></li>';
                            }
                        }

                        if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.access')) {
                            if (! empty($row->document)) {
                                $document_name = ! empty(explode('_', $row->document, 2)[1]) ? explode('_', $row->document, 2)[1] : $row->document;
                                $html .= '<li><a href="'.url('uploads/documents/'.$row->document).'" download="'.$document_name.'"><i class="fas fa-download" aria-hidden="true"></i>'.__('purchase.download_document').'</a></li>';
                                if (isFileImage($document_name)) {
                                    $html .= '<li><a href="#" data-href="'.url('uploads/documents/'.$row->document).'" class="view_uploaded_document"><i class="fas fa-image" aria-hidden="true"></i>'.__('lang_v1.view_document').'</a></li>';
                                }
                            }
                        }

                        if ($is_admin || auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'editShipping'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-truck" aria-hidden="true"></i>'.__('lang_v1.edit_shipping').'</a></li>';
                        }

                        if ($row->type == 'sell') {
                            if (auth()->user()->can('print_invoice')) {
                                $html .= '<li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.print_invoice').'</a></li>
                                    <li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'?package_slip=true"><i class="fas fa-file-alt" aria-hidden="true"></i> '.__('lang_v1.packing_slip').'</a></li>';

                                $html .= '<li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'?delivery_note=true"><i class="fas fa-file-alt" aria-hidden="true"></i> '.__('lang_v1.delivery_note').'</a></li>';
                            }
                            $html .= '<li class="divider"></li>';
                            if (! $only_shipments) {
                                if ($row->is_direct_sale == 0 && ! auth()->user()->can('sell.update') &&
                                auth()->user()->can('edit_pos_payment')) {
                                    $html .= '<li><a href="'.route('edit-pos-payment', [$row->id]).'" 
                                    ><i class="fas fa-money-bill-alt"></i> '.__('lang_v1.add_edit_payment').
                                    '</a></li>';
                                }

                                if (auth()->user()->can('sell.payments') ||
                                    auth()->user()->can('edit_sell_payment') ||
                                    auth()->user()->can('delete_sell_payment')) {
                                    if ($row->payment_status != 'paid') {
                                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$row->id]).'" class="add_payment_modal"><i class="fas fa-money-bill-alt"></i> '.__('purchase.add_payment').'</a></li>';
                                    }

                                    $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->id]).'" class="view_payment_modal"><i class="fas fa-money-bill-alt"></i> '.__('purchase.view_payments').'</a></li>';
                                }

                                if (auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access')) {
                                    // $html .= '<li><a href="' . action([\App\Http\Controllers\SellController::class, 'duplicateSell'], [$row->id]) . '"><i class="fas fa-copy"></i> ' . __("lang_v1.duplicate_sell") . '</a></li>';

                                    $html .= '<li><a href="'.action([\App\Http\Controllers\SellReturnController::class, 'add'], [$row->id]).'"><i class="fas fa-undo"></i> '.__('lang_v1.sell_return').'</a></li>

                                    <li><a href="'.action([\App\Http\Controllers\SellPosController::class, 'showInvoiceUrl'], [$row->id]).'" class="view_invoice_url"><i class="fas fa-eye"></i> '.__('lang_v1.view_invoice_url').'</a></li>';
                                }
                            }

                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $row->id, 'template_for' => 'new_sale']).'" class="btn-modal" data-container=".view_modal"><i class="fa fa-envelope" aria-hidden="true"></i>'.__('lang_v1.new_sale_notification').'</a></li>';
                        } else {
                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'viewMedia'], ['model_id' => $row->id, 'model_type' => \App\Transaction::class, 'model_media_type' => 'shipping_document']).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-paperclip" aria-hidden="true"></i>'.__('lang_v1.shipping_documents').'</a></li>';
                        }

                        $html .= '</ul></div>';

                        return $html;
                    }
                )
                ->removeColumn('id')
                ->editColumn(
                    'final_total',
                    '<span class="final-total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
                )
                ->editColumn(
                    'tax_amount',
                    '<span class="total-tax" data-orig-value="{{$tax_amount}}">@format_currency($tax_amount)</span>'
                )
                ->editColumn(
                    'total_paid',
                    '<span class="total-paid" data-orig-value="{{$total_paid}}">@format_currency($total_paid)</span>'
                )
                ->editColumn(
                    'total_before_tax',
                    '<span class="total_before_tax" data-orig-value="{{$total_before_tax}}">@format_currency($total_before_tax)</span>'
                )
                ->editColumn(
                    'discount_amount',
                    function ($row) {
                        $discount = ! empty($row->discount_amount) ? $row->discount_amount : 0;

                        if (! empty($discount) && $row->discount_type == 'percentage') {
                            $discount = $row->total_before_tax * ($discount / 100);
                        }

                        return '<span class="total-discount" data-orig-value="'.$discount.'">'.$this->transactionUtil->num_f($discount, true).'</span>';
                    }
                )
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn(
                    'payment_status',
                    function ($row) {
                        $payment_status = Transaction::getPaymentStatus($row);

                        return (string) view('sell.partials.payment_status', ['payment_status' => $payment_status, 'id' => $row->id]);
                    }
                )
                ->editColumn(
                    'types_of_service_name',
                    '<span class="service-type-label" data-orig-value="{{$types_of_service_name}}" data-status-name="{{$types_of_service_name}}">{{$types_of_service_name}}</span>'
                )
                ->addColumn('total_remaining', function ($row) {
                    $total_remaining = $row->final_total - $row->total_paid;
                    $total_remaining_html = '<span class="payment_due" data-orig-value="'.$total_remaining.'">'.$this->transactionUtil->num_f($total_remaining, true).'</span>';

                    return $total_remaining_html;
                })
                ->addColumn('return_due', function ($row) {
                    $return_due_html = '';
                    if (! empty($row->return_exists)) {
                        $return_due = $row->amount_return - $row->return_paid;
                        $return_due_html .= '<a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->return_transaction_id]).'" class="view_purchase_return_payment_modal"><span class="sell_return_due" data-orig-value="'.$return_due.'">'.$this->transactionUtil->num_f($return_due, true).'</span></a>';
                    }

                    return $return_due_html;
                })
                ->editColumn('invoice_no', function ($row) use ($is_crm) {
                    $invoice_no = $row->invoice_no;
                    if (! empty($row->woocommerce_order_id)) {
                        $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="'.__('lang_v1.synced_from_woocommerce').'"></i>';
                    }
                    if (! empty($row->return_exists)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="'.__('lang_v1.some_qty_returned_from_sell').'"><i class="fas fa-undo"></i></small>';
                    }
                    if (! empty($row->is_recurring)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="'.__('lang_v1.subscribed_invoice').'"><i class="fas fa-recycle"></i></small>';
                    }

                    if (! empty($row->recur_parent_id)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="'.__('lang_v1.subscription_invoice').'"><i class="fas fa-recycle"></i></small>';
                    }

                    if (! empty($row->is_export)) {
                        $invoice_no .= '</br><small class="label label-default no-print" title="'.__('lang_v1.export').'">'.__('lang_v1.export').'</small>';
                    }

                    if ($is_crm && ! empty($row->crm_is_order_request)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-yellow label-round no-print" title="'.__('crm::lang.order_request').'"><i class="fas fa-tasks"></i></small>';
                    }

                    return $invoice_no;
                })
                ->editColumn('shipping_status', function ($row) use ($shipping_statuses) {
                    $status_color = ! empty($this->shipping_status_colors[$row->shipping_status]) ? $this->shipping_status_colors[$row->shipping_status] : 'bg-gray';
                    $status = ! empty($row->shipping_status) ? '<a href="#" class="btn-modal" data-href="'.action([\App\Http\Controllers\SellController::class, 'editShipping'], [$row->id]).'" data-container=".view_modal"><span class="label '.$status_color.'">'.$shipping_statuses[$row->shipping_status].'</span></a>' : '';

                    return $status;
                })
                ->addColumn('conatct_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
                ->editColumn('total_items', '{{@format_quantity($total_items)}}')
                ->filterColumn('conatct_name', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('contacts.name', 'like', "%{$keyword}%")
                        ->orWhere('contacts.supplier_business_name', 'like', "%{$keyword}%");
                    });
                })
                ->addColumn('payment_methods', function ($row) use ($payment_types) {
                    $methods = array_unique($row->payment_lines->pluck('method')->toArray());
                    $count = count($methods);
                    $payment_method = '';
                    if ($count == 1) {
                        $payment_method = $payment_types[$methods[0]] ?? '';
                    } elseif ($count > 1) {
                        $payment_method = __('lang_v1.checkout_multi_pay');
                    }

                    $html = ! empty($payment_method) ? '<span class="payment-method" data-orig-value="'.$payment_method.'" data-status-name="'.$payment_method.'">'.$payment_method.'</span>' : '';

                    return $html;
                })
                ->editColumn('status', function ($row) use ($sales_order_statuses, $is_admin) {
                    $status = '';

                    if ($row->type == 'sales_order') {
                        if ($is_admin && $row->status != 'completed') {
                            $status = '<span class="edit-so-status label '.$sales_order_statuses[$row->status]['class'].'" data-href="'.action([\App\Http\Controllers\SalesOrderController::class, 'getEditSalesOrderStatus'], ['id' => $row->id]).'">'.$sales_order_statuses[$row->status]['label'].'</span>';
                        } else {
                            $status = '<span class="label '.$sales_order_statuses[$row->status]['class'].'" >'.$sales_order_statuses[$row->status]['label'].'</span>';
                        }
                    }

                    return $status;
                })
                ->editColumn('so_qty_remaining', '{{@format_quantity($so_qty_remaining)}}')
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can('sell.view') || auth()->user()->can('view_own_sell_only')) {
                            return  action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]);
                        } else {
                            return '';
                        }
                    }, ]);

            $rawColumns = ['final_total', 'action', 'total_paid', 'total_remaining', 'payment_status', 'invoice_no', 'discount_amount', 'tax_amount', 'total_before_tax', 'shipping_status', 'types_of_service_name', 'payment_methods', 'return_due', 'conatct_name', 'status'];

            return $datatable->rawColumns($rawColumns)
                      ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);
        $sales_representative = User::forDropdown($business_id, false, false, true);

        //Commission agent filter
        $is_cmsn_agent_enabled = request()->session()->get('business.sales_cmsn_agnt');
        $commission_agents = [];
        if (! empty($is_cmsn_agent_enabled)) {
            $commission_agents = User::forDropdown($business_id, false, true, true);
        }

        //Service staff filter
        $service_staffs = null;
        if ($this->productUtil->isModuleEnabled('service_staff')) {
            $service_staffs = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $sources = $this->transactionUtil->getSources($business_id);
        if ($is_woocommerce) {
            $sources['woocommerce'] = 'Woocommerce';
        }

        $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

        // Inertia render — US-SELL-008 pattern Cockpit canon (PR único).
        // DataTables AJAX legacy continua funcionando via request()->ajax() acima.
        // KPIs counters — mesmas regras de tenant scope que getListSells (defesa em profundidade).
        $kpiBase = \App\Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNull('sub_type');

        $sellKpis = [
            'total' => (clone $kpiBase)->count(),
            'paid' => (clone $kpiBase)->where('payment_status', 'paid')->count(),
            'due' => (clone $kpiBase)->where('payment_status', 'due')->count(),
            'partial' => (clone $kpiBase)->where('payment_status', 'partial')->count(),
            'overdue' => (clone $kpiBase)
                ->whereIn('payment_status', ['due', 'partial'])
                ->whereNotNull('pay_term_number')
                ->whereNotNull('pay_term_type')
                ->whereRaw("IF(pay_term_type='days', DATE_ADD(transaction_date, INTERVAL pay_term_number DAY) < CURDATE(), DATE_ADD(transaction_date, INTERVAL pay_term_number MONTH) < CURDATE())")
                ->count(),
        ];

        return \Inertia\Inertia::render('Sells/Index', [
            'sellKpis' => $sellKpis,
            'businessLocations' => $business_locations,
            'paymentTypes' => $payment_types,
            'shippingStatuses' => $shipping_statuses,
            'sources' => $sources,
            'permissions' => [
                'create' => auth()->user()->can('direct_sell.access'),
                'view' => auth()->user()->can('direct_sell.view') ||
                          auth()->user()->can('view_own_sell_only') ||
                          auth()->user()->can('view_commission_agent_sell'),
            ],
            'datatableUrl' => '/sells',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $sale_type = request()->get('sale_type', '');

        if ($sale_type == 'sales_order') {
            if (! auth()->user()->can('so.create')) {
                abort(403, 'Unauthorized action.');
            }
        } else {
            if (! auth()->user()->can('direct_sell.access')) {
                abort(403, 'Unauthorized action.');
            }
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not, then check for users quota
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        } elseif (! $this->moduleUtil->isQuotaAvailable('invoices', $business_id)) {
            return $this->moduleUtil->quotaExpiredResponse('invoices', $business_id, action([\App\Http\Controllers\SellController::class, 'index']));
        }

        $walk_in_customer = $this->contactUtil->getWalkInCustomer($business_id);

        $business_details = $this->businessUtil->getDetails($business_id);
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $business_locations = BusinessLocation::forDropdown($business_id, false, true);
        $bl_attributes = $business_locations['attributes'];
        $business_locations = $business_locations['locations'];

        $default_location = null;
        foreach ($business_locations as $id => $name) {
            $default_location = BusinessLocation::findOrFail($id);
            break;
        }

        $commsn_agnt_setting = $business_details->sales_cmsn_agnt;
        $commission_agent = [];
        if ($commsn_agnt_setting == 'user') {
            $commission_agent = User::forDropdown($business_id);
        } elseif ($commsn_agnt_setting == 'cmsn_agnt') {
            $commission_agent = User::saleCommissionAgentsDropdown($business_id);
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

        $payment_line = $this->dummyPaymentLine;
        $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

        //Selling Price Group Dropdown
        $price_groups = SellingPriceGroup::forDropdown($business_id);

        $default_price_group_id = ! empty($default_location->selling_price_group_id) && array_key_exists($default_location->selling_price_group_id, $price_groups) ? $default_location->selling_price_group_id : null;

        // format_now_local pra evitar shift +3h intencional do format_date
        // (ver feedback_carbon_timezone_bug.md). format_date('now') empurra 3h
        // pro futuro porque mantem o shift histórico — pra "agora" e errado.
        $default_datetime = $this->businessUtil->format_now_local(true);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $invoice_schemes = InvoiceScheme::forDropdown($business_id);
        $default_invoice_schemes = InvoiceScheme::getDefault($business_id);
        if (! empty($default_location) && !empty($default_location->sale_invoice_scheme_id)) {
            $default_invoice_schemes = InvoiceScheme::where('business_id', $business_id)
                                        ->findorfail($default_location->sale_invoice_scheme_id);
        }
        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        //Types of service
        $types_of_service = [];
        if ($this->moduleUtil->isModuleEnabled('types_of_service')) {
            $types_of_service = TypesOfService::forDropdown($business_id);
        }

        //Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        $status = request()->get('status', '');

        $statuses = Transaction::sell_statuses();

        if ($sale_type == 'sales_order') {
            $status = 'ordered';
        }

        $is_order_request_enabled = false;
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        if ($is_crm) {
            $crm_settings = Business::where('id', auth()->user()->business_id)
                                ->value('crm_settings');
            $crm_settings = ! empty($crm_settings) ? json_decode($crm_settings, true) : [];

            if (! empty($crm_settings['enable_order_request'])) {
                $is_order_request_enabled = true;
            }
        }

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        $change_return = $this->dummyPaymentLine;

        // US-SELL-002 — dual response: Inertia/React (MWART) se feature flag on, senão Blade legacy.
        // Rota /sells/create mapeia pra SellController (este). Pos POS rápido em /pos/create
        // mapeia pra SellPosController (que tem branch idêntico).
        // Refs: ADR 0104 (MWART canônico §F2 backend baseline), ADR 0105 (3 graus regulação).
        //
        // HOTFIX rollback emergencial 2026-05-13 — biz=4 (Larissa/ROTA LIVRE) reportou bugs v2:
        //   (1) "traz o mesmo produto com estoque" (duplicação/seleção variação errada)
        //   (2) faltam botões "preço diferenciado / tamanho / conversão unidade medida" do Blade
        //   (3) erro visível na tela
        // Cintura+suspensório: GrowthBook regra biz=4 OFF + este guard hardcoded.
        // TODO: remover guard quando bugs corrigidos + canary biz=4 re-ativado.
        $ffs = app(\App\Services\FeatureFlagService::class);
        $useV2 = $business_id !== 4
            && $ffs->isOn('useV2SellsCreate', ['business_id' => $business_id]);
        if ($useV2) {
            return \Inertia\Inertia::render('Sells/Create', [
                'businessLocations'    => $business_locations,
                'blAttributes'         => $bl_attributes,
                'defaultLocation'      => $default_location,
                'walkInCustomer'       => $walk_in_customer,
                'paymentTypes'         => $payment_types,
                'invoiceSchemes'       => $invoice_schemes,
                'defaultInvoiceScheme' => $default_invoice_schemes,
                // TaxRate::forBusinessDropdown(business_id, true, true) retorna
                // ['tax_rates' => Collection<id,name>, 'attributes' => array<id, attrs>].
                // Frontend espera Record<id, name> simples.
                'taxes'                => is_array($taxes) && isset($taxes['tax_rates'])
                    ? $taxes['tax_rates']
                    : $taxes,
                'priceGroups'          => $price_groups,
                'defaultPriceGroupId'  => $default_price_group_id,
                'shippingStatuses'     => $shipping_statuses,
                'defaultDatetime'      => $default_datetime,
                'commissionAgents'     => $commission_agent,
                'customerGroups'       => $customer_groups,
                'accounts'             => $accounts,
                'typesOfService'       => $types_of_service,
                'users'                => $users,
                'permissions'          => [
                    'editDiscount' => true,  // SellController não tem flag separado (pos screen é só SellPosController)
                    'editPrice'    => true,
                    'maxDiscount'  => auth()->user()->max_sales_discount_percent,
                ],
                'posSettings'          => $pos_settings,
                'subType'              => $sale_type ?: null,
                'statuses'             => $statuses,
                'isOrderRequestEnabled' => $is_order_request_enabled,
            ]);
        }

        return view('sell.create')
            ->with(compact(
                'business_details',
                'taxes',
                'walk_in_customer',
                'business_locations',
                'bl_attributes',
                'default_location',
                'commission_agent',
                'types',
                'customer_groups',
                'payment_line',
                'payment_types',
                'price_groups',
                'default_datetime',
                'pos_settings',
                'invoice_schemes',
                'default_invoice_schemes',
                'types_of_service',
                'accounts',
                'shipping_statuses',
                'status',
                'sale_type',
                'statuses',
                'is_order_request_enabled',
                'users',
                'default_price_group_id',
                'change_return'
            ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * US-SELL-008 — Lista JSON minimalista pra Sells/Index.tsx (Inertia React).
     * Retorna últimas N vendas filtradas por payment_status (pill ativa).
     * Simpler do que getListSells — 8 campos por linha.
     */
    public function inertiaList(Request $request)
    {
        if (!auth()->user()->can('direct_sell.view') &&
            !auth()->user()->can('view_own_sell_only') &&
            !auth()->user()->can('view_commission_agent_sell')) {
            abort(403);
        }

        $business_id = request()->session()->get('user.business_id');
        $payment_status = $request->input('payment_status'); // '', paid, due, partial, overdue
        $search = trim((string) $request->input('q', ''));
        // anti-DoS: hard cap 200 (US-SELL-008 contract).
        $limit = min((int) $request->input('limit', 200), 200);
        $perPage = min(max((int) $request->input('per_page', 25), 5), $limit);
        $page = max((int) $request->input('page', 1), 1);

        // US-SELL-021 — Whitelist de campos de data (header dropdown 7 opções).
        // Alias frontend → expressão SQL. Mapping Delphi → Laravel canônico em
        // memory/research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md §5.
        $dateFieldMap = [
            'transaction_date' => 'transactions.transaction_date', // DT_EMISSAO (default)
            'updated_at'       => 'transactions.updated_at',       // DT_ALTERACAO
            'nfe_issued_at'    => 'nfe.emitido_em',                // NF_DT_EMISSAO (JOIN nfe_emissoes)
            'invoiced_at'      => 'transactions.invoiced_at',      // DT_FATURAMENTO
            'invoice_sent_at'  => 'transactions.invoice_sent_at',  // FATURAMENTO_DT_ENVIO
            'competence_date'  => 'transactions.competence_date',  // DT_COMPETENCIA
            'due_date'         => 'transactions.due_date',         // PROJETO_DT_FIM (data prometida)
        ];
        $dateField = $request->input('date_field', 'transaction_date');
        if (! array_key_exists($dateField, $dateFieldMap)) {
            $dateField = 'transaction_date';
        }
        $dateFieldSql = $dateFieldMap[$dateField];

        // date_from / date_to aplicam ao date_field escolhido.
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        // Whitelist de colunas ordenáveis — alias frontend → expressão SQL.
        $sortMap = [
            'transaction_date' => 'transactions.transaction_date',
            'invoice_no'       => 'transactions.invoice_no',
            'customer_name'    => 'contacts.name',
            'final_total'      => 'transactions.final_total',
            'payment_status'   => 'transactions.payment_status',
        ];
        $sortKey = $request->input('sort', 'transaction_date');
        if (! array_key_exists($sortKey, $sortMap)) {
            $sortKey = 'transaction_date';
        }
        $sortDir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $q = \App\Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->leftJoin('business_locations as bl', 'transactions.location_id', '=', 'bl.id')
            // US-SELL-023 — JOIN sale_process_stages pra retornar current_stage_key
            // (badge produção na lista). LEFT JOIN preserva vendas legadas sem FSM
            // (current_stage_id NULL → stage_key NULL → frontend mostra "—" silencioso).
            // sale_process_stages NÃO tem business_id direto — tenancy garantido via
            // SaleProcess (skill multi-tenant-patterns Tier A); FK em current_stage_id
            // só pode apontar pra stage do mesmo business porque foi resolvido pelo
            // ExecuteStageActionService que valida tenancy.
            ->leftJoin('sale_process_stages as sps', 'transactions.current_stage_id', '=', 'sps.id')
            // US-SELL-COWORK — JOIN users pra exibir vendedor "atendido por" (Cowork KB-9.75).
            // LEFT JOIN preserva vendas sem created_by (legacy).
            ->leftJoin('users as seller_u', 'transactions.created_by', '=', 'seller_u.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->whereNull('transactions.sub_type');

        // US-SELL-021 — JOIN nfe_emissoes só quando precisamos da NF_DT_EMISSAO.
        // Tabela tem unique (business_id, transaction_id) — não duplica linhas.
        if ($dateField === 'nfe_issued_at') {
            $q->leftJoin('nfe_emissoes as nfe', function ($j) use ($business_id) {
                $j->on('nfe.transaction_id', '=', 'transactions.id')
                    ->where('nfe.business_id', '=', $business_id);
            });
        }

        // US-SELL-021 — filtros date_from/date_to aplicam ao date_field escolhido.
        if ($dateFrom !== '') {
            $q->whereRaw("$dateFieldSql >= ?", [$dateFrom]);
        }
        if ($dateTo !== '') {
            $q->whereRaw("$dateFieldSql <= ?", [$dateTo]);
        }

        // Permission scope (mesmo padrão do index() AJAX).
        if (!auth()->user()->can('direct_sell.view')) {
            $q->where(function ($qq) {
                if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                    $qq->where('transactions.created_by', request()->session()->get('user.id'));
                }
                if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                    $qq->orWhere('transactions.commission_agent', request()->session()->get('user.id'));
                }
            });
        }

        // Busca livre por cliente (name / supplier_business_name) ou número de fatura.
        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $like = '%' . $search . '%';
                $qq->where('contacts.name', 'like', $like)
                    ->orWhere('contacts.supplier_business_name', 'like', $like)
                    ->orWhere('transactions.invoice_no', 'like', $like);
            });
        }

        // Pill filter.
        if ($payment_status === 'overdue') {
            $q->whereIn('transactions.payment_status', ['due', 'partial'])
                ->whereNotNull('transactions.pay_term_number')
                ->whereNotNull('transactions.pay_term_type')
                ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
        } elseif (in_array($payment_status, ['paid', 'due', 'partial'], true)) {
            $q->where('transactions.payment_status', $payment_status);
        }

        // US-SELL-017 — Totalizador rodapé. Calcula totais SOBRE O FILTRO INTEIRO
        // (não só página corrente). Clone do builder antes do paginate pra preservar
        // joins/wheres mas remover orderBy/limit. Subquery separada pra somar
        // total_paid (mesma lógica do select da grid — coluna não existe direta).
        $totalsQuery = (clone $q);
        $totalsRow = $totalsQuery->selectRaw(
            'COUNT(transactions.id) as count_rows, '.
            'COALESCE(SUM(transactions.final_total), 0) as sum_final_total, '.
            'COALESCE(SUM((SELECT COALESCE(SUM(IF(tp.is_return = 0, tp.amount, tp.amount * -1)), 0) FROM transaction_payments as tp WHERE tp.transaction_id = transactions.id)), 0) as sum_total_paid'
        )->first();
        $sumFinalTotal = (float) ($totalsRow->sum_final_total ?? 0);
        $sumTotalPaid = (float) ($totalsRow->sum_total_paid ?? 0);
        $totals = [
            'count'           => (int) ($totalsRow->count_rows ?? 0),
            'sum_final_total' => $sumFinalTotal,
            'sum_total_paid'  => $sumTotalPaid,
            'sum_due'         => max(0.0, $sumFinalTotal - $sumTotalPaid),
        ];

        // total_paid via subquery — coluna não existe em transactions (UltimatePOS pattern).
        // Ref: TransactionUtil.php:2400 e :2983.
        $paginator = $q->orderBy($sortMap[$sortKey], $sortDir)
            // Ordem secundária estável pra empate (id desc evita resultados embaralhados).
            ->orderBy('transactions.id', 'desc')
            ->paginate($perPage, [
                'transactions.id',
                'transactions.transaction_date',
                'transactions.invoice_no',
                'transactions.final_total',
                \DB::raw('(SELECT COALESCE(SUM(IF(tp.is_return = 0, tp.amount, tp.amount * -1)), 0) FROM transaction_payments as tp WHERE tp.transaction_id = transactions.id) as total_paid'),
                'transactions.payment_status',
                'transactions.shipping_status',
                'transactions.pay_term_number',
                'transactions.pay_term_type',
                'contacts.name as customer_name',
                'contacts.supplier_business_name as customer_business',
                'bl.name as location_name',
                // US-SELL-021 — display_date é o valor do date_field escolhido pra mostrar na coluna Data.
                \DB::raw($dateFieldSql . ' as display_date'),
                // US-SELL-023 — current_stage_key vem do JOIN sale_process_stages (badge produção).
                // NULL pra vendas legadas sem FSM iniciado.
                'sps.key as current_stage_key',
                // US-SELL-024 — flag boolean explícita "venda agrupada" (default false em vendas legadas).
                // Defesa: COALESCE pra schemas SQLite Pest sem a coluna ainda (default 0).
                \DB::raw('COALESCE(transactions.is_grouped_invoice, 0) as is_grouped_invoice'),
                // US-SELL-COWORK — pipeline visual (dot stepper + label) do prototype KB-9.75.
                // pipeline_total via subquery — mesmo process_id da stage atual.
                'sps.sort_order as pipeline_sort_order',
                'sps.name as pipeline_name',
                'sps.color as pipeline_color',
                \DB::raw('(SELECT COUNT(*) FROM sale_process_stages sps_t WHERE sps_t.process_id = sps.process_id) as pipeline_total'),
                // US-SELL-COWORK — vendedor (created_by → users).
                'seller_u.first_name as seller_first_name',
                'seller_u.surname as seller_surname',
                'seller_u.username as seller_username',
                'seller_u.id as seller_id',
                // US-SELL-COWORK — items_summary (primeiro produto + qty total) pra exibir sub-linha "cliente / produto".
                \DB::raw('(SELECT p.name FROM transaction_sell_lines tsl_n LEFT JOIN products p ON tsl_n.product_id = p.id WHERE tsl_n.transaction_id = transactions.id ORDER BY tsl_n.id ASC LIMIT 1) as items_first_name'),
                \DB::raw('(SELECT COUNT(*) FROM transaction_sell_lines tsl_c WHERE tsl_c.transaction_id = transactions.id) as items_count'),
                \DB::raw('(SELECT COALESCE(SUM(tsl_q.quantity), 0) FROM transaction_sell_lines tsl_q WHERE tsl_q.transaction_id = transactions.id) as items_total_qty'),
                // US-SELL-COWORK — pagamento: método dominante (último registrado) + número de parcelas (count > 0 amount).
                \DB::raw('(SELECT method FROM transaction_payments tp_m WHERE tp_m.transaction_id = transactions.id AND tp_m.is_return = 0 ORDER BY tp_m.id DESC LIMIT 1) as last_payment_method'),
                \DB::raw('(SELECT COUNT(*) FROM transaction_payments tp_i WHERE tp_i.transaction_id = transactions.id AND tp_i.is_return = 0 AND tp_i.amount > 0) as installments_count'),
            ], 'page', $page);
        $rows = $paginator->getCollection();

        // US-NFE-MANUAL — lookup fiscal_status pra mostrar badge na lista (1 query extra).
        // Pega emissão mais recente por TX (autorizada > pendente > rejeitada).
        $txIds = $rows->pluck('id')->toArray();
        $fiscalByTx = collect();
        if (!empty($txIds) && class_exists(\Modules\NfeBrasil\Models\NfeEmissao::class)) {
            $fiscalByTx = \Modules\NfeBrasil\Models\NfeEmissao::where('business_id', $business_id)
                ->whereIn('transaction_id', $txIds)
                ->orderByDesc('id')
                ->get(['transaction_id', 'modelo', 'status'])
                ->groupBy('transaction_id')
                ->map(fn($group) => $group->first());
        }

        $rows = $rows
            ->map(function ($r) use ($fiscalByTx) {
                // Calcula overdue inline (boolean derivado, evita re-query).
                $overdue = false;
                $daysToDue = null;
                if (in_array($r->payment_status, ['due', 'partial'], true) && $r->pay_term_number && $r->pay_term_type) {
                    $dueDate = $r->pay_term_type === 'days'
                        ? \Carbon\Carbon::parse($r->transaction_date)->addDays((int) $r->pay_term_number)
                        : \Carbon\Carbon::parse($r->transaction_date)->addMonths((int) $r->pay_term_number);
                    $overdue = $dueDate->isPast();
                    // US-SELL-COWORK — days_to_due signed int (negativo = atraso).
                    // diffInDays(false) preserva sinal. now()->startOfDay() pra evitar drift de horas.
                    $daysToDue = (int) round(\Carbon\Carbon::now()->startOfDay()->diffInDays($dueDate, false));
                }

                // US-SELL-COWORK — sla_kind (fresh/warning/overdue/paid) — espelha vdSlaInfo() do prototype.
                //   fsm/payment paid → paid (sem SLA)
                //   overdue ou daysToDue < 0 → overdue
                //   daysToDue <= 7 → warning (atrasando)
                //   daysToDue > 7 → fresh (vence em N+d)
                //   sem pay_term → fresh (não está atrasando — sem prazo definido)
                if ($r->payment_status === 'paid') {
                    $slaKind = 'paid';
                } elseif ($daysToDue === null) {
                    $slaKind = 'fresh';
                } elseif ($daysToDue < 0) {
                    $slaKind = 'overdue';
                } elseif ($daysToDue <= 7) {
                    $slaKind = 'warning';
                } else {
                    $slaKind = 'fresh';
                }

                $fiscal = $fiscalByTx->get($r->id);

                // US-SELL-COWORK — seller_name display (preferência first_name; fallback username; fallback null).
                $sellerName = trim(($r->seller_first_name ?? '') . ' ' . ($r->seller_surname ?? '')) ?: ($r->seller_username ?? null);
                $sellerAbbr = null;
                if ($sellerName) {
                    $parts = explode(' ', trim($sellerName));
                    $sellerAbbr = mb_strtoupper(mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : mb_substr($parts[0], 1, 1)));
                }

                // US-SELL-COWORK — payment_method_label PT-BR (UltimatePOS armazena chaves curtas).
                $paymentMethodLabel = match ((string) ($r->last_payment_method ?? '')) {
                    'cash'         => 'Dinheiro',
                    'card'         => 'Cartão',
                    'bank_transfer'=> 'Transferência',
                    'cheque'       => 'Cheque',
                    'other'        => 'Outro',
                    'custom_pay_1' => 'PIX',
                    'custom_pay_2' => 'Boleto',
                    'custom_pay_3' => 'Crediário',
                    ''             => null,
                    default        => ucfirst((string) ($r->last_payment_method ?? '')),
                };

                // US-SELL-COWORK — items_summary "Produto X · 200un" ou "Produto X (+2 itens)".
                $itemsSummary = null;
                if ($r->items_first_name) {
                    $qtyInt = (int) ($r->items_total_qty ?? 0);
                    $cnt = (int) ($r->items_count ?? 0);
                    if ($cnt > 1) {
                        $itemsSummary = $r->items_first_name . ' (+' . ($cnt - 1) . ' itens)';
                    } elseif ($qtyInt > 0) {
                        $itemsSummary = $r->items_first_name . ' · ' . $qtyInt . 'un';
                    } else {
                        $itemsSummary = $r->items_first_name;
                    }
                }

                return [
                    'id' => $r->id,
                    'transaction_date' => $r->transaction_date,
                    // US-SELL-021 — valor a exibir na coluna Data (depende do date_field escolhido).
                    'display_date' => $r->display_date ?? $r->transaction_date,
                    'invoice_no' => $r->invoice_no,
                    'final_total' => (float) $r->final_total,
                    'total_paid' => (float) $r->total_paid,
                    'payment_status' => $r->payment_status,
                    'shipping_status' => $r->shipping_status,
                    'customer_name' => $r->customer_business ?: $r->customer_name,
                    'customer_secondary' => $r->customer_business && $r->customer_name !== $r->customer_business ? $r->customer_name : null,
                    'location_name' => $r->location_name,
                    'is_overdue' => $overdue,
                    // US-NFE-MANUAL — fiscal_status pra badge na lista.
                    'fiscal_status' => $fiscal?->status,
                    'fiscal_modelo' => $fiscal ? (string) $fiscal->modelo : null,
                    // US-SELL-023 — stage_key FSM (NULL = legacy sem FSM iniciado).
                    // Frontend mapeia pra badge ("Em produção", "Faturada", etc) ou "—" silencioso.
                    'current_stage_key' => $r->current_stage_key,
                    // US-SELL-024 — boolean explícito "venda agrupada" (cast pra bool defensivo
                    // — vem como 0/1 do COALESCE quando coluna ausente em SQLite Pest).
                    'is_grouped_invoice' => (bool) $r->is_grouped_invoice,

                    // US-SELL-COWORK — campos novos do prototype KB-9.75 (visual-comparison.md):
                    'sla_kind' => $slaKind,            // fresh | warning | overdue | paid
                    'days_to_due' => $daysToDue,       // signed int, null quando paid/sem pay_term
                    'pay_term_number' => $r->pay_term_number,
                    'pay_term_type' => $r->pay_term_type,

                    'pipeline_step' => $r->pipeline_sort_order !== null ? (int) $r->pipeline_sort_order : null,
                    'pipeline_total' => $r->pipeline_total !== null ? (int) $r->pipeline_total : null,
                    'pipeline_label' => $r->pipeline_name,
                    'pipeline_color' => $r->pipeline_color,

                    'seller_id' => $r->seller_id ?? null,
                    'seller_name' => $sellerName,
                    'seller_abbr' => $sellerAbbr,
                    // origem fixa "balcão" como fallback — UltimatePOS não tem campo dedicado
                    // (refino futuro: source_channel via custom_field_X).
                    'seller_origin' => 'balcão',

                    'items_summary' => $itemsSummary,
                    'items_count' => (int) ($r->items_count ?? 0),

                    'payment_method_label' => $paymentMethodLabel,
                    'installments' => (int) ($r->installments_count ?? 0),
                ];
            });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
                'sort'         => $sortKey,
                'dir'          => $sortDir,
                // US-SELL-021 — echo do campo escolhido (validado via whitelist).
                'date_field'   => $dateField,
            ],
            // US-SELL-017 — totais sobre o filtro inteiro (não só página).
            'totals' => $totals,
        ]);
    }

    /**
     * US-SELL-016 — Bulk Print. Recebe lista de transaction IDs e devolve uma
     * página HTML printable que combina todos os recibos/DANFEs num doc só.
     * Reusa receiptContent() (mesmo helper do printInvoice) por venda + concatena.
     *
     * Multi-tenant Tier 0 (ADR 0093): SEMPRE filtra business_id antes de carregar.
     * IDs cross-tenant são silenciosamente descartados (não vaza existência).
     *
     * Payload: { ids: [int, int, ...] } (max 200 anti-DoS).
     * Retorna: text/html (browser imprime via window.print).
     */
    public function bulkPrint(Request $request)
    {
        if (!auth()->user()->can('direct_sell.view') &&
            !auth()->user()->can('view_own_sell_only') &&
            !auth()->user()->can('view_commission_agent_sell')) {
            abort(403);
        }

        $business_id = $request->session()->get('user.business_id');
        $ids = (array) $request->input('ids', []);
        // Sanitiza: só inteiros positivos, max 200 (anti-DoS, mesma constante da lista).
        $ids = array_values(array_filter(array_map('intval', $ids), fn($i) => $i > 0));
        if (count($ids) > 200) {
            $ids = array_slice($ids, 0, 200);
        }
        if (empty($ids)) {
            return response('Nenhuma venda selecionada.', 400);
        }

        // Multi-tenant: WHERE business_id BEFORE WHERE IN (Tier 0 ADR 0093).
        $query = \App\Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNull('sub_type')
            ->whereIn('id', $ids);

        // Permission scope (mesmo padrão do inertiaList — defesa em profundidade).
        if (!auth()->user()->can('direct_sell.view')) {
            $userId = request()->session()->get('user.id');
            $query->where(function ($qq) use ($userId) {
                if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                    $qq->where('created_by', $userId);
                }
                if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                    $qq->orWhere('commission_agent', $userId);
                }
            });
        }

        $transactions = $query->with(['location'])->get();

        if ($transactions->isEmpty()) {
            return response('Nenhuma venda válida no seu tenant.', 404);
        }

        // Reusa receiptContent() de SellPosController via Reflection (private method).
        // Alternativa: refactor pra public/Service — fora do escopo P0 desta US.
        $sellPosController = app(\App\Http\Controllers\SellPosController::class);
        $reflection = new \ReflectionClass($sellPosController);
        $method = $reflection->getMethod('receiptContent');
        $method->setAccessible(true);

        $receipts = [];
        foreach ($transactions as $tx) {
            $invoice_layout_id = $tx->is_direct_sale ? optional($tx->location)->sale_invoice_layout_id : null;
            try {
                $receipt = $method->invoke(
                    $sellPosController,
                    $business_id,
                    $tx->location_id,
                    $tx->id,
                    'browser', // printer_type
                    false,     // is_package_slip
                    false,     // from_pos_screen
                    $invoice_layout_id,
                    false      // is_delivery_note
                );
                if (!empty($receipt) && !empty($receipt['html_content'])) {
                    $receipts[] = $receipt['html_content'];
                }
            } catch (\Throwable $e) {
                \Log::warning('[bulkPrint] Falha receipt tx '.$tx->id.': '.$e->getMessage());
            }
        }

        if (empty($receipts)) {
            return response('Falha ao gerar recibos.', 500);
        }

        // Combina HTML — page-break-after entre recibos pra impressora separar.
        $body = '';
        foreach ($receipts as $idx => $html) {
            $pageBreak = $idx < count($receipts) - 1 ? 'style="page-break-after: always;"' : '';
            $body .= '<div '.$pageBreak.'>'.$html.'</div>';
        }

        $combined = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Vendas selecionadas</title>'.
            '<style>@media print { @page { margin: 1cm; } } body { font-family: sans-serif; }</style>'.
            '</head><body onload="window.print()">'.$body.'</body></html>';

        return response($combined)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * US-SELL-016 — Bulk Export CSV. Recebe IDs + colunas opcionais e devolve
     * arquivo CSV download. Multi-tenant Tier 0: SEMPRE business_id where antes
     * de WHERE IN. Streaming response pra evitar memória em export grande.
     *
     * Payload: { ids: [int], columns?: [string] }.
     * Retorna: text/csv attachment.
     */
    public function bulkExport(Request $request)
    {
        if (!auth()->user()->can('direct_sell.view') &&
            !auth()->user()->can('view_own_sell_only') &&
            !auth()->user()->can('view_commission_agent_sell')) {
            abort(403);
        }

        $business_id = $request->session()->get('user.business_id');
        $ids = (array) $request->input('ids', []);
        $ids = array_values(array_filter(array_map('intval', $ids), fn($i) => $i > 0));
        if (count($ids) > 200) {
            $ids = array_slice($ids, 0, 200);
        }
        if (empty($ids)) {
            return response('Nenhuma venda selecionada.', 400);
        }

        // Whitelist de colunas (default: tudo que aparece na grid).
        $allColumns = [
            'transaction_date' => 'Data',
            'invoice_no'       => 'Nº fatura',
            'customer_name'    => 'Cliente',
            'final_total'      => 'Total',
            'total_paid'       => 'Pago',
            'due_amount'       => 'A receber',
            'payment_status'   => 'Status pagamento',
            'location_name'    => 'Localização',
        ];
        $requestedCols = (array) $request->input('columns', array_keys($allColumns));
        $columns = array_values(array_intersect($requestedCols, array_keys($allColumns)));
        if (empty($columns)) {
            $columns = array_keys($allColumns);
        }

        $query = \App\Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->leftJoin('business_locations as bl', 'transactions.location_id', '=', 'bl.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->whereNull('transactions.sub_type')
            ->whereIn('transactions.id', $ids);

        // Permission scope (mesmo padrão do inertiaList — defesa em profundidade).
        if (!auth()->user()->can('direct_sell.view')) {
            $userId = request()->session()->get('user.id');
            $query->where(function ($qq) use ($userId) {
                if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                    $qq->where('transactions.created_by', $userId);
                }
                if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                    $qq->orWhere('transactions.commission_agent', $userId);
                }
            });
        }

        $rows = $query->select([
                'transactions.id',
                'transactions.transaction_date',
                'transactions.invoice_no',
                'transactions.final_total',
                \DB::raw('(SELECT COALESCE(SUM(IF(tp.is_return = 0, tp.amount, tp.amount * -1)), 0) FROM transaction_payments as tp WHERE tp.transaction_id = transactions.id) as total_paid'),
                'transactions.payment_status',
                'contacts.name as customer_name',
                'contacts.supplier_business_name as customer_business',
                'bl.name as location_name',
            ])
            ->orderBy('transactions.transaction_date', 'desc')
            ->get();

        $filename = 'vendas-'.date('Y-m-d-His').'.csv';
        $callback = function () use ($rows, $columns, $allColumns) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pra Excel BR abrir com acentuação correta.
            fwrite($handle, "\xEF\xBB\xBF");
            // Cabeçalho PT-BR.
            $headers = array_map(fn($c) => $allColumns[$c], $columns);
            fputcsv($handle, $headers, ';');
            foreach ($rows as $r) {
                $line = [];
                foreach ($columns as $c) {
                    $val = match ($c) {
                        'transaction_date' => $r->transaction_date,
                        'invoice_no'       => (string) $r->invoice_no,
                        'customer_name'    => $r->customer_business ?: $r->customer_name ?: '',
                        'final_total'      => number_format((float) $r->final_total, 2, ',', '.'),
                        'total_paid'       => number_format((float) $r->total_paid, 2, ',', '.'),
                        'due_amount'       => number_format(max(0, (float) $r->final_total - (float) $r->total_paid), 2, ',', '.'),
                        'payment_status'   => $r->payment_status,
                        'location_name'    => $r->location_name ?: '',
                        default            => '',
                    };
                    $line[] = $val;
                }
                fputcsv($handle, $line, ';');
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    /**
     * US-SELL-008 — Detalhes JSON pra drawer SaleSheet.tsx (lateral direito).
     * Reusa show() lógica mas serializa minimal pra React renderizar.
     */
    public function sheetData($id)
    {
        if (!auth()->user()->can('direct_sell.view') &&
            !auth()->user()->can('view_own_sell_only') &&
            !auth()->user()->can('view_commission_agent_sell')) {
            abort(403);
        }

        $business_id = request()->session()->get('user.business_id');

        $sale = \App\Transaction::with([
            'contact:id,name,supplier_business_name,mobile,email',
            'sell_lines:id,transaction_id,product_id,quantity,unit_price_inc_tax,line_discount_amount',
            'sell_lines.product:id,name,sku',
            'payment_lines:id,transaction_id,amount,method,paid_on,note,is_return',
            'location:id,name',
        ])
            ->where('business_id', $business_id)
            ->where('type', 'sell')
            ->whereNull('sub_type')
            ->find($id);

        if (!$sale) {
            abort(404);
        }

        // total_paid não existe em transactions — soma via payment_lines (já eager loaded).
        $totalPaid = $sale->payment_lines->reduce(function ($carry, $p) {
            return $carry + ((bool) ($p->is_return ?? false) ? -1 : 1) * (float) $p->amount;
        }, 0.0);

        return response()->json([
            'id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'transaction_date' => $sale->transaction_date,
            'final_total' => (float) $sale->final_total,
            'total_paid' => $totalPaid,
            'tax_amount' => (float) $sale->tax_amount,
            'discount_amount' => (float) $sale->discount_amount,
            'discount_type' => $sale->discount_type,
            'shipping_charges' => (float) $sale->shipping_charges,
            'payment_status' => $sale->payment_status,
            'shipping_status' => $sale->shipping_status,
            'status' => $sale->status,
            'additional_notes' => $sale->additional_notes,
            'customer' => $sale->contact ? [
                'id' => $sale->contact->id,
                'name' => $sale->contact->supplier_business_name ?: $sale->contact->name,
                'secondary' => $sale->contact->supplier_business_name && $sale->contact->name !== $sale->contact->supplier_business_name
                    ? $sale->contact->name : null,
                'mobile' => $sale->contact->mobile,
                'email' => $sale->contact->email,
            ] : null,
            'location' => $sale->location ? ['id' => $sale->location->id, 'name' => $sale->location->name] : null,
            'lines' => $sale->sell_lines->map(fn($l) => [
                'id' => $l->id,
                'product_name' => $l->product?->name,
                'product_sku' => $l->product?->sku,
                'quantity' => (float) $l->quantity,
                'unit_price' => (float) $l->unit_price_inc_tax,
                'discount' => (float) $l->line_discount_amount,
                'subtotal' => (float) $l->quantity * (float) $l->unit_price_inc_tax - (float) $l->line_discount_amount,
            ])->values(),
            'payments' => $sale->payment_lines->map(fn($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'paid_on' => $p->paid_on,
                'note' => $p->note,
            ])->values(),
            'urls' => [
                'edit' => '/sells/' . $sale->id . '/edit',
                'print' => '/sells/' . $sale->id . '/print',
            ],
        ]);
    }

    /**
     * US-SELL-PAY-DRAWER — POST JSON pra criar TransactionPayment a partir do
     * drawer SaleSheet. Versão enxuta de TransactionPaymentController@store
     * (sem cartão/cheque/denominations) que retorna JSON em vez de redirect.
     *
     * Payload: { amount, method, paid_on?, note?, account_id? }
     * Retorna: { success, msg, payment_status, total_paid }
     */
    public function quickPayment(\Illuminate\Http\Request $request, $id)
    {
        if (! auth()->user()->can('sell.payments')) {
            abort(403);
        }

        $business_id = $request->session()->get('user.business_id');

        $sale = \App\Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->whereNull('sub_type')
            ->find($id);

        if (! $sale) {
            return response()->json(['success' => false, 'msg' => 'Venda não encontrada.'], 404);
        }

        if ($sale->payment_status === 'paid') {
            return response()->json(['success' => false, 'msg' => 'Venda já está totalmente paga.'], 422);
        }

        $data = $request->validate([
            'amount'     => ['required', 'numeric', 'min:0.01'],
            'method'     => ['required', 'string', 'max:30'],
            'paid_on'    => ['nullable', 'date'],
            'note'       => ['nullable', 'string', 'max:500'],
            'account_id' => ['nullable', 'integer'],
        ]);

        try {
            \DB::beginTransaction();

            $ref_count = $this->transactionUtil->setAndGetReferenceCount('sell_payment');

            $payment = \App\TransactionPayment::create([
                'business_id'    => $business_id,
                'transaction_id' => $sale->id,
                'amount'         => $data['amount'],
                'method'         => $data['method'],
                'paid_on'        => ! empty($data['paid_on'])
                    ? \Carbon\Carbon::parse($data['paid_on'])->toDateTimeString()
                    : now()->toDateTimeString(),
                'note'           => $data['note'] ?? null,
                'account_id'     => $data['account_id'] ?? null,
                'created_by'     => auth()->user()->id,
                'payment_for'    => $sale->contact_id,
                'payment_ref_no' => $this->transactionUtil->generateReferenceNumber('sell_payment', $ref_count),
            ]);

            event(new \App\Events\TransactionPaymentAdded($payment, [
                'transaction_type' => $sale->type,
                'amount'           => $data['amount'],
                'method'           => $data['method'],
            ]));

            $payment_status = $this->transactionUtil->updatePaymentStatus($sale->id, $sale->final_total);

            \DB::commit();

            // Recalcula total_paid pra retornar atualizado.
            $totalPaid = (float) \DB::table('transaction_payments')
                ->where('transaction_id', $sale->id)
                ->sum(\DB::raw('IF(is_return = 0, amount, amount * -1)'));

            return response()->json([
                'success'        => true,
                'msg'            => 'Pagamento registrado.',
                'payment_status' => $payment_status,
                'total_paid'     => $totalPaid,
                'payment'        => [
                    'id'      => $payment->id,
                    'amount'  => (float) $payment->amount,
                    'method'  => $payment->method,
                    'paid_on' => $payment->paid_on,
                    'note'    => $payment->note,
                ],
            ]);
        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error('SellController.quickPayment failed', [
                'sale_id' => $id,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'msg'     => 'Falha ao registrar pagamento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * US-OFICINA-OS-LINK — Cria Service Order(s) a partir de uma venda.
     *
     * Suporta os 2 modos canônicos:
     *   - 'single'   — 1 OS pra venda toda (caso Martinho)
     *   - 'per_line' — 1 OS por produto (caso ComunicacaoVisual)
     *   - 'auto'     — lê business.os_default_per_line
     *
     * Multi-tenant Tier 0 (ADR 0093): business_id da transaction (nunca payload).
     * Idempotente (Service deduplica).
     *
     * Payload: { mode: 'auto'|'single'|'per_line' }
     * Retorna: { success, message, mode_resolved, created_count, existing_count, service_orders[] }
     */
    public function createOs(\Illuminate\Http\Request $request, $id, \App\Services\CriarOsPorVendaService $service)
    {
        if (! auth()->user()->can('direct_sell.view')
            && ! auth()->user()->can('direct_sell.access')
            && ! auth()->user()->can('view_own_sell_only')) {
            abort(403);
        }

        $business_id = $request->session()->get('user.business_id');

        $sale = \App\Transaction::with('sell_lines.product:id,name,sku')
            ->where('business_id', $business_id)
            ->where('type', 'sell')
            ->whereNull('sub_type')
            ->find($id);

        if (! $sale) {
            return response()->json(['success' => false, 'msg' => 'Venda não encontrada.'], 404);
        }

        $data = $request->validate([
            'mode' => ['nullable', 'string', 'in:auto,single,per_line'],
        ]);
        $mode = $data['mode'] ?? \App\Services\CriarOsPorVendaService::MODE_AUTO;

        try {
            $result = $service->criar($sale, $mode);

            return response()->json([
                'success'        => true,
                'message'        => $result['message'],
                'mode_resolved'  => $result['mode_resolved'],
                'created_count'  => $result['created']->count(),
                'existing_count' => $result['existing']->count(),
                'service_orders' => $result['created']->merge($result['existing'])->map(fn ($os) => [
                    'id'                       => $os->id,
                    'transaction_id'           => $os->transaction_id,
                    'transaction_sell_line_id' => $os->transaction_sell_line_id,
                    'status'                   => $os->status,
                    'vehicle_id'               => $os->vehicle_id,
                ])->values(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'msg' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('SellController.createOs failed', [
                'sale_id' => $id,
                'mode'    => $mode,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'msg'     => 'Falha ao criar OS: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // Wave 1 W1-A — MWART migração. Branch dual Inertia/Blade preserva legacy.
        // Auth gate explícito (legacy tinha comentado; reativado conforme ADR 0093 + RUNBOOK-show §2).
        if (! auth()->user()->can('sell.view') && ! auth()->user()->can('direct_sell.access') && ! auth()->user()->can('view_own_sell_only')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $taxes = TaxRate::where('business_id', $business_id)
                            ->pluck('name', 'id');
        $query = Transaction::where('business_id', $business_id)
                    ->where('id', $id)
                    ->with(['contact', 'delivery_person_user', 'sell_lines' => function ($q) {
                        $q->whereNull('parent_sell_line_id');
                    }, 'sell_lines.product', 'sell_lines.product.unit', 'sell_lines.product.second_unit', 'sell_lines.variations', 'sell_lines.variations.product_variation', 'payment_lines', 'sell_lines.modifiers', 'sell_lines.lot_details', 'tax', 'sell_lines.sub_unit', 'table', 'service_staff', 'sell_lines.service_staff', 'types_of_service', 'sell_lines.warranties', 'media']);

        if (! auth()->user()->can('sell.view') && ! auth()->user()->can('direct_sell.access') && auth()->user()->can('view_own_sell_only')) {
            $query->where('transactions.created_by', request()->session()->get('user.id'));
        }

        $sell = $query->firstOrFail();

        $activities = Activity::forSubject($sell)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        $line_taxes = [];
        foreach ($sell->sell_lines as $key => $value) {
            if (! empty($value->sub_unit_id)) {
                $formated_sell_line = $this->transactionUtil->recalculateSellLineTotals($business_id, $value);
                $sell->sell_lines[$key] = $formated_sell_line;
            }

            if (! empty($taxes[$value->tax_id])) {
                if (isset($line_taxes[$taxes[$value->tax_id]])) {
                    $line_taxes[$taxes[$value->tax_id]] += ($value->item_tax * $value->quantity);
                } else {
                    $line_taxes[$taxes[$value->tax_id]] = ($value->item_tax * $value->quantity);
                }
            }
        }

        $payment_types = $this->transactionUtil->payment_types($sell->location_id, true);
        $order_taxes = [];
        if (! empty($sell->tax)) {
            if ($sell->tax->is_tax_group) {
                $order_taxes = $this->transactionUtil->sumGroupTaxDetails($this->transactionUtil->groupTaxDetails($sell->tax, $sell->tax_amount));
            } else {
                $order_taxes[$sell->tax->name] = $sell->tax_amount;
            }
        }

        $business_details = $this->businessUtil->getDetails($business_id);
        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);
        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        $shipping_status_colors = $this->shipping_status_colors;
        $common_settings = session()->get('business.common_settings');
        $is_warranty_enabled = ! empty($common_settings['enable_product_warranty']) ? true : false;

        $statuses = Transaction::sell_statuses();

        if ($sell->type == 'sales_order') {
            $sales_order_statuses = Transaction::sales_order_statuses(true);
            $statuses = array_merge($statuses, $sales_order_statuses);
        }
        $status_color_in_activity = Transaction::sales_order_statuses();
        $sales_orders = $sell->salesOrders();

        // Wave 1 W1-A — branch dual MWART. Inertia se header X-Inertia presente.
        // Detail (8 with()-pesados + activities + line_taxes) vai DEFERRED — RUNBOOK-show §3.1.
        if (request()->header('X-Inertia')) {
            $totalPaid = (float) collect($sell->payment_lines ?? [])->sum('amount');
            $headline = [
                'id' => (int) $sell->id,
                'invoice_no' => (string) $sell->invoice_no,
                'transaction_date' => (string) $sell->transaction_date,
                'final_total' => (float) $sell->final_total,
                'total_paid' => $totalPaid,
                'payment_status' => (string) $sell->payment_status,
                'status' => (string) $sell->status,
                'current_stage_key' => null,  // FSM ADR 0143 — lazy preencher se relation existir
                'customer' => $sell->contact ? [
                    'id' => (int) $sell->contact->id,
                    'name' => (string) $sell->contact->name,
                    'mobile' => $sell->contact->mobile ?: null,
                    'email' => $sell->contact->email ?: null,
                ] : null,
                'location' => $sell->location_id ? [
                    'id' => (int) $sell->location_id,
                    'name' => optional(\App\BusinessLocation::find($sell->location_id))->name,
                ] : null,
            ];

            // Detail payload já calculado acima — encapsular pra defer.
            $detailPayload = function () use ($sell, $taxes, $payment_types, $order_taxes, $pos_settings, $shipping_statuses, $shipping_status_colors, $is_warranty_enabled, $activities, $statuses, $status_color_in_activity, $sales_orders, $line_taxes) {
                return [
                    'lines' => collect($sell->sell_lines)->map(function ($line) {
                        return [
                            'id' => (int) $line->id,
                            'product_name' => optional($line->product)->name ?? '',
                            'product_sku' => optional($line->variations)->sub_sku ?? '',
                            'quantity' => (float) $line->quantity,
                            'unit_price' => (float) $line->unit_price,
                            'discount' => (float) ($line->line_discount_amount ?? 0),
                            'subtotal' => (float) ($line->unit_price_inc_tax * $line->quantity),
                            'tax_amount' => (float) ($line->item_tax * $line->quantity),
                            'unit' => optional(optional($line->product)->unit)->short_name ?? '',
                        ];
                    })->values()->toArray(),
                    'payments' => collect($sell->payment_lines ?? [])->map(function ($p) {
                        return [
                            'id' => (int) $p->id,
                            'amount' => (float) $p->amount,
                            'method' => (string) $p->method,
                            'paid_on' => $p->paid_on ? (string) $p->paid_on : null,
                            'note' => $p->note ? (string) $p->note : null,
                        ];
                    })->values()->toArray(),
                    'taxes' => [
                        'order_taxes' => $order_taxes,
                        'line_taxes' => $line_taxes,
                    ],
                    'activities' => collect($activities)->map(function ($a) {
                        return [
                            'description' => (string) $a->description,
                            'causer_name' => optional($a->causer)->user_full_name ?? (optional($a->causer)->surname . ' ' . optional($a->causer)->first_name),
                            'created_at' => (string) $a->created_at,
                        ];
                    })->values()->toArray(),
                    'shipping' => [
                        'details' => (string) ($sell->shipping_details ?? ''),
                        'address' => (string) ($sell->shipping_address ?? ''),
                        'cost' => (float) ($sell->shipping_charges ?? 0),
                        'status' => $sell->shipping_status ? (string) $sell->shipping_status : null,
                    ],
                    'notes' => $sell->additional_notes ? (string) $sell->additional_notes : null,
                    'sub_type' => $sell->sub_type ? (string) $sell->sub_type : null,
                    'sales_orders' => collect($sales_orders ?? [])->map(fn ($so) => (string) ($so->invoice_no ?? ''))->values()->toArray(),
                    'statuses' => $statuses,
                    'shipping_statuses' => $shipping_statuses,
                    'is_warranty_enabled' => (bool) $is_warranty_enabled,
                ];
            };

            return Inertia::render('Sells/Show', [
                'saleId' => (int) $id,
                'headline' => $headline,
                'detail' => Inertia::defer($detailPayload),
                'permissions' => [
                    'edit' => auth()->user()->can('direct_sell.update') || auth()->user()->can('so.update'),
                    'delete' => auth()->user()->can('direct_sell.delete') || auth()->user()->can('sell.delete'),
                    'print' => true,
                ],
                'urls' => [
                    'edit' => action([\App\Http\Controllers\SellController::class, 'edit'], [$id]),
                    'print' => '/sells/' . $id . '/print',
                    'sheet_data' => '/sells/' . $id . '/sheet-data',
                    'back' => '/sells',
                ],
            ]);
        }

        return view('sale_pos.show')
            ->with(compact(
                'taxes',
                'sell',
                'payment_types',
                'order_taxes',
                'pos_settings',
                'shipping_statuses',
                'shipping_status_colors',
                'is_warranty_enabled',
                'activities',
                'statuses',
                'status_color_in_activity',
                'sales_orders',
                'line_taxes'
            ));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('direct_sell.update') && ! auth()->user()->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        //Check if the transaction can be edited or not.
        $edit_days = request()->session()->get('business.transaction_edit_days');
        if (! $this->transactionUtil->canBeEdited($id, $edit_days)) {
            // Wave 1 W1-A — resposta 422 JSON se header X-Inertia (frontend trata via onError).
            if (request()->header('X-Inertia')) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('messages.transaction_edit_not_allowed', ['days' => $edit_days]),
                ], 422);
            }
            return back()
                ->with('status', ['success' => 0,
                    'msg' => __('messages.transaction_edit_not_allowed', ['days' => $edit_days]), ]);
        }

        //Check if return exist then not allowed
        if ($this->transactionUtil->isReturnExist($id)) {
            if (request()->header('X-Inertia')) {
                return response()->json([
                    'success' => 0,
                    'msg' => __('lang_v1.return_exist'),
                ], 422);
            }
            return back()->with('status', ['success' => 0,
                'msg' => __('lang_v1.return_exist'), ]);
        }

        $business_id = request()->session()->get('user.business_id');

        $business_details = $this->businessUtil->getDetails($business_id);
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $transaction = Transaction::where('business_id', $business_id)
                            ->with(['price_group', 'types_of_service', 'media', 'media.uploaded_by_user'])
                            ->whereIn('type', ['sell', 'sales_order'])
                            ->findorfail($id);

        if ($transaction->type == 'sales_order' && ! auth()->user()->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = $transaction->location_id;
        $location_printer_type = BusinessLocation::find($location_id)->receipt_printer_type;

        $sell_details = TransactionSellLine::join(
                            'products AS p',
                            'transaction_sell_lines.product_id',
                            '=',
                            'p.id'
                        )
                        ->join(
                            'variations AS variations',
                            'transaction_sell_lines.variation_id',
                            '=',
                            'variations.id'
                        )
                        ->join(
                            'product_variations AS pv',
                            'variations.product_variation_id',
                            '=',
                            'pv.id'
                        )
                        ->leftjoin('variation_location_details AS vld', function ($join) use ($location_id) {
                            $join->on('variations.id', '=', 'vld.variation_id')
                                ->where('vld.location_id', '=', $location_id);
                        })
                        ->leftjoin('units', 'units.id', '=', 'p.unit_id')
                        ->leftjoin('units as u', 'p.secondary_unit_id', '=', 'u.id')
                        ->where('transaction_sell_lines.transaction_id', $id)
                        ->with(['warranties', 'so_line'])
                        ->select(
                            DB::raw("IF(pv.is_dummy = 0, CONCAT(p.name, ' (', pv.name, ':',variations.name, ')'), p.name) AS product_name"),
                            'p.id as product_id',
                            'p.image as product_image',
                            'p.enable_stock',
                            'p.name as product_actual_name',
                            'p.type as product_type',
                            'pv.name as product_variation_name',
                            'pv.is_dummy as is_dummy',
                            'variations.name as variation_name',
                            'variations.sub_sku',
                            'p.barcode_type',
                            'p.enable_sr_no',
                            'variations.id as variation_id',
                            'units.short_name as unit',
                            'units.allow_decimal as unit_allow_decimal',
                            'u.short_name as second_unit',
                            'transaction_sell_lines.secondary_unit_quantity',
                            'transaction_sell_lines.tax_id as tax_id',
                            'transaction_sell_lines.item_tax as item_tax',
                            'transaction_sell_lines.unit_price as default_sell_price',
                            'transaction_sell_lines.unit_price_inc_tax as sell_price_inc_tax',
                            'transaction_sell_lines.unit_price_before_discount as unit_price_before_discount',
                            'transaction_sell_lines.id as transaction_sell_lines_id',
                            'transaction_sell_lines.id',
                            'transaction_sell_lines.quantity as quantity_ordered',
                            'transaction_sell_lines.sell_line_note as sell_line_note',
                            'transaction_sell_lines.parent_sell_line_id',
                            'transaction_sell_lines.lot_no_line_id',
                            'transaction_sell_lines.line_discount_type',
                            'transaction_sell_lines.line_discount_amount',
                            'transaction_sell_lines.res_service_staff_id',
                            'units.id as unit_id',
                            'transaction_sell_lines.sub_unit_id',
                            'transaction_sell_lines.so_line_id',
                            DB::raw('vld.qty_available + transaction_sell_lines.quantity AS qty_available')
                        )
                        ->get();

        if (! empty($sell_details)) {
            foreach ($sell_details as $key => $value) {

                $variation = Variation::with('media')->findOrFail($value->variation_id);
                $sell_details[$key]->media = $variation->media;

                //If modifier or combo sell line then unset
                if (! empty($sell_details[$key]->parent_sell_line_id)) {
                    unset($sell_details[$key]);
                } else {
                    if ($transaction->status != 'final') {
                        $actual_qty_avlbl = $value->qty_available - $value->quantity_ordered;
                        $sell_details[$key]->qty_available = $actual_qty_avlbl;
                        $value->qty_available = $actual_qty_avlbl;
                    }

                    $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($value->qty_available, false, null, true);
                    $lot_numbers = [];
                    if (request()->session()->get('business.enable_lot_number') == 1) {
                        $lot_number_obj = $this->transactionUtil->getLotNumbersFromVariation($value->variation_id, $business_id, $location_id);
                        foreach ($lot_number_obj as $lot_number) {
                            //If lot number is selected added ordered quantity to lot quantity available
                            if ($value->lot_no_line_id == $lot_number->purchase_line_id) {
                                $lot_number->qty_available += $value->quantity_ordered;
                            }

                            $lot_number->qty_formated = $this->transactionUtil->num_f($lot_number->qty_available);
                            $lot_numbers[] = $lot_number;
                        }
                    }
                    $sell_details[$key]->lot_numbers = $lot_numbers;

                    if (! empty($value->sub_unit_id)) {
                        $value = $this->productUtil->changeSellLineUnit($business_id, $value);
                        $sell_details[$key] = $value;
                    }

                    if ($this->transactionUtil->isModuleEnabled('modifiers')) {
                        //Add modifier details to sel line details
                        $sell_line_modifiers = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'modifier')
                            ->get();
                        $modifiers_ids = [];
                        if (count($sell_line_modifiers) > 0) {
                            $sell_details[$key]->modifiers = $sell_line_modifiers;
                            foreach ($sell_line_modifiers as $sell_line_modifier) {
                                $modifiers_ids[] = $sell_line_modifier->variation_id;
                            }
                        }
                        $sell_details[$key]->modifiers_ids = $modifiers_ids;

                        //add product modifier sets for edit
                        $this_product = Product::find($sell_details[$key]->product_id);
                        if (count($this_product->modifier_sets) > 0) {
                            $sell_details[$key]->product_ms = $this_product->modifier_sets;
                        }
                    }

                    //Get details of combo items
                    if ($sell_details[$key]->product_type == 'combo') {
                        $sell_line_combos = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'combo')
                            ->get()
                            ->toArray();
                        if (! empty($sell_line_combos)) {
                            $sell_details[$key]->combo_products = $sell_line_combos;
                        }

                        //calculate quantity available if combo product
                        $combo_variations = [];
                        foreach ($sell_line_combos as $combo_line) {
                            $combo_variations[] = [
                                'variation_id' => $combo_line['variation_id'],
                                'quantity' => $combo_line['quantity'] / $sell_details[$key]->quantity_ordered,
                                'unit_id' => null,
                            ];
                        }
                        $sell_details[$key]->qty_available =
                        $this->productUtil->calculateComboQuantity($location_id, $combo_variations);

                        if ($transaction->status == 'final') {
                            $sell_details[$key]->qty_available = $sell_details[$key]->qty_available + $sell_details[$key]->quantity_ordered;
                        }

                        $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($sell_details[$key]->qty_available, false, null, true);
                    }
                }
            }
        }

        $commsn_agnt_setting = $business_details->sales_cmsn_agnt;
        $commission_agent = [];
        if ($commsn_agnt_setting == 'user') {
            $commission_agent = User::forDropdown($business_id);
        } elseif ($commsn_agnt_setting == 'cmsn_agnt') {
            $commission_agent = User::saleCommissionAgentsDropdown($business_id);
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

        $transaction->transaction_date = $this->transactionUtil->format_date($transaction->transaction_date, true);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $waiters = [];
        if ($this->productUtil->isModuleEnabled('service_staff') && ! empty($pos_settings['inline_service_staff'])) {
            $waiters = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $invoice_schemes = [];
        $default_invoice_schemes = null;

        if ($transaction->status == 'draft') {
            $invoice_schemes = InvoiceScheme::forDropdown($business_id);
            $default_invoice_schemes = InvoiceScheme::getDefault($business_id);
        }

        $redeem_details = [];
        if (request()->session()->get('business.enable_rp') == 1) {
            $redeem_details = $this->transactionUtil->getRewardRedeemDetails($business_id, $transaction->contact_id);

            $redeem_details['points'] += $transaction->rp_redeemed;
            $redeem_details['points'] -= $transaction->rp_earned;
        }

        $edit_discount = auth()->user()->can('edit_product_discount_from_sale_screen');
        $edit_price = auth()->user()->can('edit_product_price_from_sale_screen');

        //Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $common_settings = session()->get('business.common_settings');
        $is_warranty_enabled = ! empty($common_settings['enable_product_warranty']) ? true : false;
        $warranties = $is_warranty_enabled ? Warranty::forDropdown($business_id) : [];

        $statuses = Transaction::sell_statuses();

        $is_order_request_enabled = false;
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        if ($is_crm) {
            $crm_settings = Business::where('id', auth()->user()->business_id)
                                ->value('crm_settings');
            $crm_settings = ! empty($crm_settings) ? json_decode($crm_settings, true) : [];

            if (! empty($crm_settings['enable_order_request'])) {
                $is_order_request_enabled = true;
            }
        }

        $sales_orders = [];
        if (! empty($pos_settings['enable_sales_order']) || $is_order_request_enabled) {
            $sales_orders = Transaction::where('business_id', $business_id)
                                ->where('type', 'sales_order')
                                ->where('contact_id', $transaction->contact_id)
                                ->where(function ($q) use ($transaction) {
                                    $q->where('status', '!=', 'completed');

                                    if (! empty($transaction->sales_order_ids)) {
                                        $q->orWhereIn('id', $transaction->sales_order_ids);
                                    }
                                })
                                ->pluck('invoice_no', 'id');
        }

        $payment_types = $this->transactionUtil->payment_types($transaction->location_id, false, $business_id);

        $payment_lines = $this->transactionUtil->getPaymentDetails($id);
        //If no payment lines found then add dummy payment line.
        if (empty($payment_lines)) {
            $payment_lines[] = $this->dummyPaymentLine;
        }

        $change_return = $this->dummyPaymentLine;

        $customer_due = $this->transactionUtil->getContactDue($transaction->contact_id, $transaction->business_id);

        $customer_due = $customer_due != 0 ? $this->transactionUtil->num_f($customer_due, true) : '';

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        // Wave 1 W1-A — branch dual MWART. Inertia se header X-Inertia presente.
        // Form payload pesado (sell_details join 6 tables + payment_lines + dropdowns) vai DEFERRED.
        if (request()->header('X-Inertia')) {
            $headline = [
                'id' => (int) $transaction->id,
                'invoice_no' => (string) $transaction->invoice_no,
                'type' => (string) $transaction->type,
                'status' => (string) $transaction->status,
                'current_stage_key' => null,  // FSM ADR 0143 (lazy)
            ];

            $formPayload = function () use ($transaction, $business_details, $taxes, $sell_details, $commission_agent, $types, $customer_groups, $pos_settings, $waiters, $invoice_schemes, $default_invoice_schemes, $redeem_details, $edit_discount, $edit_price, $shipping_statuses, $warranties, $statuses, $sales_orders, $payment_types, $accounts, $payment_lines, $change_return, $is_order_request_enabled, $customer_due, $users) {
                return [
                    'transaction' => [
                        'id' => (int) $transaction->id,
                        'invoice_no' => (string) $transaction->invoice_no,
                        'transaction_date' => (string) $transaction->transaction_date,
                        'status' => (string) $transaction->status,
                        'contact_id' => (int) $transaction->contact_id,
                        'location_id' => (int) $transaction->location_id,
                        'final_total' => (float) $transaction->final_total,
                        'discount_type' => (string) ($transaction->discount_type ?? 'percentage'),
                        'discount_amount' => (float) ($transaction->discount_amount ?? 0),
                        'tax_id' => $transaction->tax_id ? (int) $transaction->tax_id : null,
                        'tax_amount' => (float) ($transaction->tax_amount ?? 0),
                        'shipping_details' => (string) ($transaction->shipping_details ?? ''),
                        'shipping_address' => (string) ($transaction->shipping_address ?? ''),
                        'shipping_charges' => (float) ($transaction->shipping_charges ?? 0),
                        'shipping_status' => $transaction->shipping_status ? (string) $transaction->shipping_status : null,
                        'additional_notes' => $transaction->additional_notes ? (string) $transaction->additional_notes : null,
                        'invoice_scheme_id' => $transaction->invoice_scheme_id ? (int) $transaction->invoice_scheme_id : null,
                        'pay_term_number' => $transaction->pay_term_number,
                        'pay_term_type' => $transaction->pay_term_type ? (string) $transaction->pay_term_type : null,
                    ],
                    'sellDetails' => $sell_details,
                    'taxes' => $taxes,
                    'commissionAgents' => $commission_agent,
                    'types' => $types,
                    'customerGroups' => $customer_groups,
                    'posSettings' => $pos_settings,
                    'waiters' => $waiters,
                    'invoiceSchemes' => $invoice_schemes,
                    'defaultInvoiceScheme' => $default_invoice_schemes,
                    'redeemDetails' => $redeem_details,
                    'permissions' => [
                        'editDiscount' => (bool) $edit_discount,
                        'editPrice' => (bool) $edit_price,
                    ],
                    'shippingStatuses' => $shipping_statuses,
                    'warranties' => $warranties,
                    'statuses' => $statuses,
                    'salesOrders' => $sales_orders,
                    'paymentTypes' => $payment_types,
                    'accounts' => $accounts,
                    'paymentLines' => $payment_lines,
                    'isOrderRequestEnabled' => (bool) $is_order_request_enabled,
                    'customerDue' => (string) $customer_due,
                    'users' => $users,
                ];
            };

            return Inertia::render('Sells/Edit', [
                'saleId' => (int) $id,
                'headline' => $headline,
                'form' => Inertia::defer($formPayload),
                'permissions' => [
                    'editPrice' => (bool) $edit_price,
                    'editDiscount' => (bool) $edit_discount,
                    'update' => true,
                ],
                'urls' => [
                    'submit' => '/sells/' . $id,
                    'cancel' => '/sells/' . $id,
                    'back' => '/sells',
                ],
            ]);
        }

        return view('sell.edit')
            ->with(compact('business_details', 'taxes', 'sell_details', 'transaction', 'commission_agent', 'types', 'customer_groups', 'pos_settings', 'waiters', 'invoice_schemes', 'default_invoice_schemes', 'redeem_details', 'edit_discount', 'edit_price', 'shipping_statuses', 'warranties', 'statuses', 'sales_orders', 'payment_types', 'accounts', 'payment_lines', 'change_return', 'is_order_request_enabled', 'customer_due', 'users'));
    }

    /**
     * Display a listing sell drafts.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDrafts()
    {
        if (! auth()->user()->can('draft.view_all') && ! auth()->user()->can('draft.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        // Wave 1 W1-A — branch dual MWART. KPI agregado leve eager + customers deferred.
        if (request()->header('X-Inertia')) {
            $draftCount = Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('status', 'draft')
                ->whereNull('sub_status')
                ->count();

            return Inertia::render('Sells/Drafts', [
                'kpis' => [
                    'total' => (int) $draftCount,
                ],
                'filters' => [
                    'businessLocations' => $business_locations,
                    // Customers pode ser dropdown grande (ROTA LIVRE tem ~1500 clientes) — deferred.
                    'customers' => Inertia::defer(fn () => Contact::customersDropdown($business_id, false)),
                    'salesRepresentative' => $sales_representative,
                ],
                'permissions' => [
                    'view_all' => auth()->user()->can('draft.view_all'),
                    'view_own' => auth()->user()->can('draft.view_own'),
                ],
                'urls' => [
                    'datatable' => '/sells/drafts',  // mesmo endpoint AJAX
                    'back' => '/sells',
                ],
            ]);
        }

        return view('sale_pos.draft')
            ->with(compact('business_locations', 'customers', 'sales_representative'));
    }

    /**
     * Display a listing sell quotations.
     *
     * @return \Illuminate\Http\Response
     */
    public function getQuotations()
    {
        if (! auth()->user()->can('quotation.view_all') && ! auth()->user()->can('quotation.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        // Wave 1 W1-A — branch dual MWART. KPIs cotação leves eager.
        if (request()->header('X-Inertia')) {
            $quoteBase = Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('status', 'draft')
                ->where('sub_status', 'quotation');

            $kpis = [
                'total' => (int) (clone $quoteBase)->count(),
            ];

            return Inertia::render('Sells/Quotations', [
                'kpis' => $kpis,
                'filters' => [
                    'businessLocations' => $business_locations,
                    'customers' => Inertia::defer(fn () => Contact::customersDropdown($business_id, false)),
                    'salesRepresentative' => $sales_representative,
                ],
                'permissions' => [
                    'view_all' => auth()->user()->can('quotation.view_all'),
                    'view_own' => auth()->user()->can('quotation.view_own'),
                ],
                'urls' => [
                    'datatable' => '/sells/quotations?is_quotation=1',
                    'back' => '/sells',
                ],
            ]);
        }

        return view('sale_pos.quotations')
                ->with(compact('business_locations', 'customers', 'sales_representative'));
    }

    /**
     * Send the datatable response for draft or quotations.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDraftDatables()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $is_quotation = request()->input('is_quotation', 0);

            $is_woocommerce = $this->moduleUtil->isModuleInstalled('Woocommerce');

            $sells = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
                ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
                ->join(
                    'business_locations AS bl',
                    'transactions.location_id',
                    '=',
                    'bl.id'
                )
                ->leftJoin('transaction_sell_lines as tsl', function ($join) {
                    $join->on('transactions.id', '=', 'tsl.transaction_id')
                        ->whereNull('tsl.parent_sell_line_id');
                })
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'sell')
                ->where('transactions.status', 'draft')
                ->select(
                    'transactions.id',
                    'transaction_date',
                    'invoice_no',
                    'contacts.name',
                    'contacts.mobile',
                    'contacts.supplier_business_name',
                    'bl.name as business_location',
                    'is_direct_sale',
                    'sub_status',
                    DB::raw('COUNT( DISTINCT tsl.id) as total_items'),
                    DB::raw('SUM(tsl.quantity) as total_quantity'),
                    DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as added_by"),
                    'transactions.is_export'
                );

            if ($is_quotation == 1) {
                $sells->where('transactions.sub_status', 'quotation');

                if (! auth()->user()->can('quotation.view_all') && auth()->user()->can('quotation.view_own')) {
                    $sells->where('transactions.created_by', request()->session()->get('user.id'));
                }
            } else {
                if (! auth()->user()->can('draft.view_all') && auth()->user()->can('draft.view_own')) {
                    $sells->where('transactions.created_by', request()->session()->get('user.id'));
                }
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $sells->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $sells->whereDate('transaction_date', '>=', $start)
                            ->whereDate('transaction_date', '<=', $end);
            }

            if (request()->has('location_id')) {
                $location_id = request()->get('location_id');
                if (! empty($location_id)) {
                    $sells->where('transactions.location_id', $location_id);
                }
            }

            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (! empty($created_by)) {
                    $sells->where('transactions.created_by', $created_by);
                }
            }

            if (! empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $sells->where('contacts.id', $customer_id);
            }

            if ($is_woocommerce) {
                $sells->addSelect('transactions.woocommerce_order_id');
            }

            $sells->groupBy('transactions.id');

            return Datatables::of($sells)
                 ->addColumn(
                    'action', function ($row) {
                        $html = '<div class="btn-group">
                                <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max dropdown-toggle" 
                                    data-toggle="dropdown" aria-expanded="false">'.
                                    __('messages.actions').
                                    '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                    </span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right" role="menu">
                                    <li>
                                    <a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal">
                                        <i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'
                                    </a>
                                    </li>';

                        if (auth()->user()->can('draft.update') || auth()->user()->can('quotation.update')) {
                            if ($row->is_direct_sale == 1) {
                                $html .= '<li>
                                            <a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'">
                                                <i class="fas fa-edit"></i>'.__('messages.edit').'
                                            </a>
                                        </li>';
                            } else {
                                $html .= '<li>
                                            <a target="_blank" href="'.action([\App\Http\Controllers\SellPosController::class, 'edit'], [$row->id]).'">
                                                <i class="fas fa-edit"></i>'.__('messages.edit').'
                                            </a>
                                        </li>';
                            }
                        }

                        $html .= '<li>
                                    <a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'"><i class="fas fa-print" aria-hidden="true"></i>'.__('messages.print').'</a>
                                </li>';

                        if (config('constants.enable_download_pdf')) {
                            $sub_status = $row->sub_status == 'proforma' ? 'proforma' : '';
                            $html .= '<li>
                                        <a href="'.route('quotation.downloadPdf', ['id' => $row->id, 'sub_status' => $sub_status]).'" target="_blank">
                                            <i class="fas fa-print" aria-hidden="true"></i>'.__('lang_v1.download_pdf').'
                                        </a>
                                    </li>';
                        }

                        if ((auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access')) && config('constants.enable_convert_draft_to_invoice')) {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'convertToInvoice'], [$row->id]).'" class="convert-draft"><i class="fas fa-sync-alt"></i>'.__('lang_v1.convert_to_invoice').'</a>
                                    </li>';
                        }

                        if ($row->sub_status != 'proforma') {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'convertToProforma'], [$row->id]).'" class="convert-to-proforma"><i class="fas fa-sync-alt"></i>'.__('lang_v1.convert_to_proforma').'</a>
                                    </li>';
                        }

                        if (auth()->user()->can('draft.delete') || auth()->user()->can('quotation.delete')) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$row->id]).'" class="delete-sale"><i class="fas fa-trash"></i>'.__('messages.delete').'</a>
                                </li>';
                        }

                        if ($row->sub_status == 'quotation') {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'copyQuotation'],[$row->id]).'" 
                                        class="copy_quotation"><i class="fas fa-copy"></i>'.
                                        __("lang_v1.copy_quotation").'</a>
                                    </li>
                                    <li>
                                        <a href="#" data-href="'.action("\App\Http\Controllers\NotificationController@getTemplate", ["transaction_id" => $row->id,"template_for" => "new_quotation"]).'" class="btn-modal" data-container=".view_modal"><i class="fa fa-envelope" aria-hidden="true"></i>' . __("lang_v1.new_quotation_notification") . '
                                        </a>
                                    </li>';

                            $html .= '<li>
                                        <a href="'.action("\App\Http\Controllers\SellPosController@showInvoiceUrl", [$row->id]).'" class="view_invoice_url"><i class="fas fa-eye"></i>'.__("lang_v1.view_quote_url").'</a>
                                    </li>';
                        }

                        $html .= '</ul></div>';

                        return $html;
                    })
                ->removeColumn('id')
                ->editColumn('invoice_no', function ($row) {
                    $invoice_no = $row->invoice_no;
                    if (! empty($row->woocommerce_order_id)) {
                        $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="'.__('lang_v1.synced_from_woocommerce').'"></i>';
                    }

                    if ($row->sub_status == 'proforma') {
                        $invoice_no .= '<br><span class="label bg-gray">'.__('lang_v1.proforma_invoice').'</span>';
                    }

                    if (! empty($row->is_export)) {
                        $invoice_no .= '</br><small class="label label-default no-print" title="'.__('lang_v1.export').'">'.__('lang_v1.export').'</small>';
                    }

                    return $invoice_no;
                })
                ->editColumn('transaction_date', '{{@format_date($transaction_date)}}')
                ->editColumn('total_items', '{{@format_quantity($total_items)}}')
                ->editColumn('total_quantity', '{{@format_quantity($total_quantity)}}')
                ->addColumn('conatct_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br>@endif {{$name}}')
                ->filterColumn('conatct_name', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('contacts.name', 'like', "%{$keyword}%")
                        ->orWhere('contacts.supplier_business_name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('added_by', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can('sell.view')) {
                            return  action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]);
                        } else {
                            return '';
                        }
                    }, ])
                ->rawColumns(['action', 'invoice_no', 'transaction_date', 'conatct_name'])
                ->make(true);
        }
    }

    /**
     * Creates copy of the requested sale.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function duplicateSell($id)
    {
        if (! auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $transaction = Transaction::where('business_id', $business_id)
                            ->where('type', 'sell')
                            ->findorfail($id);
            $duplicate_transaction_data = [];
            foreach ($transaction->toArray() as $key => $value) {
                if (! in_array($key, ['id', 'created_at', 'updated_at'])) {
                    $duplicate_transaction_data[$key] = $value;
                }
            }
            $duplicate_transaction_data['status'] = 'draft';
            $duplicate_transaction_data['payment_status'] = null;
            $duplicate_transaction_data['transaction_date'] = \Carbon::now();
            $duplicate_transaction_data['created_by'] = $user_id;
            $duplicate_transaction_data['invoice_token'] = null;

            DB::beginTransaction();
            $duplicate_transaction_data['invoice_no'] = $this->transactionUtil->getInvoiceNumber($business_id, 'draft', $duplicate_transaction_data['location_id']);

            //Create duplicate transaction
            $duplicate_transaction = Transaction::create($duplicate_transaction_data);

            //Create duplicate transaction sell lines
            $duplicate_sell_lines_data = [];

            foreach ($transaction->sell_lines as $sell_line) {
                $new_sell_line = [];
                foreach ($sell_line->toArray() as $key => $value) {
                    if (! in_array($key, ['id', 'transaction_id', 'created_at', 'updated_at', 'lot_no_line_id'])) {
                        $new_sell_line[$key] = $value;
                    }
                }

                $duplicate_sell_lines_data[] = $new_sell_line;
            }

            $duplicate_transaction->sell_lines()->createMany($duplicate_sell_lines_data);

            DB::commit();

            $output = ['success' => 0,
                'msg' => trans('lang_v1.duplicate_sell_created_successfully'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        if (! empty($duplicate_transaction)) {
            if ($duplicate_transaction->is_direct_sale == 1) {
                return redirect()->action([\App\Http\Controllers\SellController::class, 'edit'], [$duplicate_transaction->id])->with(['status', $output]);
            } else {
                return redirect()->action([\App\Http\Controllers\SellPosController::class, 'edit'], [$duplicate_transaction->id])->with(['status', $output]);
            }
        } else {
            abort(404, 'Not Found.');
        }
    }

    /**
     * Shows modal to edit shipping details.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editShipping($id)
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $transaction = Transaction::where('business_id', $business_id)
                                ->with(['media', 'media.uploaded_by_user'])
                                ->findorfail($id);

        $users = User::forDropdown($business_id, false, false, false);

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $activities = Activity::forSubject($transaction)
           ->with(['causer', 'subject'])
           ->where('activity_log.description', 'shipping_edited')
           ->latest()
           ->get();

        return view('sell.partials.edit_shipping')
               ->with(compact('transaction', 'shipping_statuses', 'activities', 'users'));
    }

    /**
     * Update shipping.
     *
     * @param  Request  $request, int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateShipping(Request $request, $id)
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only([
                'shipping_details', 'shipping_address',
                'shipping_status', 'delivered_to', 'delivery_person', 'shipping_custom_field_1', 'shipping_custom_field_2', 'shipping_custom_field_3', 'shipping_custom_field_4', 'shipping_custom_field_5',
            ]);


            $business_id = $request->session()->get('user.business_id');

            $transaction = Transaction::where('business_id', $business_id)
                                ->findOrFail($id);

            $transaction_before = $transaction->replicate();

            $transaction->update($input);

            $activity_property = ['update_note' => $request->input('shipping_note', '')];
            $this->transactionUtil->activityLog($transaction, 'shipping_edited', $transaction_before, $activity_property);

            $output = ['success' => 1,
                'msg' => trans('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display list of shipments.
     *
     * @return \Illuminate\Http\Response
     */
    public function shipments()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');

        //Service staff filter
        $service_staffs = null;
        if ($this->productUtil->isModuleEnabled('service_staff')) {
            $service_staffs = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $delevery_person = User::forDropdown($business_id, false, false, true);

        return view('sell.shipments')->with(compact('shipping_statuses'))
                ->with(compact('business_locations', 'customers', 'sales_representative', 'is_service_staff_enabled', 'service_staffs', 'delevery_person'));
    }

    public function viewMedia($model_id)
    {
        if (request()->ajax()) {
            $model_type = request()->input('model_type');
            $business_id = request()->session()->get('user.business_id');

            $query = Media::where('business_id', $business_id)
                        ->where('model_id', $model_id)
                        ->where('model_type', $model_type);

            $title = __('lang_v1.attachments');
            if (! empty(request()->input('model_media_type'))) {
                $query->where('model_media_type', request()->input('model_media_type'));
                $title = __('lang_v1.shipping_documents');
            }

            $medias = $query->get();

            return view('sell.view_media')->with(compact('medias', 'title'));
        }
    }

    public function resetMapping()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        Artisan::call('pos:mapPurchaseSell');

        echo 'Mapping reset success';
        exit;
    }

    public function destroy($id)
    {
        if (! auth()->user()->can('sell.delete') && ! auth()->user()->can('direct_sell.delete') && ! auth()->user()->can('so.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                DB::beginTransaction();

                $output = $this->transactionUtil->deleteSale($business_id, $id);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output['success'] = false;
                $output['msg'] = trans('messages.something_went_wrong');
            }

            return $output;
        }
    }
}
