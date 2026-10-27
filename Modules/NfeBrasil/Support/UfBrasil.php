<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Support;

/**
 * Sigla de UF a partir do que está gravado em `business_locations.state` (varchar livre).
 *
 * Medido em produção em 2026-09-04 (proposta `sped-identificacao-do-emitente-fonte-de-verdade`):
 * 77 de 89 locations têm a sigla e 12 têm o nome por extenso ("São Paulo", "Santa Catarina").
 * Os resolvedores de UF do NfeBrasil só aceitam a sigla e caem em 'SP' para os 12. Este é o
 * conversor único; quem quiser parar de cair em 'SP' passa a usá-lo. Sem fallback: o que não
 * é UF brasileira devolve null, e quem chama decide se isso é erro.
 */
final class UfBrasil
{
    /** Sigla => nome sem acento, em maiúsculas (comparação feita sem acento). */
    private const UFS = [
        'AC' => 'ACRE', 'AL' => 'ALAGOAS', 'AP' => 'AMAPA', 'AM' => 'AMAZONAS', 'BA' => 'BAHIA',
        'CE' => 'CEARA', 'DF' => 'DISTRITO FEDERAL', 'ES' => 'ESPIRITO SANTO', 'GO' => 'GOIAS',
        'MA' => 'MARANHAO', 'MT' => 'MATO GROSSO', 'MS' => 'MATO GROSSO DO SUL', 'MG' => 'MINAS GERAIS',
        'PA' => 'PARA', 'PB' => 'PARAIBA', 'PR' => 'PARANA', 'PE' => 'PERNAMBUCO', 'PI' => 'PIAUI',
        'RJ' => 'RIO DE JANEIRO', 'RN' => 'RIO GRANDE DO NORTE', 'RS' => 'RIO GRANDE DO SUL',
        'RO' => 'RONDONIA', 'RR' => 'RORAIMA', 'SC' => 'SANTA CATARINA', 'SP' => 'SAO PAULO',
        'SE' => 'SERGIPE', 'TO' => 'TOCANTINS',
    ];

    public static function sigla(?string $valor): ?string
    {
        $texto = strtoupper(trim((string) $valor));
        if ($texto === '') {
            return null;
        }
        if (isset(self::UFS[$texto])) {
            return $texto;
        }

        $semAcento = strtr($texto, ['Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'À' => 'A', 'É' => 'E', 'Ê' => 'E',
            'Í' => 'I', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C',
            'á' => 'A', 'â' => 'A', 'ã' => 'A', 'à' => 'A', 'é' => 'E', 'ê' => 'E',
            'í' => 'I', 'ó' => 'O', 'ô' => 'O', 'õ' => 'O', 'ú' => 'U', 'ç' => 'C']);
        $semAcento = preg_replace('/\s+/', ' ', $semAcento) ?? $semAcento;

        $sigla = array_search($semAcento, self::UFS, true);

        return $sigla === false ? null : $sigla;
    }
}
