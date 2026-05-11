<?php

namespace Modules\OficinaAuto\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

        // Global scope multi-tenant já filtra por business_id (ADR 0093)
        $vehicles = Vehicle::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->string('q') . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('plate', 'like', $term)
                        ->orWhere('secondary_plate', 'like', $term)
                        ->orWhere('chassis', 'like', $term);
                });
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get([
                'id', 'plate', 'secondary_plate', 'chassis', 'vehicle_type',
                'manufacture_year', 'model_year', 'color', 'mileage_at_entry',
                'contact_id', 'created_at',
            ]);

        return Inertia::render('OficinaAuto/Vehicles/Index', [
            'vehicles' => $vehicles,
            'filters'  => ['q' => $request->string('q')],
        ]);
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
