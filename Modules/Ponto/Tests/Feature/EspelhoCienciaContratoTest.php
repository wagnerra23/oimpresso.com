<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Modules\Ponto\Entities\EspelhoCiencia;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Ciência do colaborador no espelho mensal — US-PONTO-020.
 *
 * Âncora (não o código): a lacuna "assinatura do espelho mensal pelo colaborador" (2026-10-25),
 * o art. 84 da Portaria MTP 671/2021 (espelho com acesso do trabalhador ao menos mensal) e a
 * ADR 0413 (competência fechada não reabre — logo é o único espelho que não muda depois).
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 semeado pela lane. Transação
 * revertida por caso. Guard api via Passport::actingAs.
 *
 * @covers-us US-PONTO-020
 */

const CIEN_BIZ = 98;
const CIEN_BIZ_OUTRO = 2;
const CIEN_MES = '2025-03';

function cienUsuario(int $biz): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'CIEN', 'last_name' => 'Teste', 'username' => 'cien_' . uniqid(), 'password' => 'x',
        'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('ponto_colaborador_config')->insert([
        'business_id' => $biz, 'user_id' => $id, 'matricula' => 'CIEN-' . uniqid(),
        'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return User::findOrFail($id);
}

function cienFechar(int $biz, int $userId, string $mes = CIEN_MES): void
{
    if (DB::table('ponto_competencias')->where('business_id', $biz)->where('competencia', $mes . '-01')->exists()) {
        return; // append-only: a competência já fechada por outro run serve igual
    }
    DB::table('ponto_competencias')->insert([
        'business_id' => $biz, 'competencia' => $mes . '-01', 'fechada_por' => $userId, 'fechada_em' => now(),
    ]);
}

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK + triggers exigem MySQL (ADR 0358).');
    }
    foreach (['ponto_competencias', 'ponto_espelho_ciencias', 'ponto_colaborador_config'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
    if (! DB::table('business')->where('id', CIEN_BIZ)->exists()
        || ! DB::table('business')->where('id', CIEN_BIZ_OUTRO)->exists()) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-REPP-17 · competência ainda aberta: a ciência é recusada e nada é gravado', function () {
    $eu = cienUsuario(CIEN_BIZ);
    Passport::actingAs($eu, [], 'api');
    $mesAberto = '2025-04';
    if (DB::table('ponto_competencias')->where('business_id', CIEN_BIZ)->where('competencia', $mesAberto . '-01')->exists()) {
        $this->markTestSkipped('O mês de controle já foi fechado no tenant 98 por outro teste.');
    }

    $this->postJson('/ponto/api/espelho/ciencia', ['mes' => $mesAberto])
        ->assertStatus(422)->assertJsonPath('erro', 'competencia_aberta');

    $this->assertSame(0, DB::table('ponto_espelho_ciencias')->where('business_id', CIEN_BIZ)
        ->where('competencia', $mesAberto . '-01')->count());
    $this->getJson('/ponto/api/espelho?mes=' . $mesAberto)->assertOk()
        ->assertJsonPath('competencia_fechada', false)->assertJsonPath('ciencia', null);
});

it('UC-REPP-18 · competência fechada: o colaborador dá ciência e o espelho passa a mostrá-la, conferindo', function () {
    $eu = cienUsuario(CIEN_BIZ);
    cienFechar(CIEN_BIZ, $eu->id);
    Passport::actingAs($eu, [], 'api');

    $this->postJson('/ponto/api/espelho/ciencia', ['mes' => CIEN_MES])
        ->assertStatus(201)->assertJsonPath('ciencia.confere', true);

    $r = $this->getJson('/ponto/api/espelho?mes=' . CIEN_MES)->assertOk();
    $this->assertTrue($r->json('competencia_fechada'));
    $this->assertTrue($r->json('ciencia.confere'));
    $this->assertNotEmpty($r->json('ciencia.ciente_em'));
});

it('UC-REPP-19 · uma ciência por competência: a segunda é 409 e não cria outra linha', function () {
    $eu = cienUsuario(CIEN_BIZ);
    cienFechar(CIEN_BIZ, $eu->id);
    Passport::actingAs($eu, [], 'api');

    $this->postJson('/ponto/api/espelho/ciencia', ['mes' => CIEN_MES])->assertStatus(201);
    $this->postJson('/ponto/api/espelho/ciencia', ['mes' => CIEN_MES])
        ->assertStatus(409)->assertJsonPath('erro', 'ja_ciente');

    $colabId = DB::table('ponto_colaborador_config')->where('user_id', $eu->id)->value('id');
    $this->assertSame(1, DB::table('ponto_espelho_ciencias')->where('colaborador_config_id', $colabId)->count());
});

