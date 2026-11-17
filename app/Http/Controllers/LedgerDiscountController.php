<?php

namespace App\Http\Controllers;

use App\Contact;
use App\Transaction;
use App\Utils\Util;
use Illuminate\Http\Request;

class LedgerDiscountController extends Controller
{
    protected $commonUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(
        Util $commonUtil
    ) {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Lançar desconto mexe no saldo do cliente: o servidor confere a mesma permissão que a
        // ficha usa para mostrar "Aplicar desconto". Fora do try, para o 403 não virar
        // {success: 0} genérico.
        $this->exigirPermissaoDeDesconto();

        try {
            $business_id = $request->session()->get('user.business_id');
            $input = $request->only(['date', 'amount', 'note', 'contact_id']);

            // Contato tem que ser da empresa da sessão (Tier 0, ADR 0093).
            $contact = Contact::where('business_id', $business_id)->findOrFail($input['contact_id']);

            $valor = $this->commonUtil->num_uf($input['amount'] ?? null);
            if ($valor <= 0) {
                return ['success' => 0, 'msg' => 'Informe um valor de desconto maior que zero.'];
            }

            $sub_type = 'sell_discount';
            if ($contact->type == 'customer') {
                $sub_type = 'sell_discount';
            } elseif ($contact->type == 'supplier') {
                $sub_type = 'purchase_discount';
            } else {
                $sub_type = $request->input('sub_type');
                if (! in_array($sub_type, ['sell_discount', 'purchase_discount'], true)) {
                    return ['success' => 0, 'msg' => 'Escolha se o desconto vale para vendas ou compras.'];
                }
            }
            $transaction_data = [
                'business_id' => $business_id,
                'final_total' => $valor,
                'total_before_tax' => $valor,
                'status' => 'final',
                'type' => 'ledger_discount',
                'sub_type' => $sub_type,
                'contact_id' => $input['contact_id'],
                'created_by' => auth()->user()->id,
                'additional_notes' => $input['note'] ?? null,
                // A ficha manda ISO (`2026-11-17 14:30`); a Blade legada manda no formato da
                // empresa. uf_datetime_input aceita os dois — com uf_date direto, a data ISO da
                // ficha caía em exceção e o desconto nunca gravava.
                'transaction_date' => $this->commonUtil->uf_datetime_input($input['date'] ?? ''),
            ];

            $discount = Transaction::create($transaction_data);

            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
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
        $is_admin = $this->commonUtil->is_admin(auth()->user());

        if (! $is_admin) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $discount = Transaction::where('business_id', $business_id)
                    ->where('type', 'ledger_discount')
                    ->findOrFail($id);

        $contact = Contact::where('business_id', $business_id)->find($discount->contact_id);

        return view('ledger_discount.edit')->with(compact('discount', 'contact'));
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
        $this->exigirPermissaoDeDesconto();

        try {
            $business_id = $request->session()->get('user.business_id');
            $input = $request->only(['date', 'amount', 'note', 'contact_id']);

            $valor = $this->commonUtil->num_uf($input['amount'] ?? null);
            if ($valor <= 0) {
                return ['success' => 0, 'msg' => 'Informe um valor de desconto maior que zero.'];
            }

            $transaction_data = [
                'final_total' => $valor,
                'total_before_tax' => $valor,
                'additional_notes' => $input['note'] ?? null,
                'transaction_date' => $this->commonUtil->uf_datetime_input($input['date'] ?? ''),
            ];

            if ($request->has('sub_type')) {
                if (! in_array($request->input('sub_type'), ['sell_discount', 'purchase_discount'], true)) {
                    return ['success' => 0, 'msg' => 'Escolha se o desconto vale para vendas ou compras.'];
                }
                $transaction_data['sub_type'] = $request->input('sub_type');
            }

            Transaction::where('business_id', $business_id)
                    ->where('type', 'ledger_discount')
                    ->where('id', $id)
                    ->update($transaction_data);

            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
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
        $is_admin = $this->commonUtil->is_admin(auth()->user());

        if (! $is_admin) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        try {
            Transaction::where('business_id', $business_id)
                    ->where('type', 'ledger_discount')
                    ->where('id', $id)
                    ->delete();

            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => 'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage(),
            ];
        }

        return $output;
    }

    /**
     * `discount.manage` é a permissão que a ficha do cliente usa para mostrar "Aplicar desconto"
     * (ContactController, `permissions.add_discount`). O dono do negócio passa pelo Gate::before.
     */
    private function exigirPermissaoDeDesconto(): void
    {
        if (! auth()->user()->can('discount.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
