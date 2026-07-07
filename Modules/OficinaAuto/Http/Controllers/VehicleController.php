<?php

namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Illuminate\Http\JsonResponse;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Http\Requests\StoreVehicleRequest;
use Modules\OficinaAuto\Http\Requests\UpdateVehicleRequest;
use Modules\OficinaAuto\Services\PlacaLookup\PlacaLookupException;
use Modules\OficinaAuto\Services\VehicleLookupService;

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
        // closure D-14: por business, não muda com filtro q/status — pula no partial
        // reload (only: vehicles/filters). O reload pós-FSM pede 'kpis' e re-executa.
        $kpis = fn () => $this->buildKpis($hasFsmSchema);

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

    public function store(StoreVehicleRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        // D8 Security Wave 15: authorize() rodou no FormRequest (Spatie permission check).
        // abort_unless mantido como defense-in-depth caso FormRequest::authorize seja burlado.
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.vehicle.create'),
            403
        );

        // business_id é setado automaticamente pelo Model::creating hook (ADR 0093)
        $vehicle = Vehicle::create($request->validated());

        // ADR 0251 — quick-add da venda (QuickAddVehicleSheet) faz fetch direto (não
        // Inertia router) pra NÃO perder o draft da venda; espera JSON com o veículo
        // criado. Gate `wantsJson() && !X-Inertia` distingue do form Inertia normal
        // (que manda X-Requested-With mas Accept:text/html + header X-Inertia) — sem
        // isso o redirect do fluxo padrão quebraria.
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id'              => (int) $vehicle->id,
                    'plate'           => $vehicle->plate,
                    'secondary_plate' => $vehicle->secondary_plate,
                    'vehicle_type'    => $vehicle->vehicle_type,
                ],
            ]);
        }

        return redirect('/oficina-auto/veiculos/' . $vehicle->id)
            ->with('status', ['success' => 1, 'msg' => 'Veículo cadastrado.']);
    }

    /**
     * Consulta de placa (AJAX) — digita a placa e busca os dados do veículo.
     *
     * Endpoint JSON consumido pelo botão "Buscar" no form de cadastro
     * (resources/js/Pages/OficinaAuto/Vehicles/Create.tsx). Retorna SÓ dados
     * técnicos do veículo (charter Create v2) — NENHUM dado de proprietário é
     * devolvido nem armazenado (sem PII de terceiro). PII da placa nunca é logada.
     *
     * Multi-tenant Tier 0 (ADR 0093): cache do resultado é namespeada por
     * business_id da sessão dentro do VehicleLookupService.
     *
     * @see Modules\OficinaAuto\Services\VehicleLookupService
     */
    public function consultaPlaca(Request $request, VehicleLookupService $lookup): JsonResponse
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.vehicle.create'),
            403
        );

        $validated = $request->validate([
            'plate' => ['required', 'string', 'max:10'],
        ]);

        $plate = VehicleLookupService::normalizePlate($validated['plate']);

        if (! VehicleLookupService::isValidPlate($plate)) {
            return response()->json([
                'found'   => false,
                'message' => 'Placa inválida — use o formato ABC1234 (antiga) ou ABC1D23 (Mercosul).',
            ], 422);
        }

        $businessId = (int) (session('user.business_id') ?? session('business.id') ?? 0);

        try {
            $result = $lookup->lookup($plate, $businessId);
        } catch (PlacaLookupException $e) {
            // D7 LGPD: a exceção já é PII-safe (não carrega placa), mas redaciona por garantia.
            \Log::warning('[oficinaauto] consulta-placa falhou: ' . app(PiiRedactor::class)->redact($e->getMessage()));

            return response()->json([
                'found'   => false,
                'message' => 'Consulta de placa indisponível no momento. Preencha os dados manualmente.',
            ], 502);
        }

        if ($result === null) {
            return response()->json([
                'found'   => false,
                'message' => 'Nenhum dado encontrado para esta placa.',
            ]);
        }

        return response()->json([
            'found' => true,
            'data'  => $result->toArray(),
        ]);
    }

    public function show(Vehicle $vehicle): Response
    {
        // D8 Security Wave 15: Policy multi-tenant sameTenant() guard.
        $this->authorize('view', $vehicle);

        // Global scope garante isolamento — se vehicle.business_id != session, model binding falha (404)
        $vehicle->load('serviceOrders');

        return Inertia::render('OficinaAuto/Vehicles/Show', [
            'vehicle' => $vehicle,
        ]);
    }

    public function edit(Vehicle $vehicle): Response
    {
        // D8 Security Wave 15: Policy sameTenant guard.
        $this->authorize('update', $vehicle);

        return Inertia::render('OficinaAuto/Vehicles/Edit', [
            'vehicle'      => $vehicle,
            'vehicleTypes' => self::vehicleTypes(),
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        // D8 Security Wave 15: Policy multi-tenant + FormRequest validation.
        $this->authorize('update', $vehicle);

        $vehicle->update($request->validated());

        return redirect('/oficina-auto/veiculos/' . $vehicle->id)
            ->with('status', ['success' => 1, 'msg' => 'Veículo atualizado.']);
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        // D8 Security Wave 15: Policy multi-tenant sameTenant guard.
        $this->authorize('delete', $vehicle);

        try {
            $vehicle->delete();
        } catch (\Throwable $e) {
            // D7 LGPD: redaciona PII (placa/chassi/RENAVAM podem aparecer em FK errors) antes de logar.
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            return redirect('/oficina-auto/veiculos')
                ->with('status', ['success' => 0, 'msg' => 'Falha ao remover veículo.']);
        }

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
