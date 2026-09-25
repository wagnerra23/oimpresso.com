<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Services\ConformidadeService;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do Painel de Conformidade CLT — thread 05 do playbook Ponto, ADR 0413 D0.
 *
 * Os UC vêm do casos.md do protótipo (`prototipo-ui/cowork/Wagner/resources/js/Pages/Ponto/
 * Conformidade.casos.md`, UC-CONF-01..03) + das Leis da thread 05 (read-only, reusar a
 * apuração) + Tier 0 — nunca do código do Service (§5 2026-06-05).
 *
 * Tier 0 (ADR 0093/0358): tenant fictício 98 vs adversário 99. NUNCA biz=4, nunca biz=1.
 * Sem `RefreshDatabase` — a lane `ponto-pest` proíbe.
 *
 * @covers-us US-PONTO-016
 */

const CONF_MARCADOR = 'CONF-CONTRATO';
const CONF_MES = '2019-05'; // mês sintético no passado: não colide com dado real

/** Tenant canônico de teste (98, ADR 0358) — pula se o seed não rodou. */
function confPreparar(): int
{
    foreach (['ponto_colaborador_config', 'ponto_apuracao_dia'] as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }

    return (int) test()->seededTenant()->id;
}

/** @param array<string,mixed> $extra */
function confColaborador(int $bizId, array $extra = []): Colaborador
{
    $user = User::factory()->create([
        'business_id' => $bizId,
        'user_type'   => 'user',
        'username'    => strtolower(CONF_MARCADOR) . '-' . uniqid(),
    ]);

    return Colaborador::forceCreate(array_merge([
        'business_id'    => $bizId,
        'user_id'        => $user->id,
        'matricula'      => CONF_MARCADOR . '-' . uniqid(),
        'pis'            => '12345678900',
        'controla_ponto' => true,
        'admissao'       => '2019-01-01',
    ], $extra));
}

/** @param array<string,mixed> $campos */
function confDia(Colaborador $c, string $dia, array $campos): void
{
    ApuracaoDia::forceCreate(array_merge([
        'business_id'           => $c->business_id,
        'colaborador_config_id' => $c->id,
        'data'                  => CONF_MES . '-' . $dia,
        'qtd_marcacoes'         => 4,
        'estado'                => ApuracaoDia::ESTADO_DIVERGENCIA,
        'divergencias'          => [],
    ], $campos));
}

/** @return array<string,mixed> */
function confVerificacao(array $painel, string $id): array
{
    return collect($painel['verificacoes'])->firstWhere('id', $id);
}

afterEach(function () {
    try {
        $ids = Colaborador::withoutGlobalScopes()->where('matricula', 'like', CONF_MARCADOR . '%')->pluck('id');
        if ($ids->isNotEmpty()) {
            // SUPERADMIN: a limpeza alcança o fixture gravado no tenant adversário 99.
            ApuracaoDia::withoutGlobalScopes()->whereIn('colaborador_config_id', $ids)->delete();
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }
        DB::table('users')->where('username', 'like', strtolower(CONF_MARCADOR) . '%')->delete();
    } catch (\Throwable $e) {
        // best-effort
    }
    $this->removerBizAlheio();
});

it('UC-CONF-01 · Cada apontamento cita a lei e os números', function () {
    $biz = confPreparar();
    $c = confColaborador($biz);
    confDia($c, '06', ['realizada_trabalhada_minutos' => 480, 'realizada_intrajornada_minutos' => 35, 'intrajornada_violacao_minutos' => 25]);
    confDia($c, '07', ['interjornada_violacao_minutos' => 120]);
    confDia($c, '08', ['realizada_trabalhada_minutos' => 660, 'prevista_carga_minutos' => 480,
        'divergencias' => [['chave' => 'he_acima_limite', 'mensagem' => 'Hora extra de 3h excede limite diário de 2h (Art. 59 CLT).']]]);
    confDia($c, '09', ['qtd_marcacoes' => 3]);

    $p = app(ConformidadeService::class)->competencia($biz, CONF_MES);

    expect(confVerificacao($p, 'intrajornada')['artigo'])->toContain('Art. 71');
    expect(confVerificacao($p, 'interjornada')['artigo'])->toContain('Art. 66');
    expect(confVerificacao($p, 'he')['artigo'])->toContain('Art. 59');
    expect(confVerificacao($p, 'jornada_aberta')['artigo'])->toContain('Art. 74');

    $intra = $p['casos']['intrajornada'][0];
    $this->assertSame([$c->id, CONF_MES . '-06', '0h35', '1h00'],
        [$intra['colaborador_id'], $intra['dia'], $intra['apurado'], $intra['limite']]);
    $this->assertSame('9h00', $p['casos']['interjornada'][0]['apurado'], 'Interjornada apurada = 11h − 2h de violação.');
    $this->assertSame('3h00', $p['casos']['he'][0]['apurado']);
    $this->assertSame(CONF_MES . '-09', $p['casos']['jornada_aberta'][0]['dia'], 'Marcação ímpar = jornada sem fechamento.');
});

