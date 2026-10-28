<?php

declare(strict_types=1);

namespace Modules\Fiscal\Services\Sped;

use InvalidArgumentException;

/**
 * US-FISCAL-028 (fatia 1) — regras de LEIAUTE da EFD-ICMS/IPI que não dependem de dado.
 *
 * Fonte: Nota Técnica EFD ICMS IPI 2025.001 v1.0 (leiaute 020, válido de 01/01/2026 a
 * 31/12/2026) e Guia Prático EFD-ICMS/IPI v3.2.3 (atualização de 06/05/2026), baixados
 * do portal SPED em 2026-10-27. Seções citadas em cada constante.
 *
 * Tudo aqui é norma publicada, não decisão de contador. O que depende de decisão do
 * contador (ex.: os indicadores S/N do registro 1010) fica fora desta classe e está
 * listado em `memory/requisitos/Fiscal/SPEC.md` (US-FISCAL-028, perguntas abertas).
 */
final class EfdIcmsIpiLeiaute
{
    /**
     * NT 2025.001 §3.1.1 "Tabela Versão do Leiaute": código do campo COD_VER (0000)
     * pelo ano de início da obrigatoriedade. Só os anos que o gerador aceita
     * (`validar()` recusa ano < 2020).
     *
     * 2027 fica de fora de propósito: o leiaute 021 (NT 2026.001) muda campos que
     * este gerador não conhece. Gerar 2027 com o leiaute de 2026 seria declarar uma
     * versão que o arquivo não cumpre.
     */
    public const COD_VER_POR_ANO = [
        2020 => '014',
        2021 => '015',
        2022 => '016',
        2023 => '017',
        2024 => '018',
        2025 => '019',
        2026 => '020',
    ];

    /**
     * Guia Prático v3.2.3, Capítulo II, Seção 1: "É obrigatória a apresentação de
     * todos os blocos, na sequência" — 0, B, C, D, E, G, H, K, 1, 9.
     */
    public const ORDEM_DOS_BLOCOS = ['0', 'B', 'C', 'D', 'E', 'G', 'H', 'K', '1', '9'];

    public static function codVer(int $ano): string
    {
        if (! isset(self::COD_VER_POR_ANO[$ano])) {
            throw new InvalidArgumentException(
                "Leiaute da EFD-ICMS/IPI para {$ano} não implementado neste gerador "
                . '(tabela 3.1.1 da NT 2025.001 cobre até 2026; 2027 usa o leiaute 021 da NT 2026.001).'
            );
        }

        return self::COD_VER_POR_ANO[$ano];
    }
}
