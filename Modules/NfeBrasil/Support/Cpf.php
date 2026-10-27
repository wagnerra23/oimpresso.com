<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Support;

/**
 * Dígitos verificadores do CPF (módulo 11). Mesma conta do `ClienteAutosaveController::isValidCpf`
 * do CRM, que é privado; aqui fica reutilizável pelo Fiscal sem depender do controller de outro módulo.
 */
final class Cpf
{
    /** Só os dígitos, ou null quando não são 11 dígitos com dígitos verificadores válidos. */
    public static function normalizar(?string $valor): ?string
    {
        $d = preg_replace('/\D/', '', (string) $valor) ?? '';

        return self::valido($d) ? $d : null;
    }

    public static function valido(string $digitos): bool
    {
        if (preg_match('/^\d{11}$/', $digitos) !== 1 || preg_match('/^(\d)\1{10}$/', $digitos) === 1) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $digitos[$i] * (($t + 1) - $i);
            }
            $resto = ($soma * 10) % 11;
            if ((int) $digitos[$t] !== ($resto === 10 ? 0 : $resto)) {
                return false;
            }
        }

        return true;
    }
}
