<?php
// @covers-us US-PCP-028

declare(strict_types=1);
// Cobre UC-PCV-22 (fim do turno que fecha o cronômetro) e UC-FLX-06 (dias úteis do calendário) com a
// regra única do turno: pcp_turnos → expediente do RH → 08h–18h, e folga não cadastrada no RH = sábado e domingo.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Services\CalendarioDeProducao;
use Modules\Producao\Services\TurnoService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: decisão [W] 2026-10-22, a partir do caso da WR2 (turno do RH 08:00–12:00 sem folga
 * cadastrada): (a) turno do RH sem folga marcada vale sábado e domingo; (b) o cronômetro fecha no
 * mesmo fim de turno que a Linha do tempo usa. Tenant 98 × 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_turnos') || ! Schema::hasTable('essentials_shifts')) {
        $this->markTestSkipped('Tabelas pcp_turnos ou essentials_shifts ausentes nesta lane.');
    }
    if (! app()->bound(\App\Contracts\Rh\CalendarioDaEmpresa::class)) {
        $this->markTestSkipped('Módulo RH (Essentials) não ligado nesta lane.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    foreach (['essentials_holidays', 'essentials_shifts', 'pcp_turnos'] as $t) {
        if (Schema::hasTable($t)) {
            DB::table($t)->whereIn('business_id', [$this->b98, $this->b99])->delete();
        }
    }
    $this->calendario = app(CalendarioDeProducao::class);
    $this->turno = app(TurnoService::class);
});

function tcTurnoRh(int $biz, string $inicio, string $fim, ?array $folgas): void
{
    DB::table('essentials_shifts')->insert(['business_id' => $biz, 'name' => 'Turno TC', 'type' => 'fixed_shift',
        'start_time' => $inicio, 'end_time' => $fim, 'holidays' => $folgas === null ? null : json_encode($folgas),
        'created_at' => now(), 'updated_at' => now()]);
}

it('UC-FLX-06 · turno do RH sem folga cadastrada vale sábado e domingo; com folga cadastrada vale a do RH', function () {
    tcTurnoRh($this->b98, '08:00:00', '12:00:00', null);
    tcTurnoRh($this->b99, '08:00:00', '17:00:00', ['sunday']);

    $r98 = $this->calendario->regras($this->b98, null, Carbon::parse('2026-11-06'));
    $r99 = $this->calendario->regras($this->b99, null, Carbon::parse('2026-11-06'));

    expect($r98['folgas'])->toBe([0, 6])
        ->and(implode(' ', $r98['premissas']))->toContain('não tem folga cadastrada')
        ->and($r99['folgas'])->toBe([0]); // controle: folga cadastrada no RH continua valendo
});

it('UC-PCV-22 · sem turno da produção, o cronômetro fecha no fim do expediente do RH, o mesmo da Linha', function () {
    tcTurnoRh($this->b98, '08:00:00', '12:00:00', null);
    $dia = Carbon::parse('2026-11-03');

    expect($this->turno->fimDoTurno($this->b98, $dia)->format('Y-m-d H:i'))->toBe('2026-11-03 12:00')
        ->and($this->calendario->regras($this->b98, null, $dia)['fecha'])->toBe(12 * 60);
});

it('UC-PCV-22 · o turno da produção vale antes do RH, e sem nenhum dos dois o fim é 18h', function () {
    tcTurnoRh($this->b98, '08:00:00', '12:00:00', null);
    DB::table('pcp_turnos')->insert(['business_id' => $this->b98, 'inicio' => '07:00:00', 'fim' => '17:00:00', 'created_at' => now(), 'updated_at' => now()]);
    $dia = Carbon::parse('2026-11-03');

    expect($this->turno->fimDoTurno($this->b98, $dia)->format('H:i'))->toBe('17:00')
        ->and($this->turno->fimDoTurno($this->b99, $dia)->format('H:i'))->toBe('18:00'); // 99 sem turno nenhum (Tier 0: o da 98 não vaza)
});
