<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Services\AbasContadoresService;
use Modules\Ponto\Services\ConformidadeService;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contagens das abas do header de módulo do Ponto — W9 · ADR 0418.
 *
 * Contrato: o shell `PontoPage` do protótipo (`prototipo-ui/cowork/Wagner/ponto-page.jsx`),
 * que põe nas abas: Aprovações = intercorrências PENDENTE · Intercorrências = total ·
 * Conformidade = apontamentos da competência · Colaboradores = total. E a regra da própria
 * tela de Conformidade (`Conformidade.tsx`, UC-CONF-09): sem apuração o número NÃO existe —
 * a aba não pode dizer 0.
 *
 * Tier 0 (ADR 0093/0358): tenant fictício 98 vs adversário 99. Nunca biz=4, nunca biz=1.
 * Asserções por DELTA: o tenant 98 pode ter dado de seed; o que se prova é o efeito dos
 * fixtures deste arquivo, não um número absoluto.
 */

const ABAS_MARCADOR = 'ABAS-CONTAGEM';
const ABAS_MES = '2018-11'; // competência sintética no passado: não colide com dado real

function abasPreparar(): int
{
    foreach (['ponto_colaborador_config', 'ponto_intercorrencias', 'ponto_apuracao_dia'] as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }

    return (int) test()->seededTenant()->id;
}

function abasColaborador(int $bizId): Colaborador
{
    $user = User::factory()->create([
        'business_id' => $bizId,
        'user_type'   => 'user',
        'username'    => strtolower(ABAS_MARCADOR) . '-' . uniqid(),
    ]);

    $c = new Colaborador();
    $c->forceFill([
        'business_id'    => $bizId,
        'user_id'        => $user->id,
        'matricula'      => ABAS_MARCADOR . '-' . uniqid(),
        'pis'            => '12345678900',
        'controla_ponto' => true,
        'admissao'       => '2018-01-01',
    ])->save();

    return $c;
}

