<?php

declare(strict_types=1);

namespace App\Support\BR;

/**
 * CNPJ numérico e alfanumérico — normalização e dígito verificador.
 *
 * Contrato: IN RFB nº 2.229, de 15 de outubro de 2024, que altera a IN RFB nº 2.119/2022.
 * Texto literal do parágrafo acrescido:
 *
 *   "Parágrafo único. O CNPJ adotará o formato alfanumérico composto por quatorze
 *    posições, conforme disposto no Anexo XV, com previsão de implementação a partir
 *    de julho de 2026."
 *
 * Composição (Anexo XV): raiz de 8 posições + ordem de 4 posições, ambas com letras
 * maiúsculas ou números, + 2 dígitos verificadores sempre numéricos. O DV segue o
 * módulo 11 de sempre; o valor de cada caractere passa a ser o código ASCII menos 48
 * (0..9 continuam valendo 0..9; A=17, B=18 ... Z=42). Por isso um CNPJ só numérico
 * calcula o mesmo DV de antes — os CNPJs existentes não mudam.
 *
 * ⚠️ O Anexo XV não foi transcrito literalmente aqui: a fonte consultada em 2026-10-06
 * trazia a IN sem o anexo. A regra acima foi conferida contra o exemplo publicado pela
 * Receita (`12.ABC.345/01DE-35` ⇒ DV `35`), que este código reproduz.
 *
 * LGPD: nunca logar o valor recebido.
 */
final class Cnpj
{
    /** 12 posições alfanuméricas + 2 DV numéricos, já sem máscara e em maiúsculas. */
    private const FORMATO = '/^[0-9A-Z]{12}[0-9]{2}$/';

    /** Tira a máscara (ponto, barra, hífen, espaço) e põe em maiúsculas. Não tira letra. */
    public static function normalizar(string $valor): string
    {
        return strtoupper((string) preg_replace('/[\s.\/-]+/', '', $valor));
    }

    /** O valor, já normalizado, tem letra? Decide se é o formato novo. */
    public static function temLetra(string $valor): bool
    {
        return preg_match('/[A-Z]/', self::normalizar($valor)) === 1;
    }

    public static function valido(string $valor): bool
    {
        $cnpj = self::normalizar($valor);

        if (preg_match(self::FORMATO, $cnpj) !== 1) {
            return false;
        }
        // Todos os caracteres iguais (00000000000000, AAAAAAAAAAAA..) não é inscrição.
        if (count(array_unique(str_split(substr($cnpj, 0, 12)))) === 1) {
            return false;
        }

        $dv1 = self::digito(substr($cnpj, 0, 12));
        $dv2 = self::digito(substr($cnpj, 0, 12) . $dv1);

        return substr($cnpj, 12, 2) === $dv1 . $dv2;
    }

    /**
     * Documento do destinatário/emitente pronto para o XML da NF-e.
     *
     * Antes: `preg_replace('/\D/', '', ...)` — arrancava as letras e um CNPJ
     * alfanumérico virava um número de 8 a 13 dígitos, recusado como "sem CPF/CNPJ".
     * Agora: valor com letra só sai com as letras quando é um CNPJ alfanumérico com
     * DV válido; todo o resto cai no comportamento antigo (só dígitos), para não mudar
     * nada no que já funcionava.
     *
     * Exigir o DV não é zelo: a forma sozinha não basta. `CPF 529.982.247-25` sem
     * máscara vira `CPF52998224725` — 12 alfanuméricos + 2 dígitos, a forma exata do
     * CNPJ novo — e iria para o XML como CNPJ (pego pelo R-NFE-033 no CI).
     */
    public static function documentoFiscal(string $valor): string
    {
        if (self::temLetra($valor) && self::valido($valor)) {
            return self::normalizar($valor);
        }

        return (string) preg_replace('/\D/', '', $valor);
    }

    /** Módulo 11 com pesos 2..9 da direita para a esquerda; valor = ASCII − 48. */
    private static function digito(string $base): string
    {
        $soma = 0;
        $peso = 2;
        for ($i = strlen($base) - 1; $i >= 0; $i--) {
            $soma += (ord($base[$i]) - 48) * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }
        $resto = $soma % 11;

        return (string) ($resto < 2 ? 0 : 11 - $resto);
    }
}
