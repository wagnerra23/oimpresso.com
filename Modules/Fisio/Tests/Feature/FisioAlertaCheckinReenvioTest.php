<?php

declare(strict_types=1);
// @covers-us US-FISIO-007
// Cobre UC-FPP-13 (Fisio/Public/Portal.casos.md): o aviso de sinal de alerta não se perde.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Modules\Fisio\Notifications\SinalDeAlertaNotification;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Auditoria do Fisio d4 (2026-11-05): o aviso ao profissional roda depois do check-in gravado.
 * Antes, uma falha no aviso virava 500 no portal e, no reenvio, os sinais já estavam no
 * check-in: ninguém era avisado e não sobrava rastro.
 *
 * Contrato: a falha não desfaz o check-in, vai para o log e o sinal fica pendente
 * (`red_flags` − `red_flags_avisados`); o `fisio:reenviar-alertas` entrega depois, cada
 * check-in ao profissional da própria empresa (98 × 99).
 *
 * Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 19:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    $this->prog = F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->u98->id);
    $this->itens = F::itens($this->prog);
    $this->token = F::portalPronto($this, $this->p);
});

afterEach(fn () => Carbon::setTestNow());

function farEnviar($t, array $flags)
{
    return $t->postJson('/fisio/p/'.$t->token.'/checkin', [
        'done_item_ids' => $t->itens,
        'pain' => 4,
        'effort' => 'adequado',
        'red_flags' => $flags,
    ]);
}

function farAvisados(int $patientId): ?array
{
    $v = DB::table('fisio_checkins')->where('patient_id', $patientId)->value('red_flags_avisados');

    return $v === null ? null : json_decode((string) $v, true);
}

it('UC-FPP-13 · aviso que falha não desfaz o check-in, deixa log e o sinal fica pendente', function () {
    Log::spy();
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('canal do sino fora'));

    farEnviar($this, ['febre'])->assertCreated();

    $linha = DB::table('fisio_checkins')->where('patient_id', $this->p->id)->first();
    expect($linha)->not->toBeNull();
    expect(json_decode((string) $linha->red_flags, true))->toBe(['febre']);
    expect(farAvisados($this->p->id))->toBe([]);
    Log::shouldHaveReceived('error')->withArgs(
        fn ($msg, $ctx = []) => $msg === 'fisio.alerta_nao_enviado'
            && $ctx['business_id'] === $this->b98
            && $ctx['checkin_id'] === (int) $linha->id
    );
});

it('UC-FPP-13 · o reenvio do mesmo dia tenta de novo o aviso que falhou', function () {
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('canal do sino fora'));
    farEnviar($this, ['febre'])->assertCreated();

    Notification::fake();
    farEnviar($this, ['febre'])->assertOk();

    Notification::assertSentTo($this->u98, SinalDeAlertaNotification::class,
        fn ($n) => $n->patientId === $this->p->id && $n->redFlags === ['febre']);
    expect(farAvisados($this->p->id))->toBe(['febre']);
});

it('UC-FPP-13 · fisio:reenviar-alertas entrega o pendente na empresa certa e não repete', function () {
    $b99 = (int) $this->seededSupportClientTenant()->id;
    $u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    $p99 = F::paciente($b99, $u99->id);
    $prog99 = F::programa($p99, [F::exercicio(null, 'Prancha')], $u99->id);

    // Dois check-ins com aviso pendente (red_flags_avisados nulo = nada avisado), um por empresa.
    $c98 = F::checkin($this->p, $this->prog, '2026-10-14', [], 5, 'adequado', ['febre']);
    $c99 = F::checkin($p99, $prog99, '2026-10-14', [], 5, 'adequado', ['dormencia_progressiva']);
    expect(DB::table('fisio_checkins')->whereIn('id', [$c98->id, $c99->id])->whereNull('red_flags_avisados')->count())->toBe(2);

    Notification::fake();
    $this->artisan('fisio:reenviar-alertas')->assertSuccessful();

    Notification::assertSentTo($this->u98, SinalDeAlertaNotification::class,
        fn ($n) => $n->businessId === $this->b98 && $n->checkinId === $c98->id && $n->redFlags === ['febre']);
    Notification::assertSentTo($u99, SinalDeAlertaNotification::class,
        fn ($n) => $n->businessId === $b99 && $n->checkinId === $c99->id && $n->redFlags === ['dormencia_progressiva']);
    Notification::assertNotSentTo($this->u98, SinalDeAlertaNotification::class, fn ($n) => $n->businessId === $b99);
    Notification::assertNotSentTo($u99, SinalDeAlertaNotification::class, fn ($n) => $n->businessId === $this->b98);
    expect(farAvisados($this->p->id))->toBe(['febre']);
    expect(farAvisados($p99->id))->toBe(['dormencia_progressiva']);

    // Segunda rodada: nada pendente, nada enviado.
    Notification::fake();
    $this->artisan('fisio:reenviar-alertas')->assertSuccessful();
    Notification::assertNothingSent();
});