function abasIntercorrencia(Colaborador $c, string $estado): void
{
    $id = (string) Str::uuid();
    DB::table('ponto_intercorrencias')->insert([
        'id'                    => $id,
        'business_id'           => $c->business_id,
        'colaborador_config_id' => $c->id,
        'codigo'                => ABAS_MARCADOR . '-' . substr($id, 0, 8),
        'tipo'                  => 'ATESTADO_MEDICO',
        'data'                  => '2018-11-12',
        'dia_todo'              => 1,
        'justificativa'         => 'Fixture de contrato — contagem das abas.',
        'estado'                => $estado,
        'prioridade'            => 'NORMAL',
        'solicitante_id'        => $c->user_id,
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
}

afterEach(function () {
    try {
        $ids = Colaborador::withoutGlobalScopes()->where('matricula', 'like', ABAS_MARCADOR . '%')->pluck('id');
        if ($ids->isNotEmpty()) {
            // SUPERADMIN: a limpeza alcança o fixture gravado no tenant adversário 99.
            DB::table('ponto_intercorrencias')->whereIn('colaborador_config_id', $ids)->delete();
            ApuracaoDia::withoutGlobalScopes()->whereIn('colaborador_config_id', $ids)->delete();
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }
        DB::table('users')->where('username', 'like', strtolower(ABAS_MARCADOR) . '%')->delete();
    } catch (\Throwable $e) {
        // best-effort
    }
    $this->removerBizAlheio();
});

it('cada aba conta o que o protótipo conta — pendentes, total de intercorrências, colaboradores', function () {
    $biz = abasPreparar();
    $svc = app(AbasContadoresService::class);
    $antes = $svc->contar($biz, ABAS_MES);

    $a = abasColaborador($biz);
    $b = abasColaborador($biz);
    abasIntercorrencia($a, Intercorrencia::ESTADO_PENDENTE);
    abasIntercorrencia($a, Intercorrencia::ESTADO_PENDENTE);
    abasIntercorrencia($b, Intercorrencia::ESTADO_APROVADA);

    $depois = $svc->contar($biz, ABAS_MES);

    // Aprovações conta SÓ pendente: a aprovada entra no total, não na fila.
    $this->assertSame(2, $depois['aprovacoes'] - $antes['aprovacoes'], 'Aprovações = só PENDENTE.');
    $this->assertSame(3, $depois['intercorrencias'] - $antes['intercorrencias'], 'Intercorrências = todas.');
    $this->assertSame(2, $depois['colaboradores'] - $antes['colaboradores'], 'Colaboradores = total do business.');
});

it('[T0] nada do tenant 99 entra nas contagens do tenant de teste', function () {
    $biz = abasPreparar();
    $alheio = $this->garantirBizAlheio();
    $svc = app(AbasContadoresService::class);
    $antes = $svc->contar($biz, ABAS_MES);

    $x = abasColaborador($alheio);
    abasIntercorrencia($x, Intercorrencia::ESTADO_PENDENTE);
    abasIntercorrencia($x, Intercorrencia::ESTADO_APROVADA);

    // Controle positivo: os fixtures EXISTEM no 99 (lidos sem scope) — senão o "0 de
    // diferença" abaixo passaria por nada ter sido gravado.
    $this->assertSame(2, DB::table('ponto_intercorrencias')->where('business_id', $alheio)
        ->where('colaborador_config_id', $x->id)->count());

    $depois = $svc->contar($biz, ABAS_MES);
    $this->assertSame($antes['aprovacoes'], $depois['aprovacoes'], 'Vazamento cross-tenant na aba Aprovações (ADR 0093).');
    $this->assertSame($antes['intercorrencias'], $depois['intercorrencias'], 'Vazamento cross-tenant na aba Intercorrências.');
    $this->assertSame($antes['colaboradores'], $depois['colaboradores'], 'Vazamento cross-tenant na aba Colaboradores.');
});

it('Conformidade sem apuração não tem número (null, nunca 0); com apuração é o total da tela', function () {
    $biz = abasPreparar();
    $svc = app(AbasContadoresService::class);

    // Competência sem nenhum dia apurado neste business → sem número.
    $painelVazio = app(ConformidadeService::class)->competencia($biz, '2017-02');
    if (($painelVazio['cobertura']['estado'] ?? null) === ConformidadeService::ESTADO_APURADO) {
        test()->markTestSkipped('O tenant de teste tem apuração em 2017-02 — a competência "vazia" do caso não está vazia.');
    }
    expect($svc->contar($biz, '2017-02')['conformidade'])->toBeNull();

    // Com apuração: um dia com marcação ímpar = jornada aberta (Art. 74).
    $c = abasColaborador($biz);
    ApuracaoDia::forceCreate([
        'business_id'           => $biz,
        'colaborador_config_id' => $c->id,
        'data'                  => ABAS_MES . '-09',
        'qtd_marcacoes'         => 3,
        'estado'                => ApuracaoDia::ESTADO_DIVERGENCIA,
        'divergencias'          => [],
    ]);

    $n = $svc->contar($biz, ABAS_MES)['conformidade'];
    expect($n)->toBeInt();
    $this->assertGreaterThanOrEqual(1, $n, 'A jornada aberta do fixture tem de aparecer.');

    // Segundo caminho: a regra do cabeçalho da tela (`Conformidade.tsx`) — soma das
    // verificações MEDIDAS — refeita aqui sobre o painel do serviço.
    $painel = app(ConformidadeService::class)->competencia($biz, ABAS_MES);
    $somaDaTela = collect($painel['verificacoes'])->where('medido', true)->sum('total');
    $this->assertSame((int) $somaDaTela, $n, 'A aba tem de mostrar o MESMO total que a tela de Conformidade.');
});

it('contexto: "N colaboradores no ponto" conta só quem controla ponto e não foi desligado', function () {
    $biz = abasPreparar();
    $svc = app(AbasContadoresService::class);
    $antes = $svc->contexto($biz)['colaboradores_no_ponto'];

    abasColaborador($biz);                                        // conta
    abasColaborador($biz)->forceFill(['controla_ponto' => false])->save();       // não conta
    abasColaborador($biz)->forceFill(['desligamento' => '2018-12-31'])->save();  // não conta

    $this->assertSame(1, $svc->contexto($biz)['colaboradores_no_ponto'] - $antes,
        'Só colaborador com controle de ponto e sem desligamento entra na linha de contexto.');
});

it('[T0] contexto: colaborador do tenant 99 não entra no "no ponto" do tenant de teste', function () {
    $biz = abasPreparar();
    $alheio = $this->garantirBizAlheio();
    $svc = app(AbasContadoresService::class);
    $antes = $svc->contexto($biz)['colaboradores_no_ponto'];

    abasColaborador($alheio);

    $this->assertSame($antes, $svc->contexto($biz)['colaboradores_no_ponto'],
        'Vazamento cross-tenant na linha de contexto (ADR 0093).');
});

it('contexto: competência por extenso, no formato do protótipo ("Setembro/2026")', function () {
    $biz = abasPreparar();
    $svc = app(AbasContadoresService::class);

    $this->assertSame('Setembro/2026', $svc->contexto($biz, \Carbon\Carbon::parse('2026-09-15'))['competencia']);
    $this->assertSame('Março/2027', $svc->contexto($biz, \Carbon\Carbon::parse('2027-03-01'))['competencia']);
});
