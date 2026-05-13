<?php

namespace Modules\OficinaAuto\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * ProducaoOficinaController — Kanban estado das caçambas (Martinho 13/maio 2026).
 *
 * Tela "Produção · Oficina" — espelha 1:1 protótipo Cowork canônico
 * `prototipo-ui/prototipos/producao-oficina/F1.html` adaptado pra caçambas:
 *
 * 5 colunas Kanban (workflow real Martinho — locação caçamba estacionária):
 *  1. disponivel        — caçambas no pátio prontas pra locar
 *  2. locada            — em poder do cliente, no prazo
 *  3. aguardando        — locada + expected_return_date < hoje (overdue)
 *                         destaque amber accent (ação imediata)
 *  4. manutencao        — oficina, peça quebrada
 *  5. pronta            — current_status=indisponivel (acabou manut., voltando pátio)
 *
 * Filtros:
 *  - capacidade: all | 3 | 5 | 7 (m³)
 *  - q: busca livre (placa / vehicle_number / cliente atual)
 *
 * KPIs inline filter bar:
 *  - total caçambas
 *  - atrasadas
 *  - aguardando recolhimento (alias semântico de atrasadas)
 *
 * Permission: oficinaauto.vehicle.view (mesma da listagem CRUD).
 *
 * Multi-tenant Tier 0 (ADR 0093): global scope em Vehicle filtra business_id
 * automaticamente — controller não precisa filtrar manualmente.
 *
 * @see prototipo-ui/prototipos/producao-oficina/F1.html (canon visual)
 * @see Modules/OficinaAuto/Http/Controllers/VehicleController.php (pattern)
 * @see memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md
 */
class ProducaoOficinaController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.vehicle.view'),
            403
        );

        // Schema fail-soft — antes de Wave 5 (Agent A) entregar coluna
        // current_status, mostramos tela com kanban vazio em vez de quebrar.
        $hasFsmSchema = Schema::hasColumn('vehicles', 'current_status');

        $capacidade = $this->normalizeCapacidade($request->string('capacidade')->toString());
        $search     = trim((string) $request->string('q'));

        $vehicles = $this->loadVehicles($hasFsmSchema, $capacidade, $search);

        $kanban = $this->groupIntoKanban($vehicles);
        $kpis   = $this->buildKpis($kanban);

        return Inertia::render('OficinaAuto/ProducaoOficina/Index', [
            'kanban'  => $kanban,
            'kpis'    => $kpis,
            'filters' => [
                'capacidade' => $capacidade,
                'q'          => $search,
            ],
        ]);
    }

    /**
     * Normaliza filtro de capacidade. Aceita 'all', '3', '5', '7'. Default 'all'.
     */
    protected function normalizeCapacidade(string $raw): string
    {
        $allowed = ['all', '3', '5', '7'];
        return in_array($raw, $allowed, true) ? $raw : 'all';
    }

    /**
     * Carrega vehicles aplicando filters (capacidade + q) — global scope
     * já filtra business_id automaticamente.
     *
     * @return Collection<int, Vehicle>
     */
    protected function loadVehicles(bool $hasFsmSchema, string $capacidade, string $search): Collection
    {
        $query = Vehicle::query();

        if ($hasFsmSchema) {
            $query->with([
                'currentRental:id,vehicle_id,contact_id,entered_at,delivery_address,expected_return_date,daily_rate,status',
                'currentRental.contact:id,name,mobile',
            ]);
        }

        if ($capacidade !== 'all') {
            $query->where('capacity_m3', (int) $capacidade);
        }

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($w) use ($term, $hasFsmSchema) {
                $w->where('plate', 'like', $term)
                  ->orWhere('secondary_plate', 'like', $term);
                if (Schema::hasColumn('vehicles', 'vehicle_number')) {
                    $w->orWhere('vehicle_number', 'like', $term);
                }
                if ($hasFsmSchema) {
                    $w->orWhereHas('currentRental.contact', function ($qq) use ($term) {
                        $qq->where('name', 'like', $term);
                    });
                }
            });
        }

        // Ordenação intra-coluna: locadas primeiro (mais ativas no topo),
        // depois disponíveis, depois manut. — kanban agrupa por status depois.
        if ($hasFsmSchema) {
            $query->orderByRaw("FIELD(current_status, 'locada', 'manutencao', 'disponivel', 'indisponivel')");
        }

        return $query->orderByDesc('id')->get();
    }

    /**
     * Agrupa vehicles em 5 colunas Kanban + projeta payload mínimo pro frontend
     * (evita expor PII desnecessária + reduz JSON Inertia).
     *
     * Ordem de prioridade pro mapping:
     *  - locada + overdue  → 'aguardando' (não 'locada')
     *  - locada + ok       → 'locada'
     *  - manutencao        → 'manutencao'
     *  - disponivel        → 'disponivel'
     *  - indisponivel + qq → 'pronta' (caçamba acabou manut., voltando pátio)
     *
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function groupIntoKanban(Collection $vehicles): array
    {
        $groups = [
            'disponivel'  => [],
            'locada'      => [],
            'aguardando'  => [],
            'manutencao'  => [],
            'pronta'      => [],
        ];

        foreach ($vehicles as $v) {
            $status = $v->current_status ?? 'indisponivel';
            $rental = $v->relationLoaded('currentRental') ? $v->currentRental : null;
            $isOverdue = $rental ? (bool) $rental->is_overdue : false;

            $bucket = match (true) {
                $status === 'locada' && $isOverdue       => 'aguardando',
                $status === 'locada'                     => 'locada',
                $status === 'manutencao'                 => 'manutencao',
                $status === 'disponivel'                 => 'disponivel',
                $status === 'indisponivel'               => 'pronta',
                default                                  => 'disponivel',
            };

            $groups[$bucket][] = $this->projectVehicleCard($v, $rental, $isOverdue);
        }

        return $groups;
    }

    /**
     * Payload mínimo por card — só o essencial pro Kanban renderizar.
     * Drawer faz fetch completo via /oficina-auto/service-orders/{id} (existing).
     *
     * @return array<string, mixed>
     */
    protected function projectVehicleCard(Vehicle $v, $rental, bool $isOverdue): array
    {
        return [
            'id'                 => $v->id,
            'plate'              => $v->plate,
            'vehicle_number'     => $v->vehicle_number ?? null,
            'capacity_m3'        => $v->capacity_m3 !== null ? (float) $v->capacity_m3 : null,
            'current_status'     => $v->current_status ?? 'indisponivel',
            'is_overdue'         => $isOverdue,
            'current_rental_id'  => $v->current_rental_id,
            'cliente_nome'       => $rental?->contact?->name,
            'delivery_address'   => $rental?->delivery_address,
            'entered_at'         => $rental?->entered_at?->toIso8601String(),
            'expected_return'    => $rental?->expected_return_date?->toDateString(),
            'dias_locacao'       => $rental ? (int) $rental->dias_locacao : null,
            'valor_receber'      => $rental ? (float) $rental->valor_receber : null,
        ];
    }

    /**
     * Conta KPIs derivados das colunas (single source of truth — evita query
     * extra + garante consistência com listagem renderizada).
     *
     * @param  array<string, array<int, array<string, mixed>>>  $kanban
     * @return array<string, int>
     */
    protected function buildKpis(array $kanban): array
    {
        $total = 0;
        foreach ($kanban as $col) {
            $total += count($col);
        }
        $aguardando = count($kanban['aguardando'] ?? []);

        return [
            'total'                   => $total,
            'atrasadas'               => $aguardando,
            'aguardando_recolhimento' => $aguardando,
        ];
    }
}
