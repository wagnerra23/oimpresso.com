<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Business;
use App\Product;
use App\Transaction;
use App\User;
use App\Util\OtelHelper;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\VariationLocationDetails;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Http\Requests\StoreBusinessRequest;
use Modules\Superadmin\Http\Requests\UpdateBusinessPasswordRequest;
use Modules\Superadmin\Notifications\PasswordUpdateNotification;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\Facades\DataTables;

class BusinessController extends BaseController
{
    protected $businessUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(BusinessUtil $businessUtil, ModuleUtil $moduleUtil)
    {
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Lista de negócios (`GET /superadmin/business`) — onda SA-O2.
     *
     * Deixou de servir DataTables por AJAX e passou a Inertia com paginação SERVER-SIDE.
     * A troca não é cosmética: o legado trazia a página inteira pro DataTables montar, e
     * o `groupBy('business.id')` que ele usava para desfazer a multiplicação do join com
     * `business_locations` quebra a contagem do `paginate()` (o COUNT passa a ser por
     * grupo). Aqui o local vira SUBQUERY ESCALAR e a assinatura entra pela mais recente
     * (`MAX(id)`), então cada negócio é exatamente uma linha e o total pagina certo.
     */
    public function index(Request $request): InertiaResponse
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        return OtelHelper::spanBiz('superadmin.negocios.index', function () use ($request) {
            $aberto = (int) $request->input('negocio', 0);

            $filtros = [
                'q' => trim((string) $request->input('q', '')),
                'pacote' => $request->input('pacote'),
                'assinatura' => $this->opcaoValida($request->input('assinatura'), ['vigente', 'vencida', 'sem']),
                'status' => $this->opcaoValida($request->input('status'), ['ativo', 'inativo']),
                'venda' => $this->opcaoValida($request->input('venda'), ['today', 'yesterday', 'this_week', 'this_month', 'last_month', 'this_year', 'last_year']),
            ];

            return Inertia::render('superadmin/Negocios/Index', [
                'filtros' => $filtros,
                'aberto' => $aberto > 0 ? $aberto : null,
                'pacotes' => Inertia::defer(fn () => $this->opcoesDePacote()),
                'negocios' => Inertia::defer(fn () => $this->negociosPayload($filtros)),
                // Drawer PT-02: e um ESTADO da lista (?negocio=<id>), nao outra tela. Só
                // consulta quando ha id — sem id, a closure nem roda.
                'detalhe' => Inertia::defer(
                    fn () => $aberto > 0 ? $this->detalheDoNegocio($aberto) : null
                ),
            ]);
        }, ['component' => 'superadmin.negocios.index']);
    }

    /** Opção de filtro fora da lista vira `null` — nunca chega cru na query. */
    private function opcaoValida(?string $valor, array $aceitos): ?string
    {
        return in_array($valor, $aceitos, true) ? $valor : null;
    }

