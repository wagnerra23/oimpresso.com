<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do workflow de intercorrência/aprovação — trio derivado do SDD
 * (agent `sdd-from-source`, ADR 0351).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Aprovacoes/Index.casos.md      → UC-PAPR-01..04
 *   Intercorrencias/Show.casos.md  → UC-INTSH-01..03
 *
 * Os UC derivam do SDD §6.2 (CU-PONTO-05..07) + CU-PONTO-12, ancorados em
 * US-PONTO-003 (estados canon + trilha de aprovação) e US-PONTO-007 (Tier 0).
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101).
 * Sem RefreshDatabase: a lane ponto-pest proíbe (dropa schema + limpa seed biz=1).
 *
 * ---------------------------------------------------------------------------
 * POR QUE O PREFIXO MUDOU: `UC-APROV` → `UC-PAPR` (medido 2026-09-04)
 * ---------------------------------------------------------------------------
 * Os ids `UC-APROV-01..04` eram declarados por DOIS módulos, em telas diferentes,
 * com enunciados diferentes:
 *   · Modules/Forja/…/Forja/Aprovacoes/Index.casos.md  → UC-APROV-01..08
 *       UC-APROV-01 = "Acesso à mesa exige login + permissão"
 *   · resources/js/Pages/Ponto/Aprovacoes/Index.casos.md → UC-APROV-01..04
 *       UC-APROV-01 = "Rejeitar exige motivo registrado"
 *
 * Nenhuma das três camadas que consomem UC-id tem escopo de módulo:
 *   1. `casos-coverage-guard.mjs` (teto de prova) casa `tituloCorpus.includes(uc)` —
 *      corpus GLOBAL de títulos. Como só o Forja tem `it('UC-APROV-NN …')`, os 4 do
 *      Ponto sumiam da lista `⛓` que deveria cobrá-los.
 *   2. `scripts/casos-test-results.json` indexa `ucs` por id PLANO: as 8 entradas
 *      `UC-APROV-01..08` (todas `verdict:"pass"`, `tests:1`) vêm só do Forja.
 *   3. o G-7 do mesmo guard (REQUIRED) lê esse manifesto — logo um `✅` no casos.md
 *      do Ponto passaria com a prova do Forja.
 *
 * O agravante é `UC-PAPR-02`, marcado `[T0]`: isolamento multi-tenant (ADR 0093).
 * Ele tem teste de verdade — este arquivo — mas em PHPUnit o `name` do <testcase>
 * é o NOME DO MÉTODO, e PHP não aceita `-` em identificador; `uc_aprov_02_…` nunca
 * casou o regex canônico. A única prova registrada sob aquela chave provava outra
 * coisa, em outro módulo.
 *
 * Escala medida (184 `.casos.md`, 996 ids distintos): 107 ids em >1 módulo, mas 103
 * são contra `prototipo-ui` — espelho da MESMA tela, benigno. Colisão entre dois
 * módulos de produto: só esta. Por isso o conserto aqui é o PREFIXO (forward-only,
 * barato); escopar id por módulo nas 3 camadas é conserto de raiz, exige FP medido
 * antes de tocar gate required, e fica registrado sem armar (ADR 0344 two-strikes).
 *
 * ---------------------------------------------------------------------------
 * POR QUE VIROU PEST (mesma razão do irmão `EspelhoContratoTest`, #6405)
 * ---------------------------------------------------------------------------
 * Conversão de FORMA, não de comportamento: os enunciados das asserções são os
 * mesmos, na mesma ordem. O que muda é o título chegar ao JUnit com o UC-id.
 * Deltas de forma, cada um com o porquê:
 *   · helpers privados viram funções de arquivo com prefixo `jornada*` (idioma dos
 *     irmãos já convertidos).
 *   · `business_id`/`solicitante_id` passam por PARÂMETRO: em Pest o helper está
 *     fora do escopo da classe e as propriedades são `protected`. Quem passa é o
 *     `it()`, que É escopo da classe.
 *   · `self::BIZ_ALHEIO` → RETORNO de `garantirBizAlheio()`: em closure Pest o
 *     `self::` não resolve pra base. Chamar antes do guard é inerte (o helper é
 *     idempotente: se o business logado FOR o fictício, ele já existe).
 *   · os dois helpers locais de partial reload saem em favor do `inertiaPartialGet()`
 *     que a `PontoTestCase` já expõe — byte-idêntico (os mesmos 3 headers + version).
 *   · `tearDown()` → `afterEach()`, mesma ordem (fixtures, depois `removerBizAlheio`:
 *     FK sem CASCADE).
 *   · `declare(strict_types=1)` para casar os irmãos.
 *
 * @covers-us US-PONTO-003 US-PONTO-007
 */
const JORNADA_MARCADOR = 'SDD-WORKFLOW-CONTRATO';

function jornadaPrecisaDeSchema(): void
{
    foreach (['ponto_colaborador_config', 'ponto_intercorrencias'] as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/**
 * Um user NOVO do business informado, para vincular a UM colaborador.
 *
 * Por que nao reusar o admin: um Observer cria `ponto_colaborador_config` por
 * colaborador, e essa tabela tem `user_id` UNIQUE -- dois colaboradores no mesmo
 * user estouram com Duplicate entry (medido na lane: 17 ocorrencias). O admin
 * segue sendo quem AUTENTICA; so o vinculo do colaborador muda.
 */
function jornadaNovoUser(int $businessId): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'user_type'   => 'user',
    ]);
}

