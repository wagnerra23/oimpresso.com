<?php

namespace App\Http\Controllers;

use App\Printer;
use App\Services\FeatureFlagService;
use Datatables;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PrinterController extends Controller
{
    /**
     * Flag do caminho React (thread sistema/playbook/04, F3). Convenção `useV2<Modulo><Tela>`.
     * Sem a chave no GrowthBook o FeatureFlagService cai no fallbackDefaults, que não a lista:
     * default OFF, a Blade segue servindo. Ligar é toggle no GrowthBook (flag:set --biz=1), não deploy.
     *
     * @see memory/requisitos/Configuracoes/RUNBOOK-impressoras.md
     */
    private const FLAG_V2 = 'useV2ConfiguracoesImpressoras';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('access_printers')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // `! inertia()`: o Inertia v3 manda `X-Requested-With` em toda visita; sem esta perna o
        // partial reload da prop adiada caía no JSON do DataTables (RUNBOOK-impressoras §10).
        if (request()->ajax() && ! request()->inertia()) {
            $printer = Printer::where('business_id', $business_id)
                        ->select(['name', 'connection_type',
                            'capability_profile', 'char_per_line', 'ip_address', 'port', 'path', 'id', ]);

            return Datatables::of($printer)
                ->editColumn('capability_profile', function ($row) {
                    return Printer::capability_profile_srt($row->capability_profile);
                })
                ->editColumn('connection_type', function ($row) {
                    return Printer::connection_type_str($row->connection_type);
                })
                ->addColumn(
                    'action',
                    '@can("printer.update")
                    <a href="{{action(\'App\Http\Controllers\PrinterController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</a>
                        &nbsp;
                    @endcan
                    @can("printer.delete")
                        <button data-href="{{action(\'App\Http\Controllers\PrinterController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_printer_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                    @endcan'
                )
                ->removeColumn('id')
                ->rawColumns([7])
                ->make(false);
        }

        if (app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            return Inertia::render('Configuracoes/Impressoras/Index', [
                'impressoras' => Inertia::defer(fn () => $this->impressorasDoNegocio((int) $business_id)),
                'opcoes' => ['conexao' => Printer::connection_types(), 'perfil' => Printer::capability_profiles()],
            ]);
        }

        return view('printer.index');
    }

    /** Impressoras do negócio da sessão, no shape da tela React. */
    private function impressorasDoNegocio(int $business_id): array
    {
        return Printer::where('business_id', $business_id)
            ->orderBy('name')
            ->get(['id', 'name', 'connection_type', 'capability_profile', 'char_per_line', 'ip_address', 'port', 'path'])
            ->map(fn (Printer $p) => [
                'id' => $p->id,
                'nome' => $p->name,
                'conexao' => $p->connection_type,
                'perfil' => $p->capability_profile,
                'caracteres_linha' => (string) $p->char_per_line,
                'ip' => (string) $p->ip_address,
                'porta' => (string) $p->port,
                'caminho' => (string) $p->path,
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
        if (! auth()->user()->can('access_printers')) {
            abort(403, 'Unauthorized action.');
        }

        $capability_profiles = Printer::capability_profiles();
        $connection_types = Printer::connection_types();

        return view('printer.create')
            ->with(compact('capability_profiles', 'connection_types'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('access_printers')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $input = $request->only(['name', 'connection_type', 'capability_profile', 'ip_address', 'port', 'path', 'char_per_line']);

            $input['business_id'] = $business_id;
            $input['created_by'] = $request->session()->get('user.id');

            if ($input['connection_type'] == 'network') {
                $input['path'] = '';
            } elseif (in_array($input['connection_type'], ['windows', 'linux'])) {
                $input['ip_address'] = '';
                $input['port'] = '';
            }

            $printer = new Printer;
            $printer->fill($input)->save();

            $output = ['success' => 1,
                'msg' => __('printer.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('printers')->with('status', $output);
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
        if (! auth()->user()->can('access_printers')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $printer = Printer::where('business_id', $business_id)->find($id);

        $capability_profiles = Printer::capability_profiles();
        $connection_types = Printer::connection_types();

        return view('printer.edit')
            ->with(compact('printer', 'capability_profiles', 'connection_types'));
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
        if (! auth()->user()->can('access_printers')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'connection_type', 'capability_profile', 'ip_address', 'port', 'path', 'char_per_line']);
            $business_id = $request->session()->get('user.business_id');

            $printer = Printer::where('business_id', $business_id)->findOrFail($id);

            if ($input['connection_type'] == 'network') {
                $input['path'] = '';
            } elseif (in_array($input['connection_type'], ['windows', 'linux'])) {
                $input['ip_address'] = '';
                $input['port'] = '';
            }

            $printer->fill($input)->save();

            $output = ['success' => true,
                'msg' => __('printer.updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('printers')->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('access_printers')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                $printer = Printer::where('business_id', $business_id)->findOrFail($id);
                $printer->delete();

                $output = ['success' => true,
                    'msg' => __('printer.deleted_success'),
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
