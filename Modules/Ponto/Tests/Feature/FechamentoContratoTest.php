<?php

declare(strict_types=1);

use App\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Ponto\Services\FechamentoService;
use Modules\Ponto\Tests\Feature\PontoTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(PontoTestCase::class);

/**
 * Contrato do fechamento da competência — regras da ADR 0413, não do código:
 *  - fechar exige `ponto.fechar` (D1); `ponto.access` sozinho não basta;
 *  - bloqueio grave aberto só fecha com aceite, e os bloqueios ficam na linha (D2 + W3);
 *  - fechar de novo é recusado — não existe reabrir (D1);
 *  - fechar NÃO altera `ponto_marcacoes` nem `ponto_apuracao_dia` (Portaria MTP 671/2021).
 *
 * @covers-us US-PONTO-015
 *
 * Tier 0: tenant fictício 98 × adversário 99 (ADR 0358). Transação revertida por caso
 * (`ponto_competencias` recusa DELETE). Mês 2099-07: fora de qualquer competência real.
 */

const FCH_BIZ = 98;
const FCH_MES = '2099-07';

function fchPrecondicoes(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('Schema UltimatePOS + FK exigem MySQL (ADR 0358).');
    }
    foreach (['ponto_competencias', 'ponto_apuracao_dia', 'ponto_intercorrencias'] as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — migrations do Ponto não rodaram.");
        }
    }
    if (! DB::table('business')->where('id', FCH_BIZ)->exists()) {
        test()->markTestSkipped('Tenant fictício 98 ausente — seed do pest-mysql-setup não rodou.');
    }
}

function fchUsuario(): User
{
    return User::query()->where('business_id', FCH_BIZ)->first() ?? test()->markTestSkipped('Nenhum user no biz 98.');
}

