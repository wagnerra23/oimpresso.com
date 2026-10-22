<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * US-NFE-071 · UC-FLX-32 — os três modos de contingência da NF-e modelo 55.
 *
 *   epec   → tpEmis 4. A nota fica guardada; o evento 110140 vai ao Ambiente Nacional
 *            e a nota é transmitida à SEFAZ da UF quando ela voltar.
 *   svc-an → tpEmis 6. A nota é transmitida NA HORA para a SEFAZ Virtual do AN.
 *   svc-rs → tpEmis 7. A nota é transmitida NA HORA para a SEFAZ Virtual do RS.
 *
 * Cada UF tem UMA SEFAZ Virtual designada; a outra recusa a nota. A tabela abaixo é a
 * mesma de `NFePHP\NFe\Factories\Contingency::activate()` (lib que transmite), copiada
 * em vez de chamada porque aquele método exige motivo e relógio só para devolver a sigla.
 *
 * NFC-e (modelo 65) não tem SEFAZ Virtual: segue off-line (tpEmis 9) em qualquer modo.
 */
final class ModoContingencia
{
    public const EPEC = 'epec';

    public const SVC_AN = 'svc-an';

    public const SVC_RS = 'svc-rs';

    /** UF → SEFAZ Virtual designada (sped-nfe `Contingency::activate`). */
    private const SVC_POR_UF = [
        'AC' => self::SVC_AN, 'AL' => self::SVC_AN, 'AM' => self::SVC_RS, 'AP' => self::SVC_AN,
        'BA' => self::SVC_RS, 'CE' => self::SVC_AN, 'DF' => self::SVC_AN, 'ES' => self::SVC_AN,
        'GO' => self::SVC_RS, 'MA' => self::SVC_RS, 'MG' => self::SVC_AN, 'MS' => self::SVC_RS,
        'MT' => self::SVC_RS, 'PA' => self::SVC_AN, 'PB' => self::SVC_AN, 'PE' => self::SVC_RS,
        'PI' => self::SVC_AN, 'PR' => self::SVC_RS, 'RJ' => self::SVC_AN, 'RN' => self::SVC_AN,
        'RO' => self::SVC_AN, 'RR' => self::SVC_AN, 'RS' => self::SVC_AN, 'SC' => self::SVC_AN,
        'SE' => self::SVC_AN, 'SP' => self::SVC_AN, 'TO' => self::SVC_AN,
    ];

    /** @return list<string> */
    public static function todos(): array
    {
        return [self::EPEC, self::SVC_AN, self::SVC_RS];
    }

    /** SEFAZ Virtual que atende a UF; null para UF desconhecida. */
    public static function svcDaUf(?string $uf): ?string
    {
        return self::SVC_POR_UF[strtoupper((string) $uf)] ?? null;
    }

    public static function ehSvc(?string $modo): bool
    {
        return $modo === self::SVC_AN || $modo === self::SVC_RS;
    }

    /**
     * tpEmis da NF-e modelo 55 para o modo escolhido. Modo vazio é o comportamento de antes
     * desta US (EPEC), para tenant que ativou contingência antes de a escolha existir.
     */
    public static function tpEmisNfe(?string $modo): int
    {
        return match ($modo) {
            self::SVC_AN => NfeEmissao::TP_EMIS_SVC_AN,
            self::SVC_RS => NfeEmissao::TP_EMIS_SVC_RS,
            default => NfeEmissao::TP_EMIS_EPEC,
        };
    }

    /** Sigla que a sped-nfe usa no `Contingency::$type` para rotear o webservice. */
    public static function tipoSpedNfe(int $tpEmis): string
    {
        return match ($tpEmis) {
            NfeEmissao::TP_EMIS_SVC_AN => 'SVCAN',
            NfeEmissao::TP_EMIS_SVC_RS => 'SVCRS',
            NfeEmissao::TP_EMIS_EPEC => 'EPEC',
            default => '',
        };
    }

    public static function rotulo(?string $modo): string
    {
        return match ($modo) {
            self::SVC_AN => 'SVC-AN',
            self::SVC_RS => 'SVC-RS',
            default => 'EPEC',
        };
    }
}
