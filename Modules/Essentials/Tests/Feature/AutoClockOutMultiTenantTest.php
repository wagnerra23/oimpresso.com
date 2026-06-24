<?php

declare(strict_types=1);

use App\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsAttendance;
use Modules\Essentials\Entities\Shift;
use Modules\Jana\Scopes\ScopeByBusiness;

uses(Tests\TestCase::class);

/**
 * Isolamento multi-tenant + fuso per-business do command `pos:autoClockOutUser`.
 *
 * BUG ORIGINAL (censo artisan 2026-06-24): o command fazia um UPDATE de massa em
 * `essentials_attendances` SEM filtro `business_id`, batendo a saída automática de
 * funcionários de TODOS os businesses de uma vez (vazamento cross-tenant). E comparava
 * o `auto_clockout_time` (hora LOCAL de parede) contra `Carbon::now()` no fuso default
 * do CLI — errado pra qualquer business fora desse fuso. Em CLI o global scope
 * `ScopeByBusiness` é no-op (sem auth), então a proteção tinha que ser explícita.
 *
 * ADR 0093: multi-tenant Tier 0 IRREVOGÁVEL.
 * ADR 0101: tests sempre biz=1 (Wagner) e biz=99 (fictício). NUNCA biz=4 (ROTA LIVRE produção).
 *
 * @see Modules\Essentials\Console\AutoClockOutUser
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const ACO_BIZ_WAGNER = 1;
const ACO_BIZ_FICTICIO = 99;
const ACO_MARKER = 'TEST-AUTOCLOCKOUT';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema essentials requer MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['essentials_attendances', 'essentials_shifts', 'business'] as $table) {
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Tabela {$table} ausente — rode migrate do UltimatePOS/Essentials primeiro.");
        }
    }
});

afterEach(function () {
    Carbon::setTestNow();
    EssentialsAttendance::withoutGlobalScope(ScopeByBusiness::class)
        ->where('clock_in_note', ACO_MARKER)->delete();
    Shift::withoutGlobalScope(ScopeByBusiness::class)
        ->where('name', 'like', ACO_MARKER . '%')->delete();
});

/** Cria um shift de teste (per-business) com janela de auto clock-out configurável. */
function acoMakeShift(int $bizId, ?string $autoClockoutTime, bool $allowed = true): Shift
{
    return Shift::create([
        'business_id' => $bizId,
        'name' => ACO_MARKER . '-' . $bizId . '-' . uniqid(),
        'type' => 'fixed_shift',
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'is_allowed_auto_clockout' => $allowed ? 1 : 0,
        'auto_clockout_time' => $autoClockoutTime,
    ]);
}

/** Cria uma marcação de ponto aberta (ou já fechada se $clockOutTime). */
function acoMakeAttendance(int $bizId, int $shiftId, ?string $clockOutTime = null): EssentialsAttendance
{
    return EssentialsAttendance::create([
        'business_id' => $bizId,
        'user_id' => 1,
        'essentials_shift_id' => $shiftId,
        'clock_in_time' => '2026-06-24 08:00:00',
        'clock_out_time' => $clockOutTime,
        'clock_in_note' => ACO_MARKER,
    ]);
}

function acoFresh(int $id): ?EssentialsAttendance
{
    return EssentialsAttendance::withoutGlobalScope(ScopeByBusiness::class)->find($id);
}

// ------------------------------------------------------------------
// 1. HAPPY PATH — saída aberta na janela é batida (biz=1, fuso do business)
// ------------------------------------------------------------------

it('bate a saída automática de marcação aberta cujo shift está na janela', function () {
    $biz = Business::find(ACO_BIZ_WAGNER);
    if (! $biz) {
        $this->markTestSkipped('biz=1 não existe — precisa do seeder UltimatePOS.');
    }
    $tz = $biz->time_zone ?: 'America/Sao_Paulo';
    Carbon::setTestNow(Carbon::parse('2026-06-24 12:00:00', $tz)); // 12:00 local → janela [12:00, 12:30]

    $shift = acoMakeShift(ACO_BIZ_WAGNER, '12:15:00');
    $att = acoMakeAttendance(ACO_BIZ_WAGNER, $shift->id);

    Artisan::call('pos:autoClockOutUser');

    $fresh = acoFresh($att->id);
    expect($fresh->clock_out_time)->not->toBeNull();
    expect(Carbon::parse($fresh->clock_out_time)->toDateTimeString())->toBe('2026-06-24 12:00:00');
    expect($fresh->clock_out_note)->toContain('automática');
});

