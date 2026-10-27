<?php

namespace App\Http\Controllers;

use App\Account;
use App\AccountTransaction;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Response;
use Yajra\DataTables\Facades\DataTables;

/**
 * Livro da conta do núcleo (`/account/account/{id}`). O cadastro, a lista e as ações de conta moraram aqui até
 * 2026-10-27; pela decisão [W] (opção A) foram para `Financeiro/ContasBancarias` e as rotas antigas redirecionam.
 * Transferência, depósito e edição de lançamento saíram (uso zero medido em produção).
 *
 * @see memory/requisitos/Financeiro/RUNBOOK-contas-bancarias.md
 */
class AccountController extends Controller
{
    protected $commonUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(Util $commonUtil, ModuleUtil $moduleUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $start_date = request()->input('start_date');
            $end_date = request()->input('end_date');

            $before_bal_query = AccountTransaction::join(
                'accounts as A',
                'account_transactions.account_id',
                '=',
                'A.id'
            )
                    ->where('A.business_id', $business_id)
                    ->where('A.id', $id)
                    ->select([
                        DB::raw('SUM(IF(account_transactions.type="credit", account_transactions.amount, -1 * account_transactions.amount)) as prev_bal'), ])
                    ->where('account_transactions.operation_date', '<', $start_date)
                    ->whereNull('account_transactions.deleted_at');
            if (! empty(request()->input('type'))) {
                $before_bal_query->where('account_transactions.type', request()->input('type'));
            }
            $bal_before_start_date = $before_bal_query->first()->prev_bal;

            $accounts = AccountTransaction::join(
                'accounts as A',
                'account_transactions.account_id',
                '=',
                'A.id'
            )
            ->leftJoin('transaction_payments AS tp', 'account_transactions.transaction_payment_id', '=', 'tp.id')
            ->leftJoin('contacts AS c', 'tp.payment_for', '=', 'c.id')
            ->leftJoin('users AS u', 'account_transactions.created_by', '=', 'u.id')
            ->leftjoin(
                    'transaction_payments as child_payments',
                    'tp.id',
                    '=',
                    'child_payments.parent_id'
                )
            ->leftjoin(
                'transactions as child_sells',
                'child_sells.id',
                '=',
                'child_payments.transaction_id'
            )
            ->with(['transaction', 'transaction.contact', 'transfer_transaction', 'transaction.transaction_for'])
                            ->where('A.business_id', $business_id)
                            ->where('A.id', $id)
                            ->with(['transaction', 'transaction.contact', 'transfer_transaction', 'media', 'transfer_transaction.media'])
                            ->select(['account_transactions.type', 'account_transactions.amount', 'operation_date',
                                'account_transactions.sub_type', 'transfer_transaction_id',
                                'A.id as account_id',
                                'account_transactions.transaction_id',
                                'account_transactions.id',
                                'account_transactions.note',
                                'tp.is_advance',
                                'tp.is_return',
                                'tp.payment_ref_no',
                                'tp.method',
                                'tp.transaction_no',
                                'tp.card_transaction_number',
                                'tp.card_number',
                                'tp.card_type',
                                'tp.card_holder_name',
                                'tp.card_month',
                                'tp.card_year',
                                'tp.card_security',
                                'tp.cheque_number',
                                'tp.bank_account_number',
                                DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by"),
                                'c.name as payment_for_contact',
                                'c.type as payment_for_type',
                                'c.supplier_business_name as payment_for_business_name',
                                DB::raw('SUM(child_payments.amount) total_recovered'),
                                DB::raw('GROUP_CONCAT(child_sells.invoice_no) as child_sells'),
                            ])
                             ->groupBy('account_transactions.id')
                             //->orderBy('account_transactions.id', 'asc')
                             ->orderBy('account_transactions.operation_date', 'asc');
            if (! empty(request()->input('type'))) {
                $accounts->where('account_transactions.type', request()->input('type'));
            }

            if (! empty($start_date) && ! empty($end_date)) {
                $accounts->whereDate('operation_date', '>=', $start_date)
                        ->whereDate('operation_date', '<=', $end_date);
            }

            $payment_types = $this->commonUtil->payment_types(null, true, $business_id);

            return DataTables::of($accounts)
                        ->editColumn('method', function ($row) use ($payment_types) {
                            if (! empty($row->method) && isset($payment_types[$row->method])) {
                                return $payment_types[$row->method];
                            } else {
                                return '';
                            }
                        })
                        ->addColumn('payment_details', function ($row) {
                            $arr = [];
                            if (! empty($row->transaction_no)) {
                                $arr[] = '<b>'.__('lang_v1.transaction_no').'</b>: '.$row->transaction_no;
                            }

                            if ($row->method == 'card' && ! empty($row->card_transaction_number)) {
                                $arr[] = '<b>'.__('lang_v1.card_transaction_no').'</b>: '.$row->card_transaction_number;
                            }

                            if ($row->method == 'card' && ! empty($row->card_number)) {
                                $arr[] = '<b>'.__('lang_v1.card_no').'</b>: '.$row->card_number;
                            }
                            if ($row->method == 'card' && ! empty($row->card_type)) {
                                $arr[] = '<b>'.__('lang_v1.card_type').'</b>: '.$row->card_type;
                            }
                            if ($row->method == 'card' && ! empty($row->card_holder_name)) {
                                $arr[] = '<b>'.__('lang_v1.card_holder_name').'</b>: '.$row->card_holder_name;
                            }
                            if ($row->method == 'card' && ! empty($row->card_month)) {
                                $arr[] = '<b>'.__('lang_v1.month').'</b>: '.$row->card_month;
                            }
                            if ($row->method == 'card' && ! empty($row->card_year)) {
                                $arr[] = '<b>'.__('lang_v1.year').'</b>: '.$row->card_year;
                            }
                            if ($row->method == 'card' && ! empty($row->card_security)) {
                                $arr[] = '<b>'.__('lang_v1.security_code').'</b>: '.$row->card_security;
                            }
                            if (! empty($row->cheque_number)) {
                                $arr[] = '<b>'.__('lang_v1.cheque_no').'</b>: '.$row->cheque_number;
                            }
                            if (! empty($row->bank_account_number)) {
                                $arr[] = '<b>'.__('lang_v1.card_no').'</b>: '.$row->bank_account_number;
                            }

                            return implode(', ', $arr);
                        })
                            ->addColumn('debit', function ($row) {
                                if ($row->type == 'debit') {
                                    return '<span class="debit" data-orig-value="'.$row->amount.'">'.$this->commonUtil->num_f($row->amount, true).'</span>';
                                }

                                return '';
                            })
                            ->addColumn('credit', function ($row) {
                                if ($row->type == 'credit') {
                                    return '<span class="credit"  data-orig-value="'.$row->amount.'">'.$this->commonUtil->num_f($row->amount, true).'</span>';
                                }

                                return '';
                            })
                            ->addColumn('balance', function ($row) use ($bal_before_start_date, $start_date) {
                                //TODO:: Need to fix same balance showing for transactions having same operation date
                                $current_bal = AccountTransaction::where('account_id',
                                                    $row->account_id)
                                                ->where('operation_date', '>=', $start_date)
                                                ->where('operation_date', '<=', $row->operation_date)
                                                ->select(DB::raw("SUM(IF(type='credit', amount, -1 * amount)) as balance"))
                                                ->first()->balance;
                                $bal = $bal_before_start_date + $current_bal;

                                return '<span class="balance" data-orig-value="'.$bal.'">'.$this->commonUtil->num_f($bal, true).'</span>';
                            })
                            ->editColumn('operation_date', function ($row) {
                                return $this->commonUtil->format_date($row->operation_date, true);
                            })
                            ->editColumn('sub_type', function ($row) {
                                return $this->__getPaymentDetails($row);
                            })
                            ->editColumn('action', function ($row) {
                                $action = '';
                                if (! empty($row->media->first()) || (! empty($row->transfer_transaction && ! empty($row->transfer_transaction->media->first())))) {
                                    $display_url = ! empty($row->media->first()) ? $row->media->first()->display_url : $row->transfer_transaction->media->first()->display_url;

                                    $display_name = ! empty($row->media->first()) ? $row->media->first()->display_name : $row->transfer_transaction->media->first()->display_name;

                                    $action .= '&nbsp; <a class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-accent" href="'.$display_url.'" download="'.$display_name.'"><i class="fa fa-download"></i> '.__('purchase.download_document').'</a>';
                                }

                                return $action;
                            })
                            ->filterColumn('added_by', function ($query, $keyword) {
                                $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                            })
                            ->removeColumn('id')
                            ->removeColumn('is_closed')
                            ->rawColumns(['credit', 'debit', 'balance', 'sub_type', 'action', 'payment_details'])
                            ->make(true);
        }
        $account = Account::where('business_id', $business_id)
                        ->with(['account_type', 'account_type.parent_account'])
                        ->findOrFail($id);

