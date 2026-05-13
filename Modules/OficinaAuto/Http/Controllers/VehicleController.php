<?php

namespace Modules\OficinaAuto\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * VehicleController — CRUD de veículos (Modules/OficinaAuto V0).
 *
 * Convenção UltimatePOS:
 * - Permissões Spatie: oficinaauto.vehicle.{view,create,update,delete}
 * - Multi-tenant: global scope em Vehicle filtra business_id automaticamente (ADR 0093)
 * - Inertia pages em resources/js/Pages/OficinaAuto/Vehicles/
 *
 * Rotas (Routes/web.php):
 *   GET    /oficina-auto/veiculos              → index
 *   GET    /oficina-auto/veiculos/create       → create
 *   POST   /oficina-auto/veiculos              → store
 *   GET    /oficina-auto/veiculos/{vehicle}    → show
 *   GET    /oficina-auto/veiculos/{vehicle}/edit → edit
 *   PUT    /oficina-auto/veiculos/{vehicle}    → update
 *   DELETE /oficina-auto/veiculos/{vehicle}    → destroy
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-001
 */
class VehicleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.vehicle.view'),
            403
        );

        // Global scope multi-tenant já filtra por business_id (ADR 0093) — todas
        // as queries abaixo herdam scope automaticamente.
        $statusFilter = $request->string('status')->toString() ?: 'all';
        $allowedStatuses = ['all', 'disponivel', 'locada', 'manutencao', 'atrasada'];
        if (! in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = 'all';
        }

        $search = trim((string) $request->string('q'));

        // Schema Wave 5 (Agent A) entrega coluna `current_status` enum + relation
        // `currentRental` (FK pra service_orders). Até essa migration rodar,
        // degradamos pra CRUD básico (sem KPIs nem filter por status) — fail-soft.
        $hasFsmSchema = Schema::hasColumn('vehicles', 'current_status');

        // KPIs — 4 contagens conforme demo Martinho (mockup demo-martinho-2026-05-13).
        $kpis = $this->buildKpis($hasFsmSchema);

        $query = Vehicle::query();

        if ($hasFsmSchema) {
            $query->with([
                'currentRental:id,vehicle_id,contact_id,entered_at,delivery_address,expected_return_date',
                'currentRental.contact:id,name',
            ]);

            // Filter por status
            if ($statusFilter === 'atrasada') {
                $query->where('current_status', 'locada')
                    ->whereHas('currentRental', function ($q) {
                        $q->whereDate('expected_return_date', '<', now());
                    });
            } elseif ($statusFilter !== 'all') {
                $query->where('current_status', $statusFilter);
            }
        }

        // Search livre por placa, vehicle_number ou nome do cliente atual
        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($w) use ($term, $hasFsmSchema) {
                $w->where('plate', 'like', $term)
                    ->orWhere('secondary_plate', 'like', $term);
                if ($hasFsmSchema && Schema::hasColumn('vehicles', 'vehicle_number')) {
                    $w->orWhere('vehicle_number', 'like', $term);
                }
                if ($hasFsmSchema) {
                    $w->orWhereHas('currentRental.contact', function ($qq) use ($term) {
                        $qq->where('name', 'like', $term);
                    });
                }
            });
        }

        if ($hasFsmSchema) {
            $query->orderByRaw("FIELD(current_status, 'locada', 'manutencao', 'disponivel', 'indisponivel')");
        }

        $vehicles = $query
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('OficinaAuto/Vehicles/Index', [
            'vehicles' => $vehicles,
            'kpis'     => $kpis,
            'filters'  => [
                'q'      => $search,
                'status' => $statusFilter,
            ],
        ]);
    }

    /**
     * KPIs counts pra header da tela de caçambas.
     *
     * Agent A (Wave 5) entrega coluna `current_status` enum + relation
     * `currentRental`. Até essa migration rodar, retorna zeros pra evitar
     * tela quebrada (fail-soft) — mockup mostra estado vazio aceitável.
     *
     * @return array{disponivel:int,locada:int,manutencao:int,atrasada:int,total:int}
     */
    protected function buildKpis(bool $hasFsmSchema): array
    {
        $total = (int) Vehicle::count();

        if (! $hasFsmSchema) {
            return [
                'disponivel' => 0,
                'locada'     => 0,
                'manutencao' => 0,
                'atrasada'   => 0,
                'total'      => $total,
            ];
        }

        $disponivel = (int) Vehicle::where('current_status', 'disponivel')->count();
        $locada     = (int) Vehicle::where('current_status', 'locada')->count();
        $manutencao = (int) Vehicle::where('current_status', 'manutencao')->count();
        $atrasada   = (int) Vehicle::where('current_status', 'locada')
            ->whereHas('currentRental', function ($q) {
                $q->whereDate('expected_return_date', '<', now());
            })
            ->count();

        return compact('disponivel', 'locada', 'manutencao', 'atrasada', 'total');
    }

    public function create(): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.vehicle.create'),
            403
        );

        return Inertia::render('OficinaAuto/Vehicles/Create', [
            'vehicleTypes' => self::vehicleTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.vehicle.create'),
            403
        );

        $validated = $request->validate([
            'plate'             => ['required', 'string', 'max:10'],
            'secondary_plate'   => ['nullable', 'string', 'max:10'],
            'chassis'           => ['nullable', 'string', 'max:30'],
            'secondary_chassis' => ['nullable', 'string', 'max:30'],
            'contact_id'        => ['nullable', 'integer'],
            'manufacture_year'  => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'model_year'        => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'renavam'           => ['nullable', 'string', 'max:11'],
            'vehicle_type'      => ['required', 'in:' . implode(',', array_keys(self::vehicleTypes()))],
            'engine'            => ['nullable', 'string', 'max:50'],
            'mileage_at_entry'  => ['nullable', 'integer', 'min:0'],
            'fuel_type'         => ['nullable', 'string', 'max:30'],
            'color'             => ['nullable', 'string', 'max:30'],
            'notes'             => ['nullable', 'string'],
            'legacy_id'         => ['nullable', 'string', 'max:20'],
        ]);

        // business_id é setado automaticamente pelo Model::creating hook (ADR 0093)
        $vehicle = Vehicle::create($validated);

        return redirect('/oficina-auto/veiculos/' . $vehicle->id)
            ->with('status', ['success' => 1, 'msg' => 'Veículo cadastrado.']);
    }

    public function show(Vehicle $vehicle): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.vehicle.view'),
            403
        );

        // Global scope garante isolamento — se vehicle.business_id != session, model binding falha (404)
        $vehicle->load('serviceOrders');

        return Inertia::render('OficinaAuto/Vehicles/Show', [
            'vehicle' => $vehicle,
        ]);
    }

    public function edit(Vehicle $vehicle): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.vehicle.update'),
            403
        );

        return Inertia::render('OficinaAuto/Vehicles/Edit', [
            'vehicle'      => $vehicle,
            'vehicleTypes' => self::vehicleTypes(),
        ]);
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.vehicle.update'),
            403
        );

        $validated = $request->validate([
            'plate'             => ['required', 'string', 'max:10'],
            'secondary_plate'   => ['nullable', 'string', 'max:10'],
            'chassis'           => ['nullable', 'string', 'max:30'],
            'secondary_chassis' => ['nullable', 'string', 'max:30'],
            'contact_id'        => ['nullable', 'integer'],
            'manufacture_year'  => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'model_year'        => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'renavam'           => ['nullable', 'string', 'max:11'],
            'vehicle_type'      => ['required', 'in:' . implode(',', array_keys(self::vehicleTypes()))],
            'engine'            => ['nullable', 'string', 'max:50'],
            'mileage_at_entry'  => ['nullable', 'integer', 'min:0'],
            'fuel_type'         => ['nullable', 'string', 'max:30'],
            'color'             => ['nullable', 'string', 'max:30'],
            'notes'             => ['nullable', 'string'],
        ]);

        $vehicle->update($validated);

        return redirect('/oficina-auto/veiculos/' . $vehicle->id)
            ->with('status', ['success' => 1, 'msg' => 'Veículo atualizado.']);
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.vehicle.delete'),
            403
        );

        $vehicle->delete();

        return redirect('/oficina-auto/veiculos')
            ->with('status', ['success' => 1, 'msg' => 'Veículo removido (soft delete).']);
    }

    /**
     * Tipos de veículo suportados (alinhado ENUM da migration vehicles).
     */
    public static function vehicleTypes(): array
    {
        return [
            'caminhao'              => 'Caminhão',
            'cavalo'                => 'Cavalo (truck-cabine)',
            'semi_reboque'          => 'Semi-reboque',
            'cacamba_estacionaria'  => 'Caçamba estacionária',
            'automovel'             => 'Automóvel',
            'motocicleta'           => 'Motocicleta',
            'outro'                 => 'Outro',
        ];
    }
}