// ------------------------------------------------------------------
// 2. FORA DA JANELA — não bate
// ------------------------------------------------------------------

it('NÃO bate saída de marcação cujo shift está fora da janela', function () {
    $biz = Business::find(ACO_BIZ_WAGNER);
    if (! $biz) {
        $this->markTestSkipped('biz=1 não existe.');
    }
    $tz = $biz->time_zone ?: 'America/Sao_Paulo';
    Carbon::setTestNow(Carbon::parse('2026-06-24 12:00:00', $tz));

    $shift = acoMakeShift(ACO_BIZ_WAGNER, '09:00:00'); // fora de [12:00, 12:30]
    $att = acoMakeAttendance(ACO_BIZ_WAGNER, $shift->id);

    Artisan::call('pos:autoClockOutUser');

    expect(acoFresh($att->id)->clock_out_time)->toBeNull();
});

// ------------------------------------------------------------------
// 3. ISOLAMENTO TIER 0 — marcação de OUTRO business não é tocada (o bug original)
// ------------------------------------------------------------------

it('NÃO bate saída de marcação de OUTRO business mesmo com shift na mesma janela', function () {
    $biz = Business::find(ACO_BIZ_WAGNER);
    if (! $biz) {
        $this->markTestSkipped('biz=1 não existe.');
    }
    $tz = $biz->time_zone ?: 'America/Sao_Paulo';
    Carbon::setTestNow(Carbon::parse('2026-06-24 12:00:00', $tz));

    // biz=99 (fictício) com shift na MESMA janela do biz=1 — o código legado (sem
    // business_id) teria batido esta saída cross-tenant.
    $shift99 = acoMakeShift(ACO_BIZ_FICTICIO, '12:15:00');
    $att99 = acoMakeAttendance(ACO_BIZ_FICTICIO, $shift99->id);

    Artisan::call('pos:autoClockOutUser');

    expect(acoFresh($att99->id)->clock_out_time)->toBeNull(); // intacto — sem vazamento
});

// ------------------------------------------------------------------
// 4. IDEMPOTÊNCIA — não sobrescreve marcação já fechada
// ------------------------------------------------------------------

it('NÃO sobrescreve clock_out_time de marcação já fechada', function () {
    $biz = Business::find(ACO_BIZ_WAGNER);
    if (! $biz) {
        $this->markTestSkipped('biz=1 não existe.');
    }
    $tz = $biz->time_zone ?: 'America/Sao_Paulo';
    Carbon::setTestNow(Carbon::parse('2026-06-24 12:00:00', $tz));

    $shift = acoMakeShift(ACO_BIZ_WAGNER, '12:15:00');
    $att = acoMakeAttendance(ACO_BIZ_WAGNER, $shift->id, '2026-06-24 11:00:00'); // já fechada

    Artisan::call('pos:autoClockOutUser');

    expect(Carbon::parse(acoFresh($att->id)->clock_out_time)->toDateTimeString())
        ->toBe('2026-06-24 11:00:00'); // preservada
});

// ------------------------------------------------------------------
// 5. FUSO DO BUSINESS — usa o time_zone do próprio business, não o default do CLI
// ------------------------------------------------------------------

it('calcula a janela no fuso do próprio business (não no fuso default do CLI)', function () {
    $biz = Business::find(ACO_BIZ_WAGNER);
    if (! $biz) {
        $this->markTestSkipped('biz=1 não existe.');
    }

    $tzOriginalBiz = $biz->time_zone;
    try {
        // Fuso bem distinto (UTC+5:30) — independe do default do servidor de teste.
        $biz->time_zone = 'Asia/Kolkata';
        $biz->save();

        // Instante em que Kolkata = 12:00 → janela local [12:00, 12:30].
        Carbon::setTestNow(Carbon::parse('2026-06-24 12:00:00', 'Asia/Kolkata'));

        $shift = acoMakeShift(ACO_BIZ_WAGNER, '12:15:00'); // só entra na janela se usar fuso Kolkata
        $att = acoMakeAttendance(ACO_BIZ_WAGNER, $shift->id);

        Artisan::call('pos:autoClockOutUser');

        $fresh = acoFresh($att->id);
        expect($fresh->clock_out_time)->not->toBeNull();
        expect(Carbon::parse($fresh->clock_out_time)->format('H:i'))->toBe('12:00');
    } finally {
        $biz->time_zone = $tzOriginalBiz;
        $biz->save();
    }
});