it('UC-CONF-02 · Limite vem do config', function () {
    $biz = confPreparar();
    $c = confColaborador($biz);
    confDia($c, '06', ['realizada_trabalhada_minutos' => 480, 'realizada_intrajornada_minutos' => 35, 'intrajornada_violacao_minutos' => 10]);

    config(['pontowr2.clt.intrajornada_minima_minutos' => 45]);
    $p = app(ConformidadeService::class)->competencia($biz, CONF_MES);

    $this->assertSame('0h45', $p['casos']['intrajornada'][0]['limite'], 'O limite exibido tem de ser o do config, não constante.');
});

it('UC-CONF-04 · Painel do tenant de teste não enxerga apuração nem colaborador do tenant 99', function () {
    $biz = confPreparar();
    $alheio = $this->garantirBizAlheio();
    $meu = confColaborador($biz);
    $outro = confColaborador($alheio, ['pis' => null]);
    confDia($meu, '10', ['interjornada_violacao_minutos' => 60]);
    confDia($outro, '10', ['interjornada_violacao_minutos' => 60]);

    $p = app(ConformidadeService::class)->competencia($biz, CONF_MES);

    $ids = collect($p['casos'])->flatten(1)->pluck('colaborador_id')->all();
    $this->assertContains($meu->id, $ids, 'Controle positivo: o caso do próprio tenant tem de aparecer.');
    $this->assertNotContains($outro->id, $ids, 'Vazamento cross-tenant: caso do tenant 99 no painel do tenant de teste (ADR 0093).');
});

it('UC-CONF-05 · Colaborador ativo sem PIS é listado; desligado e com PIS não', function () {
    $biz = confPreparar();
    $semPis = confColaborador($biz, ['pis' => null]);
    $desligado = confColaborador($biz, ['pis' => null, 'desligamento' => '2019-02-01']);
    $comPis = confColaborador($biz);

    $p = app(ConformidadeService::class)->competencia($biz, CONF_MES);
    $ids = collect($p['casos']['sem_pis'])->pluck('colaborador_id')->all();

    $this->assertContains($semPis->id, $ids);
    $this->assertNotContains($desligado->id, $ids);
    $this->assertNotContains($comPis->id, $ids);
});

it('UC-CONF-06 · NSR fora de sequência sai como não medido, nunca como zero', function () {
    $biz = confPreparar();
    $p = app(ConformidadeService::class)->competencia($biz, CONF_MES);
    $nsr = confVerificacao($p, 'nsr');

    $this->assertFalse($nsr['medido']);
    $this->assertNull($nsr['total'], 'Zero afirmaria "NSR em ordem" sem ter medido.');
});

it('UC-CONF-07 · O painel é somente leitura (ADR 0413 D0)', function () {
    $biz = confPreparar();
    $c = confColaborador($biz);
    confDia($c, '06', ['interjornada_violacao_minutos' => 60]);
    // SUPERADMIN: contagem global de linhas — prova que nenhum tenant recebeu escrita.
    $antes = [ApuracaoDia::withoutGlobalScopes()->count(), Colaborador::withoutGlobalScopes()->count()];

    app(ConformidadeService::class)->competencia($biz, CONF_MES);

    $this->assertSame($antes, [ApuracaoDia::withoutGlobalScopes()->count(), Colaborador::withoutGlobalScopes()->count()]);
});

it('UC-CONF-08 · A tela abre pela rota com o painel deferido', function () {
    confPreparar();
    $this->actAsAdmin();

    $url = '/ponto/conformidade?mes=' . CONF_MES;
    $primeiro = $this->inertiaGet($url);
    $this->assertInertiaComponent($primeiro, 'Ponto/Conformidade');
    $this->assertSame(CONF_MES, $primeiro->json('props.mes'));
    $this->assertNull($primeiro->json('props.painel'), 'painel é deferido — não vai no primeiro render.');

    $parcial = $this->inertiaPartialGet($url, ['painel'], 'Ponto/Conformidade');
    $parcial->assertStatus(200);
    $ids = collect($parcial->json('props.painel.verificacoes'))->pluck('id')->all();
    $this->assertSame(['jornada_aberta', 'interjornada', 'intrajornada', 'he', 'nsr', 'sem_pis'], $ids);
});
