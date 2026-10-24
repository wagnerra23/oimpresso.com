<?php
// @covers-us US-PCP-029
// @covers-us US-PCP-024
// @covers-us US-PCP-017

declare(strict_types=1);
// Cobre UC-PCV-05, UC-PCV-06, UC-PCV-07 e, no Painel, UC-PCV-01..04 e UC-PCV-29 (Producao.casos.md · thread 06 do playbook) e a entrada na tela de UC-FLX-10
// (alocador) e UC-FLX-13 (simulador de bobina), cujos endpoints já têm contrato próprio.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: UC-PCV-05 — "cada OS aparece uma vez, na coluna da etapa atual; o cartão traz nº, cliente,
 * etapa atual + máquina, progresso, prazo e responsável". Etapa atual = a em andamento; sem ela, a
 * próxima pendente. Tenant 98 × 99 (ADR 0358). ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_bobinas') || ! Schema::hasTable('comvis_os') || ! Schema::hasTable('comvis_orcamento_itens')) {
        $this->markTestSkipped('Tabelas pcp_* ou comvis_* ausentes nesta lane.');
    }
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->B99 = $this->seededSupportClientTenant();
    $this->b99 = (int) $this->B99->id;
    $this->gestor = $this->usuarioComPermissoes(['producao.access', 'producao.etapa.mover'], $this->B98);
    $this->op = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->g99 = $this->usuarioComPermissoes(['producao.access', 'producao.etapa.mover'], $this->B99);

    $this->pre = qdPosto($this->b98, 'Estação de arte', 'Pré-impressão');
    $this->imp = qdPosto($this->b98, 'Roland', 'Impressão');
    $this->aca = qdPosto($this->b98, 'Mesa de corte', 'Acabamento');
    $this->lona = (int) DB::table('comvis_materiais')->insertGetId(['business_id' => $this->b98, 'nome' => 'Lona QD '.uniqid(),
        'categoria' => 'lona', 'created_at' => now(), 'updated_at' => now()]);
});

function qdPosto(int $biz, string $nome, string $setor): int
{
    return (int) DB::table('pcp_postos')->insertGetId(['business_id' => $biz, 'codigo' => 'QD-'.uniqid(), 'nome' => $nome, 'setor' => $setor,
        'tipo_custo' => 'maquina', 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now()]);
}

