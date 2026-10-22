<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Contracts\Rh\CalendarioDaEmpresa;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Producao\Entities\Turno;

/**
 * Calendário de produção da empresa (UC-FLX-06 · US-PCP-028). Decisão [W] 2026-10-20: "pula sábado,
 * domingo e feriados", pelo período de funcionamento que a empresa JÁ cadastrou — nada fixo no código.
 *
 * - Hora útil: o turno da produção (`pcp_turnos`) → senão o expediente do RH (turno fixo do
 *   Essentials, via `App\Contracts\Rh\CalendarioDaEmpresa`) → senão 08h–18h, com aviso.
 * - Dias de folga: os do expediente do RH; sem expediente, sábado e domingo.
 * - Feriados: os do RH da empresa e da loja da venda (mesma regra do Ponto). Sem o Essentials,
 *   nenhum — e isso vem nas premissas.
 *
 * O módulo não lê tabela do Essentials: só o contrato, e só se estiver ligado.
 * Tier 0: turno, expediente e feriados vêm só do `$businessId` recebido.
 */
final class CalendarioDeProducao
{
    /** Janela de feriados consultada a partir do início (folga de sobra para qualquer fila). */
    private const JANELA_DIAS = 400;

    /**
     * Regras do calendário da empresa a partir de `$de`.
     *
     * @return array{abre:int, fecha:int, folgas:list<int>, feriados:array<string,true>, premissas:list<string>}
     */
    public function regras(int $businessId, ?int $locationId, CarbonInterface $de): array
    {
        $rh = app()->bound(CalendarioDaEmpresa::class) ? app(CalendarioDaEmpresa::class) : null;
        $expediente = $rh?->expediente($businessId);
        $turno = Turno::query()->where('business_id', $businessId)->first(['inicio', 'fim']);
        $min = fn (?string $h) => $h === null ? null : (int) substr($h, 0, 2) * 60 + (int) substr($h, 3, 2);
        $hm = fn (int $m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

        [$abre, $fecha, $fonte] = [$min($turno?->inicio), $min($turno?->fim), 'turno da produção'];
        if ($abre === null || $fecha <= $abre) {
            [$abre, $fecha, $fonte] = [$min($expediente['inicio'] ?? null), $min($expediente['fim'] ?? null), 'expediente do RH «'.($expediente['nome'] ?? '').'»'];
        }
        $premissas = [];
        if ($abre === null || $fecha <= $abre) {
            [$abre, $fecha] = [8 * 60, 18 * 60];
            $premissas[] = 'Sem turno da produção nem expediente no RH: considerado 08:00–18:00.';
        } else {
            $premissas[] = sprintf('Hora útil: %s, %s–%s.', $fonte, $hm($abre), $hm($fecha));
        }

        $folgas = $expediente['folgas'] ?? [0, 6];
        $premissas[] = $expediente === null ? 'Sem expediente no RH: folga no sábado e no domingo.'
            : 'Folgas da semana: as do expediente do RH «'.$expediente['nome'].'».';

        $feriados = [];
        if ($rh === null) {
            $premissas[] = 'Sem o módulo RH: feriados não considerados.';
        } else {
            $ate = Carbon::instance($de)->copy()->addDays(self::JANELA_DIAS);
            $feriados = array_fill_keys($rh->diasNaoUteis($businessId, $locationId, $de, $ate), true);
            $premissas[] = 'Feriados: os cadastrados no RH para a empresa e para a loja da venda.';
        }

        return ['abre' => $abre, 'fecha' => $fecha, 'folgas' => $folgas, 'feriados' => $feriados, 'premissas' => $premissas];
    }

    /** @param array{abre:int, fecha:int} $regras */
    public function horasDoTurno(array $regras): float
    {
        return ($regras['fecha'] - $regras['abre']) / 60;
    }

    /**
     * Soma horas úteis a partir de `$inicio`, pulando o que fica fora do turno, as folgas e os
     * feriados. Começo fora do turno anda até a próxima abertura; fechar no fim do turno fica no dia.
     *
     * @param  array{abre:int, fecha:int, folgas:list<int>, feriados:array<string,true>}  $regras
     */
    public function somarHoras(array $regras, CarbonInterface $inicio, float $horas): Carbon
    {
        ['abre' => $abre, 'fecha' => $fecha] = $regras;
        $resta = (int) round($horas * 60);
        $t = Carbon::instance($inicio)->copy()->second(0);

        for ($guarda = 0; $guarda < 4000; $guarda++) {
            $dia = $t->copy()->startOfDay();
            $agora = $t->hour * 60 + $t->minute;
            $util = ! in_array($dia->dayOfWeek, $regras['folgas'], true) && ! isset($regras['feriados'][$dia->toDateString()]);
            if (! $util || $agora >= $fecha) {
                $t = $dia->addDay()->addMinutes($abre);

                continue;
            }
            $agora = max($agora, $abre);
            if ($resta <= $fecha - $agora) {
                return $dia->addMinutes($agora + $resta);
            }
            $resta -= $fecha - $agora;
            $t = $dia->addDay()->addMinutes($abre);
        }

        return $t; // só com a semana inteira de folga
    }
}
