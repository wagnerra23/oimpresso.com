<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato das telas de intercorrência — fila (`/ponto/intercorrencias`) e registro
 * (`/ponto/intercorrencias/create`).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Intercorrencias/Index.casos.md  → UC-INTIDX-01..03
 *   Intercorrencias/Create.casos.md → UC-INTCRE-01..02
 *
 * Os UC derivam do SDD §6.2 (CU-PONTO-05) e §6.5 (CU-PONTO-12) + US-PONTO-003 +
 * fluxo F4 (§5.3). NÃO do `.tsx`.
 *
 * ⚠️ UM UC nasce FAILING-FIRST por desenho:
 *   UC-INTCRE-01 → `business_id` NUNCA é atribuído no caminho de criação. Medido:
 *                  o FormRequest não declara a chave, o Service seta só
 *                  codigo/solicitante_id/estado, o `creating` só gera UUID, o trait
 *                  HasBusinessScope só adiciona scope de LEITURA, e há 0 `observe()`
 *                  no módulo. A coluna é NOT NULL + FK, sem default. O próprio
 *                  Service denuncia que sabia: usa `($dados['business_id'] ?? 0)`.
 *                  Registrar intercorrência pela tela NÃO grava.
 *
 * Não duplica as telas irmãs: a DECISÃO (aprovar/rejeitar/lote cross-tenant) é
 * UC-APROV-* em Aprovacoes/Index; o DETALHE é UC-INTSHOW-* em Intercorrencias/Show.
 *
 * PII: os asserts comparam ids, nunca o texto da justificativa; a fixture usa texto
 * neutro (LGPD Art. 7º II · SDD §3.1).
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101). Sem
 * RefreshDatabase: a lane ponto-pest proíbe.
 *
 * @see \Modules\Ponto\Http\Controllers\IntercorrenciaController
 */

const INTC_MARCADOR = 'SDD-INTC-CONTRATO';
const INTC_BIZ_ALHEIO = 99;
const INTC_BIZ_NOME = 'INTC Test Biz Adversario#99';

function intcPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/** Stub do biz fictício — sem ele o INSERT morre na FK (medido na run 30778424885). */
function intcGarantirBizAlheio(): void
{
    if (\App\Business::find(INTC_BIZ_ALHEIO)) {
        return;
    }

    \App\Business::forceCreate([
        'id'                              => INTC_BIZ_ALHEIO,
        'name'                            => INTC_BIZ_NOME,
        'currency_id'                     => 1,
        'start_date'                      => now()->toDateString(),
        'default_profit_percent'          => 0,
        'owner_id'                        => 1,
        'stop_selling_before'             => 0,
        'weighing_scale_setting'          => '',
        'certificado'                     => '',
        'officeimpresso_numerodemaquinas' => 0,
    ]);
}

/**
 * GET com cabeçalho Inertia.
 *
 * MEDIDO na run 30779959209: `$this->get(...)` cru devolve o HTML da página (o
 * Inertia só responde JSON quando o request se declara Inertia), então
 * `->json('props...')` estoura "Invalid JSON was returned from the route" — o caso
 * morre sem exercer nada. Mesmo helper que o EspelhoContratoTest usa.
 */
function intcInertiaGet(string $url)
{
    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    return test()->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get($url);
}

function intcCriarColaborador(int $businessId, int $userBusinessId): Colaborador
{
    $user = User::factory()->create([
        'business_id' => $userBusinessId,
        'user_type'   => 'user',
    ]);

    $colab = new Colaborador();
    $colab->forceFill([
        'business_id'    => $businessId,
        'user_id'        => $user->id,
        'matricula'      => INTC_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
        'admissao'       => '2019-01-01',
    ])->save();

    return $colab;
}

/** Justificativa NEUTRA de propósito — nada de conteúdo sensível em fixture (LGPD). */
function intcCriarIntercorrencia(int $businessId, int $colaboradorId, int $solicitanteId, string $estado): Intercorrencia
{
    $i = new Intercorrencia();
    $i->forceFill([
        'business_id'           => $businessId,
        'colaborador_config_id' => $colaboradorId,
        'codigo'                => INTC_MARCADOR . '-' . strtoupper(substr(uniqid(), -8)),
        'tipo'                  => 'OUTRO',
        'data'                  => '2019-03-11',
        'dia_todo'              => true,
        'justificativa'         => 'Fixture de contrato SDD — texto neutro, sem PII.',
        'estado'                => $estado,
        'prioridade'            => 'NORMAL',
        'solicitante_id'        => $solicitanteId,
    ])->save();

    return $i;
}

