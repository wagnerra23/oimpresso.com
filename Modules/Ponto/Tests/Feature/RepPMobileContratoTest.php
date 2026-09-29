<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Tests\Feature\PontoTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(PontoTestCase::class);

/**
 * Contrato da tela REP-P no celular (`/ponto/mobile` · Ponto/Mobile/Index) — thread 06.
 *
 * UCs de `resources/js/Pages/Ponto/Mobile/Index.casos.md`, derivados da tabela EARS §C da
 * thread + ADR 0383 (sem biometria) + W5 (GPS fraco é recusa). NÃO do `.tsx`.
 * A API por trás (`/ponto/api`, Passport) tem os seus casos no Wave28MobileMarcacaoTest;
 * aqui é a MESMA lógica servida sob sessão web, que é o caminho da tela.
 *
 * @covers-us US-PONTO-001
 *
 * Tier 0: tenant fictício 98 (ADR 0358). Transação revertida por caso — `ponto_marcacoes`
 * recusa DELETE por trigger (Portaria MTP 671/2021).
 */

const RPM_BIZ = 98;

function rpmUsuario(bool $comColaborador = true): User
{
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'RPM teste', 'username' => 'rpm_' . uniqid(), 'password' => 'x',
        'business_id' => RPM_BIZ, 'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($comColaborador) {
        DB::table('ponto_colaborador_config')->insert([
            'business_id' => RPM_BIZ, 'user_id' => $userId, 'matricula' => 'RPM-' . uniqid(),
            'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $u = User::findOrFail($userId);
    Permission::firstOrCreate(['name' => 'ponto.access', 'guard_name' => 'web']);
    $u->givePermissionTo('ponto.access');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    session(['user.business_id' => RPM_BIZ, 'business.id' => RPM_BIZ]);

    return $u;
}

function rpmPayload(array $extra = []): array
{
    return array_merge([
        'tipo' => Marcacao::TIPO_ENTRADA, 'lat' => -28.336, 'lng' => -48.926, 'accuracy' => 15,
        'device_uuid' => 'rpm-device', 'timestamp_device' => now()->toIso8601String(),
    ], $extra);
}

function rpmMarcacoesDo(User $u): int
{
    $colab = DB::table('ponto_colaborador_config')->where('user_id', $u->id)->value('id');

    return DB::table('ponto_marcacoes')->where('colaborador_config_id', $colab)->count();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK + triggers exigem MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('ponto_marcacoes') || ! DB::table('business')->where('id', RPM_BIZ)->exists()) {
        $this->markTestSkipped('Schema do Ponto ou tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();
    config()->set('pontowr2.geofence.business_' . RPM_BIZ, null);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-REPP-00: a aba "REP-P (celular)" é a 10ª do header, aponta pra /ponto/mobile, e a tela abre', function () {
    $fonte = file_get_contents(base_path('Modules/Ponto/Http/Controllers/DataController.php'));
    $iColab = strpos($fonte, "'key' => 'colaboradores'");
    $iMobile = strpos($fonte, "'key' => 'mobile'");
    $iImp = strpos($fonte, "'key' => 'importacoes'", (int) $iColab);
    expect($iMobile)->not->toBeFalse();
    expect($iColab < $iMobile && $iMobile < $iImp)->toBeTrue();
    expect($fonte)->toContain("'href' => '/ponto/mobile'");

    $this->actingAs(rpmUsuario());
    $this->assertInertiaComponent($this->inertiaGet('/ponto/mobile'), 'Ponto/Mobile/Index');
});

it('UC-REPP-01: bater ponto grava REP_P no meu cadastro com NSR do servidor, e aparece em Hoje ao recarregar', function () {
    $u = rpmUsuario();
    $this->actingAs($u);

    $r = $this->postJson('/ponto/mobile/marcar', rpmPayload())->assertStatus(201);
    expect((int) $r->json('marcacao.nsr'))->toBeGreaterThan(0);

    $m = DB::table('ponto_marcacoes')->where('id', $r->json('marcacao.id'))->first();
    expect((int) $m->business_id)->toBe(RPM_BIZ);
    expect($m->dispositivo_id)->toBe('mobile:rpm-device');
    expect(rpmMarcacoesDo($u))->toBe(1);

    $this->inertiaGet('/ponto/mobile')->assertJsonCount(1, 'props.marcacoes_hoje')
        ->assertJsonPath('props.marcacoes_hoje.0.tipo', Marcacao::TIPO_ENTRADA)
        ->assertJsonPath('props.marcacoes_hoje.0.nsr', (int) $m->nsr);
});

it('UC-REPP-02: GPS acima de 500 m → 422 com a mensagem do serviço e nada gravado', function () {
    $u = rpmUsuario();
    $this->actingAs($u);

    $this->postJson('/ponto/mobile/marcar', rpmPayload(['accuracy' => 900]))
        ->assertStatus(422)->assertJsonPath('erro', 'validacao_falhou');
    expect(rpmMarcacoesDo($u))->toBe(0);
});

it('UC-REPP-03: fora do geofence grava e volta revisar=true', function () {
    config()->set('pontowr2.geofence.business_' . RPM_BIZ, ['lat' => -27.595, 'lng' => -48.548, 'raio_metros' => 500.0]);
    $u = rpmUsuario();
    $this->actingAs($u);

    $this->postJson('/ponto/mobile/marcar', rpmPayload())->assertStatus(201)->assertJsonPath('marcacao.revisar', true);
    expect(rpmMarcacoesDo($u))->toBe(1);
});

it('UC-REPP-04: sem cadastro de ponto a tela vem com colaborador nulo e marcar → 403', function () {
    $this->actingAs(rpmUsuario(false));

    $this->inertiaGet('/ponto/mobile')->assertJsonPath('props.colaborador', null)->assertJsonCount(0, 'props.marcacoes_hoje');
    $this->postJson('/ponto/mobile/marcar', rpmPayload())->assertStatus(403)->assertJsonPath('erro', 'sem_colaborador');
});

it('UC-REPP-05: GUARD ADR 0383 — o fonte da tela não usa câmera, não captura imagem e não oferece "mesmo assim"', function () {
    // A tela INTEIRA: Index.tsx + _components/ (a câmera caberia em qualquer sub-tela).
    $arquivos = array_merge(
        glob(base_path('resources/js/Pages/Ponto/Mobile/*.tsx')),
        glob(base_path('resources/js/Pages/Ponto/Mobile/_components/*.tsx')),
    );
    expect(count($arquivos))->toBeGreaterThanOrEqual(3);
    $fonte = implode(PHP_EOL, array_map('file_get_contents', $arquivos));
    expect($fonte)->not->toContain('getUserMedia');
    expect($fonte)->not->toContain('capture=');
    expect(strtolower($fonte))->not->toContain('selfie');
    expect(strtolower($fonte))->not->toContain('mesmo assim');
    // Controle positivo: a leitura achou a tela certa (senão os `not` passam vazios).
    expect($fonte)->toContain('/ponto/mobile/marcar');
});

it('UC-REPP-06: Meu espelho traz os totais e os dias do MEU mês — não os de um colega', function () {
    $u = rpmUsuario();
    $colega = rpmUsuario();
    $this->actingAs($u);
    foreach ([[$u, 480, 30], [$colega, 300, 90]] as [$quem, $trab, $atraso]) {
        DB::table('ponto_apuracao_dia')->insert([
            'business_id' => RPM_BIZ, 'data' => now()->toDateString(), 'estado' => 'CALCULADO',
            'colaborador_config_id' => DB::table('ponto_colaborador_config')->where('user_id', $quem->id)->value('id'),
            'realizada_trabalhada_minutos' => $trab, 'atraso_minutos' => $atraso, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $p = $this->inertiaPartialGet('/ponto/mobile', ['totais', 'linhas'], 'Ponto/Mobile/Index')->assertOk();
    $p->assertJsonPath('props.totais.trabalhado', 480)->assertJsonPath('props.totais.atraso', 30);
    $hoje = collect($p->json('props.linhas'))->firstWhere('data', now()->toDateString());
    expect($hoje['trabalhado'])->toBe(480);
});

it('UC-REPP-07: Justificar pela tela cria intercorrência PENDENTE no meu cadastro', function () {
    $u = rpmUsuario();
    $this->actingAs($u);
    $this->inertiaGet('/ponto/mobile')->assertJsonCount(8, 'props.tipos');

    $r = $this->postJson('/ponto/mobile/intercorrencias', [
        'tipo' => 'ESQUECIMENTO_MARCACAO', 'data' => now()->toDateString(), 'dia_todo' => true,
        'justificativa' => 'Texto neutro de teste com mais de dez caracteres.',
    ])->assertStatus(201)->assertJsonPath('intercorrencia.estado', 'PENDENTE');

    $i = DB::table('ponto_intercorrencias')->where('id', $r->json('intercorrencia.id'))->first();
    expect((int) $i->business_id)->toBe(RPM_BIZ);
    expect((int) $i->colaborador_config_id)->toBe((int) DB::table('ponto_colaborador_config')->where('user_id', $u->id)->value('id'));
});
