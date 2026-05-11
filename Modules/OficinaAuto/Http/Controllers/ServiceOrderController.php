<?php

namespace Modules\OficinaAuto\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
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
    public function index(Request $request): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.view'),
            403
        );

        $orders = ServiceOrder::query()
            ->with('vehicle:id,plate,vehicle_type')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return Inertia::render('OficinaAuto/ServiceOrders/Index', [
            'orders'  => $orders,
            'filters' => ['status' => $request->string('status')],
        ]);
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

        return redirect('/oficina-auto/ordens-servico/' . $order->id)
            ->with('status', ['success' => 1, 'msg' => 'Ordem de Serviço criada.']);
    }

    public function show(ServiceOrder $order): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.service_order.view'),
            403
        );

        $order->load('vehicle');

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
