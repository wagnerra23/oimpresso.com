<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\PushDispositivo;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Registro do aparelho para o lembrete de bater ponto — ADR 0422 (PR 1).
 *
 * UCs de `resources/js/Pages/Ponto/Mobile/Index.casos.md` (UC-REPP-11/12/13), derivados da
 * ADR 0422 §3-§4. NÃO do controller.
 *
 * @covers-us US-PONTO-001
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 semeado pela lane. Transação
 * revertida por caso.
 */

const PDC_BIZ = 98;
const PDC_BIZ_OUTRO = 2;

function pdcUsuario(int $biz, bool $comColaborador = true): User
{
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'PDC teste', 'username' => 'pdc_' . uniqid(), 'password' => 'x',
        'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($comColaborador) {
        DB::table('ponto_colaborador_config')->insert([
            'business_id' => $biz, 'user_id' => $userId, 'matricula' => 'PDC-' . uniqid(),
            'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return User::findOrFail($userId);
}

/** Age como $u, com a sessão no business dele (como faz o SetSessionData). */
function pdcComo($teste, User $u)
{
    session(['user.business_id' => (int) $u->business_id, 'business.id' => (int) $u->business_id]);

    return $teste->actingAs($u);
}

function pdcLinhas(string $token)
{
    return DB::table('ponto_push_dispositivos')->where('token', $token)->get();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK exigem MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('ponto_push_dispositivos')
        || ! DB::table('business')->where('id', PDC_BIZ)->exists()
        || ! DB::table('business')->where('id', PDC_BIZ_OUTRO)->exists()) {
        $this->markTestSkipped('Tabela de aparelhos ou tenants 98/2 ausentes nesta lane.');
    }
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-REPP-11: registra o aparelho no MEU usuário e business, ignorando business_id/user_id do corpo', function () {
    $eu = pdcUsuario(PDC_BIZ);
    $outro = pdcUsuario(PDC_BIZ_OUTRO);
    $token = 'tok-' . uniqid();

    pdcComo($this, $eu)->postJson('/ponto/mobile/push/dispositivo', [
        'token' => $token, 'plataforma' => 'android',
        'business_id' => PDC_BIZ_OUTRO, 'user_id' => $outro->id,
    ])->assertOk()->assertJsonPath('ativo', true);

    $linhas = pdcLinhas($token);
    expect($linhas)->toHaveCount(1);
    expect((int) $linhas[0]->business_id)->toBe(PDC_BIZ);
    expect((int) $linhas[0]->user_id)->toBe((int) $eu->id);
    expect((bool) $linhas[0]->ativo)->toBeTrue();
    expect($linhas[0]->plataforma)->toBe('android');
});

it('UC-REPP-11: sem cadastro de ponto → 403 e nada gravado; plataforma inválida → 422', function () {
    $semPonto = pdcUsuario(PDC_BIZ, false);
    $token = 'tok-' . uniqid();

    pdcComo($this, $semPonto)->postJson('/ponto/mobile/push/dispositivo', ['token' => $token, 'plataforma' => 'ios'])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_colaborador');
    expect(pdcLinhas($token))->toHaveCount(0);

    pdcComo($this, pdcUsuario(PDC_BIZ))->postJson('/ponto/mobile/push/dispositivo', ['token' => $token, 'plataforma' => 'windows'])
        ->assertStatus(422);
    expect(pdcLinhas($token))->toHaveCount(0);
});

it('UC-REPP-11: o global scope esconde o aparelho de outro business', function () {
    $outro = pdcUsuario(PDC_BIZ_OUTRO);
    $token = 'tok-' . uniqid();
    pdcComo($this, $outro)->postJson('/ponto/mobile/push/dispositivo', ['token' => $token, 'plataforma' => 'ios'])->assertOk();

    session(['user.business_id' => PDC_BIZ, 'business.id' => PDC_BIZ]);
    expect(PushDispositivo::query()->where('token', $token)->count())->toBe(0);
    session(['user.business_id' => PDC_BIZ_OUTRO, 'business.id' => PDC_BIZ_OUTRO]);
    expect(PushDispositivo::query()->where('token', $token)->count())->toBe(1);
});

it('UC-REPP-12: aparelho compartilhado passa para quem registrou por último, numa linha só', function () {
    $antes = pdcUsuario(PDC_BIZ_OUTRO);
    $depois = pdcUsuario(PDC_BIZ);
    $token = 'tok-' . uniqid();

    pdcComo($this, $antes)->postJson('/ponto/mobile/push/dispositivo', ['token' => $token, 'plataforma' => 'android'])->assertOk();
    pdcComo($this, $antes)->deleteJson('/ponto/mobile/push/dispositivo', ['token' => $token])->assertOk();
    pdcComo($this, $depois)->postJson('/ponto/mobile/push/dispositivo', ['token' => $token, 'plataforma' => 'android'])->assertOk();

    $linhas = pdcLinhas($token);
    expect($linhas)->toHaveCount(1);
    expect((int) $linhas[0]->business_id)->toBe(PDC_BIZ);
    expect((int) $linhas[0]->user_id)->toBe((int) $depois->id);
    expect((bool) $linhas[0]->ativo)->toBeTrue();
});

it('UC-REPP-13: parar os lembretes desativa só o MEU aparelho; outro business não desativa o meu', function () {
    $eu = pdcUsuario(PDC_BIZ);
    $intruso = pdcUsuario(PDC_BIZ_OUTRO);
    $token = 'tok-' . uniqid();
    pdcComo($this, $eu)->postJson('/ponto/mobile/push/dispositivo', ['token' => $token, 'plataforma' => 'ios'])->assertOk();

    pdcComo($this, $intruso)->deleteJson('/ponto/mobile/push/dispositivo', ['token' => $token])
        ->assertOk()->assertJsonPath('afetados', 0);
    expect((bool) pdcLinhas($token)[0]->ativo)->toBeTrue();

    pdcComo($this, $eu)->deleteJson('/ponto/mobile/push/dispositivo', ['token' => $token])
        ->assertOk()->assertJsonPath('afetados', 1)->assertJsonPath('ativo', false);
    expect((bool) pdcLinhas($token)[0]->ativo)->toBeFalse();
    expect((int) pdcLinhas($token)[0]->user_id)->toBe((int) $eu->id);
});
