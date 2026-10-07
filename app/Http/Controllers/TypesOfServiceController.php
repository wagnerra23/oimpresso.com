<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\SellingPriceGroup;
use App\Services\FeatureFlagService;
use App\TypesOfService;
use App\Utils\Util;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Yajra\DataTables\Facades\DataTables;

class TypesOfServiceController extends Controller
{
    /**
     * Flag do caminho React (thread sistema/playbook/05, F3). Convenção `useV2<Modulo><Tela>`.
     * Sem a chave no GrowthBook o FeatureFlagService cai no fallbackDefaults, que não a lista:
     * default OFF, a Blade segue servindo. Ligar é toggle no GrowthBook (flag:set --biz=1), não deploy.
     *
     * @see memory/requisitos/Configuracoes/RUNBOOK-tipos-servico.md
     */
    private const FLAG_V2 = 'useV2ConfiguracoesTiposServico';

    /**
     * All Utils instance.
     */
    protected $commonUtil;

    /**
     * Constructor
     *
     * @param  TaxUtil  $taxUtil
     * @return void
     */
    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View|\Inertia\Response
     */
    public function index()
    {
        if (! auth()->user()->can('access_types_of_service')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // `! inertia()`: o Inertia v3 manda `X-Requested-With` em toda visita; sem esta perna o
        // partial reload da prop adiada caía no JSON do DataTables (RUNBOOK-tipos-servico §10).
        if (request()->ajax() && ! request()->inertia()) {
            $tax_rates = TypesOfService::where('business_id', $business_id)
                        ->select('*');

            return Datatables::of($tax_rates)
                ->addColumn(
                    'action',
                    '<button data-href="{{action(\'App\Http\Controllers\TypesOfServiceController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".type_of_service_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                    <button data-href="{{action(\'App\Http\Controllers\TypesOfServiceController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_type_of_service"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>'
                )
                ->editColumn('packing_charge', function ($row) {
                    $html = '<span class="display_currency" data-currency_symbol="false">'.$row->packing_charge.'</span>';

                    if ($row->packing_charge_type == 'percent') {
                        $html .= '%';
                    }

                    return $html;
                })
                ->removeColumn('id')
                ->rawColumns(['action', 'packing_charge'])
                ->make(true);
        }

        if (app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            return Inertia::render('Configuracoes/TiposServico/Index', [
                'tipos' => Inertia::defer(fn () => $this->tiposDoNegocio((int) $business_id)),
                // Mesmas listas do create() da Blade: locais ativos e permitidos ao usuário, tabelas ativas (0 = preço padrão).
                'opcoes' => Inertia::defer(fn () => [
                    'locais' => BusinessLocation::forDropdown($business_id),
                    'tabelas' => SellingPriceGroup::forDropdown($business_id),
                ], 'formulario'),
            ]);
        }

        return view('types_of_service.index');
    }

    /**
     * Tipos de serviço do negócio no shape da tela React. A taxa vai como número do banco (a tela formata); a tabela
     * de preço por local vai resolvida em nomes, e só com locais e tabelas do próprio negócio.
     */
    private function tiposDoNegocio(int $business_id): array
    {
        $locais = BusinessLocation::where('business_id', $business_id)->pluck('name', 'id');
        $tabelas = SellingPriceGroup::where('business_id', $business_id)->pluck('name', 'id');

        return TypesOfService::where('business_id', $business_id)->orderBy('name')->get()
            ->map(fn (TypesOfService $t) => [
                'id' => $t->id,
                'nome' => $t->name,
                'descricao' => (string) $t->description,
                'taxa' => (float) $t->packing_charge,
                'tipo_taxa' => $t->packing_charge_type === 'percent' ? 'percent' : 'fixed',
                'campos_personalizados' => (bool) $t->enable_custom_fields,
                // Mapa cru local → tabela para o drawer devolver no update(); só chaves e valores do próprio negócio
                // (0 = preço padrão, a opção que a Blade oferece).
                'tabela_por_local' => (object) collect((array) $t->location_price_group)
                    ->filter(fn ($tabela, $local) => isset($locais[$local]) && ((string) $tabela === '0' || isset($tabelas[$tabela])))
                    ->map(fn ($tabela) => (string) $tabela)->all(),
                'precos_por_local' => collect((array) $t->location_price_group)
                    ->filter(fn ($tabela, $local) => isset($locais[$local], $tabelas[$tabela]))
                    ->map(fn ($tabela, $local) => ['local' => $locais[$local], 'tabela' => $tabelas[$tabela]])
                    ->values()->all(),
            ])->all();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('access_types_of_service')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $locations = BusinessLocation::forDropdown($business_id);
        $price_groups = SellingPriceGroup::forDropdown($business_id);

        return view('types_of_service.create')
                ->with(compact('locations', 'price_groups'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('access_types_of_service')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description',
                'location_price_group', 'packing_charge_type',
                'packing_charge', ]);

            $input['business_id'] = $request->session()->get('user.business_id');
            $input['packing_charge'] = ! empty($input['packing_charge']) ? $this->commonUtil->num_uf($input['packing_charge']) : 0;
            $input['enable_custom_fields'] = ! empty($request->input('enable_custom_fields')) ? 1 : 0;

            TypesOfService::create($input);

            $output = ['success' => true,
                'msg' => __('lang_v1.added_success'),
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
     * @param  \App\TypesOfService  $typesOfService
     * @return \Illuminate\Http\Response
     */
    public function show(TypesOfService $typesOfService)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\TypesOfService  $typesOfService
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('access_types_of_service')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $locations = BusinessLocation::forDropdown($business_id);
        $price_groups = SellingPriceGroup::forDropdown($business_id);

        $type_of_service = TypesOfService::where('business_id', $business_id)
                                        ->findOrFail($id);

        return view('types_of_service.edit')
                ->with(compact('locations', 'price_groups', 'type_of_service'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\TypesOfService  $typesOfService
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('access_types_of_service')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description',
                'location_price_group', 'packing_charge_type',
                'packing_charge', ]);

            $business_id = $request->session()->get('user.business_id');
            $input['packing_charge'] = ! empty($input['packing_charge']) ? $this->commonUtil->num_uf($input['packing_charge']) : 0;
            $input['enable_custom_fields'] = ! empty($request->input('enable_custom_fields')) ? 1 : 0;
            $input['location_price_group'] = ! empty($input['location_price_group']) ? json_encode($input['location_price_group']) : null;

            TypesOfService::where('business_id', $business_id)
                        ->where('id', $id)
                        ->update($input);

            $output = ['success' => true,
                'msg' => __('lang_v1.updated_success'),
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
     * Remove the specified resource from storage.
     *
     * @param  \App\TypesOfService  $typesOfService
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('access_types_of_service')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');
                TypesOfService::where('business_id', $business_id)
                        ->where('id', $id)
                        ->delete();

                $output = ['success' => true,
                    'msg' => __('lang_v1.deleted_success'),
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
