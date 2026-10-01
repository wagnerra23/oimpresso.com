<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Jobs\EnviarLembretePontoJob;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Envio do lembrete de bater ponto — ADR 0423 §5-§6 (PR 2b).
 *
 * UCs UC-REPP-14/15 de `resources/js/Pages/Ponto/Mobile/Index.casos.md`, derivados da ADR.
 * O Job é falso (`Bus::fake`) — o envio em si tem o seu teste em EnviarLembretePontoJobTest.
 *
 * @covers-us US-PONTO-001
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 semeado pela lane. Transação
 * revertida por caso.
 */

const LPC_BIZ = 98;
const LPC_BIZ_OUTRO = 2;

/** Colaborador com escala FIXA e turno de HOJE 08:00-12:00/13:00-17:00, com aparelho ativo. */
function lpcColaborador(int $biz, string $token): User
{
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'LPC teste', 'username' => 'lpc_' . uniqid(), 'password' => 'x',
        'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $escalaId = DB::table('ponto_escalas')->insertGetId([
        'business_id' => $biz, 'nome' => 'LPC ' . uniqid(), 'tipo' => 'FIXA',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('ponto_escala_turnos')->insert([
        'escala_id' => $escalaId, 'dia_semana' => now()->dayOfWeek, 'hora_entrada' => '08:00:00',
        'hora_almoco_inicio' => '12:00:00', 'hora_almoco_fim' => '13:00:00', 'hora_saida' => '17:00:00',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('ponto_colaborador_config')->insert([
        'business_id' => $biz, 'user_id' => $userId, 'matricula' => 'LPC-' . uniqid(),
        'controla_ponto' => true, 'admissao' => '2020-01-01', 'escala_atual_id' => $escalaId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('ponto_push_dispositivos')->insert([
        'business_id' => $biz, 'user_id' => $userId, 'token' => $token, 'plataforma' => 'android',
        'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return User::findOrFail($userId);
}

/** Despachos do Job para um usuário. */
function lpcDespachosPara(User $u)
{
    return Bus::dispatched(EnviarLembretePontoJob::class, fn ($j) => $j->userId === (int) $u->id);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK + triggers exigem MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('ponto_push_envios')
        || ! DB::table('business')->where('id', LPC_BIZ)->exists()
        || ! DB::table('business')->where('id', LPC_BIZ_OUTRO)->exists()) {
        $this->markTestSkipped('Tabelas de push ou tenants 98/2 ausentes nesta lane.');
    }
    DB::beginTransaction();

    Carbon::setTestNow(now()->setTime(7, 52));
    config()->set('pontowr2.push.enabled', true);
});

afterEach(function () {
    Carbon::setTestNow();
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-REPP-14: 8 min antes da entrada despacha UM lembrete por colaborador, cada um com o SEU business; o tick seguinte não repete', function () {
    Bus::fake([EnviarLembretePontoJob::class]);
    $u = lpcColaborador(LPC_BIZ, 'tok-' . uniqid());
    $outro = lpcColaborador(LPC_BIZ_OUTRO, 'tok-' . uniqid());

    $this->artisan('ponto:lembretes-push')->assertSuccessful();
    expect(lpcDespachosPara($outro)->first()->businessId)->toBe(LPC_BIZ_OUTRO);
    $despachos = lpcDespachosPara($u);
    expect($despachos)->toHaveCount(1);
    expect($despachos->first()->businessId)->toBe(LPC_BIZ);
    expect($despachos->first()->tipo)->toBe(Marcacao::TIPO_ENTRADA);
    expect($despachos->first()->hora)->toBe('08:00');

    $this->artisan('ponto:lembretes-push')->assertSuccessful();
    expect(lpcDespachosPara($u))->toHaveCount(1);
    expect(DB::table('ponto_push_envios')->where('user_id', $u->id)->count())->toBe(1);
});

it('UC-REPP-14: fora da janela, com a flag desligada ou com o aparelho desativado, nada sai', function () {
    Bus::fake([EnviarLembretePontoJob::class]);
    $u = lpcColaborador(LPC_BIZ, 'tok-' . uniqid());

    Carbon::setTestNow(now()->setTime(7, 30));
    $this->artisan('ponto:lembretes-push')->assertSuccessful();
    expect(lpcDespachosPara($u))->toHaveCount(0);

    Carbon::setTestNow(now()->setTime(7, 52));
    config()->set('pontowr2.push.enabled', false);
    $this->artisan('ponto:lembretes-push')->assertSuccessful();
    expect(lpcDespachosPara($u))->toHaveCount(0);

    config()->set('pontowr2.push.enabled', true);
    DB::table('ponto_push_dispositivos')->where('user_id', $u->id)->update(['ativo' => false]);
    $this->artisan('ponto:lembretes-push')->assertSuccessful();
    expect(lpcDespachosPara($u))->toHaveCount(0);
});

it('UC-REPP-15: quem já bateu a entrada não recebe o lembrete dela', function () {
    Bus::fake([EnviarLembretePontoJob::class]);
    $u = lpcColaborador(LPC_BIZ, 'tok-' . uniqid());

    session(['user.business_id' => LPC_BIZ, 'business.id' => LPC_BIZ]);
    $this->actingAs($u)->postJson('/ponto/mobile/marcar', [
        'tipo' => Marcacao::TIPO_ENTRADA, 'lat' => -28.336, 'lng' => -48.926, 'accuracy' => 15,
        'device_uuid' => 'lpc-device', 'timestamp_device' => now()->toIso8601String(),
    ])->assertStatus(201);

    $this->artisan('ponto:lembretes-push')->assertSuccessful();
    expect(lpcDespachosPara($u))->toHaveCount(0);
});
