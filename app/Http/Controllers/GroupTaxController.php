<?php

namespace App\Http\Controllers;

use App\TaxRate;
use Datatables;
use Illuminate\Http\Request;

class GroupTaxController extends Controller
{
    private const MSG_SUB_IMPOSTO_INVALIDO = 'Escolha ao menos uma alíquota, e só alíquotas simples desta empresa.';

    /**
     * Os sub-impostos pedidos, só se TODOS forem alíquotas simples (não-grupo) da empresa.
     *
     * Antes o `store()`/`update()` buscavam por id sem filtrar a empresa: um POST com id de
     * alíquota de outro negócio somava a alíquota dele no total do grupo e criava o vínculo
     * (Tier 0, ADR 0093 — `TaxRate` não tem global scope de business). Agora um id que não
     * passa recusa a operação inteira, sem gravar nada; somar só os válidos esconderia o erro
     * e gravaria um grupo com alíquota diferente da que a pessoa escolheu.
     *
     * @return \Illuminate\Support\Collection<int, TaxRate>|null  null = pedido inválido
     */
    private function subImpostosDaEmpresa(int $business_id, $ids)
    {
        $ids = array_values(array_unique(array_map('intval', array_filter((array) $ids, 'is_numeric'))));
        if (empty($ids)) {
            return null;
        }

        $sub_taxes = TaxRate::where('business_id', $business_id)
            ->where('is_tax_group', 0)
            ->whereIn('id', $ids)
            ->get();

        return $sub_taxes->count() === count($ids) ? $sub_taxes : null;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Mesma barreira do TaxRateController::index (as duas listas vivem na mesma página).
        if (! auth()->user()->can('tax_rate.view') && ! auth()->user()->can('tax_rate.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $tax_rates = TaxRate::where('business_id', $business_id)
                        ->where('is_tax_group', '1')
                        ->with(['sub_taxes']);

            return Datatables::of($tax_rates)
                ->addColumn(
                    'action',
                    '<button data-href="{{action(\'App\Http\Controllers\GroupTaxController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".tax_group_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                        <button data-href="{{action(\'App\Http\Controllers\GroupTaxController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_tax_group_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>'
                )
                ->editColumn('amount', '{{@num_format($amount)}}')
                ->editColumn('sub_taxes', function ($row) {
                    $sub_taxes = [];
                    foreach ($row->sub_taxes as $sub_tax) {
                        $sub_taxes[] = $sub_tax->name;
                    }

                    return implode(' + ', $sub_taxes);
                })
                ->removeColumn('id')
                ->rawColumns(['action'])
                ->make(true);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('tax_rate.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $taxes = TaxRate::where('business_id', $business_id)->where('is_tax_group', '0')->pluck('name', 'id');

        return view('tax_group.create')
                ->with(compact('taxes'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>  JSON {success, msg} da modal Blade
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('tax_rate.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input['name'] = $request->input('name');
            $input['business_id'] = $request->session()->get('user.business_id');
            $input['created_by'] = $request->session()->get('user.id');

            $sub_taxes = $this->subImpostosDaEmpresa((int) $input['business_id'], $request->input('taxes'));
            if ($sub_taxes === null) {
                return ['success' => false, 'msg' => self::MSG_SUB_IMPOSTO_INVALIDO];
            }
            $sub_tax_ids = $sub_taxes->pluck('id')->all();

            $amount = 0;
            foreach ($sub_taxes as $sub_tax) {
                $amount += $sub_tax->amount;
            }
            $input['amount'] = $amount;
            $input['is_tax_group'] = 1;

            $tax_rate = TaxRate::create($input);
            $tax_rate->sub_taxes()->sync($sub_tax_ids);

            $output = ['success' => true,
                'msg' => __('tax_rate.tax_group_added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
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
        if (! auth()->user()->can('tax_rate.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $tax_rate = TaxRate::where('business_id', $business_id)->where('is_tax_group', 1)->with(['sub_taxes'])->findOrFail($id);

            $taxes = TaxRate::where('business_id', $business_id)->where('is_tax_group', '0')->pluck('name', 'id');

            $sub_taxes = [];
            foreach ($tax_rate->sub_taxes as $sub_tax) {
                $sub_taxes[] = $sub_tax->id;
            }

            return view('tax_group.edit')
                ->with(compact('taxes', 'sub_taxes', 'tax_rate'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return array<string, mixed>|null  JSON {success, msg}; null fora de AJAX
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('tax_rate.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = $request->session()->get('user.business_id');

                $sub_taxes = $this->subImpostosDaEmpresa((int) $business_id, $request->input('taxes'));
                if ($sub_taxes === null) {
                    return ['success' => false, 'msg' => self::MSG_SUB_IMPOSTO_INVALIDO];
                }
                $sub_tax_ids = $sub_taxes->pluck('id')->all();

                $amount = 0;
                foreach ($sub_taxes as $sub_tax) {
                    $amount += $sub_tax->amount;
                }

                $tax_rate = TaxRate::where('business_id', $business_id)->where('is_tax_group', 1)->findOrFail($id);
                $tax_rate->name = $request->input('name');
                $tax_rate->amount = $amount;
                $tax_rate->save();
                $tax_rate->sub_taxes()->sync($sub_tax_ids);

                $output = ['success' => true,
                    'msg' => __('tax_rate.tax_group_updated_success'),
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('tax_rate.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                $tax_rate = TaxRate::where('business_id', $business_id)->findOrFail($id);
                $tax_rate->delete();

                $output = ['success' => true,
                    'msg' => __('tax_rate.deleted_success'),
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
