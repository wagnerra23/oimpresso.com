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
 * `prototipo-ui/prototipos/producao-oficina/visual-source.html` adaptado pra caçambas
 * (workflow real Martinho — locação caçamba estacionária):
 *
 * 5 colunas Kanban:
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
 * 6 KPIs (espelha visual-source.html — 6 cards horizontais):
 *  - total                    — total caçambas no estoque
 *  - disponivel               — pátio matriz
 *  - locada                   — em campo (no prazo)
 *  - aguardando_recolhimento  — locada + overdue (destaque amber)
 *  - manutencao               — oficina
 *  - atrasadas                — alias semântico de aguardando_recolhimento (KPI destaque rose)
 *  - valor_em_curso           — sum(daily_rate × dias_locacao) das ativas (locada + aguardando)
 *
 * Permission: oficinaauto.vehicle.view (mesma da listagem CRUD).
 *
 * Multi-tenant Tier 0 (ADR 0093): global scope em Vehicle filtra business_id
 * automaticamente — controller não precisa filtrar manualmente.
 *
 * @see prototipo-ui/prototipos/producao-oficina/visual-source.html (canon visual rico)
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
            // Eager-load currentRental + contact + transaction.createdBy pra atendente
            // (transaction pode ser null em rentals draft — fallback: auth user no projector).
            $query->with([
                'currentRental:id,vehicle_id,contact_id,transaction_id,entered_at,delivery_address,expected_return_date,daily_rate,status,notes,created_at',
                'currentRental.contact:id,name,mobile',
                'currentRental.transaction:id,created_by',
                'currentRental.transaction.createdBy:id,first_name,last_name,username',
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
     * Payload por card — enriquecido pra espelhar visual-source.html canon
     * (linhas: OS# + chegou + plate + cliente + endereço + obs + atendente · dias · valor).
     *
     * Drawer faz fetch completo via /oficina-auto/service-orders/{id} (existing).
     *
     * @return array<string, mixed>
     */
    protected function projectVehicleCard(Vehicle $v, $rental, bool $isOverdue): array
    {
        // Atendente — derivado da transaction.createdBy.
        // Fallback: null (frontend mostra "—") em rentals draft sem transaction.
        $atendenteNome = null;
        $atendenteIniciais = null;
        if ($rental && $rental->transaction && $rental->transaction->createdBy) {
            $u = $rental->transaction->createdBy;
            $first = (string) ($u->first_name ?? '');
            $last  = (string) ($u->last_name ?? '');
            $full  = trim($first . ' ' . $last);
            $atendenteNome = $full !== '' ? $full : (string) ($u->username ?? '');
            $atendenteIniciais = $this->makeIniciais($atendenteNome);
        }

        return [
            'id'                  => $v->id,
            'plate'               => $v->plate,
            'vehicle_number'      => $v->vehicle_number ?? null,
            'capacity_m3'         => $v->capacity_m3 !== null ? (float) $v->capacity_m3 : null,
            'current_status'      => $v->current_status ?? 'indisponivel',
            'is_overdue'          => $isOverdue,
            'current_rental_id'   => $v->current_rental_id,
            'os_number'           => $rental?->id,
            'rental_created_at'   => $rental?->created_at?->toIso8601String(),
            'rental_notes'        => $rental?->notes,
            'cliente_nome'        => $rental?->contact?->name,
            'delivery_address'    => $rental?->delivery_address,
            'entered_at'          => $rental?->entered_at?->toIso8601String(),
            'expected_return'     => $rental?->expected_return_date?->toDateString(),
            'dias_locacao'        => $rental ? (int) $rental->dias_locacao : null,
            'daily_rate'          => $rental?->daily_rate !== null ? (float) $rental->daily_rate : null,
            'valor_receber'       => $rental ? (float) $rental->valor_receber : null,
            'atendente_nome'      => $atendenteNome,
            'atendente_iniciais'  => $atendenteIniciais,
        ];
    }

    /**
     * Iniciais (até 2 letras maiúsculas) pro avatar circular do atendente.
     */
    protected function makeIniciais(string $nome): string
    {
        $parts = preg_split('/\s+/', trim($nome)) ?: [];
        $first = isset($parts[0][0]) ? mb_strtoupper(mb_substr($parts[0], 0, 1)) : '';
        $last  = '';
        if (count($parts) > 1) {
            $lastPart = end($parts);
            $last = isset($lastPart[0]) ? mb_strtoupper(mb_substr($lastPart, 0, 1)) : '';
        }
        return $first . $last;
    }

    /**
     * 6 KPIs derivados das colunas (single source of truth — evita query
     * extra + garante consistência com listagem renderizada).
     *
     *  - total                   — soma de todas as colunas
     *  - disponivel              — pátio matriz
     *  - locada                  — em campo (no prazo)
     *  - aguardando_recolhimento — locada + overdue (alias 'atrasadas')
     *  - manutencao              — oficina
     *  - atrasadas               — alias semântico (UI mostra com bg-rose destaque)
     *  - valor_em_curso          — sum(valor_receber) das colunas locada + aguardando
     *
     * @param  array<string, array<int, array<string, mixed>>>  $kanban
     * @return array<string, int|float>
     */
    protected function buildKpis(array $kanban): array
    {
        $total = 0;
        foreach ($kanban as $col) {
            $total += count($col);
        }

        $disponivel = count($kanban['disponivel'] ?? []);
        $locada     = count($kanban['locada'] ?? []);
        $aguardando = count($kanban['aguardando'] ?? []);
        $manutencao = count($kanban['manutencao'] ?? []);

        // Valor em curso — soma dos valor_receber das ativas (locada + aguardando).
        $valorEmCurso = 0.0;
        foreach (['locada', 'aguardando'] as $colKey) {
            foreach ($kanban[$colKey] ?? [] as $card) {
                $valorEmCurso += (float) ($card['valor_receber'] ?? 0);
            }
        }

        return [
            'total'                   => $total,
            'disponivel'              => $disponivel,
            'locada'                  => $locada,
            'aguardando_recolhimento' => $aguardando,
            'manutencao'              => $manutencao,
            'atrasadas'               => $aguardando,
            'valor_em_curso'          => round($valorEmCurso, 2),
        ];
    }
}