afterEach(function () {
    try {
        DB::table('ponto_intercorrencias')
            ->where('codigo', 'like', INTC_MARCADOR . '%')
            ->delete();

        $ids = Colaborador::withoutGlobalScopes()
            ->where('matricula', 'like', INTC_MARCADOR . '%')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('ponto_intercorrencias')->whereIn('colaborador_config_id', $ids)->delete();
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }

        // Só o stub deste arquivo (filtro por nome próprio).
        \App\Business::where('id', INTC_BIZ_ALHEIO)
            ->where('name', INTC_BIZ_NOME)
            ->delete();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
});

// =====================================================================
// Intercorrencias/Index — a fila
// =====================================================================

it('UC-INTIDX-01 · a fila traz as intercorrências do meu empregador', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);

    $colab = intcCriarColaborador($this->business->id, $this->business->id);
    $inc = intcCriarIntercorrencia(
        $this->business->id, $colab->id, $this->admin->id, Intercorrencia::ESTADO_PENDENTE
    );

    $resp = intcInertiaGet('/ponto/intercorrencias');
    $resp->assertStatus(200);

    $linhas = collect($resp->json('props.intercorrencias.data') ?? []);

    // Pré-condição anti-vácuo (proibicoes.md §5 2026-07-24 LC-13).
    expect($linhas)->not->toBeEmpty('A fila veio vazia — o caso não exerceu nada.');

    $minha = $linhas->firstWhere('id', $inc->id);
    expect($minha)->not->toBeNull(
        'A intercorrência do meu business tem de aparecer na fila (CU-PONTO-05).'
    );
    expect($minha['estado'])->toBe(Intercorrencia::ESTADO_PENDENTE,
        'A fila tem de mostrar o estado — é por ele que o RH sabe o que falta decidir.'
    );
    // Identidade: `optional()` encadeado devolve '—' em silêncio se o eager-load sumir.
    expect($minha['colaborador']['matricula'])->toStartWith(INTC_MARCADOR,
        'A linha tem de trazer a matrícula do colaborador, não um placeholder.'
    );
});

it('UC-INTIDX-02 · intercorrência de outro empregador não aparece na fila', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);
    intcGarantirBizAlheio();

    $meuColab    = intcCriarColaborador($this->business->id, $this->business->id);
    $alheioColab = intcCriarColaborador(INTC_BIZ_ALHEIO, $this->business->id);

    $minha  = intcCriarIntercorrencia(
        $this->business->id, $meuColab->id, $this->admin->id, Intercorrencia::ESTADO_PENDENTE
    );
    $alheia = intcCriarIntercorrencia(
        INTC_BIZ_ALHEIO, $alheioColab->id, $this->admin->id, Intercorrencia::ESTADO_PENDENTE
    );

    $resp = intcInertiaGet('/ponto/intercorrencias');
    $resp->assertStatus(200);

    // Compara IDs — nunca o texto da justificativa (PII, LGPD Art. 7º II).
    $ids = collect($resp->json('props.intercorrencias.data') ?? [])->pluck('id')->all();

    // Pré-condição anti-vácuo: sem a minha, "a alheia não está" seria verdade por
    // lista vazia, não por isolamento.
    $this->assertContains(
        $minha->id,
        $ids,
        'A minha intercorrência tem de estar na fila — senão o caso não exerce isolamento.'
    );
    $this->assertNotContains(
        $alheia->id,
        $ids,
        'Intercorrência de OUTRO empregador não pode aparecer — a justificativa é dado '
        . 'sensível (ADR 0093 · CU-PONTO-12 · LGPD Art. 7º II).'
    );
});

