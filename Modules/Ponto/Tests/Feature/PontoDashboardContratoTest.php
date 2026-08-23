<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do PAINEL do Ponto (`/ponto`) — trio da tela `Ponto/Dashboard/Index`.
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Ponto/Dashboard/Index.casos.md → UC-PTDASH-01..06
 *
 * ── De onde os UC derivam ───────────────────────────────────────────────────
 * Do **contrato de tela** `prototipo-ui/contrato/ponto-painel.contract.json`
 * (copy + ordem, schema ADR 0286), do **charter** `Index.charter.md` (§Non-Goals,
 * §Automation hooks, §Anti-hooks) e dos invariantes `CU-PONTO-12`/`CU-PONTO-13`
 * do SDD §6.5. NÃO derivam do `Index.tsx` — teste derivado do código é
 * tautológico (proibicoes.md §5 2026-06-05).
 *
 * Onde o objeto do caso É a copy (UC-PTDASH-01) ou a ordem (UC-PTDASH-06), o assert lê o
 * **JSON do contrato** e confere contra o `.tsx`: o oráculo é o contrato, o
 * `.tsx` é o material sob teste. Hard-codar a lista aqui faria o teste concordar
 * consigo mesmo em vez de com a lei.
 *
 * ── Por que Pest `it()` e não classe PHPUnit ───────────────────────────────
 * O `casos-results-collect.mjs` (manifesto G-7) lê o UC-id do atributo `name` do
 * `<testcase>` no JUnit. Método PHP não aceita hífen, então um teste em classe
 * nasceria verde e valendo 0 no painel — é o estado dos 3 `*ContratoTest` de
 * docblock deste módulo. Nasce já no formato que conta.
 *
 * ── Tier 0 ─────────────────────────────────────────────────────────────────
 * Empregador próprio = o do seed da lane; adversário = business fictício **99**
 * via `PontoTestCase::garantirBizAlheio()`. NUNCA biz=4 (ROTA LIVRE, cliente
 * real — ADR 0358 mantém a proibição sem exceção). Sem `RefreshDatabase`: a lane
 * `ponto-pest` proíbe (dropa o schema e limpa o seed).
 *
 * ── Por que asserção do PHPUnit onde há MENSAGEM ───────────────────────────
 * `toContain()` do Pest é VARIÁDICO (todo argumento é mais um needle) e
 * `toHaveKey($k, $v)` toma o 2º argumento como VALOR esperado — em nenhum dos
 * dois o 2º argumento é mensagem. Passar mensagem ali não dá erro de sintaxe:
 * ela vira needle/valor e o assert reprova por um motivo que não é o do caso.
 * Custou 4 vermelhos na run 32654078783 (UC-PTDASH-02/03/05/06), com o `.tsx`
 * inteiro no diff da falha. Onde a asserção carrega mensagem, usamos
 * `assertStringContainsString` / `assertArrayHasKey` / `assertContains`, cuja
 * mensagem-por-último é contrato estável. Os `expect()` que ficaram usam só
 * matchers cuja aceitação de mensagem está PROVADA por run verde nesta lane.
 *
 * ── Limite honesto de alcance ──────────────────────────────────────────────
 * UC-PTDASH-01, -03 e -06 medem a apresentação por LEITURA ESTÁTICA do `.tsx`
 * (a resposta Inertia é JSON — a copy vive no componente, não no payload). Isso
 * prova que a copy e a ordem do DOM estão no arquivo; NÃO prova pixel nem
 * renderização real. O que fecha essa ponta é o gate `contrato-de-tela` (mesma
 * fonte) + o screenshot 1280/1440 do portão 6.9, que é ato de [W].
 *
 * Contrato: resources/js/Pages/Ponto/Dashboard/Index.casos.md
 *
 * @see \Modules\Ponto\Http\Controllers\DashboardController::index
 */

const PTPAINEL_MARCADOR = 'SDD-PTPAINEL-CONTRATO';
const PTPAINEL_PAGE = 'resources/js/Pages/Ponto/Dashboard/Index.tsx';
const PTPAINEL_CONTRATO = 'prototipo-ui/contrato/ponto-painel.contract.json';