/**
 * Cria um colaborador marcado pro cleanup.
 *
 * DOIS business_id por fidelidade ao fixture original, não por descuido: o
 * colaborador nasce no business ALVO (que pode ser o alheio, pra montar o cenário
 * cross-tenant), enquanto o user nasce sempre no business LOGADO — era o que
 * `novoUserDoBusiness()` fazia, e mudar isso seria alterar o cenário numa conversão
 * que só pode mudar a forma.
 */
function jornadaCriarColaborador(int $businessIdColaborador, int $businessIdUser): Colaborador
{
    $colab = new Colaborador();
    $colab->forceFill([
        'business_id'    => $businessIdColaborador,
        'user_id'        => jornadaNovoUser($businessIdUser)->id,
        'matricula'      => JORNADA_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
    ])->save();

    return $colab;
}

/**
 * Cria intercorrência direto no banco (sem passar pelo global scope na leitura),
 * pra poder montar cenário cross-tenant.
 *
 * @param  array<string,mixed>  $attrs
 */
function jornadaCriarIntercorrencia(int $businessIdLogado, int $solicitanteId, array $attrs = []): string
{
    $businessId = $attrs['business_id'] ?? $businessIdLogado;
    $colaborador = $attrs['colaborador_config_id']
        ?? jornadaCriarColaborador((int) $businessId, $businessIdLogado)->id;
    $id = (string) Str::uuid();

    DB::table('ponto_intercorrencias')->insert(array_merge([
        'id'                    => $id,
        'business_id'           => $businessId,
        'colaborador_config_id' => $colaborador,
        'codigo'                => JORNADA_MARCADOR . '-' . substr($id, 0, 8),
        'tipo'                  => 'ATESTADO_MEDICO',
        'data'                  => '2019-03-11',
        'dia_todo'              => 1,
        'justificativa'         => 'Fixture de contrato SDD — atestado médico.',
        'estado'                => Intercorrencia::ESTADO_PENDENTE,
        'prioridade'            => 'NORMAL',
        'solicitante_id'        => $solicitanteId,
        'created_at'            => now(),
        'updated_at'            => now(),
    ], array_diff_key($attrs, ['business_id' => null, 'colaborador_config_id' => null]), [
        'business_id'           => $businessId,
        'colaborador_config_id' => $colaborador,
    ]));

    return $id;
}

