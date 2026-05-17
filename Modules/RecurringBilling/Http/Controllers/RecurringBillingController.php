<?php

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\RecurringBilling\Http\Presenters\SubscriptionIndexPresenter;
use Modules\RecurringBilling\Http\Requests\StoreAssinaturaRequest;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;

/**
 * Controller principal — Page Cobrança Recorrente (Inertia React).
 *
 * Refs: charter resources/js/Pages/RecurringBilling/Index.charter.md
 * Visual canon: prototipo-ui/prototipos/recurring/recurring-page.jsx
 * Skill `inertia-defer-default` Tier B: props caras via Inertia::defer.
 */
class RecurringBillingController extends Controller
{
    public function __construct(private readonly SubscriptionRepository $subscriptions)
    {
    }

    /**
     * GET /recurring-billing — Page Inertia 3-col Cowork.
     *
     * Props:
     *   - filters       (eager — state UI imediato)
     *   - tab           (eager — default 'assinaturas')
     *   - kpis          (defer — agregação que precisa allForKpis collection)
     *   - subscriptions (defer — paginação + presenter transform N rows)
     *   - plans         (defer — pra sidebar coluna 1 lista + drawer payload)
     */
    public function index(Request $request): InertiaResponse
    {
        $businessId = (int) session('user.business_id');
        $filters = [
            'status_visual' => $request->string('status', 'all')->toString(),
            'when'          => $request->string('when', 'any')->toString(),
            'busca'         => $request->string('q', '')->toString(),
        ];
        $tab = $request->string('tab', 'assinaturas')->toString();

        return Inertia::render('RecurringBilling/Index', [
            'filters' => $filters,
            'tab'     => $tab,

            'kpis' => Inertia::defer(function () use ($businessId) {
                return SubscriptionIndexPresenter::computeKpis(
                    $this->subscriptions->allForKpis($businessId)
                );
            }),

            'subscriptions' => Inertia::defer(function () use ($businessId, $filters) {
                $paginator = $this->subscriptions->paginatedForIndex($businessId, $filters, 50);

                return [
                    'data' => collect($paginator->items())
                        ->map(fn ($sub) => SubscriptionIndexPresenter::toDrawerPayload($sub))
                        ->values()
                        ->toArray(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page'    => $paginator->lastPage(),
                        'per_page'     => $paginator->perPage(),
                        'total'        => $paginator->total(),
                    ],
                ];
            }),

            'plans' => Inertia::defer(function () use ($businessId) {
                return Plan::query()
                    ->where('business_id', $businessId)
                    ->where('ativo', true)
                    ->orderBy('valor', 'desc')
                    ->get(['id', 'name', 'valor', 'ciclo', 'descricao_curta', 'fiscal_type'])
                    ->map(fn ($p) => [
                        'id'          => $p->id,
                        'name'        => $p->name,
                        'cycle'       => $p->ciclo,
                        'cycle_label' => match ($p->ciclo) {
                            'monthly'    => 'mensal',
                            'quarterly'  => 'trimestral',
                            'semiannual' => 'semestral',
                            'yearly'     => 'anual',
                            default      => $p->ciclo,
                        },
                        'price'       => (float) $p->valor,
                        'items'       => $p->descricao_curta,
                        'fiscal_type' => $p->fiscal_type,
                    ])
                    ->toArray();
            }),
        ]);
    }

    public function create()
    {
        return view('recurringbilling::create');
    }

    public function store(StoreAssinaturaRequest $request): RedirectResponse
    {
        //
    }

    public function show($id)
    {
        return view('recurringbilling::show');
    }

    public function edit($id)
    {
        return view('recurringbilling::edit');
    }

    public function update(Request $request, $id): RedirectResponse
    {
        //
    }

    public function destroy($id)
    {
        //
    }
}
