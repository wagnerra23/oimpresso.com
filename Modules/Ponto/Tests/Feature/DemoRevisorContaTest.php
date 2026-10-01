<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Console\Commands\DemoRevisorCommand;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Tests\Feature\PontoTestCase;
use Spatie\Permission\PermissionRegistrar;

uses(PontoTestCase::class);

/**
 * Conta demo da revisão das lojas — `php artisan ponto:demo-revisor`.
 *
 * Contrato (pedido [W] 2026-10-01 + RUNBOOK-mobile): o revisor da Apple/Google entra por
 * webview em /ponto/mobile e NÃO enxerga nada fora do business demo. O business demo nunca é
 * 1, 4 (ROTA LIVRE) ou 98 (testes). Senha nunca no stdout. Histórico recusado em produção.
 *
 * Tier 0: o "outro" tenant é o fictício 98 (ADR 0358). Transação revertida por caso —
 * `ponto_marcacoes` recusa DELETE por trigger (Portaria MTP 671/2021).
 */

const DRV_OUTRO_BIZ = 98;

function drvRodar(array $opcoes = []): array
{
    $arquivo = tempnam(sys_get_temp_dir(), 'drv');
    $rc = Artisan::call('ponto:demo-revisor', array_merge(['--senha-arquivo' => $arquivo], $opcoes));

    return [$rc, Artisan::output(), $arquivo];
}

function drvBizDemo(): ?object
{
    return DB::table('business')->where('name', DemoRevisorCommand::BUSINESS_NOME)->first();
}