it('UC-REPP-20 · a ciência é append-only: nem o model nem o banco deixam mudar ou apagar', function () {
    $eu = cienUsuario(CIEN_BIZ);
    cienFechar(CIEN_BIZ, $eu->id);
    Passport::actingAs($eu, [], 'api');
    $this->postJson('/ponto/api/espelho/ciencia', ['mes' => CIEN_MES])->assertStatus(201);

    $colabId = DB::table('ponto_colaborador_config')->where('user_id', $eu->id)->value('id');
    $ciencia = EspelhoCiencia::withoutGlobalScopes()->where('colaborador_config_id', $colabId)->firstOrFail(); // SUPERADMIN: leitura de fixture

    expect(fn () => $ciencia->update(['hash_espelho' => str_repeat('0', 64)]))->toThrow(RuntimeException::class);
    expect(fn () => $ciencia->delete())->toThrow(RuntimeException::class);
    // A defesa do banco, por fora do model: o trigger recusa. SAVEPOINT para não perder a transação do caso.
    DB::beginTransaction();
    expect(fn () => DB::table('ponto_espelho_ciencias')->where('id', $ciencia->id)->update(['hash_espelho' => str_repeat('0', 64)]))
        ->toThrow(\Illuminate\Database\QueryException::class);
    DB::rollBack();
});

it('UC-REPP-21 · competência fechada só em OUTRO empregador não libera a ciência (ADR 0093)', function () {
    $eu = cienUsuario(CIEN_BIZ);
    $mes = '2025-05';
    if (DB::table('ponto_competencias')->where('business_id', CIEN_BIZ)->where('competencia', $mes . '-01')->exists()) {
        $this->markTestSkipped('O mês de controle já foi fechado no tenant 98 por outro teste.');
    }
    $outro = DB::table('users')->where('business_id', CIEN_BIZ_OUTRO)->value('id')
        ?? $this->markTestSkipped('Sem user no business 2.');
    cienFechar(CIEN_BIZ_OUTRO, (int) $outro, $mes);
    Passport::actingAs($eu, [], 'api');

    $this->postJson('/ponto/api/espelho/ciencia', ['mes' => $mes])
        ->assertStatus(422)->assertJsonPath('erro', 'competencia_aberta');
});

it('UC-REPP-22 · se o espelho muda depois da ciência, ela deixa de conferir — e continua registrada', function () {
    $eu = cienUsuario(CIEN_BIZ);
    cienFechar(CIEN_BIZ, $eu->id);
    Passport::actingAs($eu, [], 'api');
    $this->postJson('/ponto/api/espelho/ciencia', ['mes' => CIEN_MES])->assertStatus(201);

    // Uma apuração nova no mês (o que uma anulação com reapuração produziria) muda o espelho.
    $colabId = DB::table('ponto_colaborador_config')->where('user_id', $eu->id)->value('id');
    DB::table('ponto_apuracao_dia')->insert([
        'business_id' => CIEN_BIZ, 'colaborador_config_id' => $colabId, 'data' => CIEN_MES . '-10',
        'estado' => 'CALCULADO', 'prevista_carga_minutos' => 480, 'realizada_trabalhada_minutos' => 300,
        'falta_minutos' => 180, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = $this->getJson('/ponto/api/espelho?mes=' . CIEN_MES)->assertOk();
    $this->assertNotNull($r->json('ciencia'), 'A ciência dada não some quando o espelho muda.');
    $this->assertFalse($r->json('ciencia.confere'), 'Espelho diferente do que o colaborador viu tem de aparecer como não conferindo.');
});

it('UC-REPP-23 · a tela "Meu espelho" mostra o último mês fechado e, depois da ciência pela própria tela, quando foi dada', function () {
    $eu = cienUsuario(CIEN_BIZ);
    // Mês sintético bem à frente: é o último fechado do tenant 98 qualquer que seja o resto do banco.
    $mes = '2099-01';
    cienFechar(CIEN_BIZ, $eu->id, $mes);
    $this->actingAs($eu);

    $antes = $this->inertiaPartialGet('/ponto/mobile', ['ciencia_espelho'], 'Ponto/Mobile/Index')->assertOk();
    $this->assertSame($mes, $antes->json('props.ciencia_espelho.mes'));
    $this->assertTrue($antes->json('props.ciencia_espelho.competencia_fechada'));
    $this->assertNull($antes->json('props.ciencia_espelho.ciencia'));

    $this->postJson('/ponto/mobile/espelho/ciencia', ['mes' => $mes])->assertStatus(201);

    $depois = $this->inertiaPartialGet('/ponto/mobile', ['ciencia_espelho'], 'Ponto/Mobile/Index')->assertOk();
    $this->assertNotNull($depois->json('props.ciencia_espelho.ciencia.ciente_em'));
    $this->assertTrue($depois->json('props.ciencia_espelho.ciencia.confere'));
});