    /**
     * Pacotes para o filtro. Só id + nome: a lista é combo, não catálogo.
     *
     * @return array<int, array{id: int, nome: string}>
     */
    private function opcoesDePacote(): array
    {
        return Package::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'nome' => (string) $p->name])
            ->all();
    }

    /**
     * Página de negócios já filtrada.
     *
     * SUPERADMIN: leitura GLOBAL cross-tenant intencional (ADR 0093 §exceções) — esta tela
     * existe para enxergar todos os negócios da plataforma.
     */
    private function negociosPayload(array $filtros): array
    {
        $hoje = \Carbon::today()->toDateString();

        // DB::table, não Business::query(): as colunas do JOIN (`sub_status`, `pacote`,
        // `cidade`, `dono`…) não existem no model, e hidratar Eloquent pra montar uma lista
        // de leitura não paga. O PHPStan reclamava disso com razão (`property.notFound`).
        $query = DB::table('business')
            // A assinatura MAIS RECENTE de cada negócio, uma só. Sem o MAX(id) o join
            // multiplicaria a linha por assinatura histórica e a paginação mentiria.
            ->leftJoin('subscriptions AS s', function ($join) {
                $join->on('business.id', '=', 's.business_id')
                    ->whereRaw('s.id = (SELECT MAX(s2.id) FROM subscriptions s2 WHERE s2.business_id = business.id)');
            })
            ->leftJoin('packages AS p', 's.package_id', '=', 'p.id')
            ->leftJoin('users AS u', 'u.id', '=', 'business.owner_id')
            ->select([
                'business.id',
                'business.name',
                'business.is_active',
                'business.created_at',
                'u.email AS dono_email',
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS dono"),
                's.status AS sub_status',
                's.end_date AS sub_fim',
                'p.name AS pacote',
                // Subquery escalar em vez de join: `business_locations` é 1-para-N e o join
                // duplicaria o negócio por local.
                DB::raw('(SELECT bl.city FROM business_locations bl WHERE bl.business_id = business.id ORDER BY bl.id LIMIT 1) AS cidade'),
            ]);

        if ($filtros['q'] !== '') {
            $termo = '%'.$filtros['q'].'%';
            $query->where(function ($w) use ($termo, $filtros) {
                $w->where('business.name', 'like', $termo)
                    ->orWhere('u.email', 'like', $termo)
                    ->orWhere('u.first_name', 'like', $termo)
                    ->orWhere('u.last_name', 'like', $termo);

                // Busca por número do negócio só quando o termo É um número — senão a
                // comparação vira cast implícito e casa linha errada.
                if (ctype_digit($filtros['q'])) {
                    $w->orWhere('business.id', (int) $filtros['q']);
                }
            });
        }

        if (! empty($filtros['pacote'])) {
            $query->where('p.id', (int) $filtros['pacote']);
        }

        if ($filtros['assinatura'] === 'vigente') {
            $query->where('s.status', 'approved')
                ->where(function ($w) use ($hoje) {
                    $w->whereNull('s.end_date')->orWhereDate('s.end_date', '>=', $hoje);
                });
        } elseif ($filtros['assinatura'] === 'vencida') {
            $query->whereNotNull('s.id')->whereDate('s.end_date', '<', $hoje);
        } elseif ($filtros['assinatura'] === 'sem') {
            $query->whereNull('s.id');
        }

        if ($filtros['status'] === 'ativo') {
            $query->where('business.is_active', 1);
        } elseif ($filtros['status'] === 'inativo') {
            $query->where('business.is_active', 0);
        }

        // 4o filtro do F1 ("ultima venda"): reusa o `filterTransactionDate` que ja servia o
        // DataTables — a subquery em `transactions` e cara, mas e a mesma que rodava antes, e
        // reescrever mudaria o resultado sem necessidade. O operador `>` = "vendeu no periodo".
        if ($filtros['venda'] !== null) {
            $this->filterTransactionDate($query, $filtros['venda'], '>');
        }

        $pagina = $query->orderByDesc('business.id')->paginate(20)->withQueryString();

        return [
            'linhas' => collect($pagina->items())->map(fn ($b) => [
                'id' => (int) $b->id,
                'nome' => (string) $b->name,
                'dono' => trim((string) ($b->dono ?? '')) ?: null,
                'email' => $b->dono_email,
                'cidade' => $b->cidade,
                'pacote' => $b->pacote,
                'ativo' => (bool) $b->is_active,
                'assinatura' => $this->rotuloDeAssinatura($b->sub_status, $b->sub_fim),
                'criado' => $b->created_at ? \Carbon::parse($b->created_at)->format('d/m/Y') : null,
            ])->all(),
            'total' => $pagina->total(),
            'pagina' => $pagina->currentPage(),
            'paginas' => $pagina->lastPage(),
            'por_pagina' => $pagina->perPage(),
        ];
    }

    /**
     * Detalhe de UM negócio para o drawer (PT-02) — onda SA-O2b.
     *
     * Chega por partial reload (`?negocio=<id>`), sem rota de página nova: o drawer é um
     * estado da lista, não outra tela.
     *
     * DUAS SEÇÕES DO F1 FICARAM DE FORA, e não por esquecimento (medido em prod 2026-08-19):
     *
     *  · **Valor recorrente / MRR do negócio** — a cobrança recorrente vive em
     *    `rb_subscriptions`, que aponta pra `contacts` do biz=1 (a carteira do CRM), e NÃO
     *    existe FK ligando contato a `business`. Tentar casar por nome acerta 4 de 109.
     *    Mostrar o valor errado no drawer de um cliente é pior que não mostrar.
     *
     *  · **Uso contra o teto do pacote** — só 5 dos 75 pacotes têm limite definido
     *    (`user_count > 0`); nos outros 70 o teto é 0, que no UltimatePOS significa
     *    ILIMITADO. Barra de progresso contra ilimitado não informa nada.
     *
     * @return array<string, mixed>|null  null quando o id não existe
     */
    private function detalheDoNegocio(int $negocioId): ?array
    {
        // SUPERADMIN: leitura GLOBAL cross-tenant intencional (ADR 0093 §exceções).
        $b = DB::table('business')
            ->leftJoin('users AS u', 'u.id', '=', 'business.owner_id')
            ->where('business.id', $negocioId)
            ->select([
                'business.id',
                'business.name',
                'business.is_active',
                'business.created_at',
                'u.email AS dono_email',
                'u.contact_number AS dono_fone',
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS dono"),
                DB::raw('(SELECT bl.city FROM business_locations bl WHERE bl.business_id = business.id ORDER BY bl.id LIMIT 1) AS cidade'),
                DB::raw('(SELECT bl.mobile FROM business_locations bl WHERE bl.business_id = business.id ORDER BY bl.id LIMIT 1) AS fone_negocio'),
            ])
            ->first();

        if ($b === null) {
            return null;
        }

        // Histórico completo de licenciamento, do mais recente pro mais antigo.
        $historico = DB::table('subscriptions AS s')
            ->leftJoin('packages AS p', 'p.id', '=', 's.package_id')
            ->where('s.business_id', $negocioId)
            ->whereNull('s.deleted_at')
            ->orderByDesc('s.id')
            ->limit(12)
            ->get(['s.id', 's.status', 's.start_date', 's.end_date', 'p.name AS pacote'])
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'pacote' => $s->pacote,
                'inicio' => $s->start_date ? \Carbon::parse($s->start_date)->format('d/m/Y') : null,
                'fim' => $s->end_date ? \Carbon::parse($s->end_date)->format('d/m/Y') : null,
                'situacao' => $this->rotuloDeAssinatura($s->status, $s->end_date),
            ])
            ->all();

        // Uso contra o teto do pacote VIGENTE. Teto 0 = ILIMITADO no UltimatePOS — confirmado
        // por [W] em 2026-08-19 — e ilimitado NÃO vira barra de progresso: vira a palavra.
        // Medido no mesmo dia: só 5 dos 75 pacotes definem limite; nos outros 70 esta seção
        // mostra o consumo real sem teto, que continua sendo informação útil.
        $pacoteVigente = DB::table('subscriptions AS s')
            ->join('packages AS p', 'p.id', '=', 's.package_id')
            ->where('s.business_id', $negocioId)
            ->where('s.status', 'approved')
            ->orderByDesc('s.id')
            ->first(['p.user_count', 'p.location_count', 'p.product_count', 'p.invoice_count']);

        $uso = [
            [
                'rotulo' => 'Usuários',
                'usado' => DB::table('users')->where('business_id', $negocioId)->count(),
                'teto' => $pacoteVigente ? (int) $pacoteVigente->user_count : null,
            ],
            [
                'rotulo' => 'Locais',
                'usado' => DB::table('business_locations')->where('business_id', $negocioId)->count(),
                'teto' => $pacoteVigente ? (int) $pacoteVigente->location_count : null,
            ],
            [
                'rotulo' => 'Produtos',
                'usado' => DB::table('products')->where('business_id', $negocioId)->count(),
                'teto' => $pacoteVigente ? (int) $pacoteVigente->product_count : null,
            ],
        ];

        $ultimaVenda = DB::table('transactions')
            ->where('business_id', $negocioId)
            ->whereIn('type', ['sell'])
            ->max('transaction_date');

        return [
            'id' => (int) $b->id,
            'nome' => (string) $b->name,
            'cidade' => $b->cidade,
            'ativo' => (bool) $b->is_active,
            'criado' => $b->created_at ? \Carbon::parse($b->created_at)->format('d/m/Y') : null,
            'dono' => trim((string) ($b->dono ?? '')) ?: null,
            'email' => $b->dono_email,
            'fone_dono' => $b->dono_fone,
            'fone_negocio' => $b->fone_negocio,
            'ultima_venda' => $ultimaVenda ? \Carbon::parse($ultimaVenda)->format('d/m/Y') : null,
            'uso' => $uso,
            'historico' => $historico,
        ];
    }

    /**
     * Enum do banco → PT-BR. A tela NUNCA mostra o valor cru.
     *
     * Mesma tabela do RUNBOOK-dashboard §2 — `declined` é gravado por
     * OnCobrancaVencidaBloqueaSubscription quando a cobrança vence.
     */
    private function rotuloDeAssinatura(?string $status, $fim): string
    {
        if ($status === null) {
            return 'Sem assinatura';
        }

        return match ($status) {
            'approved' => ($fim && \Carbon::parse($fim)->isPast()) ? 'Vencida' : 'Ativa',
            'waiting' => 'Pendente',
            'declined' => 'Bloqueada',
            'expired' => 'Vencida',
            'cancelled' => 'Cancelada',
            default => 'Sem assinatura',
        };
    }

    private function filterTransactionDate($query, $filter, $operator)
    {
        if ($filter == 'today') {
            $today = \Carbon::today()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) = '$today') $operator 0");
        } elseif ($filter == 'yesterday') {
            $yesterday = \Carbon::yesterday()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$yesterday') $operator 0");
        } elseif ($filter == 'this_week') {
            $this_week = \Carbon::today()->subDays(7)->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$this_week') $operator 0");
        } elseif ($filter == 'this_month') {
            $this_month = \Carbon::today()->firstOfMonth()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$this_month') $operator 0");
        } elseif ($filter == 'last_month') {
            $last_month = \Carbon::today()->subDays(30)->firstOfMonth()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$last_month') $operator 0");
        } elseif ($filter == 'this_year') {
            $this_year = \Carbon::today()->firstOfYear()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$this_year') $operator 0");
        } elseif ($filter == 'last_year') {
            $last_year = \Carbon::today()->subYear()->firstOfYear()->format('Y-m-d');
            $query->whereRaw("(SELECT COUNT(id) FROM transactions as t WHERE t.business_id = business.id AND DATE(t.transaction_date) >= '$last_year') $operator 0");
        }

        return $query;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $currencies = $this->businessUtil->allCurrencies();
        $timezone_list = $this->businessUtil->allTimeZones();

        $accounting_methods = $this->businessUtil->allAccountingMethods();

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = __('business.months.'.$i);
        }

        $is_admin = true;

        $packages = Package::active()->orderby('sort_order')->pluck('name', 'id');
        $gateways = $this->_payment_gateways();

        return view('superadmin::business.create')
            ->with(compact(
                'currencies',
                'timezone_list',
                'accounting_methods',
                'months',
                'is_admin',
                'packages',
                'gateways'
            ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(StoreBusinessRequest $request)
    {
        // D8.b Wave 13 — StoreBusinessRequest valida payload + authorize() força permission `superadmin`.
        // Mantém abort(403) defensivo caso FormRequest seja bypassed em testes.
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            //Create owner.
            $owner_details = $request->only(['surname', 'first_name', 'last_name', 'username', 'email', 'password']);
            $owner_details['language'] = env('APP_LOCALE');

            $user = User::create_user($owner_details);

            $business_details = $request->only(['name', 'start_date', 'currency_id', 'tax_label_1', 'tax_number_1', 'tax_label_2', 'tax_number_2', 'time_zone', 'accounting_method', 'fy_start_month']);

            $business_location = $request->only(['name', 'country', 'state', 'city', 'zip_code', 'landmark', 'website', 'mobile', 'alternate_number']);

            //Create the business
            $business_details['owner_id'] = $user->id;
            if (! empty($business_details['start_date'])) {
                $business_details['start_date'] = $this->businessUtil->uf_date($business_details['start_date']);
            }

            //upload logo
            $logo_name = $this->businessUtil->uploadFile($request, 'business_logo', 'business_logos', 'image');
            if (! empty($logo_name)) {
                $business_details['logo'] = $logo_name;
            }

            //default enabled modules
            $business_details['enabled_modules'] = ['purchases', 'add_sale', 'pos_sale', 'stock_transfers', 'stock_adjustment', 'expenses'];

            //created_by
            $business_details['created_by'] = $request->session()->get('user.id');

            $business = $this->businessUtil->createNewBusiness($business_details);

            //Update user with business id
            $user->business_id = $business->id;
            $user->save();

            $this->businessUtil->newBusinessDefaultResources($business->id, $user->id);
            $new_location = $this->businessUtil->addLocation($business->id, $business_location);

            //create new permission with the new location
            Permission::create(['name' => 'location.'.$new_location->id]);

            $subscription_details = $request->only(['package_id', 'paid_via', 'payment_transaction_id']);

            //Add subscription if present
            if (! empty($subscription_details['package_id']) && ! empty($subscription_details['paid_via'])) {
                $subscription = $this->_add_subscription($business->id, $subscription_details['package_id'], $subscription_details['paid_via'], $subscription_details['payment_transaction_id'], $request->session()->get('user.id'), true);
            }

            DB::commit();

            //Module function to be called after after business is created
            if (config('app.env') != 'demo') {
                $this->moduleUtil->getModuleData('after_business_created', ['business' => $business]);
            }

            $output = ['success' => 1,
                'msg' => __('business.business_created_succesfully'),
            ];

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\BusinessController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            // LGPD D7.a — exception->getMessage() pode conter PII cross-tenant
            $this->_log_emergency_redacted($e, 'BusinessController@store');

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show($business_id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $business = Business::with(['currency', 'locations', 'subscriptions', 'owner'])->find($business_id);

        $created_id = $business->created_by;

        $created_by = ! empty($created_id) ? User::find($created_id) : null;

        return view('superadmin::business.show')
            ->with(compact('business', 'created_by'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit()
    {
        return view('superadmin::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request)
    {
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $notAllowed = $this->businessUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            //Check if logged in busines id is same as deleted business then not allowed.
            $business_id = request()->session()->get('user.business_id');
            if ($business_id == $id) {
                $output = ['success' => 0, 'msg' => __('superadmin.lang.cannot_delete_current_business')];

                return back()->with('status', $output);
            }

            DB::beginTransaction();

            //Delete related products & transactions.
            $products_id = Product::where('business_id', $id)->pluck('id')->toArray();
            if (! empty($products_id)) {
                VariationLocationDetails::whereIn('product_id', $products_id)->delete();
            }
            Transaction::where('business_id', $id)->delete();

            Business::where('id', $id)
                ->delete();

            DB::commit();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\BusinessController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            // LGPD D7.a — exception->getMessage() pode conter PII cross-tenant
            $this->_log_emergency_redacted($e, 'BusinessController@destroy');

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }

    /**
     * Changes the activation status of a business.
     *
     * @return Response
     */
    public function toggleActive(Request $request, $business_id, $is_active)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->businessUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        Business::where('id', $business_id)
            ->update(['is_active' => $is_active]);

        $output = ['success' => 1,
            'msg' => __('lang_v1.success'),
        ];

        return back()->with('status', $output);
    }

    /**
     * Shows user list for a particular business
     *
     * @return Response
     */
    public function usersList($business_id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $user_id = request()->session()->get('user.id');

            $users = User::where('business_id', $business_id)
                        ->where('id', '!=', $user_id)
                        ->where('is_cmmsn_agnt', 0)
                        ->select(['id', 'username',
                            DB::raw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name"), 'email', ]);

            return Datatables::of($users)
                ->addColumn(
                    'role',
                    function ($row) {
                        $role_name = $this->moduleUtil->getUserRoleName($row->id);

                        return $role_name;
                    }
                )
                ->addColumn(
                    'action',
                    '@can("user.update")
                        <a href="#" class="btn btn-xs btn-primary update_user_password" data-user_id="{{$id}}" data-user_name="{{$full_name}}"><i class="glyphicon glyphicon-edit"></i> @lang("superadmin::lang.update_password")</a>
                        &nbsp;
                        @if(!empty($username))
                        <a href="{{route("sign-in-as-user", $id)}}?save_current=true" class="btn btn-xs btn-success"><i class="fas fa-sign-in-alt"></i> @lang("lang_v1.login_as_username", ["username" => $username])</a>
                        @endif
                    @endcan'
                )
                ->filterColumn('full_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->removeColumn('id')
                ->rawColumns(['action'])
                ->make(true);
        }
    }

    /**
     * Updates user password from superadmin
     *
     * @return Response
     */
    public function updatePassword(UpdateBusinessPasswordRequest $request)
    {
        // D8.b Wave 13 — UpdateBusinessPasswordRequest valida user_id + min:8 + authorize().
        // Mantém abort(403) defensivo caso FormRequest seja bypassed em testes.
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $notAllowed = $this->businessUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            $user = User::findOrFail($request->input('user_id'));
            $user->password = Hash::make($request->input('password'));
            $user->save();

            //Send password update notification
            if ($this->moduleUtil->IsMailConfigured()) {
                $user->notify(new PasswordUpdateNotification($request->input('password')));
            }

            $output = ['success' => 1,
                'msg' => __('superadmin::lang.password_updated_successfully'),
            ];
        } catch (\Exception $e) {
            // LGPD D7.a — exception->getMessage() pode conter PII cross-tenant
            $this->_log_emergency_redacted($e, 'BusinessController@updatePassword');

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }
}
