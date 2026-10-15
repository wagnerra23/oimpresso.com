<?php

namespace Modules\Producao\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Producao\Entities\Turno;

/**
 * Turno por empresa (D-TURNO): configurável, padrão 08h–18h. O fim do turno é o horário em
 * que o cronômetro aberto fecha sozinho (UC-PCV-22, thread 04).
 */
class TurnoService
{
    public const INICIO_PADRAO = '08:00:00';

    public const FIM_PADRAO = '18:00:00';

    /** @return array{inicio: string, fim: string} */
    public function daEmpresa(int $businessId): array
    {
        $turno = Turno::query()->where('business_id', $businessId)->first();

        return [
            'inicio' => $turno?->inicio ?? self::INICIO_PADRAO,
            'fim' => $turno?->fim ?? self::FIM_PADRAO,
        ];
    }

    public function fimDoTurno(int $businessId, CarbonInterface $dia): Carbon
    {
        return Carbon::parse($dia->toDateString().' '.$this->daEmpresa($businessId)['fim']);
    }
}
