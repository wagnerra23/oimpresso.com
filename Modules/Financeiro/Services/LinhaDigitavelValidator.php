<?php

namespace Modules\Financeiro\Services;

/**
 * Validador de linha digitável / código de barras BR (boleto bancário).
 *
 * Onda 23 (2026-05-20) US-FIN-029 — anti-lixo OCR.
 *
 * Aceita 2 formatos:
 *  - 44 dígitos (código de barras): banco(3) + moeda(1) + DAC(1) + fator_venc(4) + valor(10) + campo_livre(25)
 *  - 47 dígitos (linha digitável): 3 campos com DAC mod10 + 1 DAC geral mod11 + fator/valor
 *
 * Algoritmo módulo 11 (CNAB FEBRABAN):
 *  - Soma com pesos cíclicos 2 a 9 da direita pra esquerda
 *  - DAC = 11 - (soma % 11), exceto: se 0/10/11 → 1
 */
class LinhaDigitavelValidator
{
    public static function validar(string $linhaOuCodigo): bool
    {
        $digitos = preg_replace('/[^0-9]/', '', $linhaOuCodigo);
        $len = strlen($digitos);

        if ($len === 44) {
            return self::validarCodigoBarras44($digitos);
        }

        if ($len === 47) {
            return self::validarLinhaDigitavel47($digitos);
        }

        return false;
    }

    /**
     * Converte linha digitável 47 → código de barras 44 (canon pra persistir).
     */
    public static function toCodigoBarras(string $linhaOuCodigo): ?string
    {
        $digitos = preg_replace('/[^0-9]/', '', $linhaOuCodigo);
        $len = strlen($digitos);

        if ($len === 44 && self::validarCodigoBarras44($digitos)) {
            return $digitos;
        }

        if ($len === 47 && self::validarLinhaDigitavel47($digitos)) {
            // Recompor: banco(3) + moeda(1) + DAC4(1) + fator_venc(4) + valor(10) + campo_livre(25)
            // Linha digitável posições:
            //  0-3: banco+moeda+5 primeiros campo_livre (5 chars + DAC mod10)
            //  5-14: 6º-15º campo_livre (10 chars + DAC mod10)
            //  16-25: 16º-25º campo_livre (10 chars + DAC mod10)
            //  27: DAC geral mod11
            //  28-31: fator vencimento (4 chars)
            //  32-41: valor (10 chars)
            $banco = substr($digitos, 0, 3);
            $moeda = substr($digitos, 3, 1);
            $dac = substr($digitos, 32, 1);  // posição 32 da linha = DAC geral
            $fator = substr($digitos, 33, 4);
            $valor = substr($digitos, 37, 10);
            $campoLivre = substr($digitos, 4, 5) . substr($digitos, 10, 10) . substr($digitos, 21, 10);

            return $banco . $moeda . $dac . $fator . $valor . $campoLivre;
        }

        return null;
    }

    private static function validarCodigoBarras44(string $codigo): bool
    {
        if (! preg_match('/^[0-9]{44}$/', $codigo)) {
            return false;
        }

        $dacInformado = (int) $codigo[4];
        // DAC = mod 11 das posições 1-4 + 6-44 (skipping DAC na posição 5).
        $semDac = substr($codigo, 0, 4) . substr($codigo, 5);

        $soma = 0;
        $peso = 2;
        for ($i = strlen($semDac) - 1; $i >= 0; $i--) {
            $soma += ((int) $semDac[$i]) * $peso;
            $peso = ($peso === 9) ? 2 : $peso + 1;
        }

        $resto = $soma % 11;
        $dacCalc = 11 - $resto;
        if ($dacCalc === 0 || $dacCalc === 10 || $dacCalc === 11) {
            $dacCalc = 1;
        }

        return $dacInformado === $dacCalc;
    }

    private static function validarLinhaDigitavel47(string $linha): bool
    {
        if (! preg_match('/^[0-9]{47}$/', $linha)) {
            return false;
        }

        // Valida 3 campos via mod10.
        $campos = [
            ['start' => 0, 'len' => 9, 'dac_pos' => 9],   // campo 1: 9 dígitos + DAC posição 9
            ['start' => 10, 'len' => 10, 'dac_pos' => 20], // campo 2: 10 dígitos + DAC posição 20
            ['start' => 21, 'len' => 10, 'dac_pos' => 31], // campo 3: 10 dígitos + DAC posição 31
        ];

        foreach ($campos as $c) {
            $bloco = substr($linha, $c['start'], $c['len']);
            $dacInf = (int) $linha[$c['dac_pos']];
            if (self::mod10($bloco) !== $dacInf) {
                return false;
            }
        }

        // Valida DAC geral (posição 32) via mod 11 — recompõe código de barras 43 sem DAC.
        $banco = substr($linha, 0, 3);
        $moeda = substr($linha, 3, 1);
        $fator = substr($linha, 33, 4);
        $valor = substr($linha, 37, 10);
        $campoLivre = substr($linha, 4, 5) . substr($linha, 10, 10) . substr($linha, 21, 10);
        $sem4 = $banco . $moeda . $fator . $valor . $campoLivre; // 43 chars sem DAC

        $soma = 0;
        $peso = 2;
        for ($i = strlen($sem4) - 1; $i >= 0; $i--) {
            $soma += ((int) $sem4[$i]) * $peso;
            $peso = ($peso === 9) ? 2 : $peso + 1;
        }

        $resto = $soma % 11;
        $dacCalc = 11 - $resto;
        if ($dacCalc === 0 || $dacCalc === 10 || $dacCalc === 11) {
            $dacCalc = 1;
        }

        return ((int) $linha[32]) === $dacCalc;
    }

    private static function mod10(string $bloco): int
    {
        $soma = 0;
        $peso = 2;
        for ($i = strlen($bloco) - 1; $i >= 0; $i--) {
            $produto = ((int) $bloco[$i]) * $peso;
            if ($produto > 9) {
                $produto = ($produto % 10) + intdiv($produto, 10);
            }
            $soma += $produto;
            $peso = ($peso === 2) ? 1 : 2;
        }

        $resto = $soma % 10;

        return $resto === 0 ? 0 : 10 - $resto;
    }
}
