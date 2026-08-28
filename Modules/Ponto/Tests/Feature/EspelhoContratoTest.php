<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do Espelho de Ponto — trio derivado do SDD (agent `sdd-from-source`, ADR 0351).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Espelho/Index.casos.md → UC-ESPIDX-01..03
 *   Espelho/Show.casos.md  → UC-ESPSH-01..05
 *
 * Os UC derivam do SDD §6.1 (CU-PONTO-01..04) + CU-PONTO-12/13, ancorados em
 * LEI (CLT Art. 66/71/74 §2º · Portaria MTP 671/2021) e na Blade legada
 * `Modules/Ponto/Resources/views/espelho/*.blade.php` — NUNCA no `Show.tsx`
 * (teste derivado do código é tautológico — proibicoes §5 2026-06-05).
 *
 * ── Por que Pest `it()` e não método PHPUnit (conversão de 2026-08-28) ────────
 * O manifesto do G-7 (`casos-results-collect.mjs`) casa o UC-id contra o `name` do
 * `<testcase>` do JUnit, e o regex canônico (`scripts/lib/uc-regex.mjs`) exige a forma
 * `UC-ESPSH-01` — com hífen e maiúsculas. Em PHPUnit o `name` é o NOME DO MÉTODO, e PHP
 * não aceita `-` em identificador: `uc_espshow_01_…` nunca casa, então os 8 UC deste
 * arquivo rodavam verdes valendo ZERO no painel (`casos:report` os listava como `⛓`).
 * Em Pest o `name` é o título do `it()`, e o id chega ao manifesto. Isto é conversão de
 * FORMA: nenhuma asserção foi alterada. Mesmo idioma dos irmãos já convertidos
 * (`ImportacaoCreate/Index`, `Intercorrencia`, `BancoHorasIndex`).
 *
 * ⚠️ UC-ESPSH-01 NASCEU failing-first por desenho: denunciava a regressão D-1 do SDD §9
 * — o espelho lia `tem_divergencia`, atributo que não existe (varredura contada à época:
 * 2 ocorrências no repo, ambas no `EspelhoController`; não é coluna, não é accessor).
 * FATO DATADO 2026-08-28: o D-1 foi CORRIGIDO desde então (`20a2757a5e`, 2026-08-03,
 * "5 atributos fantasma" + F3 do Espelho `#6115`, 2026-08-21) — hoje `totais.divergencias`
 * conta por `estado === DIVERGENCIA` e a linha deriva `divergencia` do mesmo campo.
 * Logo a predição "vermelho" pode ter caducado. O veredito é o da lane/manifesto, nunca
 * esta leitura (G-7) — e o assert segue INTACTO justamente pra que quem responda seja a
 * lane, não o docblock.
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101).
 * Sem RefreshDatabase: a lane ponto-pest proíbe (dropa schema + limpa seed biz=1).
 *
 * @covers-us US-PONTO-005 US-PONTO-007 US-PONTO-008
 */

/** Marcador pra cleanup — todo fixture criado aqui carrega ele. */
const ESPELHO_MARCADOR = 'SDD-ESPELHO-CONTRATO';

/** Mês sintético bem no passado: não colide com dado real de biz=1. */
const ESPELHO_ANO = 2019;
const ESPELHO_MES = 3; // março/2019 — 31 dias

function espelhoMesRef(): string
{
    return sprintf('%04d-%02d', ESPELHO_ANO, ESPELHO_MES);
}

function espelhoPrecisaDeSchema(): void
{
    foreach (['ponto_colaborador_config', 'ponto_apuracao_dia', 'ponto_marcacoes'] as $t) {
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
function espelhoNovoUser(int $businessId): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'user_type'   => 'user',
    ]);
}

/**
 * Cria um colaborador do business informado, marcado pro cleanup.
 *
 * O `business_id` vem por PARÂMETRO (e não de `$this->business`) porque em Pest o helper
 * é função de arquivo, fora do escopo da classe — e a propriedade é `protected`. Quem
 * passa é o `it()`, que É escopo da classe. Mesmo idioma dos irmãos convertidos.
 *
 * @param  array<string,mixed>  $extra
 */
function espelhoCriarColaborador(int $businessId, array $extra = []): Colaborador
{
    return Colaborador::create(array_merge([
        'business_id'    => $businessId,
        'user_id'        => espelhoNovoUser($businessId)->id,
        'matricula'      => ESPELHO_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
        'admissao'       => '2019-01-01',
    ], $extra));
}