/** Props que o charter §Automation hooks lista no ciclo de 30s. */
const PTPAINEL_PROPS_POLLING = ['kpis', 'presenca_agora', 'atividade_recente', 'alertas', 'server_time'];

/** Guard de ambiente — schema do Ponto presente. Skip gracioso e VISÍVEL. */
function ptPainelPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/** Lê o contrato de tela. É o ORÁCULO dos casos de copy/ordem — nunca uma cópia local. */
function ptPainelContrato(): array
{
    $raw = file_get_contents(base_path(PTPAINEL_CONTRATO));
    test()->assertNotFalse($raw, 'Contrato de tela ausente — sem ele os UC de copy/ordem não têm oráculo.');

    $json = json_decode($raw, true);
    test()->assertIsArray($json, 'Contrato de tela não é JSON válido.');

    return $json;
}

/** Copy declarada de uma seção do contrato. */
function ptPainelCopyDaSecao(string $id): array
{
    foreach (ptPainelContrato()['secoes'] ?? [] as $secao) {
        if (($secao['id'] ?? null) === $id) {
            return $secao['copy'] ?? [];
        }
    }

    test()->fail("Seção '{$id}' não existe no contrato — o UC perdeu a âncora.");
}

/** Fonte do `.tsx` sob teste. */
function ptPainelFonteDaPage(): string
{
    $src = file_get_contents(base_path(PTPAINEL_PAGE));
    test()->assertNotFalse($src, 'Page do painel ausente: ' . PTPAINEL_PAGE);

    return $src;
}

/**
 * Cria colaborador marcado pro cleanup.
 *
 * `forceFill` (e não `create`) porque o colaborador ADVERSÁRIO precisa nascer com
 * `business_id` de OUTRO empregador — o caminho normal não deixaria, e é
 * exatamente isso que o UC-PTDASH-02 quer ver barrado na LEITURA. O `user`
 * fica sempre no MEU business (evita FK pra business inexistente).
 */
function ptPainelCriarColaborador(int $businessId, int $userBusinessId): Colaborador
{
    $user = User::factory()->create([
        'business_id' => $userBusinessId,
        'user_type'   => 'user',
    ]);

    $colab = new Colaborador();
    $colab->forceFill([
        'business_id'    => $businessId,
        'user_id'        => $user->id,
        'matricula'      => PTPAINEL_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
    ])->save();

    return $colab;
}

/**
 * Marcação de ENTRADA hoje — alimenta `presentes_agora`, `presenca_agora` e o feed.
 *
 * O id do autor entra por PARÂMETRO: `PontoTestCase::$admin` é `protected`, e
 * função global não está no escopo da classe — `test()->admin` estoura
 * "Cannot access protected property" (medido na run 32654078783, UC-PTDASH-02).
 */
function ptPainelCriarMarcacaoHoje(Colaborador $colab, int $businessId, int $autorId): void
{
    $m = new Marcacao();
    $m->forceFill([
        'business_id'           => $businessId,
        'colaborador_config_id' => $colab->id,
        'momento'               => now()->format('Y-m-d') . ' 08:00:00',
        'origem'                => Marcacao::ORIGEM_REP_P,
        'tipo'                  => Marcacao::TIPO_ENTRADA,
        'usuario_criador_id'    => $autorId,
    ])->save();
}

/** Intercorrência PENDENTE — alimenta `aprovacoes_pendentes` e a fila. */
function ptPainelCriarIntercorrenciaPendente(Colaborador $colab, int $businessId, int $autorId): Intercorrencia
{
    $i = new Intercorrencia();
    $i->forceFill([
        'business_id'           => $businessId,
        'colaborador_config_id' => $colab->id,
        'codigo'                => PTPAINEL_MARCADOR . '-' . uniqid(),
        'tipo'                  => 'ABONO',
        'data'                  => now()->format('Y-m-d'),
        'justificativa'         => PTPAINEL_MARCADOR . ' fixture',
        'estado'                => Intercorrencia::ESTADO_PENDENTE,
        'prioridade'            => 1,
        'solicitante_id'        => $autorId,
    ])->save();

    return $i;
}

