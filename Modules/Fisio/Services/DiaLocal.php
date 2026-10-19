<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Dia civil do paciente — a unidade de todas as contas do jogo e do painel (ADR 0437 §6).
 *
 * As calculadoras puras (Adesao/Sequencia/Atencao) recebem DIAS como 'Y-m-d', nunca
 * instantes: quem converte instante → dia é esta classe, no fuso da clínica. O padrão é
 * America/Sao_Paulo porque o check-in das 23h30 de Brasília (02h30 UTC do dia seguinte)
 * pertence ao dia em que o paciente fez o exercício, não ao dia do servidor.
 *
 * Pura: o "agora" entra por parâmetro; não lê relógio global, sessão nem banco.
 */
final class DiaLocal
{
    public const FUSO_PADRAO = 'America/Sao_Paulo';

    /** Converte um instante no dia civil 'Y-m-d' do fuso dado. */
    public static function de(DateTimeInterface $instante, string $fuso = self::FUSO_PADRAO): string
    {
        return DateTimeImmutable::createFromInterface($instante)
            ->setTimezone(new DateTimeZone($fuso))
            ->format('Y-m-d');
    }

    /**
     * Valida e normaliza um dia 'Y-m-d' (meia-noite UTC, só para aritmética de dias).
     *
     * @throws InvalidArgumentException dia fora do formato ou inexistente (ex. 2026-02-30)
     */
    public static function parse(string $dia): DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $dia, new DateTimeZone('UTC'));
        if ($d === false || $d->format('Y-m-d') !== $dia) {
            throw new InvalidArgumentException("Dia inválido: '{$dia}' (esperado Y-m-d).");
        }

        return $d;
    }

    /** Nº de dias de $de até $ate (positivo se $ate é depois). */
    public static function diferenca(string $de, string $ate): int
    {
        return intdiv(self::parse($ate)->getTimestamp() - self::parse($de)->getTimestamp(), 86400);
    }

    /** O dia $dia somado de $n dias (negativo para trás). */
    public static function somar(string $dia, int $n): string
    {
        return self::parse($dia)->modify(sprintf('%+d days', $n))->format('Y-m-d');
    }
}
