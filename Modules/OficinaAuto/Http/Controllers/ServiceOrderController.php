<?php

namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Http\Requests\StoreServiceOrderRequest;
use Modules\OficinaAuto\Http\Requests\UpdateServiceOrderRequest;

/**
 * ServiceOrderController — CRUD de Ordens de Serviço (Modules/OficinaAuto V0).
 *
 * Convenção UltimatePOS:
 * - Permissões Spatie: oficinaauto.service_order.{view,create,update,delete}
 * - Multi-tenant: global scope em ServiceOrder filtra business_id automaticamente (ADR 0093)
 *
 * Status V0: string livre (default 'aberta'). FSM canônica entra em US-OFICINA-003.
 *
 * Rotas (Routes/web.php):
 *   GET    /oficina-auto/ordens-servico              → index
 *   GET    /oficina-auto/ordens-servico/create       → create
 *   POST   /oficina-auto/ordens-servico              → store
 *   GET    /oficina-auto/ordens-servico/{order}      → show
 *   GET    /oficina-auto/ordens-servico/{order}/edit → edit
 *   PUT    /oficina-auto/ordens-servico/{order}      → update
 *   DELETE /oficina-auto/ordens-servico/{order}      → destroy
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-001
 */
class ServiceOrderController extends Controller
{
    /**
     * Index dashboard de Ordens de Serviço (V0.5 — pré-reunião Martinho 13/maio).
     *
     * Combina locação + manutenção numa visão única com 4 KPIs + filtros + tabela rica.
     * Espelha layout de Vehicles Index (Wave 5-B) e mockup
     * memory/requisitos/OficinaAuto/demo-martinho-2026-05-13/mockup.html
     *
     * Schema::hasColumn fallback porque colunas Wave 5-A (order_type / delivery_address /
     * expected_return_date / daily_rate) podem não estar migradas ainda nessa branch.
     *
     * Filtros aceitos (locação erradicada — ADR 0265):
     *  - ?status=manutencao_ativa | concluida_mes | atrasada | all
     *    (atrasada = OS não-terminal com expected_completion vencida — atraso de reparo)
     *  - ?type=manutencao | mecanica | all
     *  - ?q=<busca em number/cliente/vehicle>
     */
    public function index(Request $request): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.view'),
            403
        );

        $hasOrderType   = Schema::hasColumn('service_orders', 'order_type');
        $hasReturnDate  = Schema::hasColumn('service_orders', 'expected_return_date');
        $hasDeliveryAddr = Schema::hasColumn('service_orders', 'delivery_address');
        $hasDailyRate   = Schema::hasColumn('service_orders', 'daily_rate');
        $hasNumber      = Schema::hasColumn('service_orders', 'number');
        $hasStartedAt   = Schema::hasColumn('service_orders', 'started_at');
        $hasContact     = Schema::hasColumn('service_orders', 'contact_id');
        $hasCurrentStage = Schema::hasColumn('service_orders', 'current_stage_id');
        $hasVehicleNumber = Schema::hasColumn('vehicles', 'vehicle_number');
        $hasCapacityM3  = Schema::hasColumn('vehicles', 'capacity_m3');

        $statusFilter = $request->string('status')->toString();
        $typeFilter   = $request->string('type')->toString();
        $stageFilter  = $request->string('stage')->toString();
        $q            = $request->string('q')->toString();

        // ──────── Base query (multi-tenant via global scope) ────────
        $vehicleCols = ['id', 'plate', 'vehicle_type'];
        if ($hasVehicleNumber) {
            $vehicleCols[] = 'vehicle_number';
        }
        if ($hasCapacityM3) {
            $vehicleCols[] = 'capacity_m3';
        }

        $base = ServiceOrder::query()->with([
            'vehicle:' . implode(',', $vehicleCols),
        ]);

        if ($hasContact) {
            $base->with('contact:id,name');
        }

        // ──────── Filter status (semântico) ────────
        $base->when($statusFilter !== '' && $statusFilter !== 'all', function ($qb) use ($statusFilter, $hasOrderType) {
            switch ($statusFilter) {
                case 'manutencao_ativa':
                    if ($hasOrderType) {
                        $qb->where('order_type', 'manutencao');
                    }
                    $qb->whereNotIn('status', ['concluida', 'cancelada']);
                    break;
                case 'concluida_mes':
                    $qb->where('status', 'concluida')
                        ->whereMonth('updated_at', now()->month)
                        ->whereYear('updated_at', now()->year);
                    break;
                case 'atrasada':
                    // Atraso de REPARO (ADR 0265 — locação erradicada): OS não-terminal
                    // com expected_completion vencida. Espelha buildServiceOrderKpisPayload.
                    $qb->whereNotIn('status', ['concluida', 'cancelada'])
                        ->whereNotNull('expected_completion')
                        ->whereDate('expected_completion', '<', now()->toDateString());
                    break;
                default:
                    // status livre legado: aberta/em_servico/etc
                    $qb->where('status', $statusFilter);
            }
        });

        // ──────── Filter type ────────
        $base->when($typeFilter !== '' && $typeFilter !== 'all' && $hasOrderType, function ($qb) use ($typeFilter) {
            $qb->where('order_type', $typeFilter);
        });

        // ──────── Filter stage (Gap #3 — chips por current_stage_id estilo Linear) ────────
        // UI manda stage.key (ex 'disponivel'), backend resolve pra stage_id via JOIN
        // com sale_process_stages dos processos cacamba_*. Multi-tenant Tier 0 (ADR 0093)
        // preservado via global scope ServiceOrder + filter business_id em SaleProcess.
        $base->when($stageFilter !== '' && $stageFilter !== 'all' && $hasCurrentStage, function ($qb) use ($stageFilter) {
            $businessId = (int) (session('user.business_id') ?? session('business.id') ?? 0);
            $stageIds = \App\Domain\Fsm\Models\SaleProcessStage::query()
                ->whereHas('process', function ($p) use ($businessId) {
                    $p->withoutGlobalScope(\Modules\Jana\Scopes\ScopeByBusiness::class)
                        ->where('business_id', $businessId)
                        ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao']);
                })
                ->where('key', $stageFilter)
                ->pluck('id');
            $qb->whereIn('current_stage_id', $stageIds);
        });

        // ──────── Search ────────
        $base->when($q !== '', function ($qb) use ($q, $hasNumber, $hasContact) {
            $like = '%' . $q . '%';
            $qb->where(function ($w) use ($like, $q, $hasNumber, $hasContact) {
                if ($hasNumber) {
                    $w->orWhere('number', 'like', $like);
                }
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                }
                $w->orWhereHas('vehicle', function ($v) use ($like) {
                    $v->where('plate', 'like', $like);
                });
                if ($hasContact) {
                    $w->orWhereHas('contact', function ($c) use ($like) {
                        $c->where('name', 'like', $like);
                    });
                }
            });
        });

        // ──────── Sort: ativas primeiro, atrasadas no topo das ativas ────────
        // CASE: cancelada/concluida = 2, atrasada = 0, ativa normal = 1
        if ($hasReturnDate) {
            $base->orderByRaw(
                "CASE
                    WHEN status IN ('concluida', 'cancelada') THEN 2
                    WHEN expected_return_date IS NOT NULL AND expected_return_date < CURDATE() THEN 0
                    ELSE 1
                END ASC"
            );
        } else {
            $base->orderByRaw(
                "CASE WHEN status IN ('concluida', 'cancelada') THEN 1 ELSE 0 END ASC"
            );
        }
        $base->orderByDesc('id');

        // Wave 26 D6 — Inertia::defer pro kpis payload (RUNBOOK-inertia-defer-pattern):
        // - orders eager (sempre tem; resposta partial reload `only:['orders']` espera direto)
        // - kpis defer (4 COUNT queries — pula quando UI já tem cached/partial reload)
        // - schemaFlags eager (booleanos baratos Schema::hasColumn cached)
        // - filters eager (UI state)
        // Rollback Wave L/W7 PR #963 estudado: defer só aplicado em payload que NÃO é target de partial reload + tem custo > 50ms.
        return Inertia::render('OficinaAuto/ServiceOrders/Index', [
            'orders'  => $base->paginate(25)->withQueryString(),
            'filters' => [
                'status' => $statusFilter,
                'type'   => $typeFilter,
                'stage'  => $stageFilter,
                'q'      => $q,
            ],
            'kpis' => Inertia::defer(fn () => $this->buildServiceOrderKpisPayload($hasOrderType)),
            // Gap #3 estado-da-arte FSM screen — chips por stage estilo Linear.
            // Defer porque é COUNT por stage (~8 stages × cacamba_* processos).
            'stages' => Inertia::defer(fn () => $this->buildStagesPayload($hasCurrentStage)),
            // schemaFlags: booleanos baratos (Schema::hasColumn cached) — mantém eager
            'schemaFlags' => [
                'has_order_type'      => $hasOrderType,
                'has_return_date'     => $hasReturnDate,
                'has_delivery_address' => $hasDeliveryAddr,
                'has_daily_rate'      => $hasDailyRate,
                'has_number'          => $hasNumber,
                'has_started_at'      => $hasStartedAt,
                'has_contact'         => $hasContact,
                'has_current_stage'   => $hasCurrentStage,
                'has_vehicle_number'  => $hasVehicleNumber,
                'has_capacity_m3'     => $hasCapacityM3,
            ],
        ]);
    }

    /**
     * Board (Kanban) das OS de mecânica — port do protótipo Cowork do carro ([W] 2026-06-02).
     *
     * Renderiza um quadro data-driven a partir das etapas REAIS do processo FSM
     * `oficina_mecanica_os` (Recepção → Diagnóstico → Aguardando aprovação →
     * Aguardando peças → Em execução → Pronto p/ retirar). NÃO é o Kanban de caçamba
     * (ProducaoOficina) — aquele é vertical legado/equívoco (ADR 0194).
     *
     * Colunas = stages não-terminais do processo, ordenadas por sort_order (o quadro
     * se adapta sozinho se o seeder mudar — sem hardcode de coluna). O drag entre
     * colunas dispara FSM via ServiceOrderFsmActionController@execute (canon — nunca
     * UPDATE direto em current_stage_id).
     *
     * Multi-tenant Tier 0 (ADR 0093): ServiceOrder global scope + SaleProcess filtrado
     * por business_id. Frontend só lê.
     *
     * @see resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx
     * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php (oficina_mecanica_os)
     */
    public function board(Request $request): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.view'),
            403
        );

        $businessId = (int) (session('user.business_id') ?? session('business.id') ?? 0);
        $q = $request->string('q')->toString();
        // Filtros do quadro (mesmo eixo Box/Mecânico que o RichSheet mostra).
        // box_label/assigned_user_id chegaram na Wave 2.1 (US-OFICINA-027) — guard de schema.
        $hasResourceSchema = Schema::hasColumn('service_orders', 'box_label');
        $mecanico = $request->integer('mecanico') ?: null;
        $box      = $request->string('box')->toString();

        // Stages do processo real do carro, ordenados (multi-tenant via business_id).
        $stages = \App\Domain\Fsm\Models\SaleProcessStage::query()
            ->whereHas('process', function ($p) use ($businessId) {
                $p->withoutGlobalScope(\Modules\Jana\Scopes\ScopeByBusiness::class)
                    ->where('business_id', $businessId)
                    ->where('key', 'oficina_mecanica_os');
            })
            ->orderBy('sort_order')
            ->get(['id', 'key', 'name', 'color', 'sort_order', 'is_initial', 'is_terminal']);

        $boardStages = $stages->where('is_terminal', false)->values();
        $initialStageKey = optional($stages->firstWhere('is_initial', true))->key
            ?? optional($boardStages->first())->key;
        $stageKeyById = $stages->pluck('key', 'id');
        $boardStageIds = $boardStages->pluck('id');

        // Membership do quadro: OS em pipeline (stage de board) OU recém-criadas sem
        // pipeline (order_type=mecanica, current_stage_id null) — estas caem na coluna
        // inicial com in_pipeline=false (drag desabilitado até abrir a OS e iniciar).
        // Closure reusada pra montar as opções de filtro sobre o MESMO universo.
        $boardMembership = function ($w) use ($boardStageIds) {
            $w->whereIn('current_stage_id', $boardStageIds)
                ->orWhere(function ($w2) {
                    $w2->where('order_type', 'mecanica')->whereNull('current_stage_id');
                });
        };

        // Opções dos selects (Box/Mecânico) — distinct sobre o universo do quadro, ANTES
        // de aplicar box/mecanico (senão o select encolheria pra própria seleção).
        [$boxOptions, $mecanicoOptions] = $this->buildBoardFilterOptions(
            $boardMembership,
            $businessId,
            $hasResourceSchema,
        );

        $orders = ServiceOrder::query()
            ->with([
                'vehicle:id,plate,vehicle_type',
                'contact:id,name',
                'assignedUser:id,first_name,last_name,surname',
                'dviInspectionItems',
                'dviInspectionItems.arquivos',
            ])
            ->where($boardMembership)
            // Filtro por mecânico (assigned_user_id) e/ou box (texto livre) — query (canon
            // charter), não client-side. Guard de schema pra ambiente pré-Wave 2.1.
            ->when($hasResourceSchema && $mecanico !== null, fn ($qb) => $qb->where('assigned_user_id', $mecanico))
            ->when($hasResourceSchema && $box !== '', fn ($qb) => $qb->where('box_label', $box))
            ->when($q !== '', function ($qb) use ($q) {
                $like = '%' . $q . '%';
                $qb->where(function ($w) use ($like, $q) {
                    if (ctype_digit($q)) {
                        $w->orWhere('id', (int) $q);
                    }
                    $w->orWhereHas('vehicle', fn ($v) => $v->where('plate', 'like', $like))
                        ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', $like));
                });
            })
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        // Agrupa cards por stage_key (null → coluna inicial).
        $cardsByStage = [];
        foreach ($boardStages as $s) {
            $cardsByStage[$s->key] = [];
        }
        foreach ($orders as $order) {
            $currentStageId = $order->getAttribute('current_stage_id');
            $stageKey = $currentStageId !== null
                ? ($stageKeyById[$currentStageId] ?? null)
                : $initialStageKey;
            // Stage fora do board (terminal ou de outro processo) — ignora no quadro.
            if ($stageKey === null || ! array_key_exists($stageKey, $cardsByStage)) {
                continue;
            }
            $cardsByStage[$stageKey][] = $this->shapeBoardCard($order, $currentStageId !== null);
        }

        $columns = $boardStages->map(fn ($s) => [
            'key'   => $s->key,
            'name'  => $s->name,
            'color' => $s->color,
            'cards' => $cardsByStage[$s->key] ?? [],
            'count' => count($cardsByStage[$s->key] ?? []),
        ])->all();

        return Inertia::render('OficinaAuto/ServiceOrders/Board', [
            'columns'      => $columns,
            'kpis'         => $this->buildBoardKpis($columns),
            'process_seeded' => $boardStages->isNotEmpty(),
            'filters'      => ['q' => $q, 'mecanico' => $mecanico, 'box' => $box !== '' ? $box : null],
            'filterOptions' => ['boxes' => $boxOptions, 'mecanicos' => $mecanicoOptions],
        ]);
    }

    /**
     * Opções dos selects de filtro do quadro — boxes (texto livre) + mecânicos distintos
     * entre as OS do universo do quadro (closure $membership). Mesmo eixo Box/Mecânico que
     * o ServiceOrderRichSheet mostra. Global scope ServiceOrder já filtra business_id;
     * mecânicos resolvidos com scope explícito por business (User não tem global scope).
     *
     * @param  \Closure  $membership  filtro de pertencimento ao quadro (reusa o do board)
     * @return array{0: list<string>, 1: list<array{id:int, nome:string}>}
     */
    private function buildBoardFilterOptions(\Closure $membership, int $businessId, bool $hasResourceSchema): array
    {
        if (! $hasResourceSchema) {
            return [[], []];
        }

        $boxes = ServiceOrder::query()
            ->where($membership)
            ->whereNotNull('box_label')
            ->where('box_label', '!=', '')
            ->distinct()
            ->orderBy('box_label')
            ->pluck('box_label')
            ->values()
            ->all();

        $mechanicIds = ServiceOrder::query()
            ->where($membership)
            ->whereNotNull('assigned_user_id')
            ->distinct()
            ->pluck('assigned_user_id')
            ->all();

        $mecanicos = [];
        if (! empty($mechanicIds)) {
            $users = \App\User::query()
                ->whereIn('id', $mechanicIds)
                ->when($businessId > 0, fn ($qb) => $qb->where('business_id', $businessId))
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'surname', 'username']);

            foreach ($users as $u) {
                $nome = trim((string) ($u->first_name ?? '') . ' ' . (string) ($u->last_name ?? ''));
                if ($nome === '') {
                    $nome = (string) ($u->surname ?? $u->username ?? '');
                }
                $mecanicos[] = ['id' => (int) $u->id, 'nome' => $nome !== '' ? $nome : '—'];
            }
        }

        return [$boxes, $mecanicos];
    }

    /**
     * Shape de um card do board (1 OS). Aplica as modificações [W]-aceitas:
     *  - foto REAL (1ª foto de item DVI) ou null (frontend esconde o thumb);
     *  - contador DVI x/y (itens decididos pelo cliente / total) + nº de críticos;
     *  - dados de placa/cliente/mecânico/prazo pro card denso.
     *
     * @return array<string, mixed>
     */
    private function shapeBoardCard(ServiceOrder $order, bool $inPipeline): array
    {
        $dvi = $order->dviInspectionItems;
        $dviTotal = $dvi->count();
        // x/y = itens já decididos pelo cliente (aprovado/rejeitado) sobre o total.
        $dviDone = $dvi->whereIn('client_decision', ['approved', 'rejected'])->count();
        $dviCritico = $dvi->where('severity', 'critico')->count();

        // Foto REAL: 1º arquivo anexado a qualquer item DVI; fallback photo_url legado.
        // Nunca placeholder de texto ([W] mod #1) — null deixa o frontend esconder o thumb.
        $thumbUrl = null;
        foreach ($dvi as $item) {
            $arquivo = $item->arquivos->first();
            $arquivoUrl = $arquivo?->getAttribute('display_url');
            if ($arquivoUrl) {
                $thumbUrl = (string) $arquivoUrl;
                break;
            }
            if ($item->photo_url) {
                $thumbUrl = (string) $item->photo_url;
                break;
            }
        }

        $mechanic = $order->assignedUser;
        $mechanicName = $mechanic
            ? trim(($mechanic->first_name ?? '') . ' ' . ($mechanic->last_name ?? ($mechanic->surname ?? '')))
            : null;
        $mechanicInitials = $mechanicName !== null && $mechanicName !== ''
            ? mb_strtoupper(mb_substr($mechanicName, 0, 1) . (mb_strpos($mechanicName, ' ') !== false ? mb_substr(mb_strrchr($mechanicName, ' ') ?: '', 1, 1) : ''))
            : null;

        $expected = $order->expected_completion;
        $isOverdue = $expected !== null
            && ! in_array($order->status, ['concluida', 'cancelada', 'entregue'], true)
            && $expected->isPast();

        return [
            'id'                => (int) $order->id,
            'number'            => 'OS-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
            'in_pipeline'       => $inPipeline,
            'plate'             => $order->vehicle?->getAttribute('plate'),
            'vehicle_type'      => $order->vehicle?->getAttribute('vehicle_type'),
            'cliente_nome'      => $order->contact?->getAttribute('name'),
            'thumb_url'         => $thumbUrl,
            'dvi_done'          => $dviDone,
            'dvi_total'         => $dviTotal,
            'dvi_critico'       => $dviCritico,
            'valor'             => (float) $order->total_items,
            'mechanic_name'     => $mechanicName ?: null,
            'mechanic_initials' => $mechanicInitials ?: null,
            'entered_at'        => $order->entered_at?->toIso8601String(),
            'expected_completion' => $expected?->toIso8601String(),
            'is_overdue'        => $isOverdue,
            'notes'             => $order->notes,
            'urls'              => [
                'show' => '/oficina-auto/ordens-servico/' . $order->id,
                'edit' => '/oficina-auto/ordens-servico/' . $order->id . '/edit',
            ],
        ];
    }

    /**
     * KPIs compactos do board (densidade @1280 — [W] mod #3). Derivados das colunas
     * já montadas (zero query extra).
     *
     * @param  array<int, array{key:string, count:int, cards:array<int,array<string,mixed>>}>  $columns
     * @return array<string, int>
     */
    private function buildBoardKpis(array $columns): array
    {
        $byKey = [];
        $total = 0;
        $atrasadas = 0;
        foreach ($columns as $col) {
            $byKey[$col['key']] = $col['count'];
            $total += $col['count'];
            foreach ($col['cards'] as $card) {
                if (! empty($card['is_overdue'])) {
                    $atrasadas++;
                }
            }
        }

        return [
            'total'                => $total,
            'aguardando_aprovacao' => $byKey['aguardando_aprovacao'] ?? 0,
            'aguardando_pecas'     => $byKey['aguardando_pecas'] ?? 0,
            'em_execucao'          => $byKey['em_execucao'] ?? 0,
            'pronto_retirada'      => $byKey['pronto_retirada'] ?? 0,
            'atrasadas'            => $atrasadas,
        ];
    }

    /**
     * Stages payload pros chips de filtro (Gap #3 estado-da-arte FSM screen).
     *
     * Retorna lista de stages dos processos cacamba_locacao + cacamba_manutencao
     * do business atual com contador de OS em cada stage. Multi-tenant Tier 0
     * (ADR 0093) via global scope ServiceOrder + filter business_id em SaleProcess.
     *
     * @return list<array{key:string, name:string, color:string|null, count:int, is_terminal:bool, process_key:string}>
     */
    private function buildStagesPayload(bool $hasCurrentStage): array
    {
        if (! $hasCurrentStage) {
            return [];
        }

        $businessId = (int) (session('user.business_id') ?? session('business.id') ?? 0);

        $stages = \App\Domain\Fsm\Models\SaleProcessStage::query()
            ->whereHas('process', function ($p) use ($businessId) {
                $p->withoutGlobalScope(\Modules\Jana\Scopes\ScopeByBusiness::class)
                    ->where('business_id', $businessId)
                    ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao']);
            })
            ->with(['process' => function ($p) {
                $p->withoutGlobalScope(\Modules\Jana\Scopes\ScopeByBusiness::class);
            }])
            ->orderBy('sort_order')
            ->get();

        // 1 query bulk: counts por stage_id (multi-tenant via global scope ServiceOrder)
        $countsByStageId = ServiceOrder::query()
            ->whereIn('current_stage_id', $stages->pluck('id'))
            ->selectRaw('current_stage_id, COUNT(*) as total')
            ->groupBy('current_stage_id')
            ->pluck('total', 'current_stage_id');

        return $stages->map(fn ($stage) => [
            'key'         => $stage->key,
            'name'        => $stage->name,
            'color'       => $stage->color,
            'count'       => (int) ($countsByStageId[$stage->id] ?? 0),
            'is_terminal' => (bool) $stage->is_terminal,
            'process_key' => $stage->process->key,
        ])->all();
    }

    /**
     * KPIs Index — 4 COUNT queries separadas (não afetadas por filtros).
     *
     * Extraído pra helper + Inertia::defer no index() pra pular execução quando
     * partial reload `only:['orders']` não pede kpis (RUNBOOK-inertia-defer-pattern).
     */
    private function buildServiceOrderKpisPayload(bool $hasOrderType): array
    {
        $kpiBase = fn () => ServiceOrder::query();

        // Locação erradicada (ADR 0265): KPI mantida em 0 só pelo contrato do payload
        // — o frontend (ServiceOrders/Index) ainda consome a chave. A remoção visual do
        // card/chip "Locações ativas" é dívida F3 (RUNBOOK-erradicacao-locacao.md P5).
        $locacoesAtivas = 0;

        if ($hasOrderType) {
            $manutencaoAtivas = $kpiBase()
                ->where('order_type', 'manutencao')
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->count();
        } else {
            // Fallback pré-Wave 5-A: tudo ainda não tem tipo, agrupa em "manutenção"
            $manutencaoAtivas = $kpiBase()
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->count();
        }

        $concluidasMes = $kpiBase()
            ->where('status', 'concluida')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        // Atraso de REPARO (ADR 0265 — locação erradicada): OS não-terminal com
        // expected_completion vencida. Mesma regra do filtro ?status=atrasada.
        $atrasadas = $kpiBase()
            ->whereNotIn('status', ['concluida', 'cancelada'])
            ->whereNotNull('expected_completion')
            ->whereDate('expected_completion', '<', now()->toDateString())
            ->count();

        return [
            'locacoes_ativas'   => $locacoesAtivas,
            'manutencao_ativas' => $manutencaoAtivas,
            'concluidas_mes'    => $concluidasMes,
            'atrasadas'         => $atrasadas,
        ];
    }

    public function create(): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.create'),
            403
        );

        $vehicles = Vehicle::query()
            ->orderBy('plate')
            ->limit(500)
            ->get(['id', 'plate', 'secondary_plate', 'vehicle_type']);

        // Clientes (donos dos caminhões de terceiros) pro combobox do Create — charter
        // exige (sem cliente o gate de aprovação WhatsApp não tem destinatário). Sweep
        // ADR 0265: Create.tsx renderiza o campo só quando a prop `contacts` chega.
        // Multi-tenant Tier 0 (ADR 0093): business_id EXPLÍCITO + scopes Contact canon
        // (onlyCustomers/active). Contact não tem global scope (pattern UltimatePOS).
        $businessId = (int) (session('user.business_id') ?? session('business.id') ?? 0);
        $contacts = \App\Contact::where('contacts.business_id', $businessId)
            ->onlyCustomers()
            ->active()
            ->orderBy('contacts.name')
            ->limit(500)
            ->get(['contacts.id', 'contacts.name']);

        return Inertia::render('OficinaAuto/ServiceOrders/Create', [
            'vehicles' => $vehicles,
            'statuses' => self::statuses(),
            'contacts' => $contacts,
        ]);
    }

    public function store(StoreServiceOrderRequest $request): RedirectResponse
    {
        // D8 Security Wave 15: FormRequest authorize() + abort_unless defense-in-depth.
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.create'),
            403
        );

        $validated = $request->validated();

        if (empty($validated['entered_at'])) {
            $validated['entered_at'] = now();
        }

        $order = ServiceOrder::create($validated);

        // UC-07/UC-11 (casos.md Produção/Oficina): a OS recém-criada é o "documento vivo"
        // do veículo no kanban. Sem este vínculo o card fica "sem OS" (bucket disponivel não
        // tem fallback V3 em ProducaoOficinaController::loadRentalFallbacks) e o drawer rico
        // não abre — bug pego pelo E2E UC-11 (run 27273605033). Só vincula se o veículo está
        // LIVRE (não clobbera OS ativa de outro fluxo). Global scope = mesmo tenant (Tier 0).
        $vehicle = Vehicle::find($order->vehicle_id);
        if ($vehicle !== null && $vehicle->current_rental_id === null) {
            $vehicle->update(['current_rental_id' => $order->id]);
        }

        return redirect('/oficina-auto/ordens-servico/' . $order->id)
            ->with('status', ['success' => 1, 'msg' => 'Ordem de Serviço criada.']);
    }

    public function show(Request $request, ServiceOrder $order)
    {
        // D8 Security Wave 15: Policy multi-tenant sameTenant guard.
        // Causa raiz #25 (2026-05-20): ServiceOrderController extendia Illuminate\Routing\Controller
        // (sem trait AuthorizesRequests) — $this->authorize() não existia. Fixed: extends
        // App\Http\Controllers\Controller (projeto canon que já usa AuthorizesRequests).
        $this->authorize('view', $order);

        // ADR 0192 — Integração Vendas × Oficina A1 KB-9.75. Carrega Transaction
        // derivada (criada pelo ServiceOrderObserver na transição status='concluida'
        // · PR #1530) pra renderizar VendaDerivadaCard no drawer. Eager 1 query FK
        // barata — multi-tenant Tier 0 herdado via belongsTo + global scope Transaction.
        $order->load([
            'vehicle',
            'contact:id,name,mobile',
            'transaction:id,business_id,invoice_no,final_total,transaction_date',
            // Wave 2.3 US-OFICINA-027 — drawer ServiceOrderRichSheet seção PEÇAS & MÃO DE OBRA
            'items',
            // Wave 2.1 US-OFICINA-027 — drawer KV grid modo manutenção (Mecânico)
            'assignedUser:id,first_name,last_name,surname',
            // US-OFICINA-040 — itens DVI pra seção "Vistoria → orçamento" no Show
            'dviInspectionItems',
            // F3 OS-V2-1 — fotos do laudo OS-level (Fotos & Laudo) pro drawer + print
            'arquivos',
        ]);

        // Accept-aware: drawer ServiceOrderSheet faz fetch JSON via header.
        // Hotfix Wave 7+ — drawer chamava /oficina-auto/service-orders/{id} esperando
        // JSON mas show() só retornava Inertia HTML (HTTP 404 percebido por drawer).
        if ($request->wantsJson()) {
            return response()->json([
                'id'                    => $order->id,
                'number'                => 'OS-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
                'status'                => $order->status,
                'order_type'            => $order->order_type,
                'delivery_address'      => $order->delivery_address,
                'expected_return_date'  => $order->expected_return_date,
                'expected_completion'   => $order->expected_completion,
                'daily_rate'            => $order->daily_rate,
                'dias_locacao'          => $order->dias_locacao ?? 0,
                'valor_receber'         => $order->valor_receber ?? 0,
                'is_overdue'            => $order->is_overdue ?? false,
                'entered_at'            => $order->entered_at,
                'started_at'            => $order->entered_at,
                'completed_at'          => $order->completed_at,
                'notes'                 => $order->notes,
                // Wave 2.1 US-OFICINA-027 — drawer modo manutenção (sub-vertical 4 ADR 0194)
                'mileage_at_service'    => $order->mileage_at_service,
                'box_label'             => $order->box_label,
                'assigned_user'         => $order->assignedUser ? [
                    'id'   => $order->assignedUser->id,
                    // UltimatePOS canon: surname + first_name + last_name (mesmo pattern User::getUserFullNameAttribute)
                    'name' => trim(
                        ($order->assignedUser->surname ?? '') . ' ' .
                        ($order->assignedUser->first_name ?? '') . ' ' .
                        ($order->assignedUser->last_name ?? '')
                    ),
                ] : null,
                'vehicle' => $order->vehicle ? [
                    'id'                => $order->vehicle->id,
                    'plate'             => $order->vehicle->plate,
                    'vehicle_number'    => $order->vehicle->vehicle_number ?? null,
                    'vehicle_type'      => $order->vehicle->vehicle_type ?? null,
                    'capacity_m3'       => $order->vehicle->capacity_m3 ?? null,
                    'model_year'        => $order->vehicle->model_year ?? null,
                    'manufacture_year'  => $order->vehicle->manufacture_year ?? null,
                    'color'             => $order->vehicle->color ?? null,
                ] : null,
                'contact' => $order->contact ? [
                    'id'     => $order->contact->id,
                    'name'   => $order->contact->name,
                    'mobile' => $order->contact->mobile,
                ] : null,
                // Wave 2.3 US-OFICINA-027 — items lançados na OS (peça + mão-obra + terceiro)
                // alimentam seção "PEÇAS & MÃO DE OBRA" do drawer ServiceOrderRichSheet.
                // Observer ::computeFinalTotal soma valor_total destes pra Transaction.final_total.
                'items' => $order->items->map(fn ($item) => [
                    'id'             => $item->id,
                    'tipo'           => $item->tipo,
                    'descricao'      => $item->descricao,
                    'quantidade'     => (float) $item->quantidade,
                    'valor_unitario' => (float) $item->valor_unitario,
                    'valor_total'    => (float) $item->valor_total,
                    'product_id'     => $item->product_id,
                    'notes'          => $item->notes,
                ])->values()->all(),
                'items_total' => (float) $order->total_items,
                // F3 OS-V2-3 — estado do gate de aprovação (none/pending/approved/declined)
                // + timestamps. O drawer (DviInlineEditor · DviGateFoot) deriva a barra de
                // 4 estados DESTE payload — nunca de simulação client-side. `total` espelha o
                // total recomendado da DVI (atenção+crítico), o número que o cliente aprovou.
                'approval' => [
                    'state'        => $order->approval_state,
                    'total'        => (float) $order->dvi_breakdown['total_recomendado'],
                    'requested_at' => $order->approval_requested_at?->toIso8601String(),
                    'decided_at'   => $order->approval_decided_at?->toIso8601String(),
                    'decision'     => $order->approval_decision,
                ],
                // F3 OS-V2-2 — itens DVI (Vistoria Digital) pra o semáforo inline editável
                // do drawer ServiceOrderRichSheet (DviInlineEditor). Mesmo shape do branch
                // Inertia (Show), + sort_order pra ordenação estável. `budget_item_id` (em
                // metadata) sinaliza item já convertido em linha de orçamento.
                'dvi_items' => $order->dviInspectionItems
                    ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                    ->map(fn (OaInspectionItem $dvi) => [
                        'id'                => (int) $dvi->id,
                        'categoria'         => (string) $dvi->categoria,
                        'descricao'         => (string) $dvi->descricao,
                        'severity'          => (string) $dvi->severity,
                        'recomendacao'      => $dvi->recomendacao,
                        'valor_recomendado' => $dvi->valor_recomendado !== null ? (float) $dvi->valor_recomendado : null,
                        'sort_order'        => (int) $dvi->sort_order,
                        'budget_item_id'    => is_array($dvi->metadata) ? ($dvi->metadata['budget_item_id'] ?? null) : null,
                    ])->values()->all(),
                // F3 OS-V2-1 — fotos do laudo OS-level (Fotos & Laudo) pra o drawer.
                // `label` = original_name (legenda editável no lightbox). display_url
                // é signed quando bucket=sensitive (defesa em profundidade).
                'laudo_photos' => $order->arquivos
                    ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                    ->map(function ($a) {
                        // closure sem tipo no param + @var: relação HasArquivos é MorphMany
                        // sem generic (Larastan tipa Model, não Arquivo — dívida US-ARQ-TYPE).
                        /** @var \Modules\Arquivos\Entities\Arquivo $a */
                        return [
                            'id'          => (int) $a->id,
                            'label'       => (string) ($a->original_name ?? ''),
                            'mime_type'   => (string) $a->mime_type,
                            'size_bytes'  => (int) $a->size_bytes,
                            'display_url' => (string) $a->display_url,
                            'created_at'  => $a->created_at?->toIso8601String(),
                        ];
                    })->values()->all(),
                // ADR 0192 · V0 core shape (Onda 5). FASE B (items_list / items_summary /
                // fiscal NF-e) fica pra wave futura — exige join sell_lines + NfeBrasil
                // que já existe no equivalente Modules/Repair/ProducaoOficinaController
                // (`buildVendaDerivadaPayload`). Quando trouxer pra OficinaAuto, extrair
                // helper compartilhado pra App\Services\VendaDerivadaPayloadService.
                'venda_derivada' => $order->transaction
                    ? $this->shapeVendaDerivada($order->transaction)
                    : null,
                'urls' => [
                    'show' => '/oficina-auto/ordens-servico/' . $order->id,
                    'edit' => '/oficina-auto/ordens-servico/' . $order->id . '/edit',
                ],
            ]);
        }

        // Wave 5 US-OFICINA-005-bis — Show.tsx seção "Itens da OS" consome
        // `order.items[]` (peças + mão-obra + terceiros). Items já foram eager-loaded
        // acima (linha `$order->load([..., 'items', ...])`); aqui só serializamos
        // o shape consumido pelo frontend (mesmo formato do JSON branch).
        $itemsPayload = $order->items->map(fn ($item) => [
            'id'             => $item->id,
            'tipo'           => $item->tipo,
            'descricao'      => $item->descricao,
            'quantidade'     => (float) $item->quantidade,
            'valor_unitario' => (float) $item->valor_unitario,
            'valor_total'    => (float) $item->valor_total,
            'product_id'     => $item->product_id,
            'notes'          => $item->notes,
        ])->values()->all();

        // US-OFICINA-040 — itens da vistoria DVI pra seção "Vistoria → orçamento".
        // `budget_item_id` (em metadata) sinaliza item já convertido em linha de orçamento.
        $dviPayload = $order->dviInspectionItems->map(fn (OaInspectionItem $dvi) => [
            'id'                => (int) $dvi->id,
            'categoria'         => (string) $dvi->categoria,
            'descricao'         => (string) $dvi->descricao,
            'severity'          => (string) $dvi->severity,
            'recomendacao'      => $dvi->recomendacao,
            'valor_recomendado' => $dvi->valor_recomendado !== null ? (float) $dvi->valor_recomendado : null,
            'budget_item_id'    => is_array($dvi->metadata) ? ($dvi->metadata['budget_item_id'] ?? null) : null,
        ])->values()->all();

        return Inertia::render('OficinaAuto/ServiceOrders/Show', [
            'order' => array_merge($order->toArray(), [
                'items'     => $itemsPayload,
                'dvi_items' => $dviPayload,
            ]),
        ]);
    }

    public function edit(ServiceOrder $order): Response
    {
        // D8 Security Wave 15: Policy sameTenant guard.
        $this->authorize('update', $order);

        $vehicles = Vehicle::query()
            ->orderBy('plate')
            ->limit(500)
            ->get(['id', 'plate', 'secondary_plate', 'vehicle_type']);

        // Wave 5 US-OFICINA-005-bis — Edit.tsx tem seção inline "Itens da OS" idêntica
        // ao Show.tsx. Eager-load items + serializa shape consumido pelo componente
        // compartilhado ServiceOrderItemRow.
        $order->load('items');
        $itemsPayload = $order->items->map(fn ($item) => [
            'id'             => $item->id,
            'tipo'           => $item->tipo,
            'descricao'      => $item->descricao,
            'quantidade'     => (float) $item->quantidade,
            'valor_unitario' => (float) $item->valor_unitario,
            'valor_total'    => (float) $item->valor_total,
            'product_id'     => $item->product_id,
            'notes'          => $item->notes,
        ])->values()->all();

        return Inertia::render('OficinaAuto/ServiceOrders/Edit', [
            'order'    => array_merge($order->toArray(), [
                'items' => $itemsPayload,
            ]),
            'vehicles' => $vehicles,
            'statuses' => self::statuses(),
        ]);
    }

    public function update(UpdateServiceOrderRequest $request, ServiceOrder $order): RedirectResponse
    {
        // D8 Security Wave 15: Policy multi-tenant + FormRequest validation.
        $this->authorize('update', $order);

        $order->update($request->validated());

        return redirect('/oficina-auto/ordens-servico/' . $order->id)
            ->with('status', ['success' => 1, 'msg' => 'OS atualizada.']);
    }

    /**
     * US-OFICINA-041 — Enviar orçamento pro cliente aprovar (gate de aprovação).
     *
     * Delta do protótipo Cowork "Nova OS" (card "Aprovação do cliente"): o mecânico
     * dispara o pedido de aprovação com 1 clique. Reusa o pipeline AUTOMÁTICO já
     * existente — transicionar status → `orcamento` faz o `ServiceOrderObserver`
     * despachar o `EnviarLinkAprovacaoWhatsappJob` (link público + PIN, US-OFICINA-014).
     *
     * A execução não inicia até o cliente aprovar (status vira `aprovada` via
     * AprovacaoOsController público). Gate visual no Show reflete o estado.
     *
     * Defesa em profundidade: Policy update(ServiceOrder) (sameTenant ADR 0093).
     */
    public function enviarAprovacao(ServiceOrder $order): RedirectResponse
    {
        $this->authorize('update', $order);

        // Estados terminais/já-aprovados não reenviam aprovação.
        if (in_array($order->status, ['aprovada', 'concluida', 'entregue', 'cancelada'], true)) {
            return back()->with('status', [
                'success' => 0,
                'msg'     => 'OS já está aprovada ou finalizada.',
            ]);
        }

        // F3 OS-V2-3 — distingue 1º envio de RE-envio ("Cobrar" / "Revisar e reenviar").
        // O ServiceOrderObserver só dispara o WhatsApp quando o status MUDA pra orcamento;
        // num re-envio (já em orcamento) precisamos disparar manualmente + limpar a chave
        // de idempotência de 7d do Job pra forçar um novo envio.
        $jaEmOrcamento = $order->status === 'orcamento';

        // Carimba o gate: pending (requested_at) + zera decisão anterior (caso reenvio
        // pós-declined → volta pra pending). status → orcamento (Observer envia WhatsApp).
        $order->forceFill([
            'status'                => 'orcamento',
            'approval_requested_at' => now(),
            'approval_decided_at'   => null,
            'approval_decision'     => null,
        ])->save();

        if ($jaEmOrcamento) {
            \Illuminate\Support\Facades\Cache::forget("oficina:approval_dispatched:{$order->id}");
            \Modules\OficinaAuto\Jobs\EnviarLinkAprovacaoWhatsappJob::dispatch(
                (int) $order->business_id,
                (int) $order->id,
            );
        }

        return back()->with('status', [
            'success' => 1,
            'msg'     => 'Orçamento enviado ao cliente para aprovação (WhatsApp).',
        ]);
    }

    public function destroy(ServiceOrder $order): RedirectResponse
    {
        // D8 Security Wave 15: Policy multi-tenant sameTenant guard.
        $this->authorize('delete', $order);

        try {
            $order->delete();
        } catch (\Throwable $e) {
            // D7 LGPD: redaciona PII (endereço entrega/contact pode aparecer em FK errors) antes de logar.
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            return redirect('/oficina-auto/ordens-servico')
                ->with('status', ['success' => 0, 'msg' => 'Falha ao remover OS.']);
        }

        return redirect('/oficina-auto/ordens-servico')
            ->with('status', ['success' => 1, 'msg' => 'OS removida (soft delete).']);
    }

    /**
     * Shape mínimo do payload `venda_derivada` consumido pelo VendaDerivadaCard
     * shared (resources/js/Components/shared/VendaDerivadaCard.tsx).
     *
     * V0 core (Onda 5 ADR 0192): id + invoice_no + final_total + transaction_date.
     * Items breakdown + badge fiscal NF-e ficam pra wave futura (paridade com
     * Modules/Repair `buildVendaDerivadaPayload`).
     *
     * Multi-tenant Tier 0 (ADR 0093): Transaction já vem scopada por business_id
     * via belongsTo + global scope herdado. Frontend só lê.
     */
    private function shapeVendaDerivada(\App\Transaction $t): array
    {
        return [
            'id'               => (int) $t->id,
            'invoice_no'       => (string) ($t->invoice_no ?? $t->id),
            'final_total'      => (float) $t->final_total,
            'transaction_date' => $t->transaction_date
                ? (is_string($t->transaction_date)
                    ? substr($t->transaction_date, 0, 10)
                    : $t->transaction_date->toDateString())
                : null,
        ];
    }

    /**
     * Gap 3 — Imprimir OS PDF profissional A4 nota-fiscal-like (US-OFICINA-037).
     *
     * Espelha pattern `SellPosController::printInvoice` (Sells legacy) — AJAX-only
     * (404 sem X-Requested-With) + retorna `{success:1, receipt:{html_content,
     * print_title}}`. Frontend `printServiceOrder.ts` injeta `html_content` em
     * IFRAME oculto + dispara `window.print()` (cross-origin IFRAME compat).
     *
     * Multi-tenant Tier 0 [ADR 0093]: Route Model Binding já respeita global scope
     * de ServiceOrder, mas defensive guard explícito (ADR 0093 §"Defense in depth")
     * — abort 404 cross-tenant antes mesmo do render. Cliente final do Martinho
     * sub-vertical 4 (CNAE 4520 mecânica pesada) leva papel pra ressarcir
     * transportadora 3ª/seguradora — não pode vazar dado de outra biz NUNCA.
     *
     * @see resources/views/oficina_auto/print/service_order.blade.php (template A4)
     * @see resources/js/Lib/printServiceOrder.ts (helper IFRAME mirror printSaleReceipt)
     * @see memory/sessions/2026-05-26-plano-gap-3-imprimir-os-pdf-profissional.md
     */
    public function printInvoice(Request $request, ServiceOrder $order)
    {
        // AJAX-only (espelha SellPosController::printInvoice §1928) — render direto
        // via browser sem X-Requested-With retorna 404 (não AppShellV2 vazado).
        if (! $request->ajax()) {
            abort(404);
        }

        // D8 Security: defensive guard cross-tenant + permission (ADR 0093).
        // Route Model Binding já filtrou via global scope, mas dupla-checa antes
        // do render pra fechar qualquer brecha futura (ex: superadmin com session
        // de outra biz).
        $businessId = (int) ($request->session()->get('user.business_id')
            ?? $request->session()->get('business.id') ?? 0);
        abort_unless((int) $order->business_id === $businessId, 404);

        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.view'),
            403
        );

        try {
            $order->load([
                'vehicle',
                'contact:id,name,mobile,tax_number,address_line_1,city,state',
                'items',
                'dviInspectionItems',
                'assignedUser:id,first_name,last_name,surname',
                // F3 OS-V2-1 — fotos do laudo OS-level entram na folha A4 ("Fotos da vistoria")
                'arquivos',
            ]);

            $business = \App\Business::find($businessId);
            $location = $business?->locations()->first();

            $orderNumber = 'OS-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);

            $htmlContent = view('oficina_auto.print.service_order', [
                'order'    => $order,
                'business' => $business,
                'location' => $location,
                'orderNumber' => $orderNumber,
                'generatedAt' => now(),
            ])->render();

            return response()->json([
                'success' => 1,
                'receipt' => [
                    'html_content' => $htmlContent,
                    'print_title'  => $orderNumber,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::emergency('OficinaAuto printInvoice: File:' . $e->getFile()
                . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage()
                . ' Trace:' . substr($e->getTraceAsString(), 0, 800));

            $payload = [
                'success' => 0,
                'msg'     => 'Não foi possível gerar a impressão da OS.',
            ];
            if (config('app.debug')) {
                $payload['_debug'] = [
                    'class'   => get_class($e),
                    'message' => $e->getMessage(),
                    'file'    => basename($e->getFile()),
                    'line'    => $e->getLine(),
                ];
            }

            return response()->json($payload, 500);
        }
    }

    /**
     * Status livre V0 (FSM canônica chega em US-OFICINA-003 / ADR 0129).
     */
    public static function statuses(): array
    {
        return [
            'aberta'       => 'Aberta',
            'orcamento'    => 'Em Orçamento',
            'aprovada'     => 'Aprovada',
            'em_servico'   => 'Em Serviço',
            'em_producao'  => 'Em Produção',
            'concluida'    => 'Concluída',
            'entregue'     => 'Entregue',
            'cancelada'    => 'Cancelada',
        ];
    }
}