/** OS da CV com uma peça medida na mídia; devolve o id da OS. */
function qdOs(int $biz, string $numero, ?string $prazo, ?int $midia = null): int
{
    $orc = (int) DB::table('comvis_orcamentos')->insertGetId(['business_id' => $biz, 'numero' => 'ORC-QD-'.uniqid(),
        'data_emissao' => '2026-10-20', 'created_at' => now(), 'updated_at' => now()]);
    $os = (int) DB::table('comvis_os')->insertGetId(['business_id' => $biz, 'orcamento_id' => $orc, 'numero' => $numero,
        'status_etapa' => 'producao', 'data_prazo' => $prazo, 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now()]);
    if ($midia !== null) {
        DB::table('comvis_orcamento_itens')->insert(['business_id' => $biz, 'orcamento_id' => $orc, 'material_id' => $midia,
            'descricao' => 'Faixa', 'largura_m' => '1.000', 'altura_m' => '0.500', 'quantidade' => 1, 'area_m2' => '0.500',
            'ordem' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    return $os;
}

function qdEtapa(int $biz, int $os, int $posto, string $nome, string $inicio, string $status, ?int $user = null): int
{
    return (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $biz, 'os_tipo' => 'comvis_os', 'os_id' => $os,
        'posto_id' => $posto, 'nome' => $nome, 'inicio_previsto' => $inicio, 'fim_previsto' => date('Y-m-d H:i:s', strtotime($inicio) + 3600),
        'status' => $status, 'user_id' => $user, 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
}

/** Prop deferida pedida como o browser pede (X-Inertia + X-Requested-With), com sessão nova. */
function qdProps($teste, $user, string $prop): array
{
    $teste->flushSession();
    $teste->actingAs($user);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $teste->get('/producao/quadro', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $prop, 'X-Inertia-Partial-Component' => 'Producao/Quadro/Index']);
    $r->assertOk();

    return (array) $r->json('props.'.$prop);
}

it('UC-PCV-05 · cada OS aparece uma vez, na coluna da etapa atual (em andamento antes da pendente)', function () {
    $hoje = now()->toDateString();
    // A: arte feita, impressão em andamento, acabamento pendente → atual = Impressão, 1 de 3 feita.
    $a = qdOs($this->b98, 'OS-QDA'.random_int(1000, 9999), $hoje);
    qdEtapa($this->b98, $a, $this->pre, 'Arte', '2026-11-03 08:00:00', 'concluida');
    $impA = qdEtapa($this->b98, $a, $this->imp, 'Impressão', '2026-11-03 09:00:00', 'iniciada', $this->op->id);
    qdEtapa($this->b98, $a, $this->aca, 'Corte', '2026-11-03 10:00:00', 'pendente');
    // D (discriminante): pendente mais cedo e iniciada mais tarde → atual é a iniciada, não a primeira por horário.
    $d = qdOs($this->b98, 'OS-QDD'.random_int(1000, 9999), null);
    qdEtapa($this->b98, $d, $this->pre, 'Arte', '2026-11-03 07:00:00', 'pendente');
    $acaD = qdEtapa($this->b98, $d, $this->aca, 'Corte', '2026-11-03 11:00:00', 'iniciada');
    // B: tudo concluído → fora do quadro. 99: outra empresa → fora (Tier 0).
    $b = qdOs($this->b98, 'OS-QDB'.random_int(1000, 9999), $hoje);
    qdEtapa($this->b98, $b, $this->pre, 'Arte', '2026-11-03 08:00:00', 'concluida');
    $os99 = qdOs($this->b99, 'OS-QD99'.random_int(1000, 9999), $hoje);
    qdEtapa($this->b99, $os99, qdPosto($this->b99, 'Roland 99', 'Impressão'), 'Impressão', '2026-11-03 08:00:00', 'pendente');

    $cartoes = collect(qdProps($this, $this->gestor, 'cartoes'))->keyBy('os_id');

    expect($cartoes->keys()->sort()->values()->all())->toBe(collect([$a, $d])->sort()->values()->all())
        ->and($cartoes[$a]['etapa']['id'])->toBe($impA)
        ->and($cartoes[$a]['etapa']['setor'])->toBe('Impressão')
        ->and($cartoes[$a]['etapa']['posto'])->toBe('Roland')
        ->and($cartoes[$a]['etapa']['responsavel'])->toBe(trim($this->op->first_name.' '.$this->op->last_name))
        ->and($cartoes[$a]['progresso'])->toBe(33)
        ->and($cartoes[$a]['atraso'])->toBe('vence hoje')
        ->and($cartoes[$d]['etapa']['id'])->toBe($acaD)
        ->and($cartoes[$d]['etapa']['setor'])->toBe('Acabamento')
        ->and($cartoes[$d]['atraso'])->toBeNull();

    // A empresa 99 vê só a sua OS.
    expect(collect(qdProps($this, $this->g99, 'cartoes'))->pluck('os_id')->all())->toBe([$os99]);
});

it('UC-PCV-05 · ver o quadro exige producao.access; o alocador só aparece para producao.etapa.mover', function () {
    $semAcesso = $this->usuarioComPermissoes([], $this->B98);
    $this->flushSession();
    $this->actingAs($semAcesso)->get('/producao/quadro')->assertForbidden();

    $this->flushSession();
    $this->actingAs($this->op)->get('/producao/quadro')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Producao/Quadro/Index', false)->where('pode_alocar', false));
    expect(qdProps($this, $this->op, 'pessoas'))->toBe(['pessoas' => [], 'por_posto' => []]);

    $pessoas = qdProps($this, $this->gestor, 'pessoas');
    $ids = collect($pessoas['pessoas'])->pluck('id');
    // Controle negativo: quem não tem producao.access não é oferecido como responsável.
    expect($ids)->toContain($this->op->id)->and($ids)->not->toContain($semAcesso->id)
        ->and(array_keys($pessoas['por_posto']))->toContain($this->imp);
});

it('UC-FLX-10 · o cartão leva a etapa atual que o alocador grava, e a realocação aparece no quadro', function () {
    $os = qdOs($this->b98, 'OS-QDL'.random_int(1000, 9999), null);
    $etapa = qdEtapa($this->b98, $os, $this->imp, 'Impressão', '2026-11-03 08:00:00', 'pendente', $this->op->id);
    $outro = $this->usuarioComPermissoes(['producao.access'], $this->B98);

    $cartao = collect(qdProps($this, $this->gestor, 'cartoes'))->firstWhere('os_id', $os);
    expect($cartao['etapa']['id'])->toBe($etapa)->and($cartao['etapa']['inicio_previsto'])->toBe('2026-11-03T08:00');

    $this->flushSession();
    $this->actingAs($this->gestor)->putJson("/producao/etapas/{$cartao['etapa']['id']}/alocacao",
        ['user_id' => $outro->id, 'inicio_previsto' => '2026-11-04 13:30:00'])->assertOk();

    $depois = collect(qdProps($this, $this->gestor, 'cartoes'))->firstWhere('os_id', $os);
    expect($depois['etapa']['user_id'])->toBe($outro->id)
        ->and($depois['etapa']['inicio_previsto'])->toBe('2026-11-04T13:30')
        ->and($depois['etapa']['fim_previsto'])->toBe('2026-11-04T14:30');
});

it('UC-FLX-13 · OS da CV com peça medida é marcável e a mídia chega ao simulador, com a bobina quando cadastrada', function () {
    $comPeca = qdOs($this->b98, 'OS-QDM'.random_int(1000, 9999), null, $this->lona);
    qdEtapa($this->b98, $comPeca, $this->pre, 'Arte', '2026-11-03 08:00:00', 'pendente');
    $semPeca = qdOs($this->b98, 'OS-QDN'.random_int(1000, 9999), null);
    qdEtapa($this->b98, $semPeca, $this->pre, 'Arte', '2026-11-03 08:00:00', 'pendente');

    $cartoes = collect(qdProps($this, $this->op, 'cartoes'))->keyBy('os_id');
    expect($cartoes[$comPeca]['midias'])->toBe([$this->lona])->and($cartoes[$semPeca]['midias'])->toBe([]);

    $midia = collect(qdProps($this, $this->op, 'midias'))->firstWhere('id', $this->lona);
    expect($midia['bobina'])->toBeNull();

    $this->flushSession();
    $this->actingAs($this->op)->putJson("/producao/bobinas/{$this->lona}", ['largura_util_m' => 1.6, 'margem_m' => 0.02])->assertOk();
    $midia = collect(qdProps($this, $this->op, 'midias'))->firstWhere('id', $this->lona);
    expect($midia['bobina'])->toBe(['largura_util_m' => '1.600', 'margem_m' => '0.020', 'permite_girar' => true]);
});

/** Uma OS da CV em Impressão (etapa iniciada, com cronômetro aberto) e Acabamento pendente. */
function qdOsEmImpressao($teste): array
{
    $os = qdOs($teste->b98, 'OS-QDV'.random_int(1000, 9999), null);
    qdEtapa($teste->b98, $os, $teste->pre, 'Arte', '2026-11-03 08:00:00', 'concluida');
    $imp = qdEtapa($teste->b98, $os, $teste->imp, 'Impressão', '2026-11-03 09:00:00', 'iniciada', $teste->op->id);
    $aca = qdEtapa($teste->b98, $os, $teste->aca, 'Corte', '2026-11-03 10:00:00', 'pendente');
    DB::table('pcp_apontamentos')->insert(['business_id' => $teste->b98, 'chave_idempotencia' => 'qdv-'.uniqid(), 'tipo' => 'inicio',
        'programacao_id' => $imp, 'os_tipo' => 'comvis_os', 'os_id' => $os, 'posto_id' => $teste->imp, 'tipo_custo' => 'maquina',
        'custo_hora' => '30.00', 'maquina_id' => $teste->imp, 'iniciado_por' => $teste->op->id, 'momento' => '2026-11-03 09:00:00',
        'origem' => 'qr', 'created_at' => now()]);

    return [$os, $imp, $aca];
}

/** Retrato do que mover NÃO pode mudar: situação das etapas e linhas de apontamento da OS. */
function qdEstadoDaOs(int $biz, int $os): array
{
    return [
        DB::table('pcp_programacoes')->where('business_id', $biz)->where('os_id', $os)->orderBy('id')->pluck('status', 'id')->all(),
        DB::table('pcp_apontamentos')->where('business_id', $biz)->where('os_id', $os)->count(),
    ];
}

it('UC-PCV-07 · o PCP move a OS para qualquer setor, para frente ou para trás; só a coluna muda e o evento fica com o nome dele', function () {
    // Fonte: UC-PCV-07 + protótipo `producao-cv-page.jsx` (menu ⋮ "Mover para" todos os setores menos o
    // atual, e arrastar para qualquer coluna): mover troca a coluna e registra "Moveu para X (à mão)".
    [$os] = qdOsEmImpressao($this);
    $antes = qdEstadoDaOs($this->b98, $os);

    $this->flushSession();
    $this->actingAs($this->gestor)->postJson('/producao/quadro/mover', ['os_tipo' => 'comvis_os', 'os_id' => $os, 'setor' => 'Acabamento'])
        ->assertOk()->assertJson(['coluna' => 'Acabamento']);
    $cartao = collect(qdProps($this, $this->gestor, 'cartoes'))->firstWhere('os_id', $os);
    expect($cartao['coluna'])->toBe('Acabamento')->and($cartao['movida'])->toBeTrue()
        // A etapa atual continua sendo a Impressão em andamento: o mover não conclui nem abre etapa.
        ->and($cartao['etapa']['setor'])->toBe('Impressão')->and($cartao['etapa']['status'])->toBe('iniciada')
        ->and(qdEstadoDaOs($this->b98, $os))->toBe($antes);

    // Para trás também (Pré-impressão já concluída), e o feed do Painel conta como no protótipo.
    $this->flushSession();
    $this->actingAs($this->gestor)->postJson('/producao/quadro/mover', ['os_tipo' => 'comvis_os', 'os_id' => $os, 'setor' => 'Pré-impressão'])->assertOk();
    expect(collect(qdProps($this, $this->gestor, 'cartoes'))->firstWhere('os_id', $os)['coluna'])->toBe('Pré-impressão')
        ->and(qdEstadoDaOs($this->b98, $os))->toBe($antes);

    $evs = DB::table('pcp_eventos')->where('business_id', $this->b98)->where('os_id', $os)->where('tipo', 'os_movida_quadro')->orderBy('id')->get();
    expect($evs)->toHaveCount(2)
        ->and((int) $evs[0]->user_id)->toBe($this->gestor->id)
        ->and(json_decode((string) $evs[0]->dados, true))->toBe(['de' => 'Impressão', 'para' => 'Acabamento'])
        ->and(json_decode((string) $evs[1]->dados, true))->toBe(['de' => 'Acabamento', 'para' => 'Pré-impressão']);
    $numero = DB::table('comvis_os')->where('id', $os)->value('numero');
    $feed = collect(pnPainel($this, $this->gestor)['eventos'])->where('os', $numero)->pluck('texto');
    expect($feed)->toContain('Moveu para Pré-impressão (à mão)')->and($feed)->toContain('Moveu para Acabamento (à mão)');
});

it('UC-PCV-07 · a coluna manual some quando o operador anda com a OS (etapa iniciada ou concluída depois do movimento)', function () {
    [$os, $imp] = qdOsEmImpressao($this);
    // Evento de etapa ANTERIOR ao movimento não desfaz nada (discriminante: o "andou" é o mais novo).
    DB::table('pcp_eventos')->insert(['business_id' => $this->b98, 'tipo' => 'etapa_iniciada', 'os_tipo' => 'comvis_os', 'os_id' => $os,
        'posto_id' => $this->imp, 'user_id' => $this->op->id, 'dados' => json_encode(['programacao_id' => $imp]), 'created_at' => now()]);
    $this->flushSession();
    $this->actingAs($this->gestor)->postJson('/producao/quadro/mover', ['os_tipo' => 'comvis_os', 'os_id' => $os, 'setor' => 'Acabamento'])->assertOk();
    expect(collect(qdProps($this, $this->gestor, 'cartoes'))->firstWhere('os_id', $os)['coluna'])->toBe('Acabamento');

    // Um evento de outro tipo (realocação) não conta como andar.
    DB::table('pcp_eventos')->insert(['business_id' => $this->b98, 'tipo' => 'etapa_alocada', 'os_tipo' => 'comvis_os', 'os_id' => $os,
        'user_id' => $this->gestor->id, 'dados' => json_encode(['programacao_id' => $imp]), 'created_at' => now()]);
    expect(collect(qdProps($this, $this->gestor, 'cartoes'))->firstWhere('os_id', $os)['coluna'])->toBe('Acabamento');

    // O operador conclui a etapa: a coluna volta a ser a derivada.
    DB::table('pcp_eventos')->insert(['business_id' => $this->b98, 'tipo' => 'etapa_concluida', 'os_tipo' => 'comvis_os', 'os_id' => $os,
        'posto_id' => $this->imp, 'user_id' => $this->op->id, 'dados' => json_encode(['programacao_id' => $imp]), 'created_at' => now()]);
    $cartao = collect(qdProps($this, $this->gestor, 'cartoes'))->firstWhere('os_id', $os);
    expect($cartao['coluna'])->toBe('Impressão')->and($cartao['movida'])->toBeFalse();
});

it('UC-PCV-07 · mover pede producao.etapa.mover; OS de outra empresa não se move e setor estranho é recusado', function () {
    [$os] = qdOsEmImpressao($this);
    $antes = qdEstadoDaOs($this->b98, $os);
    $corpo = ['os_tipo' => 'comvis_os', 'os_id' => $os, 'setor' => 'Acabamento'];

    $this->flushSession();
    $this->actingAs($this->op)->postJson('/producao/quadro/mover', $corpo)->assertForbidden();
    // Tier 0: a empresa 99, com a permissão, não alcança a OS da 98 (404) e nada é gravado.
    $this->flushSession();
    $this->actingAs($this->g99)->postJson('/producao/quadro/mover', $corpo)->assertNotFound();
    $this->flushSession();
    $this->actingAs($this->gestor)->postJson('/producao/quadro/mover', ['setor' => 'Expedição'] + $corpo)->assertStatus(422)->assertJson(['codigo' => 'coluna_invalida']);
    $this->flushSession();
    $this->actingAs($this->gestor)->postJson('/producao/quadro/mover', ['setor' => 'Impressão'] + $corpo)->assertStatus(422)->assertJson(['codigo' => 'coluna_invalida']);

    expect(DB::table('pcp_quadro_colunas')->where('os_id', $os)->count())->toBe(0)
        ->and(DB::table('pcp_eventos')->where('os_id', $os)->where('tipo', 'os_movida_quadro')->count())->toBe(0)
        ->and(qdEstadoDaOs($this->b98, $os))->toBe($antes);
});

// ── Painel (thread 06 · UC-PCV-01..04, UC-PCV-29 · US-PCP-017) ───────────────────────────────────

/** OS por item (pcp_ordem) com prazo prometido; devolve o id. */
function pnOrdem(int $biz, ?string $prazo): int
{
    return (int) DB::table('pcp_ordens')->insertGetId(['business_id' => $biz, 'transaction_id' => random_int(800000, 899999),
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'prazo_prometido' => $prazo, 'created_at' => now(), 'updated_at' => now()]);
}

function pnEtapaOp(int $biz, int $ordem, int $posto, string $nome, string $inicio, string $status): int
{
    return (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $biz, 'os_tipo' => 'pcp_ordem', 'os_id' => $ordem,
        'posto_id' => $posto, 'nome' => $nome, 'inicio_previsto' => $inicio, 'fim_previsto' => date('Y-m-d H:i:s', strtotime($inicio) + 3600),
        'status' => $status, 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
}

/** Pedido de aprovação da arte da OS: sem decisão (aguardando) ou com decisão. */
function pnArte(int $biz, int $ordem, int $etapa, int $user, ?string $decisao, ?string $comentario, string $quando): void
{
    DB::table('pcp_aprovacoes_arte')->insert(['business_id' => $biz, 'ordem_id' => $ordem, 'programacao_id' => $etapa, 'arte_programacao_id' => $etapa,
        'token_hash' => hash('sha256', uniqid('', true)), 'arquivo' => 'https://arquivos.exemplo.test/prova.png', 'expira_em' => '2026-10-27 09:00:00',
        'decisao' => $decisao, 'comentario' => $comentario, 'decidido_em' => $decisao !== null ? $quando : null, 'created_by' => $user,
        'created_at' => $quando, 'updated_at' => $quando]);
}

/** Prop deferida do Painel, pedida como o browser pede (X-Inertia + X-Requested-With), com sessão nova. */
function pnPainel($teste, $user): array
{
    $teste->flushSession();
    $teste->actingAs($user);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $teste->get('/producao/painel', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'painel', 'X-Inertia-Partial-Component' => 'Producao/Painel/Index']);
    $r->assertOk();

    return (array) $r->json('props.painel');
}

it('UC-PCV-03 · "atrasadas ou vencendo" é o mesmo número no Painel e no Quadro, e a OS pronta não conta', function () {
    $ontem = now()->subDay()->toDateString();
    // Pronta com prazo vencido (tudo concluído) → fora. Em andamento vencida e vencendo hoje → contam. Prazo futuro → não.
    $pronta = qdOs($this->b98, 'OS-PNP'.random_int(1000, 9999), $ontem);
    qdEtapa($this->b98, $pronta, $this->aca, 'Corte', '2026-11-03 08:00:00', 'concluida');
    $vencida = qdOs($this->b98, 'OS-PNV'.random_int(1000, 9999), $ontem);
    qdEtapa($this->b98, $vencida, $this->imp, 'Impressão', '2026-11-03 08:00:00', 'iniciada');
    $hoje = qdOs($this->b98, 'OS-PNH'.random_int(1000, 9999), now()->toDateString());
    qdEtapa($this->b98, $hoje, $this->pre, 'Arte', '2026-11-03 08:00:00', 'pendente');
    $futura = qdOs($this->b98, 'OS-PNF'.random_int(1000, 9999), now()->addDays(5)->toDateString());
    qdEtapa($this->b98, $futura, $this->pre, 'Arte', '2026-11-03 08:00:00', 'pendente');

    $ind = pnPainel($this, $this->gestor)['indicadores'];
    $quadro = collect(qdProps($this, $this->gestor, 'cartoes'));
    $minhas = $quadro->whereIn('os_id', [$pronta, $vencida, $hoje, $futura]);

    // O indicador do Painel e o filtro do Quadro contam a mesma coisa (empresa inteira).
    expect($ind['atrasadas_ou_vencendo'])->toBe($quadro->whereNotNull('atraso')->count())
        ->and($ind['em_producao'])->toBe($quadro->count())
        ->and($minhas->whereNotNull('atraso')->pluck('os_id')->sort()->values()->all())->toBe(collect([$vencida, $hoje])->sort()->values()->all())
        ->and($ind['atrasadas_numeros'])->toContain($minhas->firstWhere('os_id', $vencida)['numero'])
        ->and($minhas->pluck('os_id'))->not->toContain($pronta);
});

it('UC-PCV-01 · um alerta por problema, cada um com uma ação; dia limpo não tem alerta nenhum', function () {
    // Dia limpo: a empresa 99 sem nada aberto.
    expect(pnPainel($this, $this->g99)['alertas'])->toBe([]);

    // OS vencida com 11 h programadas hoje no posto de 10 h (atraso + máquina acima do dia).
    $os = qdOs($this->b98, 'OS-PNM'.random_int(1000, 9999), now()->subDay()->toDateString());
    DB::table('pcp_programacoes')->insert(['business_id' => $this->b98, 'os_tipo' => 'comvis_os', 'os_id' => $os, 'posto_id' => $this->imp,
        'nome' => 'Impressão', 'inicio_previsto' => now()->toDateString().' 07:00:00', 'fim_previsto' => now()->toDateString().' 18:00:00',
        'status' => 'pendente', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
    // Arte esperando o cliente.
    $ordem = pnOrdem($this->b98, now()->addDays(3)->toDateString());
    $et = pnEtapaOp($this->b98, $ordem, $this->pre, 'Aprovação do cliente', '2026-11-03 08:00:00', 'pendente');
    pnArte($this->b98, $ordem, $et, $this->gestor->id, null, null, '2026-10-20 09:00:00');

    $p = pnPainel($this, $this->gestor);
    $porTipo = collect($p['alertas'])->keyBy('tipo');

    expect($porTipo->keys()->sort()->values()->all())->toBe(['arte', 'atraso', 'maquina'])
        ->and($porTipo['atraso']['acao']['href'])->toBe('/producao/quadro?so=atrasadas')
        ->and($porTipo['arte']['acao']['href'])->toBe('/producao/quadro?so=arte')
        ->and($porTipo['maquina']['titulo'])->toContain('Roland')
        ->and($porTipo['maquina']['acao']['href'])->toBe('/producao/quadro?agrupar=maquina');
    $roland = collect($p['carga'])->firstWhere('posto_id', $this->imp);
    expect($roland['horas'])->toEqual(11.0)->and($roland['pct'])->toBe(110);
    // Tier 0: a máquina da 98 não aparece na carga da 99.
    expect(collect(pnPainel($this, $this->g99)['carga'])->pluck('posto_id'))->not->toContain($this->imp);
});

it('UC-PCV-02 · os indicadores abrem o Quadro já filtrado pela URL, e valor estranho volta a "todas"', function () {
    $this->flushSession();
    $this->actingAs($this->gestor)->get('/producao/quadro?so=atrasadas')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Producao/Quadro/Index', false)->where('filtro', ['so' => 'atrasadas', 'agrupar' => 'setor', 'q' => '', 'resp' => '', 'setor' => '']));
    $this->flushSession();
    $this->actingAs($this->gestor)->get('/producao/quadro?so=arte&agrupar=maquina')->assertOk()
        ->assertInertia(fn ($p) => $p->where('filtro', ['so' => 'arte', 'agrupar' => 'maquina', 'q' => '', 'resp' => '', 'setor' => '']));
    // Controle negativo: parâmetro fora da lista não vira filtro.
    $this->flushSession();
    $this->actingAs($this->gestor)->get('/producao/quadro?so=qualquer&agrupar=x')->assertOk()
        ->assertInertia(fn ($p) => $p->where('filtro', ['so' => 'todas', 'agrupar' => 'setor', 'q' => '', 'resp' => '', 'setor' => '']));
});

it('UC-PCV-06 · o filtro inteiro vem da URL, igual para quem abrir o Quadro ou a Linha, e "Limpar" volta tudo', function () {
    // Fonte: UC-PCV-06 — "Dado o filtro 'Travadas por arte' + responsável 'Marcos' · Quando troco do Quadro
    // para a Linha · Então o filtro continua · E 'Limpar' volta tudo". O filtro mora na URL (a regra de
    // passar é `Pages/Producao/_lib/filtro.ts`, coberta por tests/js/producao-filtro.test.ts).
    $this->flushSession();
    $this->actingAs($this->gestor)->get('/producao/quadro?so=arte&resp=Marcos%20Lima&q=%20fachada%20&setor=Impress%C3%A3o')->assertOk()
        ->assertInertia(fn ($p) => $p->where('filtro', ['so' => 'arte', 'agrupar' => 'setor', 'q' => 'fachada', 'resp' => 'Marcos Lima', 'setor' => 'Impressão']));
    // "Limpar" = URL sem parâmetro: nada filtrado.
    $this->flushSession();
    $this->actingAs($this->gestor)->get('/producao/quadro')->assertOk()
        ->assertInertia(fn ($p) => $p->where('filtro', ['so' => 'todas', 'agrupar' => 'setor', 'q' => '', 'resp' => '', 'setor' => '']));
    // Controle negativo: lista no lugar de texto não vira filtro, e texto longo é cortado.
    $this->flushSession();
    $this->actingAs($this->gestor)->get('/producao/quadro?resp[]=x&q='.str_repeat('a', 200))->assertOk()
        ->assertInertia(fn ($p) => $p->where('filtro.resp', '')->where('filtro.q', str_repeat('a', 80)));
});

it('UC-PCV-04 · fila de arte mostra o que o cliente disse e quando, ajuste primeiro; aprovada não trava', function () {
    $mk = function (?string $decisao, ?string $coment, string $quando): array {
        $o = pnOrdem($this->b98, now()->addDays(4)->toDateString());
        $e = pnEtapaOp($this->b98, $o, $this->pre, 'Aprovação do cliente', '2026-11-03 08:00:00', 'pendente');
        pnArte($this->b98, $o, $e, $this->gestor->id, $decisao, $coment, $quando);

        return [$o, $e];
    };
    [$aguardando] = $mk(null, null, '2026-10-19 17:30:00');
    [$ajuste] = $mk('recusada', 'aumentar o logo e trocar o telefone', '2026-10-20 08:47:00');
    [$aprovada] = $mk('aprovada', null, '2026-10-20 10:00:00');
    // Discriminante: recusa antiga e depois aprovada → vale o pedido mais recente, não trava.
    [$reaprovada, $etR] = $mk('recusada', 'cor errada', '2026-10-18 10:00:00');
    pnArte($this->b98, $reaprovada, $etR, $this->gestor->id, 'aprovada', null, '2026-10-19 10:00:00');

    $p = pnPainel($this, $this->gestor);
    $fila = collect($p['fila_arte'])->whereIn('numero', ['OP '.$aguardando, 'OP '.$ajuste, 'OP '.$aprovada, 'OP '.$reaprovada])->values();

    expect($fila->pluck('numero')->all())->toBe(['OP '.$ajuste, 'OP '.$aguardando])
        ->and($fila[0]['estado'])->toBe('ajuste')
        ->and($fila[0]['comentario'])->toBe('aumentar o logo e trocar o telefone')
        ->and($fila[0]['desde'])->toBe('2026-10-20 08:47')
        ->and($fila[1]['estado'])->toBe('aguardando')
        ->and($fila[1]['comentario'])->toBeNull()
        ->and($p['indicadores']['arte_travando'])->toBe(collect($p['fila_arte'])->count());

    // O cartão do Quadro leva a mesma situação; aprovada e reaprovada não travam.
    $cartoes = collect(qdProps($this, $this->gestor, 'cartoes'))->where('os_tipo', 'pcp_ordem')->keyBy('os_id');
    expect($cartoes[$ajuste]['arte'])->toBe('ajuste')->and($cartoes[$aguardando]['arte'])->toBe('aguardando')
        ->and($cartoes[$aprovada]['arte'])->toBeNull()->and($cartoes[$reaprovada]['arte'])->toBeNull();
});

it('UC-PCV-29 · "Acontecendo agora" lê os eventos da produção da empresa, sem os que falam de uma pessoa', function () {
    $os = qdOs($this->b98, 'OS-PNE'.random_int(1000, 9999), null);
    $et = qdEtapa($this->b98, $os, $this->imp, 'Impressão', '2026-11-03 08:00:00', 'iniciada', $this->op->id);
    DB::table('pcp_eventos')->insert(['business_id' => $this->b98, 'tipo' => 'pendencia_gestor', 'user_id' => $this->op->id,
        'dados' => json_encode(['apontado_minutos' => 600, 'presenca_minutos' => 480]), 'created_at' => now()]);
    DB::table('pcp_eventos')->insert(['business_id' => $this->b98, 'tipo' => 'etapa_iniciada', 'os_tipo' => 'comvis_os', 'os_id' => $os,
        'posto_id' => $this->imp, 'user_id' => $this->op->id, 'dados' => json_encode(['programacao_id' => $et]), 'created_at' => now()]);
    $os99 = qdOs($this->b99, 'OS-PNE99'.random_int(1000, 9999), null);
    DB::table('pcp_eventos')->insert(['business_id' => $this->b99, 'tipo' => 'etapa_concluida', 'os_tipo' => 'comvis_os', 'os_id' => $os99,
        'user_id' => $this->g99->id, 'dados' => json_encode([]), 'created_at' => now()]);

    $eventos = collect(pnPainel($this, $this->gestor)['eventos']);
    $meu = $eventos->firstWhere('os', DB::table('comvis_os')->where('id', $os)->value('numero'));

    expect($meu)->not->toBeNull()
        ->and($meu['texto'])->toBe('Iniciou')
        ->and($meu['etapa'])->toBe('Impressão')
        ->and($meu['quem'])->toBe(trim($this->op->first_name.' '.$this->op->last_name))
        ->and($eventos->pluck('texto'))->not->toContain('pendencia_gestor')
        ->and($eventos->pluck('os'))->not->toContain(DB::table('comvis_os')->where('id', $os99)->value('numero'));
});

it('UC-PCV-01 · ver o Painel exige producao.access', function () {
    $this->flushSession();
    $this->actingAs($this->usuarioComPermissoes([], $this->B98))->get('/producao/painel')->assertForbidden();
    $this->flushSession();
    $this->actingAs($this->op)->get('/producao/painel')->assertOk()->assertInertia(fn ($p) => $p->component('Producao/Painel/Index', false));
});