function espelhoLimparFixtures(): void
{
    try {
        $ids = Colaborador::withoutGlobalScopes()
            ->where('matricula', 'like', ESPELHO_MARCADOR . '%')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            // ApuracaoDia é recalculável (não append-only) — delete direto OK.
            ApuracaoDia::withoutGlobalScopes()->whereIn('colaborador_config_id', $ids)->delete();
            // Marcacao é append-only (trigger + Eloquent) — remoção só via DB::table
            // no cleanup de teste, jamais em código de produção.
            DB::table('ponto_marcacoes')->whereIn('colaborador_config_id', $ids)->delete();
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }
    } catch (\Throwable $e) {
        // schema ausente / driver sem suporte — cleanup best-effort
    }
}

afterEach(function () {
    espelhoLimparFixtures();
    $this->removerBizAlheio(); // depois do cleanup: FK sem CASCADE (ver PontoTestCase)
});

// =====================================================================
// Espelho/Show
// =====================================================================

/**
 * UC-ESPSH-01 · [must][V0]
 *
 * Contrato: CU-PONTO-02 (SDD §6.1) + US-PONTO-005 (aceitação cita Art. 66 e
 * Art. 71 §4º) + Blade legada, que contava por `estado === 'DIVERGENCIA'`.
 *
 * O assert é sobre COMPORTAMENTO ("o dia divergente aparece sinalizado"), não
 * sobre a chave literal do payload — há 2 correções legítimas (criar accessor
 * OU o controller passar a ler `estado`) e um assert por chave reprovaria uma
 * delas arbitrariamente.
 *
 * Sobre a predição de vermelho: ver o docblock do topo — ela é fato datado da criação
 * do caso, e o D-1 foi corrigido depois. Veredito real vem da lane (G-7), não daqui.
 */
it('UC-ESPSH-01 · Dia com divergência de apuração aparece sinalizado', function () {
    $this->actAsAdmin();
    espelhoPrecisaDeSchema();

    $colaborador = espelhoCriarColaborador($this->business->id);

    // Estado do mundo: a apuração DETECTOU violação de interjornada (Art. 66).
    // É exatamente o que ApuracaoService::addDivergencia() grava.
    ApuracaoDia::create([
        'business_id'                    => $this->business->id,
        'colaborador_config_id'          => $colaborador->id,
        'data'                           => sprintf('%04d-%02d-11', ESPELHO_ANO, ESPELHO_MES),
        'realizada_trabalhada_minutos'   => 540,
        'interjornada_violacao_minutos'  => 120,
        'estado'                         => ApuracaoDia::ESTADO_DIVERGENCIA,
        'divergencias'                   => [[
            'codigo'    => 'interjornada_insuficiente',
            'mensagem'  => 'Interjornada de 9h abaixo do mínimo de 11h (Art. 66 CLT).',
        ]],
    ]);

    $url = "/ponto/espelho/{$colaborador->id}?mes=" . espelhoMesRef();
    $resp = $this->inertiaPartialGet($url, ['totais', 'linhas'], 'Ponto/Espelho/Show');
    $resp->assertStatus(200);

    $props = $resp->json('props');

    // (a) O mês informa que existe pelo menos 1 dia divergente.
    $contador = $props['totais']['divergencias'] ?? null;
    $this->assertNotNull(
        $contador,
        'O espelho precisa informar quantos dias do mês estão divergentes (CU-PONTO-02).'
    );
    $this->assertGreaterThanOrEqual(
        1,
        (int) $contador,
        'Apuração gravada em estado DIVERGENCIA (violação de interjornada, Art. 66 CLT) '
        . 'não foi contada pelo espelho. A Blade legada contava por `estado`; o React lê '
        . '`tem_divergencia`, que não existe como coluna nem accessor (SDD §9 D-1).'
    );

    // (b) O dia específico está marcado na tabela dia-a-dia.
    $linhas = $props['linhas'] ?? [];
    $diaAlvo = collect($linhas)->firstWhere('data', sprintf('%04d-%02d-11', ESPELHO_ANO, ESPELHO_MES));
    $this->assertNotNull($diaAlvo, 'O dia com apuração precisa existir na tabela dia-a-dia.');
    $this->assertTrue(
        (bool) ($diaAlvo['divergencia'] ?? false),
        'O dia cuja apuração violou a CLT precisa vir sinalizado na linha (SDD §9 D-1).'
    );
});

