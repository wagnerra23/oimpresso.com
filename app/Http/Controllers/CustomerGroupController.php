<?php

namespace App\Http\Controllers;

use App\CustomerGroup;
use App\SellingPriceGroup;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerGroupController extends Controller
{
    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        // Thread Cliente/03 (D2 = tela própria): a lista é Inertia. A DataTable da Blade
        // (`request()->ajax()`) saiu daqui: o Inertia manda X-Requested-With, então o ramo
        // ajax engoliria a visita e devolveria o JSON da DataTable no lugar da página.
        $business_id = request()->session()->get('user.business_id');

        $tabelas = collect(SellingPriceGroup::forDropdown($business_id, false))
            ->map(fn ($nome, $id) => ['id' => (int) $id, 'nome' => (string) $nome])->values();

        return Inertia::render('Cliente/Grupos/Index', [
            // defer: conta cadastros por grupo (RUNBOOK-inertia-defer-pattern).
            'grupos' => Inertia::defer(fn () => $this->gruposDoNegocio((int) $business_id)),
            'tabelas' => $tabelas,
            'pode' => [
                'criar' => auth()->user()->can('customer.create'),
                'editar' => auth()->user()->can('customer.update'),
                'excluir' => auth()->user()->can('customer.delete'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $price_groups = SellingPriceGroup::forDropdown($business_id, false);

        return view('customer_group.create')->with(compact('price_groups'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'amount', 'price_calculation_type', 'selling_price_group_id']);
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;
            $input['created_by'] = $request->session()->get('user.id');
            if (! $this->tabelaDoNegocio($input, $business_id)) {
                return ['success' => false, 'msg' => __('messages.something_went_wrong')];
            }

            $input['amount'] = ! empty($input['amount']) ? $this->commonUtil->num_uf($input['amount']) : 0;

            $customer_group = CustomerGroup::create($input);
            $output = ['success' => true,
                'data' => $customer_group,
                'msg' => __('lang_v1.success'),
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
     * Show the form for editing the specified resource.
     *
     * @param  \App\CustomerGroup  $customerGroup
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('customer.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $customer_group = CustomerGroup::where('business_id', $business_id)->find($id);

            $business_id = request()->session()->get('user.business_id');
            $price_groups = SellingPriceGroup::forDropdown($business_id, false);

            return view('customer_group.edit')
                ->with(compact('customer_group', 'price_groups'));
        }
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
        if (! auth()->user()->can('customer.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'amount', 'price_calculation_type', 'selling_price_group_id']);
                $business_id = $request->session()->get('user.business_id');
                if (! $this->tabelaDoNegocio($input, $business_id)) {
                    return ['success' => false, 'msg' => __('messages.something_went_wrong')];
                }

                $input['amount'] = ! empty($input['amount']) ? $this->commonUtil->num_uf($input['amount']) : 0;

                $customer_group = CustomerGroup::where('business_id', $business_id)->findOrFail($id);

                $customer_group->update($input);

                $output = ['success' => true,
                    'msg' => __('lang_v1.success'),
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
        if (! auth()->user()->can('customer.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                $cg = CustomerGroup::where('business_id', $business_id)->findOrFail($id);
                $cg->delete();

                $output = ['success' => true,
                    'msg' => __('lang_v1.success'),
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

    /** Lista da tela: grupos do negócio, ajuste, tabela e quantos cadastros (não apagados) usam cada um. */
    private function gruposDoNegocio(int $business_id): array
    {
        $cadastros = DB::table('contacts')
            ->where('business_id', $business_id)
            ->whereNotNull('customer_group_id')
            ->whereNull('deleted_at')
            ->groupBy('customer_group_id')
            ->pluck(DB::raw('COUNT(*)'), 'customer_group_id');

        return CustomerGroup::where('customer_groups.business_id', $business_id)
            ->leftJoin('selling_price_groups as spg', function ($j) use ($business_id) {
                $j->on('spg.id', '=', 'customer_groups.selling_price_group_id')
                    ->where('spg.business_id', $business_id);
            })
            ->orderBy('customer_groups.name')
            ->get(['customer_groups.id', 'customer_groups.name', 'customer_groups.amount',
                'customer_groups.price_calculation_type', 'customer_groups.selling_price_group_id',
                'spg.name as tabela_nome'])
            ->map(fn ($g) => [
                'id' => (int) $g->id,
                'nome' => (string) $g->name,
                'calculo' => $g->price_calculation_type === 'selling_price_group' ? 'selling_price_group' : 'percentage',
                'percentual' => (float) $g->amount,
                'tabela_id' => $g->selling_price_group_id !== null ? (int) $g->selling_price_group_id : null,
                'tabela_nome' => $g->tabela_nome,
                'cadastros' => (int) ($cadastros[$g->id] ?? 0),
            ])->values()->all();
    }

    /**
     * Tier 0 (ADR 0093): a tabela de preço do grupo tem de ser do negócio da sessão. Antes da
     * thread Cliente/03, store/update gravavam qualquer `selling_price_group_id` recebido.
     */
    private function tabelaDoNegocio(array $input, $business_id): bool
    {
        if (($input['price_calculation_type'] ?? null) !== 'selling_price_group' || empty($input['selling_price_group_id'])) {
            return true;
        }

        return SellingPriceGroup::where('business_id', $business_id)
            ->where('id', $input['selling_price_group_id'])
            ->exists();
    }
}
