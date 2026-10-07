<?php

namespace App\Http\Controllers;

use App\Barcode;
use App\Services\FeatureFlagService;
use Datatables;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BarcodeController extends Controller
{
    /**
     * Flag do caminho React (thread sistema/playbook/04, F3). Convenção `useV2<Modulo><Tela>`.
     * Sem a chave no GrowthBook o FeatureFlagService cai no fallbackDefaults, que não a lista:
     * default OFF, a Blade segue servindo. Ligar é toggle no GrowthBook (flag:set --biz=1), não deploy.
     *
     * @see memory/requisitos/Configuracoes/RUNBOOK-codigo-barras.md
     */
    private const FLAG_V2 = 'useV2ConfiguracoesCodigoBarras';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View|\Inertia\Response
     */
    public function index()
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // `! inertia()`: o Inertia v3 manda `X-Requested-With` em toda visita; sem esta perna o
        // partial reload da prop adiada caía no JSON do DataTables (RUNBOOK-codigo-barras §10).
        if (request()->ajax() && ! request()->inertia()) {
            $barcodes = Barcode::where('business_id', $business_id)
                        ->select(['name', 'description', 'id', 'is_default']);

            return Datatables::of($barcodes)
                ->addColumn(
                    'action',
                    '<a href="{{action(\'App\Http\Controllers\BarcodeController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</a>
                        &nbsp;
                        <button type="button" data-href="{{action(\'App\Http\Controllers\BarcodeController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_barcode_button" @if($is_default) disabled @endif><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>&nbsp;
                        @if($is_default)
                            <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent" disabled><i class="fa fa-check-square-o" aria-hidden="true"></i> @lang("barcode.default")</button>
                        @else
                            <button class="btn btn-xs btn-info set_default" data-href="{{action(\'App\Http\Controllers\BarcodeController@setDefault\', [$id])}}">@lang("barcode.set_as_default")</button>
                        @endif
                        '
                )
                ->editColumn('name', function ($row) {
                    if ($row->is_default == 1) {
                        return $row->name.' &nbsp; <span class="label label-success">'.__('barcode.default').'</span>';
                    } else {
                        return $row->name;
                    }
                })
                ->removeColumn('id')
                ->removeColumn('is_default')
                ->rawColumns([0, 2])
                ->make(false);
        }

        if (app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            return Inertia::render('Configuracoes/CodigoBarras/Index', [
                'etiquetas' => Inertia::defer(fn () => $this->etiquetasDoNegocio((int) $business_id)),
            ]);
        }

        return view('barcode.index');
    }

    /**
     * Configurações de etiqueta do negócio da sessão, no shape da tela React. Os modelos globais
     * (business_id NULL) ficam fora, como na DataTable. Medidas em polegada, como o banco e a impressão.
     */
    private function etiquetasDoNegocio(int $business_id): array
    {
        $campos = ['width', 'height', 'paper_width', 'paper_height', 'top_margin', 'left_margin', 'row_distance', 'col_distance'];

        return Barcode::where('business_id', $business_id)
            ->orderByDesc('is_default')->orderBy('name')
            ->get()
            ->map(fn (Barcode $b) => [
                'id' => $b->id,
                'nome' => $b->name,
                'descricao' => (string) $b->description,
                'padrao' => (bool) $b->is_default,
                'continuo' => (bool) $b->is_continuous,
                'por_linha' => $b->stickers_in_one_row,
                'por_folha' => $b->stickers_in_one_sheet,
                'medidas' => collect($campos)->mapWithKeys(fn ($c) => [$c => $b->{$c} === null ? null : (float) $b->{$c}])->all(),
            ])
            ->all();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        return view('barcode.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description', 'width', 'height', 'top_margin',
                'left_margin', 'row_distance', 'col_distance',
                'stickers_in_one_row', 'paper_width', ]);
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;

            if (! empty($request->input('is_default'))) {
                //get_default
                $default = Barcode::where('business_id', $business_id)
                                ->where('is_default', 1)
                                ->update(['is_default' => 0]);
                $input['is_default'] = 1;
            }
            if (! empty($request->input('is_continuous'))) {
                $input['is_continuous'] = 1;
                $input['stickers_in_one_sheet'] = 28;
            } else {
                $input['stickers_in_one_sheet'] = $request->input('stickers_in_one_sheet');
                $input['paper_height'] = $request->input('paper_height');
            }

            $barcode = Barcode::create($input);
            $output = ['success' => 1,
                'msg' => __('barcode.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('barcodes')->with('status', $output);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Barcode  $barcode
     * @return \Illuminate\Http\Response
     */
    public function show(Barcode $barcode)
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
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $barcode = Barcode::where('business_id', $business_id)->find($id);

        return view('barcode.edit')
            ->with(compact('barcode'));
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
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description', 'width', 'height', 'top_margin',
                'left_margin', 'row_distance', 'col_distance',
                'stickers_in_one_row', 'paper_width', ]);

            if (! empty($request->input('is_continuous'))) {
                $input['is_continuous'] = 1;
                $input['stickers_in_one_sheet'] = 28;
                $input['paper_height'] = 0;
            } else {
                $input['is_continuous'] = 0;
                $input['stickers_in_one_sheet'] = $request->input('stickers_in_one_sheet');
                $input['paper_height'] = $request->input('paper_height');
            }

            // Tier 0: sem o negócio, o id alcançava a etiqueta de outro negócio e os modelos
            // globais (business_id NULL) que o LabelsController oferece a todos.
            $business_id = $request->session()->get('user.business_id');
            Barcode::where('business_id', $business_id)->findOrFail($id)->update($input);

            $output = ['success' => 1,
                'msg' => __('barcode.updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('barcodes')->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');
                $barcode = Barcode::where('business_id', $business_id)->findOrFail($id);
                if ($barcode->is_default != 1) {
                    $barcode->delete();
                    $output = ['success' => true,
                        'msg' => __('barcode.deleted_success'),
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
     * Sets barcode setting as default
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function setDefault($id)
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                //get_default
                $business_id = request()->session()->get('user.business_id');
                // Achar antes de desmarcar: id de outro negócio falha aqui, sem tirar o padrão do próprio.
                $barcode = Barcode::where('business_id', $business_id)->findOrFail($id);
                $default = Barcode::where('business_id', $business_id)
                                ->where('is_default', 1)
                                 ->update(['is_default' => 0]);

                // refresh(): carregado antes do update acima, o model ainda diria is_default=1 e o
                // save() não gravaria nada se ela já fosse a padrão.
                $barcode->refresh();
                $barcode->is_default = true;
                $barcode->save();

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
