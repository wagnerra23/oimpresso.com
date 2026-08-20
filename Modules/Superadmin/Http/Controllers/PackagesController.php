<?php

namespace Modules\Superadmin\Http\Controllers;

use App\System;
use App\Util\OtelHelper;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Support\RedactsPiiInLogs;

class PackagesController extends Controller
{
    use RedactsPiiInLogs;

    /**
     * All Utils instance.
     */
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
     * Grade comercial (`GET /superadmin/packages`) — onda SA-O4c.
     *
     * Era `superadmin::packages.index` (Blade/AdminLTE, tabela) e passa a Inertia com GRID DE
     * CARDS, como o F1 desenha. Não é gosto: um pacote tem 4 limites, 3 flags de visibilidade,
     * uma lista de módulos liberados e uma contagem de assinantes — em tabela isso vira 12
     * colunas, e o Blade resolvia escondendo metade.
     *
     * SUPERADMIN: `packages` é catálogo GLOBAL (a tabela não tem `business_id` e nunca terá) —
     * cross-tenant intencional, ADR 0093 §exceções.
     *
     * ⚠️ Esta onda é LEITURA. `store`/`update`/`destroy` não foram tocados, e o FormDrawer do
     * F1 (UC-SA-010/011) é a SA-O4d: ele escreve `price`, e o §"CÁLCULO DE VALOR ou ESTOQUE"
     * das proibicoes exige prova por 2 caminhos + antes→depois com [W] antes de aplicar. O
     * pré-flight desta onda mediu 3 achados no backend — estão no RUNBOOK §5, não consertados.
     *
     * @see memory/requisitos/Superadmin/RUNBOOK-pacotes.md
     */
    public function index(): InertiaResponse
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        return OtelHelper::spanBiz('superadmin.pacotes.index', function () {
            return Inertia::render('superadmin/Pacotes/Index', [
                'pacotes' => Inertia::defer(fn () => $this->pacotesPayload()),
            ]);
        }, ['component' => 'superadmin.pacotes.index']);
    }

    /**
     * Catálogo inteiro, um card por pacote.
     *
     * Sem paginação de propósito: a grade comercial é curta por natureza (é o que a empresa
     * vende) e o valor da tela é comparar os pacotes LADO A LADO. Paginar um catálogo de meia
     * dúzia de itens só esconderia a comparação.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pacotesPayload(): array
    {
        // Assinantes por pacote em UMA consulta, não uma por card: esta tela sempre lista o
        // catálogo inteiro, então contar por card seria N+1 garantido.
        //
        // A contagem é de assinaturas VIVAS OU HISTÓRICAS, não só vigentes: pacote com contrato
        // antigo apontando pra ele não pode ser excluído sem migrar, e é justamente a fila
        // histórica que prova isso.
        $assinantes = DB::table('subscriptions')
            ->whereNull('deleted_at')
            ->selectRaw('package_id, COUNT(*) AS total')
            ->groupBy('package_id')
            ->pluck('total', 'package_id');

        // Nome legível de cada módulo liberável, vindo dos DataControllers dos módulos
        // (`superadmin_package`). Sem isso o card mostraria a chave crua (`fiscal_module`).
        $rotulos = [];
        foreach ($this->moduleUtil->getModuleData('superadmin_package') as $grupo) {
            foreach ($grupo as $detalhe) {
                if (isset($detalhe['name'], $detalhe['label'])) {
                    $rotulos[$detalhe['name']] = $detalhe['label'];
                }
            }
        }

        return Package::orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Package $p) use ($assinantes, $rotulos) {
                $permissoes = (array) ($p->custom_permissions ?? []);

                return [
                    'id' => (int) $p->id,
                    'nome' => (string) $p->name,
                    'descricao' => (string) ($p->description ?? ''),
                    // Número puro: quem formata moeda é a tela. Nenhum literal em R$ no back.
                    'preco' => (float) $p->price,
                    'intervalo' => (string) $p->interval,
                    'intervalo_count' => (int) $p->interval_count,
                    'trial_dias' => (int) $p->trial_days,
                    // Os 4 limites vão CRUS. `0` = ilimitado (convenção do UltimatePOS, está no
                    // COMMENT da coluna) — quem escreve "ilimitado" é a tela, porque é ela que
                    // conhece o plural PT-BR. Traduzir aqui devolveria string onde a tela
                    // precisa de número pra decidir.
                    'locais' => (int) $p->location_count,
                    'usuarios' => (int) $p->user_count,
                    'produtos' => (int) $p->product_count,
                    'faturas' => (int) $p->invoice_count,
                    'ativo' => (bool) $p->is_active,
                    'privado' => (bool) $p->is_private,
                    'avulso' => (bool) $p->is_one_time,
                    // Só as chaves LIGADAS, já com rótulo humano. Chave sem rótulo conhecido
                    // cai no próprio nome — some da tela seria pior que aparecer feio.
                    'modulos' => collect($permissoes)
                        ->filter(fn ($v) => ! empty($v))
                        ->keys()
                        ->map(fn ($k) => (string) ($rotulos[$k] ?? $k))
                        ->values()
                        ->all(),
                    'assinantes' => (int) ($assinantes[$p->id] ?? 0),
                ];
            })
            ->all();
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

        $intervals = ['days' => __('lang_v1.days'), 'months' => __('lang_v1.months'), 'years' => __('lang_v1.years')];
        $currency = System::getCurrency();
        $permissions = $this->moduleUtil->getModuleData('superadmin_package');

        return view('superadmin::packages.create')
            ->with(compact('intervals', 'currency', 'permissions'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description', 'location_count', 'user_count', 'product_count', 'invoice_count', 'interval', 'interval_count', 'trial_days', 'price', 'sort_order', 'is_active', 'custom_permissions', 'is_private', 'is_one_time', 'enable_custom_link', 'custom_link',
                'custom_link_text', ]);

            $currency = System::getCurrency();

            $input['price'] = $this->businessUtil->num_uf($input['price'], $currency);
            $input['is_active'] = empty($input['is_active']) ? 0 : 1;
            $input['created_by'] = $request->session()->get('user.id');

            $input['is_private'] = empty($input['is_private']) ? 0 : 1;
            $input['is_one_time'] = empty($input['is_one_time']) ? 0 : 1;
            $input['enable_custom_link'] = empty($input['enable_custom_link']) ? 0 : 1;

            $input['custom_link'] = empty($input['enable_custom_link']) ? '' : $input['custom_link'];
            $input['custom_link_text'] = empty($input['enable_custom_link']) ? '' : $input['custom_link_text'];

            $package = new Package;
            $package->fill($input);
            $package->save();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            // LGPD D7.a — exception->getMessage() pode conter PII cross-tenant
            $this->logEmergencyRedacted($e, 'PackagesController');

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\PackagesController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show()
    {
        return view('superadmin::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        $packages = Package::where('id', $id)
                            ->first();

        $intervals = ['days' => __('lang_v1.days'), 'months' => __('lang_v1.months'), 'years' => __('lang_v1.years')];

        $permissions = $this->moduleUtil->getModuleData('superadmin_package', true);

        return view('superadmin::packages.edit')
               ->with(compact('packages', 'intervals', 'permissions'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $packages_details = $request->only(['name', 'id', 'description', 'location_count', 'user_count', 'product_count', 'invoice_count', 'interval', 'interval_count', 'trial_days', 'price', 'sort_order', 'is_active', 'custom_permissions', 'is_private', 'is_one_time', 'enable_custom_link', 'custom_link', 'custom_link_text']);

            $packages_details['is_active'] = empty($packages_details['is_active']) ? 0 : 1;
            $packages_details['custom_permissions'] = empty($packages_details['custom_permissions']) ? null : $packages_details['custom_permissions'];

            $packages_details['is_private'] = empty($packages_details['is_private']) ? 0 : 1;
            $packages_details['is_one_time'] = empty($packages_details['is_one_time']) ? 0 : 1;
            $packages_details['enable_custom_link'] = empty($packages_details['enable_custom_link']) ? 0 : 1;
            $packages_details['custom_link'] = empty($packages_details['enable_custom_link']) ? '' : $packages_details['custom_link'];
            $packages_details['custom_link_text'] = empty($packages_details['enable_custom_link']) ? '' : $packages_details['custom_link_text'];

            $package = Package::where('id', $id)
                            ->first();
            $package->fill($packages_details);
            $package->save();

            if (! empty($request->input('update_subscriptions'))) {
                $package_details = [
                    'location_count' => $package->location_count,
                    'user_count' => $package->user_count,
                    'product_count' => $package->product_count,
                    'invoice_count' => $package->invoice_count,
                    'name' => $package->name,
                ];
                if (! empty($package->custom_permissions)) {
                    foreach ($package->custom_permissions as $name => $value) {
                        $package_details[$name] = $value;
                    }
                }

                //Update subscription package details
                $subscriptions = Subscription::where('package_id', $package->id)
                                            ->whereDate('end_date', '>=', \Carbon::now())
                                            ->update(['package_details' => json_encode($package_details)]);
            }

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            // LGPD D7.a — exception->getMessage() pode conter PII cross-tenant
            $this->logEmergencyRedacted($e, 'PackagesController');

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\PackagesController::class, 'index'])
            ->with('status', $output);
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
            Package::where('id', $id)
                ->delete();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            // LGPD D7.a — exception->getMessage() pode conter PII cross-tenant
            $this->logEmergencyRedacted($e, 'PackagesController');

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\PackagesController::class, 'index'])
            ->with('status', $output);
    }
}