        return view('account.show')
                ->with(compact('account'));
    }

    /**
     * Calculates account current balance.
     *
     * @param  int  $id
     * @return json
     */
    public function getAccountBalance($id)
    {
        if (! auth()->user()->can('account.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = session()->get('user.business_id');
        $account = Account::leftjoin(
            'account_transactions as AT',
            'AT.account_id',
            '=',
            'accounts.id'
        )
            ->whereNull('AT.deleted_at')
            ->where('accounts.business_id', $business_id)
            ->where('accounts.id', $id)
            ->select('accounts.*', DB::raw("SUM( IF(AT.type='credit', amount, -1 * amount) ) as balance"))
            ->first();

        return $account;
    }

    public function __getPaymentDetails($row)
    {
        $details = '';
        if (! empty($row->sub_type)) {
            $details = __('account.'.$row->sub_type);
            if (in_array($row->sub_type, ['fund_transfer', 'deposit']) && ! empty($row->transfer_transaction)) {
                if ($row->type == 'credit') {
                    $details .= ' ( '.__('account.from').': '.$row->transfer_transaction->account->name.')';
                } else {
                    $details .= ' ( '.__('account.to').': '.$row->transfer_transaction->account->name.')';
                }
            }
        } else {
            if (! empty($row->transaction->type)) {
                if ($row->transaction->type == 'purchase') {
                    $details = __('lang_v1.purchase').'<br><b>'.__('purchase.supplier').':</b> '.$row->transaction->contact->full_name_with_business.'<br><b>'.
                    __('purchase.ref_no').':</b> <a href="#" data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->transaction->id]).'" class="btn-modal" data-container=".view_modal">'.$row->transaction->ref_no.'</a>';
                } elseif ($row->transaction->type == 'expense') {
                    $details = __('lang_v1.expense').'<br><b>'.__('purchase.ref_no').':</b>'.$row->transaction->ref_no;
                } elseif ($row->transaction->type == 'sell') {
                    $is_return = $row->is_return == 1 ? ' ('.__('lang_v1.change_return').')' : '';
                    $details = __('sale.sale').$is_return.'<br><b>'.__('contact.customer').':</b> '.$row->transaction->contact->full_name_with_business.'<br><b>'.
                    __('sale.invoice_no').':</b> <a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->transaction->id]).'" class="btn-modal" data-container=".view_modal">'.$row->transaction->invoice_no.'</a>';
                }
            } else {
                //for contact payment which is not advance
                if ($row->is_advance != 1) {
                    if ($row->payment_for_type == 'supplier') {
                        $details .= '<b>'.__('purchase.supplier').':</b> ';
                    } elseif ($row->payment_for_type == 'customer') {
                        $details .= '<b>'.__('contact.customer').':</b> ';
                    } else {
                        $details .= '<b>'.__('account.payment_for').':</b> ';
                    }

                    if (! empty($row->payment_for_business_name)) {
                        $details .= $row->payment_for_business_name.', ';
                    }
                    if (! empty($row->payment_for_contact)) {
                        $details .= $row->payment_for_contact;
                    }
                }
            }
        }

        if (! empty($row->payment_ref_no)) {
            if (! empty($details)) {
                $details .= '<br/>';
            }

            $details .= '<b>'.__('lang_v1.pay_reference_no').':</b> '.$row->payment_ref_no;
        }
        if (! empty($row->transaction->contact) && $row->transaction->type == 'expense') {
            if (! empty($details)) {
                $details .= '<br/>';
            }

            $details .= '<b>';
            $details .= __('lang_v1.expense_for_contact');
            $details .= ':</b> '.$row->transaction->contact->full_name_with_business;
        }

        if (! empty($row->transaction->transaction_for)) {
            if (! empty($details)) {
                $details .= '<br/>';
            }

            $details .= '<b>'.__('expense.expense_for').':</b> '.$row->transaction->transaction_for->user_full_name;
        }

        if ($row->is_advance == 1) {
            $total_advance = $row->amount - $row->total_recovered;
            $details .= '<br>';

            if ($total_advance > 0) {
                $details .= '<b>'.__('lang_v1.advance_payment').'</b>: '.$this->commonUtil->num_f($total_advance, true).'<br>';
            }

            if (! empty($row->child_sells)) {
                $details .= '<b>'.__('lang_v1.payments_recovered_for').'</b>: '.$row->child_sells.'<br>';
            }

            if ($row->payment_for_type == 'supplier') {
                $details .= '<b>'.__('purchase.supplier').':</b> ';
            } elseif ($row->payment_for_type == 'customer') {
                $details .= '<b>'.__('contact.customer').':</b> ';
            } else {
                $details .= '<b>'.__('account.payment_for').':</b> ';
            }

            if (! empty($row->payment_for_business_name)) {
                $details .= $row->payment_for_business_name.', ';
            }
            if (! empty($row->payment_for_contact)) {
                $details .= $row->payment_for_contact;
            }
        }

        if (! empty($row->added_by)) {
            $details .= '<br><b>'.__('lang_v1.added_by').':</b> '.$row->added_by;
        }

        return $details;
    }
}
