<?php

namespace App\Rules\BR;

use Closure;
use Eduardokum\LaravelBoleto\Util;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CPF (11 dígitos) ou CNPJ (14 dígitos) via algoritmo mod-11 SEFAZ.
 *
 * Delega para `Eduardokum\LaravelBoleto\Util::validarCnpjCpf()` que já é
 * vendored em `lib-custom/laravel-boleto/src/Util.php:1211`. A função auto-
 * detecta CPF vs CNPJ pelo comprimento numérico (`onlyNumbers`).
 *
 * Uso:
 *   $request->validate(['cpf_cnpj' => ['nullable', new CpfCnpj]]);
 *
 * Investigação 2026-05-21 confirmou que a Util já existe vendored mas estava
 * zero-usada em `app/` e `Modules/`. Esta rule é o ponto canônico de uso.
 *
 * LGPD: NÃO logar o valor recebido em texto plano — apenas usar pra
 * validação. PII deve seguir mascaramento via `Contact::maskTaxNumber()`.
 */
class CpfCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return; // use `nullable` no caller pra permitir vazio
        }

        if (! is_string($value) && ! is_numeric($value)) {
            $fail('O campo :attribute deve ser CPF ou CNPJ válido.');

            return;
        }

        if (! Util::validarCnpjCpf((string) $value)) {
            $fail('O campo :attribute não é um CPF ou CNPJ válido (verificação mod-11).');
        }
    }
}
