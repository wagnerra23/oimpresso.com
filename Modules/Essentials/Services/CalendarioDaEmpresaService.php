<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

use App\Contracts\Rh\CalendarioDaEmpresa;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Essentials\Entities\EssentialsHoliday;
use Modules\Essentials\Entities\Shift;

/**
 * Feriados e expediente cadastrados no RH (contrato `App\Contracts\Rh\CalendarioDaEmpresa`).
 *
 * Feriado: a mesma regra do Ponto (`ApuracaoService::aplicarFeriado`) — da empresa (`location_id`
 * nulo) ou da loja, `start_date ≤ dia ≤ end_date`. Expediente: turno fixo com horário; com mais de
 * um, o com mais funcionários vinculados, empate pelo menor id. As folgas vêm do campo `holidays`
 * do turno (`sunday` … `saturday`, as chaves de `Util::getDays()`).
 *
 * Tier 0 (ADR 0093): `where('business_id')` explícito, além do escopo das Entities.
 */
final class CalendarioDaEmpresaService implements CalendarioDaEmpresa
{
    private const DIAS = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];

    public function diasNaoUteis(int $businessId, ?int $locationId, CarbonInterface $de, CarbonInterface $ate): array
    {
        $de = Carbon::instance($de)->startOfDay();
        $ate = Carbon::instance($ate)->startOfDay();
        $feriados = EssentialsHoliday::where('business_id', $businessId)
            ->whereDate('start_date', '<=', $ate->toDateString())
            ->whereDate('end_date', '>=', $de->toDateString())
            ->where(function ($q) use ($locationId) {
                $q->whereNull('location_id');
                if (! empty($locationId)) {
                    $q->orWhere('location_id', $locationId);
                }
            })
            ->get(['start_date', 'end_date']);

        $dias = [];
        foreach ($feriados as $f) {
            $d = Carbon::parse($f->start_date)->max($de)->copy();
            $fim = Carbon::parse($f->end_date)->min($ate)->copy();
            for (; $d->lte($fim); $d->addDay()) {
                $dias[$d->toDateString()] = true;
            }
        }
        $dias = array_keys($dias);
        sort($dias);

        return $dias;
    }

    public function expediente(int $businessId): ?array
    {
        $turno = Shift::where('business_id', $businessId)->where('type', 'fixed_shift')
            ->whereNotNull('start_time')->whereNotNull('end_time')
            ->withCount('user_shifts')->orderByDesc('user_shifts_count')->orderBy('id')->first();
        if ($turno === null) {
            return null;
        }
        $folgas = array_values(array_unique(array_filter(array_map(
            fn ($d) => self::DIAS[strtolower((string) $d)] ?? null,
            is_array($turno->holidays) ? $turno->holidays : []
        ), fn ($d) => $d !== null)));
        sort($folgas);

        return ['nome' => (string) $turno->name, 'inicio' => (string) $turno->start_time, 'fim' => (string) $turno->end_time, 'folgas' => $folgas];
    }
}