it('UC-INTIDX-03 · filtrar por estado devolve só aquele estado', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);

    $colab = intcCriarColaborador($this->business->id, $this->business->id);

    $pendente = intcCriarIntercorrencia(
        $this->business->id, $colab->id, $this->admin->id, Intercorrencia::ESTADO_PENDENTE
    );
    $rascunho = intcCriarIntercorrencia(
        $this->business->id, $colab->id, $this->admin->id, Intercorrencia::ESTADO_RASCUNHO
    );

    $resp = intcInertiaGet('/ponto/intercorrencias?estado=' . Intercorrencia::ESTADO_PENDENTE);
    $resp->assertStatus(200);

    $ids = collect($resp->json('props.intercorrencias.data') ?? [])->pluck('id')->all();

    // Os DOIS lados: só o positivo passaria com o filtro desligado (o `when()` vira
    // no-op silencioso se a chave do request mudar de nome).
    $this->assertContains(
        $pendente->id,
        $ids,
        'Filtrar por PENDENTE tem de trazer a intercorrência pendente.'
    );
    $this->assertNotContains(
        $rascunho->id,
        $ids,
        'Filtrar por PENDENTE NÃO pode trazer o rascunho. Filtro que vira no-op em '
        . 'silêncio faz o RH decidir sobre a lista errada (CU-PONTO-05).'
    );
});

// =====================================================================
// Intercorrencias/Create — o registro
// =====================================================================

it('UC-INTCRE-01 · registrar uma intercorrência cria o rascunho', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);

    $colab = intcCriarColaborador($this->business->id, $this->business->id);

    $antes = DB::table('ponto_intercorrencias')
        ->where('colaborador_config_id', $colab->id)
        ->count();

    $this->post('/ponto/intercorrencias', [
        'colaborador_config_id' => $colab->id,
        'tipo'                  => 'ATESTADO_MEDICO',
        'data'                  => '2019-03-11',
        'dia_todo'              => true,
        'justificativa'         => 'Fixture de contrato SDD — texto neutro, sem PII.',
        'prioridade'            => 'NORMAL',
    ]);

    // Lê o ESTADO PERSISTIDO, não o status HTTP: o assert vale para qualquer correção
    // (injetar business_id no Service, no creating, ou no FormRequest).
    $criada = DB::table('ponto_intercorrencias')
        ->where('colaborador_config_id', $colab->id)
        ->first();

    expect($criada)->not->toBeNull(
        'Registrar a intercorrência tem de GRAVAR (CU-PONTO-05). Hoje o `business_id` nunca é '
        . 'atribuído no caminho de criação — o FormRequest não declara a chave, o Service seta '
        . 'só codigo/solicitante_id/estado, o `creating` só gera UUID, o trait HasBusinessScope '
        . 'só adiciona scope de LEITURA e há 0 observe() no módulo. A coluna é NOT NULL + FK, '
        . 'sem default.'
    );
    expect($criada->estado)->toBe(Intercorrencia::ESTADO_RASCUNHO,
        'A intercorrência nasce RASCUNHO — só vai a PENDENTE por ação explícita (US-PONTO-003).'
    );
    expect((int) DB::table('ponto_intercorrencias')->where('colaborador_config_id', $colab->id)->count())
        ->toBe($antes + 1, 'Exatamente uma intercorrência tem de ter sido criada.');
});

it('UC-INTCRE-02 · a lista de colaboradores traz só os do meu empregador', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config']);
    intcGarantirBizAlheio();

    $meu    = intcCriarColaborador($this->business->id, $this->business->id);
    $alheio = intcCriarColaborador(INTC_BIZ_ALHEIO, $this->business->id);

    $resp = intcInertiaGet('/ponto/intercorrencias/create');
    $resp->assertStatus(200);

    $ids = collect($resp->json('props.colaboradores') ?? [])->pluck('id')->all();

    // Pré-condição anti-vácuo: sem o meu, "o alheio não está" seria verdade por lista
    // vazia, não por isolamento.
    $this->assertContains(
        $meu->id,
        $ids,
        'O colaborador do meu business tem de ser selecionável — senão o caso não exerce nada.'
    );
    $this->assertNotContains(
        $alheio->id,
        $ids,
        'Colaborador de OUTRO empregador não pode ser selecionável — o seletor expõe nome e '
        . 'matrícula (ADR 0093 · CU-PONTO-12 · LGPD Art. 7º).'
    );
});