/**
 * UC-ESPSH-02 · [must]
 *
 * Contrato: CU-PONTO-01 + charter §Goals ("todos os dias do mês") + Blade legada.
 */
it('UC-ESPSH-02 · Espelho cobre todos os dias do mês, não só os com marcação', function () {
    $this->actAsAdmin();
    espelhoPrecisaDeSchema();

    $colaborador = espelhoCriarColaborador($this->business->id);

    // Uma única apuração no mês — o espelho ainda assim deve trazer o mês inteiro.
    ApuracaoDia::create([
        'business_id'           => $this->business->id,
        'colaborador_config_id' => $colaborador->id,
        'data'                  => sprintf('%04d-%02d-05', ESPELHO_ANO, ESPELHO_MES),
        'estado'                => ApuracaoDia::ESTADO_CALCULADO,
    ]);

    $url = "/ponto/espelho/{$colaborador->id}?mes=" . espelhoMesRef();
    $resp = $this->inertiaPartialGet($url, ['linhas'], 'Ponto/Espelho/Show');
    $resp->assertStatus(200);

    $linhas = $resp->json('props.linhas') ?? [];
    $diasDoMes = (int) date('t', mktime(0, 0, 0, ESPELHO_MES, 1, ESPELHO_ANO));
    $mesRef = espelhoMesRef();

    $this->assertCount(
        $diasDoMes,
        $linhas,
        "O espelho de {$mesRef} precisa ter {$diasDoMes} linhas (todos os dias), "
        . 'inclusive dias sem marcação — dia vazio é informação (falta/folga/feriado).'
    );
});

/**
 * UC-ESPSH-03 · [must]
 *
 * Contrato: CU-PONTO-13 + US-PONTO-008 ("para corrigir: criar marcação com
 * origem=ANULACAO") + Portaria MTP 671/2021 (append-only).
 */
it('UC-ESPSH-03 · Marcação anulada não conta como jornada', function () {
    $this->actAsAdmin();
    espelhoPrecisaDeSchema();

    $colaborador = espelhoCriarColaborador($this->business->id);
    $dia = sprintf('%04d-%02d-07', ESPELHO_ANO, ESPELHO_MES);

    // `usuario_criador_id` é obrigatório: coluna `int NOT NULL` sem default, guardada
    // pela FK `ponto_marcacoes_usuario_criador_id_foreign` → `users`. O Model NÃO
    // preenche (sem default, sem observer); quem exige é o Service —
    // `MarcacaoService::registrar()` lança `RuntimeException` se vier vazio. Este caso
    // monta o estado pelo Model de propósito, pra testar a LEITURA do espelho, então
    // precisa passar o campo à mão. Sem ele o MySQL usa o 0 implícito, a FK reprova
    // com 1452, e o caso morre no fixture — antes de exercer o contrato de anulação
    // (CU-PONTO-13 · Portaria MTP 671/2021). Os 6 testes verdes do módulo que criam
    // Marcacao pelo Model já passam o campo; só este arquivo não passava.
    Marcacao::create([
        'business_id'           => $this->business->id,
        'colaborador_config_id' => $colaborador->id,
        'momento'               => $dia . ' 08:00:00',
        'origem'                => Marcacao::ORIGEM_REP_P,
        'tipo'                  => Marcacao::TIPO_ENTRADA,
        'usuario_criador_id'    => $this->admin->id,
    ]);

    // A correção legal: NÃO edita a anterior — acrescenta a anulação.
    Marcacao::create([
        'business_id'           => $this->business->id,
        'colaborador_config_id' => $colaborador->id,
        'momento'               => $dia . ' 09:00:00',
        'origem'                => Marcacao::ORIGEM_ANULACAO,
        'tipo'                  => Marcacao::TIPO_ENTRADA,
        'usuario_criador_id'    => $this->admin->id,
    ]);

    $url = "/ponto/espelho/{$colaborador->id}?mes=" . espelhoMesRef();
    $resp = $this->inertiaPartialGet($url, ['linhas'], 'Ponto/Espelho/Show');
    $resp->assertStatus(200);

    $linha = collect($resp->json('props.linhas') ?? [])->firstWhere('data', $dia);
    $this->assertNotNull($linha, 'O dia com marcação precisa existir no espelho.');

    $origens = collect($linha['marcacoes'] ?? [])->pluck('origem')->all();

    $this->assertContains(
        Marcacao::ORIGEM_REP_P,
        $origens,
        'A marcação original permanece no espelho — append-only preserva o registro.'
    );
    $this->assertNotContains(
        Marcacao::ORIGEM_ANULACAO,
        $origens,
        'Marcação de anulação não pode aparecer como jornada — somaria o registro '
        . 'corrigido E a correção, inflando a jornada apurada (CU-PONTO-13).'
    );
});