function drvRevisor(): User
{
    $u = User::where('username', DemoRevisorCommand::REVISOR_USERNAME)->firstOrFail();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    session(['user.business_id' => (int) $u->business_id, 'business.id' => (int) $u->business_id]);

    return $u;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK + triggers exigem MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('ponto_marcacoes') || ! DB::table('business')->where('id', DRV_OUTRO_BIZ)->exists()) {
        $this->markTestSkipped('Schema do Ponto ou tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();
    config()->set('pontowr2.geofence.business_' . DRV_OUTRO_BIZ, null);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('DEMO-01: cria business isolado (fora de 1/4/98), revisor sem permissão, cadastro de ponto e escala seg–sex — e é idempotente', function () {
    [$rc, $saida, $arquivo] = drvRodar();
    expect($rc)->toBe(0);

    $biz = drvBizDemo();
    expect($biz)->not->toBeNull();
    expect(in_array((int) $biz->id, [1, 4, 98], true))->toBeFalse();

    $u = drvRevisor();
    expect((int) $u->business_id)->toBe((int) $biz->id);
    expect((int) $u->allow_login)->toBe(1);
    expect($u->getAllPermissions()->count())->toBe(0);
    expect($u->getRoleNames()->all())->toBe(['Revisor#' . $biz->id]);

    $colab = DB::table('ponto_colaborador_config')->where('user_id', $u->id)->first();
    expect((int) $colab->business_id)->toBe((int) $biz->id);
    expect((bool) $colab->controla_ponto)->toBeTrue();
    expect(DB::table('ponto_escala_turnos')->where('escala_id', $colab->escala_atual_id)->count())->toBe(5);

    // O dono técnico (que recebe o Admin#) não entra.
    expect((int) DB::table('users')->where('id', $biz->owner_id)->value('allow_login'))->toBe(0);

    // Senha: no arquivo, nunca na saída.
    $senha = trim((string) file_get_contents($arquivo));
    expect(strlen($senha))->toBeGreaterThanOrEqual(20);
    expect(str_contains($saida, $senha))->toBeFalse();

    // 2ª rodada: nada duplica, senha mantida.
    [$rc2] = drvRodar();
    expect($rc2)->toBe(0);
    expect(DB::table('business')->where('name', DemoRevisorCommand::BUSINESS_NOME)->count())->toBe(1);
    expect(User::where('username', DemoRevisorCommand::REVISOR_USERNAME)->count())->toBe(1);
    expect(DB::table('ponto_colaborador_config')->where('user_id', $u->id)->count())->toBe(1);
    expect(\Illuminate\Support\Facades\Hash::check($senha, User::find($u->id)->password))->toBeTrue();
});

it('DEMO-02: o revisor bate ponto em /ponto/mobile e a marcação nasce no business demo', function () {
    drvRodar();
    $u = drvRevisor();
    $this->actingAs($u);

    $r = $this->inertiaGet('/ponto/mobile');
    $this->assertInertiaComponent($r, 'Ponto/Mobile/Index');
    $r->assertJsonPath('props.colaborador.matricula', 'DEMO-0001');
    $r->assertJsonPath('props.pode_ver_modulo', false);

    $m = $this->postJson('/ponto/mobile/marcar', [
        'tipo' => Marcacao::TIPO_ENTRADA, 'lat' => 37.33, 'lng' => -122.03, 'accuracy' => 30,
        'device_uuid' => 'drv-device', 'timestamp_device' => now()->toIso8601String(),
    ])->assertStatus(201);

    // Revisor fora do Brasil (coordenadas da Califórnia) grava — não é recusado por distância.
    expect((int) DB::table('ponto_marcacoes')->where('id', $m->json('marcacao.id'))->value('business_id'))
        ->toBe((int) drvBizDemo()->id);
});

it('DEMO-03: isolamento — o revisor não abre telas do ERP nem do gestor de ponto, e não vê marcação do outro tenant', function () {
    // Outro tenant (98) com colaborador e marcação de hoje.
    $outroUser = DB::table('users')->insertGetId([
        'first_name' => 'DRV outro', 'username' => 'drv_outro_' . uniqid(), 'password' => 'x',
        'business_id' => DRV_OUTRO_BIZ, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $outroColab = DB::table('ponto_colaborador_config')->insertGetId([
        'business_id' => DRV_OUTRO_BIZ, 'user_id' => $outroUser, 'matricula' => 'DRV-' . uniqid(),
        'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(\Modules\Ponto\Services\MarcacaoService::class)->registrar([
        'business_id' => DRV_OUTRO_BIZ, 'colaborador_config_id' => $outroColab, 'momento' => now()->subMinute(),
        'origem' => Marcacao::ORIGEM_MANUAL, 'tipo' => Marcacao::TIPO_ENTRADA, 'usuario_criador_id' => $outroUser,
    ]);

    drvRodar();
    $u = drvRevisor();
    $this->actingAs($u);

    // Tela do revisor: só as marcações dele (zero), nunca a do 98.
    $this->inertiaGet('/ponto/mobile')->assertJsonCount(0, 'props.marcacoes_hoje');
    $this->getJson('/ponto/mobile/marcacoes/hoje')->assertOk()->assertJsonMissing(['colaborador_config_id' => $outroColab]);

    // Telas do gestor de ponto e do ERP: nenhuma abre (403 ou redireciono pra fora).
    foreach (['/ponto', '/ponto/colaboradores', '/ponto/espelho', '/ponto/aprovacoes', '/sells', '/contacts?type=customer', '/business/settings'] as $rota) {
        $s = $this->get($rota)->getStatusCode();
        expect(in_array($s, [302, 403, 404], true))->toBeTrue("{$rota} abriu para o revisor (HTTP {$s})");
    }
});

it('DEMO-04: --com-historico é recusado em produção e não escreve nada', function () {
    app()->detectEnvironment(fn () => 'production');
    try {
        [$rc, $saida] = drvRodar(['--com-historico' => true]);
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
    expect($rc)->toBe(1);
    expect($saida)->toContain('recusado em produção');
    expect(drvBizDemo())->toBeNull();
});

it('DEMO-05: --com-historico (fora de produção) deixa marcações do mês e 1 justificativa pendente, sem duplicar', function () {
    [$rc] = drvRodar(['--com-historico' => true]);
    expect($rc)->toBe(0);
    $u = drvRevisor();
    $colab = DB::table('ponto_colaborador_config')->where('user_id', $u->id)->first();

    $n1 = DB::table('ponto_marcacoes')->where('colaborador_config_id', $colab->id)->count();
    $pend = DB::table('ponto_intercorrencias')->where('colaborador_config_id', $colab->id)->where('estado', 'PENDENTE')->count();
    expect($pend)->toBe(1);

    drvRodar(['--com-historico' => true]);
    expect(DB::table('ponto_marcacoes')->where('colaborador_config_id', $colab->id)->count())->toBe($n1);
    expect(DB::table('ponto_intercorrencias')->where('colaborador_config_id', $colab->id)->where('estado', 'PENDENTE')->count())->toBe(1);
    expect(DB::table('ponto_marcacoes')->where('colaborador_config_id', $colab->id)->where('business_id', '!=', $colab->business_id)->count())->toBe(0);
});
