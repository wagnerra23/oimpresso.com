<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\MobileMarcacaoService;
use Modules\Ponto\Tests\Feature\PontoTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(PontoTestCase::class);

/**
 * API de Marcações a validar do app das lojas (tela 39) — GET /api/app/ponto/aprovacoes,
 * POST …/{id}/validar e POST …/{id}/recusar.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §12.1 + decisão [W] relatada pela
 * sessão do app (só fora do geofence; recusa = ANULAÇÃO, nunca UPDATE/DELETE — Portaria
 * 671/2021; acesso = o das aprovações do Ponto). NÃO derivado do controller.
 *
 * Tier 0: tenant fictício 98 × adversário 99 (ADR 0358). Transação revertida por caso.
 */

const APA_BIZ = 98;
const APA_FORA = ['lat' => -28.336, 'lng' => -48.926];   // Termas do Gravatal/SC
const APA_CENTRO = ['lat' => -27.595, 'lng' => -48.548]; // Florianópolis — centro do geofence

function apaColaborador(int $bizId, string $nome): int
{
    $userId = DB::table('users')->insertGetId([
        'first_name' => $nome, 'username' => 'apa_' . uniqid(), 'password' => 'x',
        'business_id' => $bizId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('ponto_colaborador_config')->insertGetId([
        'business_id' => $bizId, 'user_id' => $userId, 'matricula' => 'APA-' . uniqid(),
        'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Pelo caminho real do REP-P — anti-fraude + MarcacaoService (NSR, hash, append-only). */
function apaMarcar(int $bizId, int $colab, array $onde): Marcacao
{
    return app(MobileMarcacaoService::class)->registrarMarcacaoMobile($bizId, $colab, [
        'tipo' => Marcacao::TIPO_ENTRADA, 'lat' => $onde['lat'], 'lng' => $onde['lng'], 'accuracy' => 20,
        'device_uuid' => 'apa-device', 'timestamp_device' => now()->toIso8601String(),
        'usuario_criador_id' => (int) DB::table('ponto_colaborador_config')->where('id', $colab)->value('user_id'),
    ]);
}

function apaUsuario(array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APA gestor', 'username' => 'apa_g_' . uniqid(), 'password' => 'x',
        'business_id' => APA_BIZ, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    foreach ($permissoes as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $u->givePermissionTo($p);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $u;
}

function apaLinha(string $id): array
{
    return (array) DB::table('ponto_marcacoes')->where('id', $id)->first();
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK + triggers exigem MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('ponto_marcacoes') || ! Schema::hasTable('activity_log')
        || ! DB::table('business')->where('id', APA_BIZ)->exists()) {
        $this->markTestSkipped('Schema do Ponto/activity_log ou tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();
    $geofence = APA_CENTRO + ['raio_metros' => 1000.0];
    config()->set('pontowr2.geofence.business_' . APA_BIZ, $geofence);
    config()->set('pontowr2.geofence.business_99', $geofence);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-APP39-01: a fila traz só a marcação do celular FORA da área, do meu business, no formato do contrato (Tier 0)', function () {
    $fora = apaMarcar(APA_BIZ, apaColaborador(APA_BIZ, 'Ana Fora'), APA_FORA);
    $dentro = apaMarcar(APA_BIZ, apaColaborador(APA_BIZ, 'Beto Dentro'), APA_CENTRO);
    $alheia = apaMarcar($this->garantirBizAlheio(), apaColaborador(99, 'Caio Alheio'), APA_FORA);
    Passport::actingAs(apaUsuario(['ponto.access']), [], 'api');

    $r = $this->getJson('/api/app/ponto/aprovacoes')->assertOk();

    $ids = collect($r->json('itens'))->pluck('id')->all();
    expect($ids)->toBe([(string) $fora->id]);
    expect($ids)->not->toContain((string) $dentro->id);
    expect($ids)->not->toContain((string) $alheia->id);
    $item = $r->json('itens.0');
    expect($item['colaborador_nome'])->toBe('Ana Fora');
    expect($item['tipo'])->toBe('ENTRADA');
    expect($item['nsr'])->toBe((int) $fora->nsr);
    expect($item['estado'])->toBe('pendente');
    expect($item['hash_curto'])->toBe(substr((string) $fora->hash, 0, 8));
    expect($item['local_texto'])->toContain('km do local de trabalho');
    expect($item['gps_precisao_m'])->toBeNull();
    expect($r->json('contadores'))->toBe(['pendente' => 1, 'validada' => 0, 'recusada' => 0, 'todas' => 1]);
    expect($r->json('pode_recusar'))->toBeFalse();
});

it('UC-APP39-02: validar registra na trilha e não toca a marcação; o filtro e os contadores acompanham', function () {
    $m = apaMarcar(APA_BIZ, apaColaborador(APA_BIZ, 'Ana Fora'), APA_FORA);
    $antes = apaLinha((string) $m->id);
    Passport::actingAs(apaUsuario(['ponto.access']), [], 'api');

    $this->postJson("/api/app/ponto/aprovacoes/{$m->id}/validar")->assertOk()->assertExactJson(['estado' => 'validada']);

    expect(apaLinha((string) $m->id))->toBe($antes);
    $a = DB::table('activity_log')->where('log_name', 'ponto.repp')->where('event', 'validada')
        ->where('business_id', APA_BIZ)->latest('id')->first();
    expect($a)->not->toBeNull();
    expect(json_decode((string) $a->properties, true)['marcacao_id'])->toBe((string) $m->id);

    $this->getJson('/api/app/ponto/aprovacoes')->assertOk()->assertJsonCount(0, 'itens');
    $r = $this->getJson('/api/app/ponto/aprovacoes?estado=validada')->assertOk();
    expect(collect($r->json('itens'))->pluck('id')->all())->toBe([(string) $m->id]);
    expect($r->json('contadores'))->toBe(['pendente' => 0, 'validada' => 1, 'recusada' => 0, 'todas' => 1]);
});

it('UC-APP39-03: recusar grava anulação NOVA apontando a original, a original fica igual, e a 2ª decisão é 409', function () {
    $m = apaMarcar(APA_BIZ, apaColaborador(APA_BIZ, 'Ana Fora'), APA_FORA);
    $antes = apaLinha((string) $m->id);
    Passport::actingAs(apaUsuario(['ponto.access', 'ponto.aprovacoes.manage']), [], 'api');

    $r = $this->postJson("/api/app/ponto/aprovacoes/{$m->id}/recusar")->assertOk();

    expect(apaLinha((string) $m->id))->toBe($antes);
    $anul = DB::table('ponto_marcacoes')->where('marcacao_anulada_id', $m->id)->get();
    expect($anul)->toHaveCount(1);
    expect($anul->first()->origem)->toBe(Marcacao::ORIGEM_ANULACAO);
    expect((int) $anul->first()->business_id)->toBe(APA_BIZ);
    expect($r->json())->toBe(['estado' => 'recusada', 'nsr_anulacao' => (int) $anul->first()->nsr]);

    $this->postJson("/api/app/ponto/aprovacoes/{$m->id}/recusar")->assertStatus(409)->assertJsonPath('erro', 'ja_revisada');
    $this->postJson("/api/app/ponto/aprovacoes/{$m->id}/validar")->assertStatus(409)->assertJsonPath('erro', 'ja_revisada');
    expect(DB::table('ponto_marcacoes')->where('marcacao_anulada_id', $m->id)->count())->toBe(1);
    $this->getJson('/api/app/ponto/aprovacoes?estado=recusada')->assertOk()->assertJsonPath('itens.0.estado', 'recusada');
});

it('UC-APP39-04: marcação de OUTRO business é 404 e nada é gravado (Tier 0)', function () {
    $alheia = apaMarcar($this->garantirBizAlheio(), apaColaborador(99, 'Caio Alheio'), APA_FORA);
    // Com as duas permissões: senão o 403 responde antes e esconde o isolamento.
    Passport::actingAs(apaUsuario(['ponto.access', 'ponto.aprovacoes.manage']), [], 'api');

    $this->postJson("/api/app/ponto/aprovacoes/{$alheia->id}/recusar")->assertNotFound()->assertJsonPath('erro', 'nao_encontrado');
    $this->postJson("/api/app/ponto/aprovacoes/{$alheia->id}/validar")->assertNotFound();
    expect(DB::table('ponto_marcacoes')->where('marcacao_anulada_id', $alheia->id)->count())->toBe(0);
    expect(DB::table('activity_log')->where('log_name', 'ponto.repp')
        ->where('properties', 'like', '%' . $alheia->id . '%')->count())->toBe(0);
});

it('UC-APP39-05: sem acesso ao Ponto é 403 em tudo e a área ponto_gestor some do Início; recusar exige ponto.aprovacoes.manage', function () {
    $m = apaMarcar(APA_BIZ, apaColaborador(APA_BIZ, 'Ana Fora'), APA_FORA);

    Passport::actingAs(apaUsuario([]), [], 'api');
    $this->getJson('/api/app/ponto/aprovacoes')->assertForbidden()->assertJsonPath('erro', 'sem_permissao');
    $this->postJson("/api/app/ponto/aprovacoes/{$m->id}/validar")->assertForbidden();
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->not->toContain('ponto_gestor');

    Passport::actingAs(apaUsuario(['ponto.access']), [], 'api');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->toContain('ponto_gestor');
    $this->postJson("/api/app/ponto/aprovacoes/{$m->id}/recusar")->assertForbidden()->assertJsonPath('erro', 'sem_permissao');
    expect(DB::table('ponto_marcacoes')->where('marcacao_anulada_id', $m->id)->count())->toBe(0);
    $this->getJson('/api/app/ponto/aprovacoes?estado=qualquer')->assertStatus(422)->assertJsonPath('erro', 'validacao')
        ->assertJsonPath('campos.estado', 'Use pendente, validada, recusada ou todas.');
});
