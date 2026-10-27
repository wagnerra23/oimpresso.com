<?php

declare(strict_types=1);

use Modules\NfeBrasil\Support\Cpf;

uses(Tests\TestCase::class);

/**
 * UC-FCFG-11 — a conta do dígito verificador do CPF do contador, sem banco (roda em qualquer lane).
 * Casos conferidos por fora: 529.982.247-25 é válido (exemplo de manual); trocar o último dígito,
 * repetir o mesmo dígito 11 vezes ou faltar dígito reprova.
 */
it('UC-FCFG-11 · CPF válido normaliza para 11 dígitos; inválido vira null', function () {
    expect(Cpf::normalizar('529.982.247-25'))->toBe('52998224725')
        ->and(Cpf::normalizar('52998224725'))->toBe('52998224725')
        ->and(Cpf::normalizar('529.982.247-24'))->toBeNull()
        ->and(Cpf::normalizar('111.111.111-11'))->toBeNull()
        ->and(Cpf::normalizar('5299822472'))->toBeNull()
        ->and(Cpf::normalizar(null))->toBeNull()
        ->and(Cpf::normalizar(''))->toBeNull();
});
