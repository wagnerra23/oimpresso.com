<?php

namespace App\Http\Controllers\Restaurant;

use App\BusinessLocation;
use App\Restaurant\ResTable;
use App\Services\FeatureFlagService;
use App\Transaction;
use App\User;
use App\Utils\RestaurantUtil;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class TableController extends Controller
{
    /**
     * Flag do caminho React (thread restaurante/playbook/01, F3). Convenção `useV2<Modulo><Tela>`.
     * Sem a chave no GrowthBook o FeatureFlagService cai no fallbackDefaults, que não a lista:
     * default OFF, a Blade segue servindo. Ligar é toggle no GrowthBook (flag:set --biz=1), não deploy.
     *
     * @see memory/requisitos/Restaurante/RUNBOOK-mesas.md
     */
    private const FLAG_V2 = 'useV2RestauranteMesas';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Contracts\View\View|\Inertia\Response
     */
    public function index()
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // `?classico=1` mantém a Blade alcançável com a flag ligada: cadastrar, editar e excluir seguem nos modais dela.
        if (! request()->boolean('classico') && (! request()->ajax() || request()->inertia())
            && app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            return Inertia::render('Restaurante/Mesas/Index', [
                'mesas' => Inertia::defer(fn () => $this->mesasDoNegocio((int) $business_id)),
                'pedidos_hoje' => Inertia::defer(fn () => $this->pedidosHoje((int) $business_id)),
                'pode_gerenciar' => auth()->user()->hasRole('Admin#'.$business_id),
            ]);
        }

        // `! inertia()`: o Inertia v3 manda `X-Requested-With` em toda visita; sem esta perna o partial reload
        // da prop adiada caía no JSON da DataTable (§5 2026-09-08, molde AccountController::index).
        if (request()->ajax() && ! request()->inertia()) {

            $tables = ResTable::where('res_tables.business_id', $business_id)
                        ->join('business_locations AS BL', 'res_tables.location_id', '=', 'BL.id')
                        ->select(['res_tables.name as name', 'BL.name as location',
                            'res_tables.description', 'res_tables.id', ]);

            return Datatables::of($tables)
                ->addColumn(
                    'action',
                    '@role("Admin#'.$business_id.'")
                    <button data-href="{{action(\'App\Http\Controllers\Restaurant\TableController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_table_button"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                    @endrole
                    @role("Admin#'.$business_id.'")
                        <button data-href="{{action(\'App\Http\Controllers\Restaurant\TableController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_table_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                    @endrole'
                )
                ->removeColumn('id')
                ->escapeColumns(['action'])
                ->make(true);
        }

        return view('restaurant.table.index');
    }

    /**
     * Mesas do negócio para a tela React, com a situação de cada uma.
     *
     * Ocupada = tem pedido aberto pela regra que a Cozinha e os Pedidos já usam (RestaurantUtil::getAllOrders:
     * venda `final` ainda não `served`, nos locais permitidos ao usuário). Atendente = o do pedido aberto mais recente.
     *
     * @return list<array<string, mixed>>
     */
    private function mesasDoNegocio(int $business_id): array
    {
        $abertos = app(RestaurantUtil::class)->getAllOrders($business_id)
            ->whereNotNull('res_table_id')
            ->groupBy('res_table_id');

        $atendentes = User::where('business_id', $business_id)
            ->whereIn('id', $abertos->map(fn ($p) => $p->first()->res_waiter_id)->filter()->values())
            ->get()
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->surname.' '.$u->first_name.' '.$u->last_name)]);

        return ResTable::where('res_tables.business_id', $business_id)
            ->join('business_locations AS BL', 'res_tables.location_id', '=', 'BL.id')
            ->orderBy('res_tables.name')
            ->get(['res_tables.id', 'res_tables.name', 'res_tables.description', 'BL.name as local'])
            ->map(function ($m) use ($abertos, $atendentes) {
                $pedidos = $abertos->get($m->id);
                $waiter = $pedidos ? $pedidos->first()->res_waiter_id : null;

                return [
                    'id' => $m->id,
                    'nome' => $m->name,
                    'local' => $m->local,
                    'descricao' => $m->description,
                    'ocupada' => $pedidos !== null,
                    'pedidos_abertos' => $pedidos ? $pedidos->count() : 0,
                    'atendente' => $waiter ? ($atendentes[$waiter] ?? null) : null,
                ];
            })
            ->values()
            ->all();
    }

    /** Vendas `final` de hoje com mesa, do negócio. */
    private function pedidosHoje(int $business_id): int
    {
        return Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNotNull('res_table_id')
            ->whereDate('transaction_date', now()->toDateString())
            ->count();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id);

        return view('restaurant.table.create')
            ->with(compact('business_locations'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description', 'location_id']);
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;
            $input['created_by'] = $request->session()->get('user.id');

            $table = ResTable::create($input);
            $output = ['success' => true,
                'data' => $table,
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
     * Show the specified resource.
     *
     * @return Response
     */
    public function show()
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        return view('restaurant.table.show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $table = ResTable::where('business_id', $business_id)->find($id);

            return view('restaurant.table.edit')
                ->with(compact('table'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'description']);
                $business_id = $request->session()->get('user.business_id');

                $table = ResTable::where('business_id', $business_id)->findOrFail($id);
                $table->name = $input['name'];
                $table->description = $input['description'];
                $table->save();

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
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                $table = ResTable::where('business_id', $business_id)->findOrFail($id);
                $table->delete();

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
