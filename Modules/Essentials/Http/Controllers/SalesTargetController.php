<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Essentials\Entities\EssentialsUserSalesTarget;
use Modules\Essentials\Services\SalesTargetFaixaValidator;
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
     * Dois retornos por desenho: JSON do DataTables no ramo `ajax()` (a Blade legada ainda
     * consome esta rota até a HRM-O8) e a Page Inertia na navegação normal.
     *
     * @return \Illuminate\Http\JsonResponse|\Inertia\Response
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

        // HRM-O7 / PR-9 (Onda 9 do EXPORT-HRM-2026-09-04): a tela vira Inertia.
        //
        // O ramo request()->ajax() ACIMA fica INTACTO de propósito: enquanto
        // `sales_targets/index.blade.php` existir (ele sai só na HRM-O8), o DataTables
        // jQuery daquela view continua consumindo esta MESMA rota. Trocar por
        // Inertia-only aqui apagaria a tela legada sem aviso.
        return Inertia::render('Essentials/Metas', [
            'filtros' => ['q' => (string) request()->input('q', '')],
            // Config do módulo (não é valor): decide se o vendido entra com ou sem tributo
            // no cálculo que o PayrollController faz. Aqui é EXIBIDA, nunca aplicada.
            'sem_imposto' => (bool) ($this->essentialsSettings()['calculate_sales_target_commission_without_tax'] ?? false),
            'paginator' => Inertia::defer(fn () => $this->paginarColaboradores($business_id)),
        ]);
    }

    /** Settings do módulo — mesma leitura de EssentialsUtil::getEssentialsSettings (sessão → JSON). */
    private function essentialsSettings(): array
    {
        $raw = request()->session()->get('business.essentials_settings');

        return ! empty($raw) ? (array) json_decode($raw, true) : [];
    }

    /**
     * Página de colaboradores + as faixas de meta JÁ GRAVADAS de cada um.
     *
     * Só LEITURA: nenhum número aqui é calculado, derivado ou reinterpretado — as faixas
     * saem de essentials_user_sales_targets como estão no banco. A apuração (quanto o
     * colaborador vendeu no mês e quanto isso vira de comissão) NÃO é servida por esta
     * tela: quem a produz é DashboardController::getUserSalesTargets, que é admin-only e
     * responde DataTables. Trazê-la é caminho de VALOR e exige a dupla prova da regra
     * mestre (memory/proibicoes.md) — PR próprio, não esta onda.
     *
     * O predicado dos colaboradores é o MESMO do ramo ajax acima (user() + allow_login=1),
     * incluindo o filtro de busca, pra que as duas tabelas nunca mostrem populações
     * diferentes enquanto a Blade coexistir.
     */
    private function paginarColaboradores(int $business_id): LengthAwarePaginator
    {
        $busca = trim((string) request()->input('q', ''));

        $users = User::where('business_id', $business_id)
                    ->user()
                    ->where('allow_login', 1)
                    ->select(['id',
                        DB::raw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name"), ]);

        if ($busca !== '') {
            $users->where(function ($q) use ($busca) {
                $q->whereRaw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$busca}%"])
                    ->orWhere('username', 'like', "%{$busca}%")
                    ->orWhere('email', 'like', "%{$busca}%");
            });
        }

        $pagina = $users->orderBy('full_name')->paginate(25)->withQueryString();

        // Tier 0 (ADR 0093): os ids vêm da página JÁ escopada por business_id acima — o
        // whereIn nunca alcança colaborador de outro tenant.
        $faixasPorUsuario = EssentialsUserSalesTarget::whereIn('user_id', $pagina->pluck('id'))
                    ->orderBy('target_start')
                    ->get()
                    ->groupBy('user_id');

        // `full_name` é ALIAS do SELECT (CONCAT), não coluna nem accessor do model — por isso
        // getAttribute() e não `$u->full_name`: a propriedade mágica não existe pro PHPStan.
        $pagina->getCollection()->transform(fn ($u) => [
            'id' => (int) $u->id,
            'nome' => trim((string) $u->getAttribute('full_name')),
            'faixas' => ($faixasPorUsuario[$u->id] ?? collect())
                ->map(fn ($f) => [
                    'id' => (int) $f->id,
                    'inicio' => (float) $f->target_start,
                    'fim' => (float) $f->target_end,
                    'percentual' => (float) $f->commission_percent,
                ])->values()->all(),
        ]);

        return $pagina;
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
     * @return RedirectResponse
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