/** Pede props diferidas do painel numa passada partial (o que o polling faz). */
function ptPainelPartial(array $props)
{
    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    return test()->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => $version,
        'X-Inertia-Partial-Data'      => implode(',', $props),
        'X-Inertia-Partial-Component' => 'Ponto/Dashboard/Index',
        'Accept'                      => 'text/html',
    ])->get('/ponto');
}

afterEach(function () {
    try {
        $ids = Colaborador::withoutGlobalScopes()
            ->where('matricula', 'like', PTPAINEL_MARCADOR . '%')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            // Marcação é append-only via Eloquent + trigger — no cleanup de TESTE
            // usamos DB::table de propósito, jamais em código de produção.
            DB::table('ponto_marcacoes')->whereIn('colaborador_config_id', $ids)->delete();
            DB::table('ponto_intercorrencias')->whereIn('colaborador_config_id', $ids)->delete();
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }
    } catch (\Throwable $e) {
        // schema ausente / driver sem suporte — cleanup best-effort
    }

    // Depois do cleanup de fixtures: FK sem CASCADE (ver PontoTestCase).
    $this->removerBizAlheio();
});

// =====================================================================
// Ponto/Dashboard/Index — Painel do Ponto
// =====================================================================

it('UC-PTDASH-01 · os 6 KPIs aparecem na ordem e com a copy do contrato', function () {
    $copy = ptPainelCopyDaSecao('painel-kpis');
    $src  = ptPainelFonteDaPage();

    // Pré-condição anti-vácuo: contrato sem copy faria o loop abaixo passar sem
    // asserir nada (LC-13 — verde por não-execução).
    expect($copy)->toHaveCount(6,
        'O contrato declara 6 KPIs. Mudou a quantidade? Então é ato de [W] no contrato, e este número acompanha.'
    );

    $posicaoAnterior = -1;
    $anterior = null;

    foreach ($copy as $etiqueta) {
        $pos = strpos($src, $etiqueta);

        expect($pos)->not->toBeFalse(
            "A etiqueta \"{$etiqueta}\" está no contrato §painel-kpis e sumiu da tela. "
            . 'Copy é lei [W] — a correção é devolver a etiqueta, não editar o contrato.'
        );

        expect($pos)->toBeGreaterThan($posicaoAnterior,
            "\"{$etiqueta}\" aparece ANTES de \"{$anterior}\" no arquivo, e o contrato pede o inverso. "
            . 'Reordenar KPI muda o que o gestor lê primeiro e nenhum teste de payload pega.'
        );

        $posicaoAnterior = $pos;
        $anterior = $etiqueta;
    }
});

