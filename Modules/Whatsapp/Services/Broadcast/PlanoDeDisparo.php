<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Broadcast;

use Carbon\CarbonImmutable;

/**
 * Horários de envio de uma campanha, com as salvaguardas anti-ban (US-WA-322).
 *
 * Os canais de campanha são o driver não-oficial (Baileys/Whatsmeow): rajada é o caminho mais
 * curto para o número ser banido. Então:
 * - **intervalo aleatório** entre uma mensagem e a próxima (`intervalo_min_s`..`intervalo_max_s`);
 * - **só em horário comercial** (`janela_inicio`..`janela_fim`, hora local da empresa) — fora dela,
 *   o próximo envio vai para o início da janela seguinte;
 * - **limite por dia** por campanha, menor enquanto o canal está em aquecimento (criado há menos de
 *   `dias_aquecimento` dias) — o excedente passa para o dia seguinte.
 *
 * Puro: o sorteio vem de fora, para o teste ser determinístico.
 */
final class PlanoDeDisparo
{
    /**
     * @param  callable(int, int): int  $sortear  devolve um inteiro entre os dois limites
     * @return list<CarbonImmutable>
     */
    public static function horarios(
        int $quantidade,
        CarbonImmutable $inicio,
        int $limitePorDia,
        int $intervaloMin,
        int $intervaloMax,
        int $janelaInicio,
        int $janelaFim,
        callable $sortear,
    ): array {
        if ($quantidade <= 0) {
            return [];
        }
        $limitePorDia = max(1, $limitePorDia);
        $horarios = [];
        $t = self::dentroDaJanela($inicio, $janelaInicio, $janelaFim);
        $diaAtual = $t->toDateString();
        $noDia = 0;

        for ($i = 0; $i < $quantidade; $i++) {
            if ($noDia >= $limitePorDia) {
                $t = $t->addDay()->setTime($janelaInicio, 0);
            }
            $t = self::dentroDaJanela($t, $janelaInicio, $janelaFim);
            if ($t->toDateString() !== $diaAtual) {
                $diaAtual = $t->toDateString();
                $noDia = 0;
            }
            $horarios[] = $t;
            $noDia++;
            $t = $t->addSeconds($sortear($intervaloMin, $intervaloMax));
        }

        return $horarios;
    }

    private static function dentroDaJanela(CarbonImmutable $t, int $inicio, int $fim): CarbonImmutable
    {
        if ($t->hour < $inicio) {
            return $t->setTime($inicio, 0);
        }
        if ($t->hour >= $fim) {
            return $t->addDay()->setTime($inicio, 0);
        }

        return $t;
    }
}