/** Colaborador com user PRÓPRIO: `ponto_colaborador_config.user_id` é unique. */
function fchColaborador(int $bizId): int
{
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'FCH teste', 'username' => 'fch_' . uniqid(), 'password' => 'x',
        'business_id' => $bizId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('ponto_colaborador_config')->insertGetId([
        'business_id' => $bizId, 'user_id' => $userId, 'matricula' => 'FCH-' . uniqid(),
        'pis' => '12345678901', 'controla_ponto' => true, 'admissao' => '2020-01-01',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function fchDia(int $bizId, int $colab, string $data, string $estado): void
{
    DB::table('ponto_apuracao_dia')->insert([
        'business_id' => $bizId, 'colaborador_config_id' => $colab, 'data' => $data,
        'estado' => $estado, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function fchGraves(int $bizId): array
{
    $b = app(FechamentoService::class)->preChecagem($bizId, CarbonImmutable::createFromFormat('!Y-m', FCH_MES));

    return collect($b)->where('grave', true)->pluck('n', 'id')->all();
}

beforeEach(function () {
    fchPrecondicoes();
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-PTF-01: pré-checagem conta dia em DIVERGENCIA e intercorrência pendente só do próprio tenant (Tier 0)', function () {
    $u = fchUsuario();
    $colab = fchColaborador(FCH_BIZ);
    fchDia(FCH_BIZ, $colab, FCH_MES . '-10', 'DIVERGENCIA');
    DB::table('ponto_intercorrencias')->insert([
        'id' => (string) Str::uuid(), 'business_id' => FCH_BIZ, 'colaborador_config_id' => $colab,
        'codigo' => 'FCH-' . uniqid(), 'tipo' => 'OUTRO', 'data' => FCH_MES . '-11',
        'justificativa' => 'teste', 'estado' => 'PENDENTE', 'solicitante_id' => $u->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $alheio = $this->garantirBizAlheio();
    fchDia($alheio, fchColaborador($alheio), FCH_MES . '-12', 'DIVERGENCIA');

    expect(fchGraves(FCH_BIZ))->toMatchArray(['divergencia' => 1, 'intercorrencia' => 1, 'clt' => 0]);
});

it('UC-PTF-02: com bloqueio grave aberto, fechar sem aceite é recusado e nada é gravado (ADR 0413 D2)', function () {
    $u = fchUsuario();
    fchDia(FCH_BIZ, fchColaborador(FCH_BIZ), FCH_MES . '-10', 'DIVERGENCIA');

    expect(fn () => app(FechamentoService::class)->fechar(FCH_BIZ, CarbonImmutable::createFromFormat('!Y-m', FCH_MES), $u->id, false))
        ->toThrow(DomainException::class, 'bloqueios graves');
    expect(DB::table('ponto_competencias')->where('business_id', FCH_BIZ)->where('competencia', FCH_MES . '-01')->exists())->toBeFalse();
});

it('UC-PTF-03 + UC-PTF-05: fechar aceitando grava quem, quando e os bloqueios aceitos — e não toca marcação nem apuração', function () {
    $u = fchUsuario();
    fchDia(FCH_BIZ, fchColaborador(FCH_BIZ), FCH_MES . '-10', 'DIVERGENCIA');
    $antes = [DB::table('ponto_marcacoes')->count(), DB::table('ponto_apuracao_dia')->where('estado', 'DIVERGENCIA')->count()];

    $c = app(FechamentoService::class)->fechar(FCH_BIZ, CarbonImmutable::createFromFormat('!Y-m', FCH_MES), $u->id, true);

    expect((int) $c->fechada_por)->toBe((int) $u->id);
    expect(collect($c->bloqueios_aceitos)->firstWhere('id', 'divergencia')['n'] ?? 0)->toBe(1);
    expect([DB::table('ponto_marcacoes')->count(), DB::table('ponto_apuracao_dia')->where('estado', 'DIVERGENCIA')->count()])
        ->toBe($antes);
});

it('UC-PTF-04: fechar o mesmo mês de novo é recusado — reabrir não existe (ADR 0413 D1)', function () {
    $u = fchUsuario();
    $s = app(FechamentoService::class);
    $mes = CarbonImmutable::createFromFormat('!Y-m', FCH_MES);
    $s->fechar(FCH_BIZ, $mes, $u->id, true);

    expect(fn () => $s->fechar(FCH_BIZ, $mes, $u->id, true))->toThrow(DomainException::class, 'já fechada');
});

it('UC-PTF-06: POST /ponto/fechamento exige ponto.fechar: só ponto.access → 403; com ponto.fechar → grava (ADR 0413 D1)', function () {
    $u = fchUsuario();
    foreach (['ponto.access', 'ponto.fechar'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $u->givePermissionTo('ponto.access');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    session(['user.business_id' => FCH_BIZ, 'business.id' => FCH_BIZ]);

    $this->actingAs($u)->post('/ponto/fechamento', ['competencia' => FCH_MES, 'aceitar_bloqueios' => true])
        ->assertForbidden();
    expect(DB::table('ponto_competencias')->where('business_id', FCH_BIZ)->where('competencia', FCH_MES . '-01')->exists())->toBeFalse();

    $u->givePermissionTo('ponto.fechar');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($u->fresh())->post('/ponto/fechamento', ['competencia' => FCH_MES, 'aceitar_bloqueios' => true])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(DB::table('ponto_competencias')->where('business_id', FCH_BIZ)->where('competencia', FCH_MES . '-01')->value('fechada_por'))
        ->toEqual($u->id);
});

it('UC-PTF-07: a tela mostra a competência aberta, sem botão pra quem não tem ponto.fechar, e depois quem fechou', function () {
    $u = fchUsuario();
    Permission::firstOrCreate(['name' => 'ponto.access', 'guard_name' => 'web']);
    $u->givePermissionTo('ponto.access');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    session(['user.business_id' => FCH_BIZ, 'business.id' => FCH_BIZ]);
    $this->actingAs($u);

    $r = $this->inertiaGet('/ponto/fechamento', ['competencia' => FCH_MES]);
    $this->assertInertiaComponent($r, 'Ponto/Fechamento/Index');
    $r->assertJsonPath('props.competencia', FCH_MES)
        ->assertJsonPath('props.fechada', null)
        ->assertJsonPath('props.pode_fechar', false);

    $p = $this->inertiaPartialGet('/ponto/fechamento?competencia=' . FCH_MES, ['bloqueios'], 'Ponto/Fechamento/Index');
    expect(collect($p->json('props.bloqueios'))->pluck('id')->all())
        ->toBe(['divergencia', 'intercorrencia', 'clt', 'sem_pis', 'importacao']);

    app(FechamentoService::class)->fechar(FCH_BIZ, CarbonImmutable::createFromFormat('!Y-m', FCH_MES), $u->id, true);

    $depois = $this->inertiaGet('/ponto/fechamento', ['competencia' => FCH_MES]);
    expect($depois->json('props.fechada.fechada_por'))->toBe(trim($u->first_name . ' ' . $u->last_name));
});
