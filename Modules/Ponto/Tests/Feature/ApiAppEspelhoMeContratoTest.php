<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * API do app das lojas (oimpresso-app, telas próprias — decisão [W] 2026-10-01):
 * GET /ponto/api/me · GET /ponto/api/espelho?mes= · GET /ponto/api/intercorrencias/tipos.
 *
 * Contrato vindo do consumidor (o app, PR #6 do oimpresso-app: cabeçalho nome+matrícula,
 * "Meu espelho" com totais+linhas, motivos aceitos pelo ERP) e do mesmo dono do cálculo que a
 * tela web /ponto/mobile já usa (EspelhoController::buildTotaisEspelho/buildLinhasEspelho).
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 semeado pela lane. Transação
 * revertida por caso. Guard api via Passport::actingAs (sem senha, sem client).
 */

const AEM_BIZ = 98;
const AEM_BIZ_OUTRO = 2;

function aemUsuario(int $biz, bool $comColaborador = true, string $matricula = ''): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'AEM', 'last_name' => 'Teste' . $biz, 'username' => 'aem_' . uniqid(), 'password' => 'x',
        'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($comColaborador) {
        DB::table('ponto_colaborador_config')->insert([
            'business_id' => $biz, 'user_id' => $id, 'matricula' => $matricula ?: 'AEM-' . uniqid(),
            'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return User::findOrFail($id);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK exigem MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', AEM_BIZ)->exists()
        || ! DB::table('business')->where('id', AEM_BIZ_OUTRO)->exists()) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('sem token as 3 rotas respondem 401', function () {
    foreach (['/ponto/api/me', '/ponto/api/espelho', '/ponto/api/intercorrencias/tipos'] as $url) {
        $this->getJson($url)->assertStatus(401);
    }
});

it('/me devolve o MEU colaborador e a MINHA empresa — nunca a do outro business', function () {
    $eu = aemUsuario(AEM_BIZ, true, 'AEM-EU');
    aemUsuario(AEM_BIZ_OUTRO, true, 'AEM-OUTRO');
    Passport::actingAs($eu, [], 'api');

    $r = $this->getJson('/ponto/api/me')->assertOk();

    expect($r->json('matricula'))->toBe('AEM-EU');
    expect($r->json('nome'))->toBe('AEM Teste' . AEM_BIZ);
    expect($r->json('empresa'))->toBe((string) DB::table('business')->where('id', AEM_BIZ)->value('name'));
    expect($r->json('limites.accuracy_max'))->toBeInt();
    expect($r->json('limites.drift_max'))->toBeInt();
});

it('/me e /espelho sem cadastro de ponto → 403 sem_colaborador', function () {
    Passport::actingAs(aemUsuario(AEM_BIZ, false), [], 'api');

    $this->getJson('/ponto/api/me')->assertStatus(403)->assertJsonPath('erro', 'sem_colaborador');
    $this->getJson('/ponto/api/espelho')->assertStatus(403)->assertJsonPath('erro', 'sem_colaborador');
});

it('/espelho sem mes devolve o mês corrente com totais e linhas', function () {
    Passport::actingAs(aemUsuario(AEM_BIZ), [], 'api');

    $r = $this->getJson('/ponto/api/espelho')->assertOk();

    expect($r->json('mes'))->toBe(now()->format('Y-m'));
    foreach (['trabalhado', 'atraso', 'falta', 'he_diurna', 'he_noturna', 'divergencias'] as $k) {
        expect($r->json('totais'))->toHaveKey($k);
    }
    expect($r->json('linhas'))->toBeArray()->not->toBeEmpty();
    expect($r->json('linhas.0'))->toHaveKeys(['data', 'dow', 'dia', 'is_weekend', 'trabalhado', 'divergencia', 'estado', 'marcacoes']);
});

it('/espelho com mes de outro mês passado é aceito; futuro ou malformado → 422', function () {
    Passport::actingAs(aemUsuario(AEM_BIZ), [], 'api');

    $passado = now()->subMonthNoOverflow()->format('Y-m');
    $this->getJson('/ponto/api/espelho?mes=' . $passado)->assertOk()->assertJsonPath('mes', $passado);

    $futuro = now()->addMonthNoOverflow()->format('Y-m');
    foreach ([$futuro, '2026-13', 'abc', '2026-1'] as $mes) {
        $this->getJson('/ponto/api/espelho?mes=' . $mes)->assertStatus(422)->assertJsonPath('erro', 'mes_invalido');
    }
});

it('/intercorrencias/tipos devolve os motivos que o ERP aceita', function () {
    Passport::actingAs(aemUsuario(AEM_BIZ), [], 'api');

    $valores = array_column($this->getJson('/ponto/api/intercorrencias/tipos')->assertOk()->json(), 'value');

    expect($valores)->toContain('ESQUECIMENTO_MARCACAO');
    expect($valores)->toContain('ATESTADO_MEDICO');
    expect($valores)->toContain('PROBLEMA_EQUIPAMENTO');
});
