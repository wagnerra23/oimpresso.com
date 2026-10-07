<?php

namespace App\Http\Controllers;

use App\InvoiceLayout;
use App\InvoiceScheme;
use App\Services\FeatureFlagService;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InvoiceSchemeController extends Controller
{
    /**
     * Flag do caminho React (thread sistema/playbook/05, F3). Convenção `useV2<Modulo><Tela>`.
     * Sem a chave no GrowthBook o FeatureFlagService cai no fallbackDefaults, que não a lista:
     * default OFF, a Blade segue servindo. Ligar é toggle no GrowthBook (flag:set --biz=1), não deploy.
     *
     * @see memory/requisitos/Configuracoes/RUNBOOK-esquemas-fatura.md
     */
    private const FLAG_V2 = 'useV2ConfiguracoesEsquemasFatura';

    protected $number_types;

    public function __construct()
    {
        $this->number_types = ['sequential' => __('invoice.sequential'), 'random'=> __('invoice.random')];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View|\Inertia\Response
     */
    public function index()
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        // `! inertia()`: o Inertia v3 manda `X-Requested-With` em toda visita; sem esta perna o
        // partial reload da prop adiada caía no JSON do DataTables (RUNBOOK-esquemas-fatura §10).
        if (request()->ajax() && ! request()->inertia()) {
            $schemes = InvoiceScheme::where('business_id', $business_id)
                            ->select(['id', 'name', 'scheme_type', 'prefix', 'number_type', 'start_number', 'invoice_count', 'total_digits', 'is_default']);

            return Datatables::of($schemes)
                ->addColumn(
                    'action',
                    '<button type="button" data-href="{{action(\'App\Http\Controllers\InvoiceSchemeController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".invoice_edit_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                        <button type="button" data-href="{{action(\'App\Http\Controllers\InvoiceSchemeController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_invoice_button" @if($is_default) disabled @endif><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>&nbsp;
                        @if($is_default)
                            <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent" disabled><i class="fa fa-check-square-o" aria-hidden="true"></i> @lang("barcode.default")</button>
                        @else
                            <button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info set_default_invoice" data-href="{{action(\'App\Http\Controllers\InvoiceSchemeController@setDefault\', [$id])}}">@lang("barcode.set_as_default")</button>
                        @endif
                        '
                )
                ->editColumn('number_type', function ($row) {
                    return $this->number_types[$row->number_type];
                })
                ->editColumn('prefix', function ($row) {
                    if ($row->scheme_type == 'year') {
                        return $row->prefix.date('Y').config('constants.invoice_scheme_separator');
                    } else {
                        return $row->prefix;
                    }
                })
                ->editColumn('name', function ($row) {
                    if ($row->is_default == 1) {
                        return $row->name.' &nbsp; <span class="label label-success">'.__('barcode.default').'</span>';
                    } else {
                        return $row->name;
                    }
                })
                ->removeColumn('id')
                ->removeColumn('is_default')
                ->removeColumn('scheme_type')
                ->rawColumns([6, 0])
                ->make(false);
        }

        if (app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            return Inertia::render('Configuracoes/EsquemasFatura/Index', [
                'fatura' => Inertia::defer(fn () => $this->faturaDoNegocio((int) $business_id)),
                'tipos_numero' => $this->number_types,
            ]);
        }

        $invoice_layouts = InvoiceLayout::where('business_id', $business_id)
                                        ->with(['locations'])
                                        ->get();

        return view('invoice_scheme.index')
                    ->with(compact('invoice_layouts'));
    }

    /**
     * Esquemas e layouts do negócio no shape da tela React. O prefixo exibido segue a DataTable (ano + separador no
     * esquema anual); `invoice_count` é o contador que a venda incrementa, só leitura aqui.
     */
    private function faturaDoNegocio(int $business_id): array
    {
        $separador = config('constants.invoice_scheme_separator');
        $locaisPorLayout = DB::table('business_locations')->where('business_id', $business_id)
            ->orderBy('name')->get(['name', 'invoice_layout_id', 'sale_invoice_layout_id']);

        return [
            'esquemas' => InvoiceScheme::where('business_id', $business_id)->orderByDesc('is_default')->orderBy('name')->get()
                ->map(fn (InvoiceScheme $e) => [
                    'id' => $e->id, 'nome' => $e->name, 'padrao' => (bool) $e->is_default, 'tipo' => $e->scheme_type,
                    'prefixo' => (string) $e->prefix,
                    'prefixo_exibido' => $e->scheme_type == 'year' ? $e->prefix.date('Y').$separador : (string) $e->prefix,
                    'tipo_numero' => $e->number_type, 'inicio' => $e->start_number, 'emitidas' => (int) $e->invoice_count,
                    'digitos' => $e->total_digits,
                ])->all(),
            'layouts' => InvoiceLayout::where('business_id', $business_id)->orderBy('name')->get(['id', 'name', 'is_default'])
                ->map(fn (InvoiceLayout $l) => [
                    'id' => $l->id, 'nome' => $l->name, 'padrao' => (bool) $l->is_default,
                    'locais' => $locaisPorLayout
                        ->filter(fn ($loc) => (int) $loc->invoice_layout_id === $l->id || (int) $loc->sale_invoice_layout_id === $l->id)
                        ->pluck('name')->values()->all(),
                ])->all(),
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $number_types = $this->number_types;
        return view('invoice_scheme.create')->with(compact('number_types'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'scheme_type', 'prefix', 'start_number', 'total_digits', 'number_type']);
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;

            $input['start_number'] = ($input['number_type'] == 'aleatory') ? '' : $input['start_number'];
            
            if (! empty($request->input('is_default'))) {
                //get_default
                $default = InvoiceScheme::where('business_id', $business_id)
                                ->where('is_default', 1)
                                ->update(['is_default' => 0]);
                $input['is_default'] = 1;
            }
            InvoiceScheme::create($input);
            $output = ['success' => true,
                'msg' => __('invoice.added_success'),
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
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

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
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $invoice = InvoiceScheme::where('business_id', $business_id)->find($id);

        $number_types = $this->number_types;

        return view('invoice_scheme.edit')
            ->with(compact('invoice', 'number_types'));
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
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'scheme_type', 'prefix', 'start_number', 'total_digits', 'number_type']);

            $input['start_number'] = ($input['number_type'] == 'aleatory') ? '' : $input['start_number'];

            // Tier 0: sem o negócio, o id alcançava o esquema de fatura de outro negócio (mesmo desenho do #8924).
            $business_id = $request->session()->get('user.business_id');
            InvoiceScheme::where('business_id', $business_id)->findOrFail($id)->update($input);

            $output = ['success' => true,
                'msg' => __('invoice.updated_success'),
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');
                $invoice = InvoiceScheme::where('business_id', $business_id)->findOrFail($id);
                if ($invoice->is_default != 1) {
                    $invoice->delete();
                    $output = ['success' => true,
                        'msg' => __('invoice.deleted_success'),
                    ];
                } else {
                    $output = ['success' => false,
                        'msg' => __('messages.something_went_wrong'),
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
     * Sets invoice scheme setting as default
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function setDefault($id)
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                //get_default
                $business_id = request()->session()->get('user.business_id');
                // Achar antes de desmarcar: id de outro negócio falha aqui, sem tirar o padrão do próprio.
                $invoice = InvoiceScheme::where('business_id', $business_id)->findOrFail($id);
                $default = InvoiceScheme::where('business_id', $business_id)
                                ->where('is_default', 1)
                                 ->update(['is_default' => 0]);

                // `true` (não 1): sobre o 1 carregado antes do update acima, o atributo fica sujo e o save()
                // grava mesmo quando ele já era o padrão (caso "já é o padrão" no EsquemaFaturaTenantTest).
                $invoice->is_default = true;
                $invoice->save();

                $output = ['success' => true,
                    'msg' => __('barcode.default_set_success'),
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