function jornadaLerCru(string $id): ?object
{
    return DB::table('ponto_intercorrencias')->where('id', $id)->first();
}

function jornadaLimparFixtures(): void
{
    try {
        Intercorrencia::withoutGlobalScopes()
            ->where('codigo', 'like', JORNADA_MARCADOR . '%')
            ->forceDelete();

        $ids = Colaborador::withoutGlobalScopes()
            ->where('matricula', 'like', JORNADA_MARCADOR . '%')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
}

afterEach(function () {
    jornadaLimparFixtures();
    $this->removerBizAlheio(); // depois do cleanup: FK sem CASCADE (ver PontoTestCase)
});

// =====================================================================
// Aprovacoes/Index
// =====================================================================

/**
 * Contrato: CU-PONTO-06 (SDD §6.2) + US-PONTO-003 (aceitação nomeia
 * `motivo_rejeicao`). Rejeição sem justificativa é passivo trabalhista.
 */
it('UC-PAPR-01 · Rejeitar exige motivo registrado', function () {
    $this->actAsAdmin();
    jornadaPrecisaDeSchema();

    $id = jornadaCriarIntercorrencia((int) $this->business->id, (int) $this->admin->id);

    $this->from('/ponto/aprovacoes')
        ->post("/ponto/aprovacoes/{$id}/rejeitar", []) // sem motivo
        ->assertSessionHasErrors('motivo');

    $this->assertSame(
        Intercorrencia::ESTADO_PENDENTE,
        jornadaLerCru($id)->estado,
        'Rejeição sem motivo não pode mudar o estado — a trilha (motivo_rejeicao) '
        . 'é o que sustenta a decisão em reclamatória (CU-PONTO-06).'
    );
});

/**
 * Contrato: CU-PONTO-07 (SDD §6.5) + US-PONTO-007 + ADR 0093.
 * O lote recebe LISTA DE IDS do cliente — o vetor mais fácil de forjar do módulo.
 * Hoje a defesa é única (global scope); este UC é o alarme (SDD §9 D-5).
 */
it('UC-PAPR-02 · Aprovação em lote não decide fora do meu empregador', function () {
    $this->actAsAdmin();
    jornadaPrecisaDeSchema();

    $bizAlheio = $this->garantirBizAlheio();

    if ((int) $this->business->id === $bizAlheio) {
        $this->markTestSkipped('Business logado colide com o business fictício do teste.');
    }

    $meu    = jornadaCriarIntercorrencia((int) $this->business->id, (int) $this->admin->id);
    $alheio = jornadaCriarIntercorrencia(
        (int) $this->business->id,
        (int) $this->admin->id,
        ['business_id' => $bizAlheio]
    );

    $this->from('/ponto/aprovacoes')
        ->post('/ponto/aprovacoes/lote', ['ids' => [$meu, $alheio]]);

    // Pré-condição anti-vácuo: PROVA que o lote executou.
    //
    // Sem isto o caso mede não-execução e chama de isolamento: um POST que falhe
    // por QUALQUER motivo (validação do `ids.*|uuid`, 419, 403, 500, rota morta)
    // deixa o alheio PENDENTE — e as duas asserções de baixo passam felizes, com
    // o guard Tier 0 nunca exercido. É a classe LC-13 do §5 (2026-07-24): teste
    // que afirma "X foi preservado" tem de provar, no MESMO caso, que a operação
    // aconteceu.
    $this->assertSame(
        Intercorrencia::ESTADO_APROVADA,
        jornadaLerCru($meu)->estado,
        'O lote precisa ter APROVADO a intercorrência do meu empregador — se não '
        . 'aprovou nada, o caso não provou isolamento, provou que o POST falhou.'
    );
    $this->assertSame(
        $this->admin->id,
        (int) jornadaLerCru($meu)->aprovador_id,
        'O aprovador gravado tem de ser quem executou o lote.'
    );

    $this->assertSame(
        Intercorrencia::ESTADO_PENDENTE,
        jornadaLerCru($alheio)->estado,
        'Intercorrência de OUTRO empregador não pode ser aprovada por lote forjado '
        . '(Tier 0 IRREVOGÁVEL, ADR 0093).'
    );
    $this->assertNull(
        jornadaLerCru($alheio)->aprovador_id,
        'Nenhum aprovador pode ser gravado em intercorrência de outro empregador.'
    );
});

/**
 * Contrato: CU-PONTO-06 + AprovacaoController@index (default ESTADO_PENDENTE)
 * + US-PONTO-003 (6 estados canon).
 */
it('UC-PAPR-03 · A fila abre no que está pendente', function () {
    $this->actAsAdmin();
    jornadaPrecisaDeSchema();

    jornadaCriarIntercorrencia((int) $this->business->id, (int) $this->admin->id);

    $resp = $this->inertiaGet('/ponto/aprovacoes');
    $this->assertInertiaComponent($resp, 'Ponto/Aprovacoes/Index');

    $this->assertSame(
        Intercorrencia::ESTADO_PENDENTE,
        $resp->json('props.filtros.estado'),
        'Sem filtro explícito, a fila abre no pendente — é caixa de entrada, '
        . 'não histórico (CU-PONTO-06).'
    );

    $contagens = $this->inertiaPartialGet(
        '/ponto/aprovacoes',
        ['contagens'],
        'Ponto/Aprovacoes/Index'
    )->json('props.contagens');

    $this->assertNotNull(
        $contagens,
        'O painel precisa informar quantas intercorrências existem por estado — '
        . 'é a única visão de backlog do gestor.'
    );
    $this->assertArrayHasKey(
        Intercorrencia::ESTADO_PENDENTE,
        $contagens,
        'A contagem precisa cobrir o estado pendente (US-PONTO-003).'
    );
});

/**
 * Contrato: CU-PONTO-06 + SDD §5.3 F5 (ordenação por FIELD(prioridade,...)).
 * `orderBy('prioridade')` puro ordenaria ALFABETICAMENTE (NORMAL antes de
 * URGENTE) e inverteria a fila em silêncio.
 */
it('UC-PAPR-04 · Urgente sobe na fila', function () {
    $this->actAsAdmin();
    jornadaPrecisaDeSchema();

    // A normal é criada DEPOIS — se a ordem fosse só por data, ela viria antes.
    jornadaCriarIntercorrencia((int) $this->business->id, (int) $this->admin->id, ['prioridade' => 'URGENTE']);
    jornadaCriarIntercorrencia((int) $this->business->id, (int) $this->admin->id, ['prioridade' => 'NORMAL']);

    $resp = $this->inertiaPartialGet(
        '/ponto/aprovacoes',
        ['aprovacoes'],
        'Ponto/Aprovacoes/Index'
    );
    $resp->assertStatus(200);

    $prioridades = collect($resp->json('props.aprovacoes.data') ?? [])
        ->pluck('prioridade')
        ->filter()
        ->values();

    if ($prioridades->isEmpty()) {
        $this->markTestSkipped('Fila vazia nesta base — sem dado pra ordenar.');
    }

    $posUrgente = $prioridades->search('URGENTE');
    $posNormal  = $prioridades->search('NORMAL');

    if ($posUrgente === false || $posNormal === false) {
        $this->markTestSkipped('Base sem as duas prioridades simultaneamente.');
    }

    $this->assertLessThan(
        $posNormal,
        $posUrgente,
        'Intercorrência URGENTE precisa vir antes da NORMAL — priorização não pode '
        . 'depender de sorte (CU-PONTO-06).'
    );
});

// =====================================================================
// Intercorrencias/Show
// =====================================================================

/**
 * Contrato: CU-PONTO-05 + US-PONTO-003 (ciclo RASCUNHO → PENDENTE → ...)
 * + IntercorrenciaController@edit (abort_unless RASCUNHO, 403).
 */
it('UC-INTSH-01 · Só rascunho pode ser editado', function () {
    $this->actAsAdmin();
    jornadaPrecisaDeSchema();

    $aprovada = jornadaCriarIntercorrencia(
        (int) $this->business->id,
        (int) $this->admin->id,
        ['estado' => Intercorrencia::ESTADO_APROVADA]
    );

    $this->get("/ponto/intercorrencias/{$aprovada}/edit")
        ->assertStatus(403);

    $this->assertSame(
        Intercorrencia::ESTADO_APROVADA,
        jornadaLerCru($aprovada)->estado,
        'Intercorrência já decidida não volta a ser editável — mexer nela por fora '
        . 'quebra a trilha da decisão (CU-PONTO-05).'
    );
});

/**
 * Contrato: CU-PONTO-12 + US-PONTO-007 + ADR 0093 + LGPD Art. 7º II.
 * O detalhe expõe motivo médico e justificativa em texto livre.
 */
it('UC-INTSH-02 · Intercorrência de outro empregador → 404', function () {
    $this->actAsAdmin();
    jornadaPrecisaDeSchema();

    $bizAlheio = $this->garantirBizAlheio();

    if ((int) $this->business->id === $bizAlheio) {
        $this->markTestSkipped('Business logado colide com o business fictício do teste.');
    }

    // Controle positivo: a MINHA abre. Sem ele, um 404 vindo de rota quebrada,
    // permissão ausente ou tela morta passaria por "isolamento funcionando".
    $meu = jornadaCriarIntercorrencia((int) $this->business->id, (int) $this->admin->id);
    $this->inertiaGet("/ponto/intercorrencias/{$meu}")->assertStatus(200);

    $alheio = jornadaCriarIntercorrencia(
        (int) $this->business->id,
        (int) $this->admin->id,
        ['business_id' => $bizAlheio]
    );

    $this->inertiaGet("/ponto/intercorrencias/{$alheio}")
        ->assertStatus(404); // nunca 200 com o conteúdo alheio
});

/**
 * Contrato: CU-PONTO-06 + US-PONTO-003 (aprovador_id, aprovado_em, motivo_rejeicao).
 * Pareia com UC-PAPR-01: aquele garante que o motivo EXISTE, este que ele APARECE.
 */
it('UC-INTSH-03 · O detalhe mostra quem decidiu e por quê', function () {
    $this->actAsAdmin();
    jornadaPrecisaDeSchema();

    $motivo = 'Atestado sem CID legível — reapresentar.';
    $id = jornadaCriarIntercorrencia((int) $this->business->id, (int) $this->admin->id, [
        'estado'          => Intercorrencia::ESTADO_REJEITADA,
        'aprovador_id'    => $this->admin->id,
        'motivo_rejeicao' => $motivo,
    ]);

    $resp = $this->inertiaGet("/ponto/intercorrencias/{$id}");
    $this->assertInertiaComponent($resp, 'Ponto/Intercorrencias/Show');

    $inc = $resp->json('props.intercorrencia');

    $this->assertSame(
        Intercorrencia::ESTADO_REJEITADA,
        $inc['estado'] ?? null,
        'O detalhe precisa mostrar o estado da decisão.'
    );
    $this->assertSame(
        $motivo,
        $inc['motivo_rejeicao'] ?? null,
        'O motivo da rejeição precisa aparecer no detalhe — exigir a justificativa na '
        . 'entrada e não exibi-la na saída é trilha que não serve pra nada (CU-PONTO-06).'
    );
    $this->assertNotEmpty(
        $inc['aprovador']['nome'] ?? null,
        'Quem decidiu precisa estar identificado no documento (US-PONTO-003).'
    );
});
