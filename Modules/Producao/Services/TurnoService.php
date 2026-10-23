<?php

namespace Modules\Producao\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Turno por empresa (D-TURNO). O fim do turno é o horário em que o cronômetro aberto fecha
 * sozinho (UC-PCV-22, thread 04).
 *
 * Lê o MESMO calendário da Linha do tempo e do prazo pela fila (`CalendarioDeProducao`): turno da
 * produção (`pcp_turnos`) → senão o expediente do RH → senão 08h–18h. Antes lia só `pcp_turnos`
 * e caía em 08h–18h; numa empresa com expediente no RH o cronômetro fechava num horário e a Linha
 * marcava estouro em outro (decisão [W] 2026-10-22).
 */
class TurnoService
{
    public function __construct(private readonly CalendarioDeProducao $calendario) {}

    /** @return array{inicio: string, fim: string} HH:MM:SS */
    public function daEmpresa(int $businessId, ?CarbonInterface $dia = null): array
    {
        $regras = $this->calendario->regras($businessId, null, $dia ?? now());
        $hms = fn (int $m) => sprintf('%02d:%02d:00', intdiv($m, 60), $m % 60);

        return ['inicio' => $hms($regras['abre']), 'fim' => $hms($regras['fecha'])];
    }

    public function fimDoTurno(int $businessId, CarbonInterface $dia): Carbon
    {
        return Carbon::parse($dia->toDateString().' '.$this->daEmpresa($businessId, $dia)['fim']);
    }
}
