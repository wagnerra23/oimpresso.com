<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Essentials\Entities\EssentialsUserSalesTarget;
use Yajra\DataTables\Facades\DataTables;

class SalesTargetController extends Controller
{
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ModuleUtil  $moduleUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module')) && ! auth()->user()->can('essentials.access_sales_target')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $user_id = request()->session()->get('user.id');

            $users = User::where('business_id', $business_id)
                        ->user()
                        ->where('allow_login', 1)
                        ->select(['id',
                            DB::raw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name"), ]);

            return Datatables::of($users)
                ->addColumn(
                    'action',
                    '<button type="button" data-href="{{action(\'\Modules\Essentials\Http\Controllers\SalesTargetController@setSalesTarget\', [$id])}}" class="btn btn-xs btn-primary btn-modal" data-container="#set_sales_target_modal"><i class="fas fa-bullseye"></i> @lang("essentials::lang.set_sales_target")</button>'
                )
                ->filterColumn('full_name', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->whereRaw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$keyword}%"])
                        ->orWhere('username', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                    });
                })
                ->removeColumn('id')
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('essentials::sales_targets.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function setSalesTarget($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module')) && ! auth()->user()->can('essentials.access_sales_target')) {
            abort(403, 'Unauthorized action.');
        }

        $user = User::where('business_id', $business_id)
                    ->find($id);

        $sales_targets = EssentialsUserSalesTarget::where('user_id', $id)
                                                ->get();

        return view('essentials::sales_targets.sales_target_modal')->with(compact('user', 'sales_targets'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function saveSalesTarget(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'essentials_module')) && ! auth()->user()->can('essentials.access_sales_target')) {
            abort(403, 'Unauthorized action.');
        }

        // Gate Tier 0 (ADR 0093): user_id vem cru do body do POST. Valida que é
        // do tenant ANTES de escrever metas/comissão — o create() abaixo não é
        // coberto pelo backstop ScopeByBusinessViaParent (que só filtra SELECT,
        // não INSERT) → sem isto, cria linha de meta no user de OUTRO business.
        // Fecha IDOR cross-tenant (follow-up #4474).
        //
        // PRECEDE a validação de faixas de propósito: id cru de outro tenant é
        // 404 antes de qualquer outra mensagem, sem oráculo sobre o que existe lá.
        User::where('business_id', $business_id)->findOrFail($request->input('user_id'));

        // HRM-O6 / PR-4 (achado A5): monta o conjunto FINAL de faixas e valida ANTES
        // de escrever. Antes disto o método aceitava faixa invertida (comissão devida
        // sumia em silêncio), faixas sobrepostas (o ->first() sem orderBy do
        // PayrollController pagava percentual indefinido) e percentual fora de 0–100
        // (calc_percentage não tem teto). Validar antes é obrigatório e não cosmético:
        // o bloco de escrita abaixo DELETA as faixas fora de edit_target antes de
        // inserir as novas — abortar no meio deixaria o colaborador sem meta nenhuma.
        [$faixas, $edicoes, $novas] = $this->montarFaixas($request);

        $erros = SalesTargetFaixaValidator::erros($faixas);
        if (! empty($erros)) {
            return back()->with('status', [
                'success' => false,
                'msg' => implode(' ', $erros),
            ]);
        }

        try {
            $target_ids = [];
            foreach ($edicoes as $id => $faixa) {
                EssentialsUserSalesTarget::where('user_id',
                                    $request->input('user_id'))
                                    ->where('id', $id)
                                    ->update([
                                        'target_start' => $faixa['start'],
                                        'target_end' => $faixa['end'],
                                        'commission_percent' => $faixa['commission'],
                                    ]);
                $target_ids[] = $id;
            }

            EssentialsUserSalesTarget::where('user_id',
                                        $request->input('user_id'))
                                    ->whereNotIn('id', $target_ids)
                                    ->delete();

            foreach ($novas as $faixa) {
                EssentialsUserSalesTarget::create([
                    'user_id' => $request->input('user_id'),
                    'target_start' => $faixa['start'],
                    'target_end' => $faixa['end'],
                    'commission_percent' => $faixa['commission'],
                ]);
            }

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return back()->with('status', $output);
    }

    /**
     * Normaliza o POST em faixas — a MESMA lista que valida é a que grava.
     *
     * Conversão de número segue exclusivamente por ModuleUtil::num_uf (heurística
     * pt-BR canônica): nenhum valor é reinterpretado depois, então para entrada
     * válida o que chega no banco é idêntico ao de antes deste PR.
     *
     * A linha inteiramente vazia continua ignorada (o modal Blade sempre envia uma
     * linha 0/0/0 em branco no fim — rejeitá-la barraria todo salvamento legítimo).
     *
     * @return array{0: list<array{rotulo: string, start: float, end: float, commission: float}>, 1: array<int|string, array{start: float, end: float, commission: float}>, 2: list<array{start: float, end: float, commission: float}>}
     */
    private function montarFaixas(Request $request): array
    {
        $faixas = [];
        $edicoes = [];
        $novas = [];
        $posicao = 0;

        foreach ((array) $request->input('edit_target', []) as $id => $valores) {
            $faixa = [
                'start' => $this->moduleUtil->num_uf($valores['target_start'] ?? null),
                'end' => $this->moduleUtil->num_uf($valores['target_end'] ?? null),
                'commission' => $this->moduleUtil->num_uf($valores['commission_percent'] ?? null),
            ];
            $edicoes[$id] = $faixa;
            $faixas[] = $faixa + ['rotulo' => 'nº '.(++$posicao)];
        }

        $fins = (array) $request->input('sales_amount_end', []);
        $comissoes = (array) $request->input('commission', []);

        foreach ((array) $request->input('sales_amount_start', []) as $key => $value) {
            $faixa = [
                'start' => $this->moduleUtil->num_uf($value),
                'end' => $this->moduleUtil->num_uf($fins[$key] ?? null),
                'commission' => $this->moduleUtil->num_uf($comissoes[$key] ?? null),
            ];

            if (empty($faixa['start']) && empty($faixa['end'])) {
                continue;
            }

            $novas[] = $faixa;
            $faixas[] = $faixa + ['rotulo' => 'nº '.(++$posicao)];
        }

        return [$faixas, $edicoes, $novas];
    }
}
