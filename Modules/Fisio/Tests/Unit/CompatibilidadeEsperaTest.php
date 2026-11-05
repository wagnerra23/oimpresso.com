<?php

declare(strict_types=1);
// Cobre US-FISIO-014 (lista de espera, etapa 04 A7). Regra pura, sem banco.

use Carbon\Carbon;
use Modules\Fisio\Services\Agenda\CompatibilidadeEspera as C;

it('preferência vazia serve em qualquer vaga', function () {
    expect(C::serve(null, Carbon::parse('2026-11-12 09:00')))->toBeTrue()
        ->and(C::serve([], Carbon::parse('2026-11-12 19:00')))->toBeTrue();
});

it('dia, turno e prazo filtram a vaga', function () {
    $qui9 = Carbon::parse('2026-11-12 09:00'); // quinta = 4

    expect(C::serve(['dias' => [4, 5]], $qui9))->toBeTrue()
        ->and(C::serve(['dias' => [1]], $qui9))->toBeFalse()
        ->and(C::serve(['turnos' => ['manha']], $qui9))->toBeTrue()
        ->and(C::serve(['turnos' => ['tarde', 'noite']], $qui9))->toBeFalse()
        ->and(C::serve(['ate' => '2026-11-12'], $qui9))->toBeTrue()
        ->and(C::serve(['ate' => '2026-11-11'], $qui9))->toBeFalse();
});

it('turnos: manhã antes de 12h, tarde até 18h, noite a partir de 18h', function () {
    expect(C::turno(Carbon::parse('2026-11-12 11:59')))->toBe('manha')
        ->and(C::turno(Carbon::parse('2026-11-12 12:00')))->toBe('tarde')
        ->and(C::turno(Carbon::parse('2026-11-12 18:00')))->toBe('noite');
});
