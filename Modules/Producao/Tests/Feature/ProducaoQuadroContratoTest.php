<?php
// @covers-us US-PCP-029
// @covers-us US-PCP-024

declare(strict_types=1);
// Cobre UC-PCV-05 (Producao.casos.md · thread 06 do playbook) e a entrada na tela de UC-FLX-10
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
