<?php

declare(strict_types=1);

use App\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\PresencaDoDiaService;
use Modules\Ponto\Tests\Feature\PontoTestCase;
use Tests\Support\WithSeededTenant;

uses(PontoTestCase::class);

/**
 * Contrato de `PresencaDoDiaService::doUsuario()` — US-PONTO-017.
 *
 * Porta só de leitura que o apontamento da Produção usa para fechar o cronômetro de pessoa
 * na saída do ponto (D-PONTO, confirmada por [W] em 2026-10-15). O que este arquivo prova:
 * a saída e a presença saem das batidas válidas, a anulação vale, a empresa é a do
 * parâmetro e nada é escrito.
 *
 * Tier 0 (ADR 0093): tenant fictício 98 contra adversário 99 (ADR 0358). Nunca biz=4 nem biz=1.
 * Sem `RefreshDatabase` (a lane ponto-pest proíbe): limpeza por marcador e por id.
 *
 * @covers-us US-PONTO-017
 */

const PDD_MARCADOR = 'US-PONTO-017-PRESENCA';
const PDD_DIA = '2026-03-10';

$GLOBALS['pdd_users'] = [];

function pddPrecisaDeSchema(): void
{
    foreach (['ponto_colaborador_config', 'ponto_marcacoes'] as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

function pddUsuario(int $bizId): User
{
    $u = User::factory()->create([
        'business_id' => $bizId,
        'user_type' => 'user',
        'username' => strtolower(PDD_MARCADOR) . '-' . uniqid(),
    ]);
    $GLOBALS['pdd_users'][] = $u->id;

    return $u;
}

function pddColaborador(int $bizId, int $userId): Colaborador
{
    return Colaborador::forceCreate([
        'business_id' => $bizId,
        'user_id' => $userId,
        'matricula' => PDD_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
        'admissao' => '2025-01-01',
    ]);
}

function pddBatida(Colaborador $c, string $hora, string $tipo, int $criador, string $origem = Marcacao::ORIGEM_REP_P, ?int $anulada = null): int
{
    return (int) Marcacao::create([
        'business_id' => $c->business_id,
        'colaborador_config_id' => $c->id,
        'momento' => PDD_DIA . ' ' . $hora,
        'origem' => $origem,
        'tipo' => $tipo,
        'marcacao_anulada_id' => $anulada,
        'motivo_anulacao' => $anulada ? 'batida em duplicidade' : null,
        'usuario_criador_id' => $criador,
    ])->id;
}

afterEach(function () {
    try {
        $ids = Colaborador::withoutGlobalScopes() // SUPERADMIN: limpeza alcança o tenant adversário 99.
            ->where('matricula', 'like', PDD_MARCADOR . '%')->pluck('id');
        if ($ids->isNotEmpty()) {
            // Append-only em produção; a remoção só existe no cleanup de teste (idioma do EspelhoContratoTest).
            DB::table('ponto_marcacoes')->whereIn('colaborador_config_id', $ids)->delete();
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete(); // SUPERADMIN: idem.
        }
        if ($GLOBALS['pdd_users'] !== []) {
            User::withoutGlobalScopes()->whereIn('id', $GLOBALS['pdd_users'])->delete(); // SUPERADMIN: idem.
        }
    } catch (\Throwable $e) {
        // best-effort: schema ausente
    }
    $GLOBALS['pdd_users'] = [];
    $this->removerBizAlheio();
});

it('lê a saída e a presença das batidas do dia, descontando o almoço', function () {
    pddPrecisaDeSchema();
    $biz = (int) WithSeededTenant::resolveSeededTenant()?->id ?: $this->markTestSkipped('sem tenant 98');
    $u = pddUsuario($biz);
    $c = pddColaborador($biz, $u->id);

    pddBatida($c, '08:00:00', Marcacao::TIPO_ENTRADA, $u->id);
    pddBatida($c, '12:00:00', Marcacao::TIPO_ALMOCO_INICIO, $u->id);
    pddBatida($c, '13:00:00', Marcacao::TIPO_ALMOCO_FIM, $u->id);
    pddBatida($c, '17:52:00', Marcacao::TIPO_SAIDA, $u->id);

    $p = app(PresencaDoDiaService::class)->doUsuario($biz, $u->id, CarbonImmutable::parse(PDD_DIA));

    $this->assertSame('17:52', $p['saida']?->format('H:i'), 'A saída do ponto é a última SAIDA do dia.');
    $this->assertSame(60, $p['almoco_minutos']);
    // 08:00→17:52 = 592 min, menos 60 de almoço. Discriminante: sem o desconto daria 592.
    $this->assertSame(532, $p['presenca_minutos']);
});

it('saída anulada não fecha nada: sem outra saída, a saída é nula', function () {
    pddPrecisaDeSchema();
    $biz = (int) WithSeededTenant::resolveSeededTenant()?->id ?: $this->markTestSkipped('sem tenant 98');
    $u = pddUsuario($biz);
    $c = pddColaborador($biz, $u->id);

    pddBatida($c, '08:00:00', Marcacao::TIPO_ENTRADA, $u->id);
    $saida = pddBatida($c, '17:52:00', Marcacao::TIPO_SAIDA, $u->id);

    $svc = app(PresencaDoDiaService::class);
    // Controle positivo: antes da anulação a saída existe — prova que a anulação é a causa do nulo.
    $this->assertSame('17:52', $svc->doUsuario($biz, $u->id, CarbonImmutable::parse(PDD_DIA))['saida']?->format('H:i'));

    pddBatida($c, '18:05:00', Marcacao::TIPO_SAIDA, $u->id, Marcacao::ORIGEM_ANULACAO, $saida);

    $p = $svc->doUsuario($biz, $u->id, CarbonImmutable::parse(PDD_DIA));
    $this->assertNull($p['saida'], 'A anulada e a própria linha de anulação não contam como saída.');
    $this->assertNull($p['presenca_minutos']);
});

it('não atravessa empresas: o ponto do 99 não responde pelo 98', function () {
    pddPrecisaDeSchema();
    $alheio = $this->garantirBizAlheio();
    $biz = (int) WithSeededTenant::resolveSeededTenant()?->id ?: $this->markTestSkipped('sem tenant 98');
    $u = pddUsuario($alheio);
    $c = pddColaborador($alheio, $u->id);
    pddBatida($c, '08:00:00', Marcacao::TIPO_ENTRADA, $u->id);
    pddBatida($c, '17:00:00', Marcacao::TIPO_SAIDA, $u->id);

    $svc = app(PresencaDoDiaService::class);
    $dia = CarbonImmutable::parse(PDD_DIA);

    // Controle positivo: na própria empresa o dado aparece.
    $this->assertSame('17:00', $svc->doUsuario($alheio, $u->id, $dia)['saida']?->format('H:i'));
    $this->assertNull($svc->doUsuario($biz, $u->id, $dia), 'Pedido pela empresa 98, o ponto do 99 não existe.');
});

it('só lê: nenhuma linha é gravada em ponto_marcacoes nem no cadastro', function () {
    pddPrecisaDeSchema();
    $biz = (int) WithSeededTenant::resolveSeededTenant()?->id ?: $this->markTestSkipped('sem tenant 98');
    $u = pddUsuario($biz);
    $c = pddColaborador($biz, $u->id);
    pddBatida($c, '08:00:00', Marcacao::TIPO_ENTRADA, $u->id);

    $antes = [DB::table('ponto_marcacoes')->count(), DB::table('ponto_colaborador_config')->count()];
    $p = app(PresencaDoDiaService::class)->doUsuario($biz, $u->id, CarbonImmutable::parse(PDD_DIA));
    $depois = [DB::table('ponto_marcacoes')->count(), DB::table('ponto_colaborador_config')->count()];

    $this->assertNotNull($p['entrada'], 'Controle: o leitor achou a batida (a contagem não é vácuo).');
    $this->assertNull($p['saida']);
    $this->assertSame($antes, $depois);
    // Usuário sem cadastro no ponto desta empresa: null, e também sem criar cadastro.
    $this->assertNull(app(PresencaDoDiaService::class)->doUsuario($biz, pddUsuario($biz)->id, CarbonImmutable::parse(PDD_DIA)));
});