// ------------------------------------------------------------------
// 6. AUDITORIA CLT — a batida automática gera trilha de activity_log (não bulk update)
// ------------------------------------------------------------------

it('grava trilha de auditoria (LogsActivity) ao bater a saída — não é bulk update', function () {
    if (! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('activity_log ausente — Spatie ActivityLog não migrado.');
    }
    $biz = Business::find(ACO_BIZ_WAGNER);
    if (! $biz) {
        $this->markTestSkipped('biz=1 não existe.');
    }
    $tz = $biz->time_zone ?: 'America/Sao_Paulo';
    Carbon::setTestNow(Carbon::parse('2026-06-24 12:00:00', $tz));

    $shift = acoMakeShift(ACO_BIZ_WAGNER, '12:15:00');
    $att = acoMakeAttendance(ACO_BIZ_WAGNER, $shift->id);

    $antes = DB::table('activity_log')->where('subject_id', $att->id)
        ->where('subject_type', 'like', '%EssentialsAttendance')->count();

    Artisan::call('pos:autoClockOutUser');

    $depois = DB::table('activity_log')->where('subject_id', $att->id)
        ->where('subject_type', 'like', '%EssentialsAttendance')->count();

    expect($depois)->toBeGreaterThan($antes); // a saída automática deixou rastro CLT Art. 74 §3
});

// ------------------------------------------------------------------
// 7. WRAP DE MEIA-NOITE — janela [23:30, 00:00] não pode virar BETWEEN vazio
// ------------------------------------------------------------------

it('bate a saída de shift na janela que cruza a meia-noite (agora 23:30 → 00:00)', function () {
    $biz = Business::find(ACO_BIZ_WAGNER);
    if (! $biz) {
        $this->markTestSkipped('biz=1 não existe.');
    }
    $tz = $biz->time_zone ?: 'America/Sao_Paulo';
    // 23:30 local → janela [23:30:00, 00:00:00] (cruza a meia-noite). No MySQL,
    // BETWEEN '23:30:00' AND '00:00:00' (low > high) retorna vazio: sem o split
    // em duas faixas o shift abaixo NUNCA seria batido.
    Carbon::setTestNow(Carbon::parse('2026-06-24 23:30:00', $tz));

    $shift = acoMakeShift(ACO_BIZ_WAGNER, '23:45:00'); // dentro de (23:30, 00:00)
    $att = acoMakeAttendance(ACO_BIZ_WAGNER, $shift->id);

    Artisan::call('pos:autoClockOutUser');

    $fresh = acoFresh($att->id);
    expect($fresh->clock_out_time)->not->toBeNull();
    expect(Carbon::parse($fresh->clock_out_time)->toDateTimeString())->toBe('2026-06-24 23:30:00');
    expect($fresh->clock_out_note)->toContain('automática');
});

// ------------------------------------------------------------------
// 8. WRAP — limite superior continua valendo (o split não vira "pega tudo")
// ------------------------------------------------------------------

it('NÃO bate shift fora da janela que cruza a meia-noite (00:15 > fim 00:00)', function () {
    $biz = Business::find(ACO_BIZ_WAGNER);
    if (! $biz) {
        $this->markTestSkipped('biz=1 não existe.');
    }
    $tz = $biz->time_zone ?: 'America/Sao_Paulo';
    // Mesma janela wrap [23:30:00, 00:00:00]: um shift às 00:15 está DEPOIS do fim
    // (00:00) e não pode ser batido — garante que a faixa ['00:00:00', fim] não
    // virou um catch-all do início da madrugada.
    Carbon::setTestNow(Carbon::parse('2026-06-24 23:30:00', $tz));

    $shift = acoMakeShift(ACO_BIZ_WAGNER, '00:15:00'); // fora de (23:30, 00:00)
    $att = acoMakeAttendance(ACO_BIZ_WAGNER, $shift->id);

    Artisan::call('pos:autoClockOutUser');

    expect(acoFresh($att->id)->clock_out_time)->toBeNull(); // intacto
});
