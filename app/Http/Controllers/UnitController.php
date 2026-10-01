<?php

namespace App\Http\Controllers;

use App\Brands;
use App\Product;
use App\Unit;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Yajra\DataTables\Facades\DataTables;

class UnitController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return mixed Inertia (tela Produto/Cadastros) · JSON do DataTables · view clássica
     */
    public function index()
    {
        // Playbook Produto · thread 02: `/units` abre a tela Produto/Cadastros (abas). O Inertia
        // manda `X-Requested-With` junto do `X-Inertia`, então `ajax()` sozinho mandaria a visita
        // pro DataTables (§5 2026-09-08). `?classico=1` mantém a Blade, que hospeda os modais.
        if (! request()->boolean('classico') && (! request()->ajax() || request()->header('X-Inertia'))) {
            return $this->cadastros();
        }

        if (! auth()->user()->can('unit.view') && ! auth()->user()->can('unit.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $unit = Unit::where('business_id', $business_id)
                        ->with(['base_unit'])
                        ->select(['actual_name', 'short_name', 'allow_decimal', 'id',
                            'base_unit_id', 'base_unit_multiplier', ]);

            return Datatables::of($unit)
                ->addColumn(
                    'action',
                    '@can("unit.update")
                    <button data-href="{{action(\'App\Http\Controllers\UnitController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_unit_button"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                    @endcan
                    @can("unit.delete")
                        <button data-href="{{action(\'App\Http\Controllers\UnitController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_unit_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                    @endcan'
                )
                ->editColumn('allow_decimal', function ($row) {
                    if ($row->allow_decimal) {
                        return __('messages.yes');
                    } else {
                        return __('messages.no');
                    }
                })
                ->editColumn('actual_name', function ($row) {
                    if (! empty($row->base_unit_id)) {
                        return  $row->actual_name.' ('.(float) $row->base_unit_multiplier.$row->base_unit->short_name.')';
                    }

                    return  $row->actual_name;
                })
                ->removeColumn('id')
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('unit.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('unit.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $quick_add = false;
        if (! empty(request()->input('quick_add'))) {
            $quick_add = true;
        }

        $units = Unit::forDropdown($business_id);

        return view('unit.create')
                ->with(compact('quick_add', 'units'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('unit.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['actual_name', 'short_name', 'allow_decimal']);
            $input['business_id'] = $request->session()->get('user.business_id');
            $input['created_by'] = $request->session()->get('user.id');

            if ($request->has('define_base_unit')) {
                if (! empty($request->input('base_unit_id')) && ! empty($request->input('base_unit_multiplier'))) {
                    $base_unit_multiplier = $this->commonUtil->num_uf($request->input('base_unit_multiplier'));
                    if ($base_unit_multiplier != 0) {
                        $input['base_unit_id'] = $request->input('base_unit_id');
                        $input['base_unit_multiplier'] = $base_unit_multiplier;
                    }
                }
            }

            $unit = Unit::create($input);
            $output = ['success' => true,
                'data' => $unit,
                'msg' => __('unit.added_success'),
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
        if (! auth()->user()->can('unit.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $unit = Unit::where('business_id', $business_id)->find($id);

            $units = Unit::forDropdown($business_id);

            return view('unit.edit')
                ->with(compact('unit', 'units'));
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
        if (! auth()->user()->can('unit.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['actual_name', 'short_name', 'allow_decimal']);
                $business_id = $request->session()->get('user.business_id');

                $unit = Unit::where('business_id', $business_id)->findOrFail($id);
                $unit->actual_name = $input['actual_name'];
                $unit->short_name = $input['short_name'];
                $unit->allow_decimal = $input['allow_decimal'];
                if ($request->has('define_base_unit')) {
                    if (! empty($request->input('base_unit_id')) && ! empty($request->input('base_unit_multiplier'))) {
                        $base_unit_multiplier = $this->commonUtil->num_uf($request->input('base_unit_multiplier'));
                        if ($base_unit_multiplier != 0) {
                            $unit->base_unit_id = $request->input('base_unit_id');
                            $unit->base_unit_multiplier = $base_unit_multiplier;
                        }
                    }
                } else {
                    $unit->base_unit_id = null;
                    $unit->base_unit_multiplier = null;
                }

                $unit->save();

                $output = ['success' => true,
                    'msg' => __('unit.updated_success'),
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
        if (! auth()->user()->can('unit.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                $unit = Unit::where('business_id', $business_id)->findOrFail($id);

                //check if any product associated with the unit
                $exists = Product::where('unit_id', $unit->id)
                                ->exists();
                if (! $exists) {
                    $unit->delete();
                    $output = ['success' => true,
                        'msg' => __('unit.deleted_success'),
                    ];
                } else {
                    $output = ['success' => false,
                        'msg' => __('lang_v1.unit_cannot_be_deleted'),
                    ];
                }
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => '__("messages.something_went_wrong")',
                ];
            }

            return $output;
        }
    }

    /**
     * Tela Produto/Cadastros (playbook Produto · thread 02 — abas Unidades e Marcas). Cada aba
     * só recebe linhas se o usuário pode ver aquele cadastro (a mesma regra do index legado:
     * `view` ou `create`). `em_uso` usa a mesma regra que a exclusão consulta, então a tela
     * avisa a recusa antes de tentar.
     *
     * @return \Inertia\Response
     */
    private function cadastros()
    {
        $user = auth()->user();
        $business_id = (int) request()->session()->get('user.business_id');
        $pode = fn (string $base) => [
            'view' => $user->can("{$base}.view") || $user->can("{$base}.create"),
            'create' => $user->can("{$base}.create"),
            'update' => $user->can("{$base}.update"),
            'delete' => $user->can("{$base}.delete"),
        ];
        $can = ['unidades' => $pode('unit'), 'marcas' => $pode('brand')];

        $visiveis = array_keys(array_filter($can, fn ($p) => $p['view']));
        if (! $visiveis) {
            abort(403, 'Unauthorized action.');
        }
        $aba = in_array(request()->input('aba'), ['unidades', 'marcas'], true) ? request()->input('aba') : $visiveis[0];

        $emUso = fn (string $coluna, string $tabela) => DB::table('products')
            ->selectRaw('count(*)')
            ->whereColumn("products.{$coluna}", "{$tabela}.id")
            ->where('products.business_id', $business_id);

        return Inertia::render('Produto/Cadastros/Index', [
            'aba' => $aba,
            'can' => $can,
            'unidades' => $can['unidades']['view'] ? Inertia::defer(fn () => Unit::where('units.business_id', $business_id)
                ->leftJoin('units as base', function ($j) use ($business_id) {
                    $j->on('base.id', '=', 'units.base_unit_id')->where('base.business_id', $business_id);
                })
                ->select('units.id', 'units.actual_name', 'units.short_name', 'units.allow_decimal', 'units.base_unit_multiplier', 'base.short_name as base_simbolo')
                ->selectSub($emUso('unit_id', 'units'), 'em_uso')
                ->orderBy('units.actual_name')
                ->get()
                ->map(fn ($u) => [
                    'id' => (int) $u->getAttribute('id'),
                    'nome' => (string) $u->getAttribute('actual_name'),
                    'simbolo' => (string) $u->getAttribute('short_name'),
                    'decimal' => (bool) $u->getAttribute('allow_decimal'),
                    'base' => $u->getAttribute('base_simbolo')
                        ? '1 '.$u->getAttribute('short_name').' = '.(float) $u->getAttribute('base_unit_multiplier').' '.$u->getAttribute('base_simbolo')
                        : null,
                    'em_uso' => (int) $u->getAttribute('em_uso'),
                ])->values()->all()) : null,
            'marcas' => $can['marcas']['view'] ? Inertia::defer(fn () => Brands::where('brands.business_id', $business_id)
                ->select('brands.id', 'brands.name', 'brands.description')
                ->selectSub($emUso('brand_id', 'brands'), 'em_uso')
                ->orderBy('brands.name')
                ->get()
                ->map(fn ($b) => [
                    'id' => (int) $b->getAttribute('id'),
                    'nome' => (string) $b->getAttribute('name'),
                    'descricao' => (string) $b->getAttribute('description'),
                    'em_uso' => (int) $b->getAttribute('em_uso'),
                ])->values()->all()) : null,
        ]);
    }
}
