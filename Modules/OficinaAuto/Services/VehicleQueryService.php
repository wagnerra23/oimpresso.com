<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * VehicleQueryService — encapsula leitura/filtragem de Vehicle pra Index
 * (extraído de VehicleController, Wave 18 saturation D4 Architecture).
 *
 * Stateless. Multi-tenant Tier 0 (ADR 0093): query usa global scope
 * de Vehicle (filtra por business_id automaticamente). Callers em CLI
 * (sem session) devem usar `withoutGlobalScopes` + `where('business_id', $biz)`.
 *
 * Spans OtelHelper::spanBiz (D9.a) — zero-cost se otel.enabled=false.
 *
 * @see Modules/OficinaAuto/Http/Controllers/VehicleController.php
 */
class VehicleQueryService
{
    /** Status validos pra filtro UI. */
    public const STATUSES = ['all', 'disponivel', 'locada', 'manutencao', 'atrasada'];

    /**
     * Lista veículos filtrados (com fail-soft schema check Wave 5-A).
     *
     * @param  array{status?:string,search?:string,limit?:int}  $filtros
     * @return iterable<Vehicle>
     */
    public function listar(array $filtros = []): iterable
    {
        return OtelHelper::spanBiz('oficinaauto.vehicle.listar', function () use ($filtros) {
            $statusFilter = $filtros['status'] ?? 'all';
            $search       = trim((string) ($filtros['search'] ?? ''));
            $limit        = (int) ($filtros['limit'] ?? 50);

            if (! in_array($statusFilter, self::STATUSES, true)) {
                $statusFilter = 'all';
            }

            $hasFsmSchema = Schema::hasColumn('vehicles', 'current_status');
            $query = Vehicle::query();

            if ($hasFsmSchema && $statusFilter !== 'all') {
                if ($statusFilter === 'atrasada') {
                    $query->where('current_status', 'locada')
                        ->whereHas('currentRental', function ($q) {
                            $q->whereDate('expected_return_date', '<', now());
                        });
                } else {
                    $query->where('current_status', $statusFilter);
                }
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('plate', 'like', "%{$search}%")
                        ->orWhere('vehicle_type', 'like', "%{$search}%");
                });
            }

            return $query->orderByDesc('id')->limit($limit)->get();
        }, [
            'module'  => 'OficinaAuto',
            'status'  => $filtros['status'] ?? 'all',
            'tem_q'   => ! empty($filtros['search']),
        ]);
    }

    /**
     * Conta veículos por status (KPIs Dashboard demo Martinho).
     *
     * @return array{disponivel:int,locada:int,manutencao:int,atrasada:int,total:int}
     */
    public function contagemPorStatus(): array
    {
        return OtelHelper::spanBiz('oficinaauto.vehicle.contagem_por_status', function () {
            $hasFsmSchema = Schema::hasColumn('vehicles', 'current_status');

            if (! $hasFsmSchema) {
                return $this->zeros();
            }

            $rows = Vehicle::query()
                ->selectRaw('current_status, COUNT(*) as total')
                ->groupBy('current_status')
                ->pluck('total', 'current_status')
                ->toArray();

            $disponivel = (int) ($rows['disponivel'] ?? 0);
            $locada     = (int) ($rows['locada'] ?? 0);
            $manutencao = (int) ($rows['manutencao'] ?? 0);

            $atrasada = Vehicle::query()
                ->where('current_status', 'locada')
                ->whereHas('currentRental', function ($q) {
                    $q->whereDate('expected_return_date', '<', now());
                })
                ->count();

            return [
                'disponivel' => $disponivel,
                'locada'     => $locada,
                'manutencao' => $manutencao,
                'atrasada'   => $atrasada,
                'total'      => $disponivel + $locada + $manutencao,
            ];
        }, ['module' => 'OficinaAuto']);
    }

    /**
     * Busca um veículo (multi-tenant Tier 0 — global scope filtra).
     */
    public function buscar(int $id): ?Vehicle
    {
        return OtelHelper::spanBiz('oficinaauto.vehicle.buscar', function () use ($id) {
            return Vehicle::query()->find($id);
        }, ['module' => 'OficinaAuto', 'vehicle_id' => $id]);
    }

    /**
     * Estrutura zeros pra fail-soft schema ausente.
     *
     * @return array{disponivel:int,locada:int,manutencao:int,atrasada:int,total:int}
     */
    private function zeros(): array
    {
        return [
            'disponivel' => 0,
            'locada'     => 0,
            'manutencao' => 0,
            'atrasada'   => 0,
            'total'      => 0,
        ];
    }
}
