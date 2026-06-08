<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * ClienteVeiculosController — endpoint JSON pro sub-tab "Placas" do OssTab
 * drawer 760 (ADR 0179).
 *
 * Wagner 2026-05-27 origem: Daniela @ Martinho cadastrou Heinig + reclamou
 * que falta "Placas" como aba do cadastro abrindo veículos do cliente.
 * Já existia `VehiclesTab` em `Pages/Cliente/_show/` (página fullpage legada)
 * + `buildClienteVehiclesPaginator` privado em `ContactController` (Onda 1
 * PR D 2026-05-26). Faltava o endpoint JSON pro self-fetch do drawer.
 *
 * Endpoint:
 *   GET /cliente/{id}/veiculos -> JSON paginator
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 *   Contact::where('business_id', $bizId)->where('id', $id)->firstOrFail()
 *   garante 404 cross-tenant sem vazar existência.
 *
 * Permission gate: customer.view OR supplier.view OR view_own variantes.
 *
 * Visibility: rota só registrada se módulo OficinaAuto instalado (verificar
 * Routes/web.php — usar Module::has('OficinaAuto') guard ou similar pattern
 * canon do projeto).
 *
 * @see Modules\Crm\Http\Controllers\ClienteAutosaveController (pattern canon
 *      pra ler contact com multi-tenant scope + permission gate)
 * @see app\Http\Controllers\ContactController::buildClienteVehiclesPaginator
 *      (lógica original — replicada aqui pra desacoplar drawer de Show.tsx)
 * @see resources\js\Pages\Cliente\_drawer\oss\PlacasSubTab.tsx (consumer)
 */
class ClienteVeiculosController extends Controller
{
    /**
     * GET /cliente/{id}/veiculos
     *
     * Query params:
     *   q=texto (filtra por plate/secondary_plate/chassis LIKE)
     *   page=N  (default 1, perPage fixo 20)
     *
     * Response 200:
     *   {
     *     data: [VehicleItem...],
     *     total, current_page, last_page, from, to
     *   }
     *
     * Response 403/404 — sem permission OR cross-tenant.
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (! $user->can('customer.view')
            && ! $user->can('supplier.view')
            && ! $user->can('customer.view_own')
            && ! $user->can('supplier.view_own')) {
            return response()->json(['message' => 'Sem permissao'], 403);
        }

        $businessId = (int) $request->session()->get('user.business_id');
        if ($businessId <= 0) {
            return response()->json(['message' => 'Sessao sem business_id'], 403);
        }

        // Multi-tenant Tier 0 ADR 0093: scope explicito antes da query relacional.
        // firstOrFail garante 404 cross-tenant sem vazar existencia.
        $contact = Contact::where('business_id', $businessId)
            ->where('id', $id)
            ->first();
        if (! $contact) {
            return response()->json(['message' => 'Cliente nao encontrado'], 404);
        }

        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        // Vehicle model tem global scope business_id (ver Entities/Vehicle.php
        // linha 100+) mas reforcamos aqui defense-in-depth.
        $query = Vehicle::where('business_id', $businessId)
            ->where('contact_id', $contact->id);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('plate', 'like', "%{$q}%")
                    ->orWhere('secondary_plate', 'like', "%{$q}%")
                    ->orWhere('chassis', 'like', "%{$q}%");
            });
        }

        $paginator = $query->orderByDesc('id')->paginate(20, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($v) => [
                'id' => (int) $v->id,
                'plate' => (string) $v->plate,
                'secondary_plate' => $v->secondary_plate,
                'chassis' => $v->chassis,
                'manufacture_year' => $v->manufacture_year,
                'model_year' => $v->model_year,
                'renavam' => $v->renavam,
                'vehicle_type' => (string) ($v->vehicle_type ?? ''),
                'current_status' => (string) ($v->current_status ?? ''),
                'color' => $v->color,
                'fuel_type' => $v->fuel_type,
                'mileage_at_entry' => $v->mileage_at_entry,
                'notes' => $v->notes,
            ])->all(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ]);
    }
}
