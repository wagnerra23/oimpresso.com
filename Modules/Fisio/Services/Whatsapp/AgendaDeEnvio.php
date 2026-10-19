<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Whatsapp;

use Closure;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Quando a próxima mensagem do Fisio pode sair (ADR 0437 §13.1, mitigações (c) e horário de
 * silêncio da etapa 02 §3.3). Pura: recebe o "agora", os horários já agendados no número e os
 * parâmetros de `config('fisio.whatsapp')`; não lê relógio, banco nem configuração.
 *
 * Três regras, nesta ordem, repetidas até estabilizar:
 *  1. Horário de silêncio: nada sai entre `silencio.inicio` e `silencio.fim` no fuso da clínica;
 *     o envio vai para o fim do silêncio (ex.: 21:00–07:00 → 07:00 do dia certo).
 *  2. Intervalo aleatório: com teto ligado, cada envio sai pelo menos `min`..`max` segundos
 *     (sorteado) depois do último agendado no número — sem rajada com cadência fixa.
 *  3. Teto por hora: nunca mais de `teto` envios numa janela móvel de 60 minutos no número.
 *
 * O sorteio entra por parâmetro (`Closure(int, int): int`) para o teste ser determinístico.
 */
final class AgendaDeEnvio
{
    private const MAX_PASSOS = 500;

    /**
     * @param  list<DateTimeImmutable>  $agendados  horários já reservados no número (qualquer ordem)
     * @param  array{teto: int, intervalo_min: int, intervalo_max: int, silencio_inicio: string, silencio_fim: string, com_teto: bool}  $regras
     * @param  Closure(int, int): int  $sorteio
     */
    public static function proximo(DateTimeImmutable $agora, array $agendados, array $regras, string $fuso, Closure $sorteio): DateTimeImmutable
    {
        $tz = new DateTimeZone($fuso);
        $slot = self::foraDoSilencio($agora, $regras['silencio_inicio'], $regras['silencio_fim'], $tz);

        if (! $regras['com_teto']) {
            return $slot;
        }

        usort($agendados, fn ($a, $b) => $a <=> $b);
        $ultimo = $agendados === [] ? null : $agendados[count($agendados) - 1];
        if ($ultimo !== null) {
            $min = max(0, $regras['intervalo_min']);
            $max = max($min, $regras['intervalo_max']);
            $depoisDoUltimo = $ultimo->modify('+'.$sorteio($min, $max).' seconds');
            if ($depoisDoUltimo > $slot) {
                $slot = $depoisDoUltimo;
            }
        }

        $teto = max(1, $regras['teto']);
        for ($passo = 0; $passo < self::MAX_PASSOS; $passo++) {
            $slot = self::foraDoSilencio($slot, $regras['silencio_inicio'], $regras['silencio_fim'], $tz);
            $janelaInicio = $slot->modify('-3600 seconds');
            $naJanela = array_values(array_filter($agendados, fn ($t) => $t > $janelaInicio && $t <= $slot));
            if (count($naJanela) < $teto) {
                return $slot;
            }
            // O mais antigo da janela sai dela 1 h depois de agendado; o envio vai para logo após.
            $slot = $naJanela[count($naJanela) - $teto]->modify('+3601 seconds');
        }

        return $slot;
    }

    /** Se o instante cai no silêncio da clínica, devolve o fim do silêncio; senão, o próprio instante. */
    public static function foraDoSilencio(DateTimeImmutable $instante, string $inicio, string $fim, DateTimeZone $tz): DateTimeImmutable
    {
        if ($inicio === $fim) {
            return $instante;
        }

        $local = $instante->setTimezone($tz);
        $hm = $local->format('H:i');
        $atravessaMeiaNoite = $inicio > $fim;
        $emSilencio = $atravessaMeiaNoite ? ($hm >= $inicio || $hm < $fim) : ($hm >= $inicio && $hm < $fim);
        if (! $emSilencio) {
            return $instante;
        }

        [$h, $m] = array_map('intval', explode(':', $fim));
        $fimHoje = $local->setTime($h, $m);
        $saida = ($atravessaMeiaNoite && $hm >= $inicio) ? $fimHoje->modify('+1 day') : $fimHoje;

        return $saida->setTimezone($instante->getTimezone());
    }
}