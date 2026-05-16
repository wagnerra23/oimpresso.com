<?php

namespace Modules\OficinaAuto\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

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
     * Filtros aceitos:
     *  - ?status=locacao_ativa | manutencao_ativa | concluida_mes | atrasada | all
     *  - ?type=locacao | manutencao | all
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
        $hasVehicleNumber = Schema::hasColumn('vehicles', 'vehicle_number');
        $hasCapacityM3  = Schema::hasColumn('vehicles', 'capacity_m3');

        $statusFilter = $request->string('status')->toString();
        $typeFilter   = $request->string('type')->toString();
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
        $base->when($statusFilter !== '' && $statusFilter !== 'all', function ($qb) use ($statusFilter, $hasOrderType, $hasReturnDate) {
            switch ($statusFilter) {
                case 'locacao_ativa':
                    if ($hasOrderType) {
                        $qb->where('order_type', 'locacao');
                    }
                    $qb->whereNotIn('status', ['concluida', 'cancelada']);
                    break;
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
                    if ($hasOrderType) {
                        $qb->where('order_type', 'locacao');
                    }
                    $qb->whereNotIn('status', ['concluida', 'cancelada']);
                    if ($hasReturnDate) {
                        $qb->whereNotNull('expected_return_date')
                            ->whereDate('expected_return_date', '<', now()->toDateString());
                    } else {
                        // Fallback: sem expected_return_date usa expected_completion
                        $qb->whereNotNull('expected_completion')
                            ->whereDate('expected_completion', '<', now()->toDateString());
                    }
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

        // ROLLBACK Wave L/W7 PR #963: Inertia::defer quebrava Pages (initial render undefined).
        return Inertia::render('OficinaAuto/ServiceOrders/Index', [
            'orders'  => $base->paginate(25)->withQueryString(),
            'filters' => [
                'status' => $statusFilter,
                'type'   => $typeFilter,
                'q'      => $q,
            ],
            'kpis' => $this->buildServiceOrderKpisPayload($hasOrderType, $hasReturnDate),
            // schemaFlags: booleanos baratos (Schema::hasColumn cached) — mantém eager
            'schemaFlags' => [
                'has_order_type'      => $hasOrderType,
                'has_return_date'     => $hasReturnDate,
                'has_delivery_address' => $hasDeliveryAddr,
                'has_daily_rate'      => $hasDailyRate,
                'has_number'          => $hasNumber,
                'has_started_at'      => $hasStartedAt,
                'has_contact'         => $hasContact,
                'has_vehicle_number'  => $hasVehicleNumber,
                'has_capacity_m3'     => $hasCapacityM3,
            ],
        ]);
    }

    /**
     * KPIs Index — 4 COUNT queries separadas (não afetadas por filtros).
     *
     * Extraído pra helper + Inertia::defer no index() pra pular execução quando
     * partial reload `only:['orders']` não pede kpis (RUNBOOK-inertia-defer-pattern).
     */
    private function buildServiceOrderKpisPayload(bool $hasOrderType, bool $hasReturnDate): array
    {
        $kpiBase = fn () => ServiceOrder::query();

        if ($hasOrderType) {
            $locacoesAtivas = $kpiBase()
                ->where('order_type', 'locacao')
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->count();

            $manutencaoAtivas = $kpiBase()
                ->where('order_type', 'manutencao')
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->count();
        } else {
            // Fallback pré-Wave 5-A: tudo ainda não tem tipo, agrupa em "manutenção"
            $locacoesAtivas = 0;
            $manutencaoAtivas = $kpiBase()
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->count();
        }

        $concluidasMes = $kpiBase()
            ->where('status', 'concluida')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        if ($hasReturnDate && $hasOrderType) {
            $atrasadas = $kpiBase()
                ->where('order_type', 'locacao')
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->whereNotNull('expected_return_date')
                ->whereDate('expected_return_date', '<', now()->toDateString())
                ->count();
        } else {
            $atrasadas = $kpiBase()
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->whereNotNull('expected_completion')
                ->whereDate('expected_completion', '<', now()->toDateString())
                ->count();
        }

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

        return Inertia::render('OficinaAuto/ServiceOrders/Create', [
            'vehicles' => $vehicles,
            'statuses' => self::statuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.create'),
            403
        );

        $validated = $request->validate([
            'vehicle_id'          => ['required', 'integer', 'exists:vehicles,id'],
            'transaction_id'      => ['nullable', 'integer'],
            'mileage_at_service'  => ['nullable', 'integer', 'min:0'],
            'status'              => ['required', 'string', 'max:30'],
            'entered_at'          => ['nullable', 'date'],
            'expected_completion' => ['nullable', 'date'],
            'notes'               => ['nullable', 'string'],
        ]);

        if (empty($validated['entered_at'])) {
            $validated['entered_at'] = now();
        }

        $order = ServiceOrder::create($validated);

        // Audit trail LGPD (Art. 37) — log PII-redacted (delivery_address contém
        // logradouro+número+bairro do cliente em locações Martinho/caçamba).
        Log::info('[oficinaauto.service_order] criada', [
            'business_id' => $order->business_id,
            'order_id'    => $order->id,
            'vehicle_id'  => $order->vehicle_id,
            'user_id'     => auth()->id(),
            'payload'     => app(PiiRedactor::class)->redact(json_encode($validated, JSON_UNESCAPED_UNICODE) ?: ''),
        ]);

        return redirect('/oficina-auto/ordens-servico/' . $order->id)
            ->with('status', ['success' => 1, 'msg' => 'Ordem de Serviço criada.']);
    }

    public function show(Request $request, ServiceOrder $order)
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.view'),
            403
        );

        $order->load(['vehicle', 'contact:id,name,mobile']);

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
                'vehicle' => $order->vehicle ? [
                    'id'              => $order->vehicle->id,
                    'plate'           => $order->vehicle->plate,
                    'vehicle_number'  => $order->vehicle->vehicle_number ?? null,
                    'capacity_m3'     => $order->vehicle->capacity_m3 ?? null,
                ] : null,
                'contact' => $order->contact ? [
                    'id'   => $order->contact->id,
                    'name' => $order->contact->name,
                ] : null,
                'urls' => [
                    'show' => '/oficina-auto/ordens-servico/' . $order->id,
                    'edit' => '/oficina-auto/ordens-servico/' . $order->id . '/edit',
                ],
            ]);
        }

        return Inertia::render('OficinaAuto/ServiceOrders/Show', [
            'order' => $order,
        ]);
    }

    public function edit(ServiceOrder $order): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.update'),
            403
        );

        $vehicles = Vehicle::query()
            ->orderBy('plate')
            ->limit(500)
            ->get(['id', 'plate', 'secondary_plate', 'vehicle_type']);

        return Inertia::render('OficinaAuto/ServiceOrders/Edit', [
            'order'    => $order,
            'vehicles' => $vehicles,
            'statuses' => self::statuses(),
        ]);
    }

    public function update(Request $request, ServiceOrder $order): RedirectResponse
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.update'),
            403
        );

        $validated = $request->validate([
            'vehicle_id'          => ['required', 'integer', 'exists:vehicles,id'],
            'transaction_id'      => ['nullable', 'integer'],
            'mileage_at_service'  => ['nullable', 'integer', 'min:0'],
            'status'              => ['required', 'string', 'max:30'],
            'entered_at'          => ['nullable', 'date'],
            'expected_completion' => ['nullable', 'date'],
            'completed_at'        => ['nullable', 'date'],
            'delivered_at'        => ['nullable', 'date'],
            'notes'               => ['nullable', 'string'],
        ]);

        $order->update($validated);

        // Audit trail LGPD (Art. 37) — log PII-redacted antes de retornar.
        Log::info('[oficinaauto.service_order] atualizada', [
            'business_id' => $order->business_id,
            'order_id'    => $order->id,
            'user_id'     => auth()->id(),
            'payload'     => app(PiiRedactor::class)->redact(json_encode($validated, JSON_UNESCAPED_UNICODE) ?: ''),
        ]);

        return redirect('/oficina-auto/ordens-servico/' . $order->id)
            ->with('status', ['success' => 1, 'msg' => 'OS atualizada.']);
    }

    public function destroy(ServiceOrder $order): RedirectResponse
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.delete'),
            403
        );

        $order->delete();

        // Audit trail LGPD (Art. 37 + Art. 18 §6 — direito de eliminação).
        Log::info('[oficinaauto.service_order] removida (soft)', [
            'business_id' => $order->business_id,
            'order_id'    => $order->id,
            'user_id'     => auth()->id(),
        ]);

        return redirect('/oficina-auto/ordens-servico')
            ->with('status', ['success' => 1, 'msg' => 'OS removida (soft delete).']);
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