/**
 * UC-ESPSH-04 · [must][T0]
 *
 * Contrato: CU-PONTO-12 + US-PONTO-007 + ADR 0093 + LGPD Art. 7º II.
 * biz=1 contra id fora do tenant — NUNCA biz=4 (ADR 0101).
 */
it('UC-ESPSH-04 · Espelho de colaborador de outro empregador → 404', function () {
    $this->actAsAdmin();
    espelhoPrecisaDeSchema();

    // Colaborador que existe, mas pertence a OUTRO business (fictício, nunca biz=4).
    //
    // `garantirBizAlheio()` é idempotente e DEVOLVE o id do fictício — usar o retorno
    // mantém o guard ancorado na const `BIZ_ALHEIO_FICTICIO` da base (era `self::`
    // dentro da classe; em closure Pest o `self::` não resolve pra ela). Chamar antes
    // do guard é inerte: se o business logado FOR o fictício, ele já existe e o helper
    // não cria nada.
    $bizAlheio = $this->garantirBizAlheio();

    if ((int) $this->business->id === $bizAlheio) {
        $this->markTestSkipped('Business logado colide com o business fictício do teste.');
    }

    $alheioId = DB::table('ponto_colaborador_config')->insertGetId([
        'business_id'    => $bizAlheio,
        'user_id'        => espelhoNovoUser($this->business->id)->id,
        'matricula'      => ESPELHO_MARCADOR . '-ALHEIO-' . uniqid(),
        'controla_ponto' => 1,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    try {
        $this->inertiaGet("/ponto/espelho/{$alheioId}")
            ->assertStatus(404); // nunca 200 com dado alheio, nunca 500
    } finally {
        DB::table('ponto_colaborador_config')->where('id', $alheioId)->delete();
    }
});

/**
 * UC-ESPSH-05 · [should]
 *
 * Contrato: CU-PONTO-01 + charter §Automation hooks (Inertia::defer em totais/linhas)
 * + RUNBOOK-inertia-defer-pattern.
 */
it('UC-ESPSH-05 · Totais e linhas chegam sob demanda, sem quebrar o contrato', function () {
    $this->actAsAdmin();
    espelhoPrecisaDeSchema();

    $colaborador = espelhoCriarColaborador($this->business->id);
    $url = "/ponto/espelho/{$colaborador->id}?mes=" . espelhoMesRef();

    // 1ª passada: o cabeçalho barato já vem resolvido.
    $primeira = $this->inertiaGet($url);
    $this->assertInertiaComponent($primeira, 'Ponto/Espelho/Show');
    $this->assertNotNull(
        $primeira->json('props.colaborador'),
        'O cabeçalho do colaborador é eager — chega já na primeira resposta.'
    );

    // 2ª passada: quando peço, o dado caro chega COMPLETO.
    $segunda = $this->inertiaPartialGet($url, ['totais', 'linhas'], 'Ponto/Espelho/Show');
    $segunda->assertStatus(200);

    $this->assertNotNull(
        $segunda->json('props.totais'),
        'Prop diferida `totais` precisa resolver quando solicitada — senão a tela '
        . 'fica em skeleton eterno.'
    );
    $this->assertNotEmpty(
        $segunda->json('props.linhas') ?? [],
        'Prop diferida `linhas` precisa resolver quando solicitada.'
    );
});

// =====================================================================
// Espelho/Index
// =====================================================================

/**
 * UC-ESPIDX-01 · [must]
 *
 * Contrato: CU-PONTO-04 + charter §Mission + Blade `espelho/index.blade.php`
 * + CLT Art. 74 §2º (o registro é de quem está sujeito a controle).
 */
it('UC-ESPIDX-01 · Só entra na lista quem tem controle de ponto ativo e não foi desligado', function () {
    $this->actAsAdmin();
    espelhoPrecisaDeSchema();

    $ativo       = espelhoCriarColaborador($this->business->id);
    $semControle = espelhoCriarColaborador($this->business->id, ['controla_ponto' => false]);
    $desligado   = espelhoCriarColaborador($this->business->id, ['desligamento' => '2019-02-01']);

    $resp = $this->inertiaPartialGet('/ponto/espelho', ['colaboradores'], 'Ponto/Espelho/Index');
    $resp->assertStatus(200);

    $ids = collect($resp->json('props.colaboradores.data') ?? [])->pluck('id')->all();

    $this->assertContains($ativo->id, $ids, 'Colaborador ativo com controle de ponto deve aparecer.');
    $this->assertNotContains(
        $semControle->id,
        $ids,
        'Quem não tem controle de ponto não tem jornada a auditar (CU-PONTO-04).'
    );
    $this->assertNotContains(
        $desligado->id,
        $ids,
        'Colaborador desligado não entra no fechamento do mês (CU-PONTO-04).'
    );
});

/**
 * UC-ESPIDX-02 · [must][T0]
 *
 * Contrato: CU-PONTO-12 + US-PONTO-007 + ADR 0093 + LGPD Art. 7º II.
 * A lista expõe matrícula/CPF/e-mail — PII a cada carga.
 */
it('UC-ESPIDX-02 · A lista não atravessa empregadores', function () {
    $this->actAsAdmin();
    espelhoPrecisaDeSchema();

    // Mesmo motivo do UC-ESPSH-04: o id do fictício vem do RETORNO do helper da base,
    // não de uma const repetida neste arquivo.
    $bizAlheio = $this->garantirBizAlheio();

    if ((int) $this->business->id === $bizAlheio) {
        $this->markTestSkipped('Business logado colide com o business fictício do teste.');
    }

    // O MEU, que TEM de aparecer — é a pré-condição anti-vácuo (ver asserção abaixo).
    $meu = espelhoCriarColaborador($this->business->id);

    $alheioId = DB::table('ponto_colaborador_config')->insertGetId([
        'business_id'    => $bizAlheio,
        'user_id'        => espelhoNovoUser($this->business->id)->id,
        'matricula'      => ESPELHO_MARCADOR . '-ALHEIO-' . uniqid(),
        'controla_ponto' => 1,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    try {
        $resp = $this->inertiaPartialGet('/ponto/espelho', ['colaboradores'], 'Ponto/Espelho/Index');
        $resp->assertStatus(200);

        $ids = collect($resp->json('props.colaboradores.data') ?? [])->pluck('id')->all();

        // Sem isto, "o alheio não está na lista" seria verdade por LISTA VAZIA — o
        // caso ficaria verde com o escopo quebrado, com a query quebrada, ou com a
        // tela em branco. É o mesmo par que os casos verdes do módulo já usam.
        $this->assertContains(
            $meu->id,
            $ids,
            'O colaborador do MEU empregador tem de estar na lista — senão o caso '
            . 'não exerce isolamento nenhum, só constata que a lista veio vazia.'
        );
        $this->assertNotContains(
            $alheioId,
            $ids,
            'Colaborador de outro empregador não pode aparecer na lista do espelho (Tier 0, ADR 0093).'
        );
    } finally {
        DB::table('ponto_colaborador_config')->where('id', $alheioId)->delete();
    }
});

/**
 * UC-ESPIDX-03 · [should]
 *
 * Contrato: CU-PONTO-04 + charter §Goals + comentário literal da Blade legada
 * ("Seletor de mês é propagado para o show via querystring").
 */
it('UC-ESPIDX-03 · O mês escolhido viaja junto para o espelho', function () {
    $this->actAsAdmin();
    espelhoPrecisaDeSchema();

    $mes = espelhoMesRef();

    // A lista carrega o mês escolhido...
    $lista = $this->inertiaGet('/ponto/espelho', ['mes' => $mes]);
    $this->assertInertiaComponent($lista, 'Ponto/Espelho/Index');
    $this->assertSame($mes, $lista->json('props.mes'), 'A lista mantém o mês selecionado.');

    // ...e o espelho abre NAQUELE mês, não no corrente.
    $colaborador = espelhoCriarColaborador($this->business->id);
    $show = $this->inertiaGet("/ponto/espelho/{$colaborador->id}", ['mes' => $mes]);
    $this->assertInertiaComponent($show, 'Ponto/Espelho/Show');
    $this->assertSame(
        $mes,
        $show->json('props.mes'),
        'O espelho precisa abrir na competência escolhida na lista — conferir o mês '
        . 'errado no fechamento é erro silencioso e caro (CU-PONTO-04).'
    );
});