it('UC-PTDASH-02 · nenhum dado de outro empregador entra no painel', function () {
    $this->actAsAdmin();
    ptPainelPrecisaDe(['ponto_colaborador_config', 'ponto_marcacoes', 'ponto_intercorrencias']);
    $bizAlheio = $this->garantirBizAlheio();

    // O MEU dado primeiro — sem ele, "o alheio não está" seria verdade por lista
    // vazia, não por isolamento (LC-13).
    $autorId = $this->admin->id;

    $meu = ptPainelCriarColaborador($this->business->id, $this->business->id);
    ptPainelCriarMarcacaoHoje($meu, $this->business->id, $autorId);
    ptPainelCriarIntercorrenciaPendente($meu, $this->business->id, $autorId);

    $antes = ptPainelPartial(['kpis']);
    $antes->assertStatus(200);
    $kpisAntes = $antes->json('props.kpis');
    $this->assertIsArray($kpisAntes, 'Os KPIs precisam chegar na passada partial — sem eles o caso não mede nada.');

    // Agora nasce o adversário: mesmo dia, mesmas superfícies, OUTRO empregador.
    $alheio = ptPainelCriarColaborador($bizAlheio, $this->business->id);
    ptPainelCriarMarcacaoHoje($alheio, $bizAlheio, $autorId);
    ptPainelCriarIntercorrenciaPendente($alheio, $bizAlheio, $autorId);

    $depois = ptPainelPartial(['kpis', 'presenca_agora', 'atividade_recente', 'aprovacoes']);
    $depois->assertStatus(200);

    // (a) Agregado: nenhum dos KPIs pode ter se mexido.
    expect($depois->json('props.kpis'))->toBe($kpisAntes,
        'Nenhum KPI do meu empregador pode mudar porque nasceu marcação/intercorrência em OUTRO. '
        . 'Agregado que vaza não deixa linha pra ninguém notar (ADR 0093 · CU-PONTO-12).'
    );

    // (b) Presença ao vivo.
    $presenca = collect($depois->json('props.presenca_agora') ?? [])->pluck('id')->all();
    $this->assertContains($meu->id, $presenca, 'O meu colaborador tem de estar na presença — senão o caso não exerceu isolamento.');
    $this->assertNotContains($alheio->id, $presenca, 'Colaborador de OUTRO empregador não pode aparecer na presença ao vivo.');

    // (c) Feed de atividade.
    $feed = collect($depois->json('props.atividade_recente') ?? [])->pluck('colaborador.id')->all();
    $this->assertContains($meu->id, $feed, 'A marcação do meu colaborador tem de estar no feed — pré-condição do caso.');
    $this->assertNotContains($alheio->id, $feed, 'Marcação de OUTRO empregador não pode aparecer no feed de atividade.');

    // (d) Fila de aprovações.
    $fila = collect($depois->json('props.aprovacoes') ?? [])->pluck('colaborador.id')->all();
    $this->assertContains($meu->id, $fila, 'A intercorrência do meu colaborador tem de estar na fila — pré-condição do caso.');
    $this->assertNotContains($alheio->id, $fila, 'Intercorrência de OUTRO empregador não pode aparecer na fila de aprovações.');
});

it('UC-PTDASH-03 · fila vazia continua visível, com a frase de vazio', function () {
    $this->actAsAdmin();
    ptPainelPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);

    // Comportamento: sem pendência minha, a fila chega vazia (e a rota não quebra).
    $resp = ptPainelPartial(['aprovacoes']);
    $resp->assertStatus(200);

    $fila = collect($resp->json('props.aprovacoes') ?? [])
        ->filter(fn ($i) => str_starts_with((string) ($i['justificativa'] ?? ''), PTPAINEL_MARCADOR));

    expect($fila)->toBeEmpty('Sem intercorrência pendente deste fixture, a fila não pode trazer nenhuma.');

    // Contrato: o estado `vazio` é DECLARADO — a seção não some, ela fala.
    $copy = ptPainelCopyDaSecao('painel-fila-aprovacoes');
    $src  = ptPainelFonteDaPage();

    foreach ($copy as $frase) {
        $this->assertStringContainsString($frase, $src,
            "A copy \"{$frase}\" está no contrato §painel-fila-aprovacoes e sumiu da tela. "
            . 'O empty state é estado declarado (`vazio`), não ausência — "lista vazia devolve nada" apaga o aviso junto.'
        );
    }

    $this->assertStringContainsString('data-contract="painel-fila-aprovacoes"', $src,
        'A âncora da fila precisa existir mesmo no estado vazio — é ela que o gate `contrato-de-tela` vigia.'
    );
});

it('UC-PTDASH-04 · o painel é read-only — nenhuma escrita parte dele', function () {
    $this->actAsAdmin();
    ptPainelPrecisaDe(['ponto_marcacoes', 'ponto_intercorrencias']);

    $marcacoesAntes = DB::table('ponto_marcacoes')->where('business_id', $this->business->id)->count();
    $intercAntes    = DB::table('ponto_intercorrencias')->where('business_id', $this->business->id)->count();

    // (a) Carregar o painel não pode escrever. Rota GET também escreve se alguém deixar.
    $this->inertiaGet('/ponto')->assertStatus(200);

    expect(DB::table('ponto_marcacoes')->where('business_id', $this->business->id)->count())
        ->toBe($marcacoesAntes,
            'Carregar o painel criou marcação. Marcação é append-only por força de lei '
            . '(Portaria MTP 671/2021) e o charter §Anti-hooks diz "não muta nada".'
        );

    expect(DB::table('ponto_intercorrencias')->where('business_id', $this->business->id)->count())
        ->toBe($intercAntes, 'Carregar o painel criou intercorrência — o charter §Non-Goals proíbe decidir aqui.');

    // (b) A rota do painel não atende verbo de escrita.
    foreach (['post', 'put', 'patch', 'delete'] as $verbo) {
        $resp = $this->{$verbo}('/ponto');

        expect($resp->getStatusCode())->toBeGreaterThanOrEqual(300,
            "A rota do painel respondeu {$resp->getStatusCode()} a um {$verbo} — o painel é read-only "
            . '(charter §Anti-hooks). Bater ponto/aprovar daqui é Non-Goal explícito.'
        );
    }
});

