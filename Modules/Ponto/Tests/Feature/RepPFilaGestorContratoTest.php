<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\MobileMarcacaoService;
use Modules\Ponto\Tests\Feature\PontoTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(PontoTestCase::class);

/**
 * Fila do gestor do REP-P — seção nova em /ponto/aprovacoes ([W] 2026-09-29, thread 06).
 *
 * UCs de `resources/js/Pages/Ponto/Aprovacoes/Index.casos.md` (UC-PAPR-06..08), derivados de:
 * thread 06 §C (geofence SINALIZA para revisão) · thread 30 PR 3 (Validar/Recusar) · D3 da ata
 * 2026-09-14 (recusar = `Marcacao::anular()`, ORIGEM_ANULACAO). NÃO do `.tsx`.
 *
 * Guard da thread 30: "recusar não faz UPDATE nem DELETE em ponto_marcacoes" — conferido pela
 * linha da original idêntica antes e depois (e o trigger do MySQL barraria de qualquer jeito).
 *
 * Tier 0: tenant fictício 98 × adversário 99 (ADR 0358). Transação revertida por caso.
 */

const RPF_BIZ = 98;
const RPF_FORA = ['lat' => -28.336, 'lng' => -48.926];   // Termas do Gravatal/SC
const RPF_CENTRO = ['lat' => -27.595, 'lng' => -48.548]; // Florianópolis — centro do geofence

function rpfColaborador(int $bizId): int
{
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'RPF teste', 'username' => 'rpf_' . uniqid(), 'password' => 'x',
        'business_id' => $bizId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('ponto_colaborador_config')->insertGetId([
        'business_id' => $bizId, 'user_id' => $userId, 'matricula' => 'RPF-' . uniqid(),
        'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Pelo caminho real do REP-P — anti-fraude + MarcacaoService (NSR, hash, append-only). */
function rpfMarcar(int $bizId, int $colab, array $onde): Marcacao
{
    return app(MobileMarcacaoService::class)->registrarMarcacaoMobile($bizId, $colab, [
        'tipo' => Marcacao::TIPO_ENTRADA, 'lat' => $onde['lat'], 'lng' => $onde['lng'], 'accuracy' => 20,
        'device_uuid' => 'rpf-device', 'timestamp_device' => now()->toIso8601String(),
        'usuario_criador_id' => (int) DB::table('ponto_colaborador_config')->where('id', $colab)->value('user_id'),
    ]);
}

function rpfGestor(): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'RPF gestor', 'username' => 'rpf_g_' . uniqid(), 'password' => 'x',
        'business_id' => RPF_BIZ, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    Permission::firstOrCreate(['name' => 'ponto.access', 'guard_name' => 'web']);
    $u->givePermissionTo('ponto.access');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    session(['user.business_id' => RPF_BIZ, 'business.id' => RPF_BIZ]);

    return $u;
}

function rpfLinha(string $id): array
{
    return (array) DB::table('ponto_marcacoes')->where('id', $id)->first();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK + triggers exigem MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('ponto_marcacoes') || ! Schema::hasTable('activity_log')
        || ! DB::table('business')->where('id', RPF_BIZ)->exists()) {
        $this->markTestSkipped('Schema do Ponto/activity_log ou tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();
    $geofence = RPF_CENTRO + ['raio_metros' => 1000.0];
    config()->set('pontowr2.geofence.business_' . RPF_BIZ, $geofence);
    config()->set('pontowr2.geofence.business_99', $geofence);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-PAPR-06: a fila do REP-P traz só a marcação do celular FORA da área, do meu empregador (Tier 0)', function () {
    $fora = rpfMarcar(RPF_BIZ, rpfColaborador(RPF_BIZ), RPF_FORA);
    rpfMarcar(RPF_BIZ, rpfColaborador(RPF_BIZ), RPF_CENTRO);
    rpfMarcar($this->garantirBizAlheio(), rpfColaborador(99), RPF_FORA);

    $this->actingAs(rpfGestor());
    $p = $this->inertiaPartialGet('/ponto/aprovacoes', ['mobile'], 'Ponto/Aprovacoes/Index')->assertOk();

    expect(collect($p->json('props.mobile'))->pluck('id')->all())->toBe([(string) $fora->id]);
    $p->assertJsonPath('props.mobile.0.estado', 'PENDENTE');
});

it('UC-PAPR-07: validar registra na trilha do meu empregador e não toca a marcação', function () {
    $m = rpfMarcar(RPF_BIZ, rpfColaborador(RPF_BIZ), RPF_FORA);
    $antes = rpfLinha((string) $m->id);
    $this->actingAs(rpfGestor());

    $this->post("/ponto/aprovacoes/mobile/{$m->id}/validar")->assertRedirect();

    expect(rpfLinha((string) $m->id))->toBe($antes);
    $a = DB::table('activity_log')->where('log_name', 'ponto.repp')->where('event', 'validada')
        ->where('business_id', RPF_BIZ)->latest('id')->first();
    expect($a)->not->toBeNull();
    expect(json_decode((string) $a->properties, true)['marcacao_id'])->toBe((string) $m->id);
    $this->inertiaPartialGet('/ponto/aprovacoes', ['mobile'], 'Ponto/Aprovacoes/Index')
        ->assertJsonPath('props.mobile.0.estado', 'VALIDADA');
});

it('UC-PAPR-08: recusar grava anulação NOVA apontando a original, a original fica igual, e não se decide duas vezes', function () {
    $m = rpfMarcar(RPF_BIZ, rpfColaborador(RPF_BIZ), RPF_FORA);
    $antes = rpfLinha((string) $m->id);
    $this->actingAs(rpfGestor());

    $this->post("/ponto/aprovacoes/mobile/{$m->id}/recusar")->assertRedirect();

    expect(rpfLinha((string) $m->id))->toBe($antes);
    $anul = DB::table('ponto_marcacoes')->where('marcacao_anulada_id', $m->id)->get();
    expect($anul)->toHaveCount(1);
    expect($anul->first()->origem)->toBe(Marcacao::ORIGEM_ANULACAO);
    expect((int) $anul->first()->business_id)->toBe(RPF_BIZ);

    $this->post("/ponto/aprovacoes/mobile/{$m->id}/recusar")->assertStatus(422);
    $this->post("/ponto/aprovacoes/mobile/{$m->id}/validar")->assertStatus(422);
    expect(DB::table('ponto_marcacoes')->where('marcacao_anulada_id', $m->id)->count())->toBe(1);
    expect(DB::table('activity_log')->where('log_name', 'ponto.repp')->where('business_id', RPF_BIZ)
        ->where('properties', 'like', '%' . $m->id . '%')->count())->toBe(0);
});

it('UC-PAPR-06: marcação de OUTRO empregador não é decidível daqui — 404, nada gravado (Tier 0)', function () {
    $alheia = rpfMarcar($this->garantirBizAlheio(), rpfColaborador(99), RPF_FORA);
    $this->actingAs(rpfGestor());

    $this->post("/ponto/aprovacoes/mobile/{$alheia->id}/recusar")->assertNotFound();
    expect(DB::table('ponto_marcacoes')->where('marcacao_anulada_id', $alheia->id)->count())->toBe(0);
});