it('UC-PTDASH-05 · o polling recarrega só props de leitura', function () {
    $this->actAsAdmin();

    // O ciclo de 30s pede exatamente estas 5 (charter §Automation hooks).
    $resp = ptPainelPartial(PTPAINEL_PROPS_POLLING);
    $resp->assertStatus(200);

    $props = $resp->json('props') ?? [];

    foreach (PTPAINEL_PROPS_POLLING as $prop) {
        $this->assertArrayHasKey($prop, $props,
            "A prop '{$prop}' está no `only` do polling e não chegou. Prop pedida que não resolve "
            . 'deixa a tela em skeleton eterno (RUNBOOK-inertia-defer-pattern §3).'
        );
    }

    // Aqui a AUSÊNCIA É o contrato (`Inertia::defer`), não proxy de valor: a prop
    // fora do `only` não pode viajar, senão o ciclo de 30s vira carga cheia.
    $this->assertArrayNotHasKey('serie_7dias', $props,
        'O polling trouxe `serie_7dias`, que não está no `only`. O defer parou de pular a closure '
        . 'não pedida — 120 requisições/hora passam a pagar o groupBy de 7 dias à toa.'
    );

    // Controle positivo: a prop excluída CHEGA quando pedida — senão este caso
    // passaria com a rota quebrada devolvendo props vazio.
    $comSerie = ptPainelPartial(['serie_7dias']);
    $comSerie->assertStatus(200);
    $this->assertArrayHasKey('serie_7dias', $comSerie->json('props') ?? [],
        '`serie_7dias` não chega nem quando pedida — aí a ausência acima não provava defer, provava rota quebrada.'
    );
});

it('UC-PTDASH-06 · a nota de fechamento fica acima dos KPIs, nos 3 estados', function () {
    $contrato = ptPainelContrato();
    $ordem    = $contrato['ordem'] ?? [];
    $src      = ptPainelFonteDaPage();

    expect($ordem)->not->toBeEmpty('O contrato precisa declarar `ordem` — sem ela o caso não tem oráculo.');
    expect($ordem[0])->toBe('painel-nota-fechamento',
        'O contrato põe a nota de fechamento como PRIMEIRA seção. Se isso mudou, mudou por ato de [W].'
    );

    // Ordem de âncora é ordem de DOM (leitura), não de CSS.
    $posicaoAnterior = -1;
    $anterior = null;

    foreach ($ordem as $ancora) {
        $pos = strpos($src, 'data-contract="' . $ancora . '"');

        expect($pos)->not->toBeFalse("A âncora `{$ancora}` sumiu da tela — o gate `contrato-de-tela` a exige.");
        expect($pos)->toBeGreaterThan($posicaoAnterior,
            "`{$ancora}` aparece no DOM antes de `{$anterior}`, e o contrato pede o inverso. "
            . 'Mover a nota pra baixo dos KPIs tira o aviso do campo de leitura de quem abre a tela.'
        );

        $posicaoAnterior = $pos;
        $anterior = $ancora;
    }

    // O estado `sem-pendencia` é DECLARADO: no dia limpo a nota informa que pode
    // consolidar — ela não desaparece. Sem este assert, uma tela que só renderiza
    // a nota quando há pendência passaria nos asserts de ordem acima.
    $this->assertStringContainsString('consolidar', $src,
        'O contrato declara o estado `sem-pendencia`. A nota tem de falar também quando NÃO há nada '
        . 'travando ("a competência pode consolidar") — sumir no dia limpo é regressão, não conformidade.'
    );
});
