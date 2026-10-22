<?php
// @covers-us US-PCP-015
// @covers-us US-PCP-012

declare(strict_types=1);
// Cobre UC-PCV-08, UC-PCV-09 e UC-PCV-30 (Producao.casos.md · thread 07 do playbook): a Linha do tempo
// por máquina, o gargalo com sugestão aplicável e reversível, e reprogramar na linha.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Services\CalendarioDeProducao;
use Modules\Producao\Services\LinhaDaProducao;
use Modules\Producao\Services\ProcessoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: UC-PCV-08/09/30 (cowork-inbox/producao/Producao.casos.md) com as regras de Fluxos do main
 * (UC-FLX-06 calendário, UC-FLX-08 operadores do posto, UC-FLX-10 alocador, D3 aguardando material,
 * US-PCP-028 entrega prometida). Tenant 98 × 99 (ADR 0358). ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_posto_operadores') || ! Schema::hasTable('pcp_aprovacoes_arte') || ! Schema::hasTable('comvis_os')) {
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
    $this->op2 = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->g99 = $this->usuarioComPermissoes(['producao.access', 'producao.etapa.mover'], $this->B99);
    $this->linha = app(LinhaDaProducao::class);

    $this->roland = lnPosto($this->b98, 'Roland', 'Impressão');
    $this->latex = lnPosto($this->b98, 'HP Latex', 'Impressão');
    $this->cnc = lnPosto($this->b98, 'Router CNC', 'Serralheria');
});

function lnPosto(int $biz, string $nome, string $setor, array $operadores = []): int
{
    $id = (int) DB::table('pcp_postos')->insertGetId(['business_id' => $biz, 'codigo' => 'LN-'.uniqid(), 'nome' => $nome, 'setor' => $setor,
        'tipo_custo' => 'maquina', 'custo_hora' => '30.00', 'horas_por_dia' => '10.00', 'created_at' => now(), 'updated_at' => now()]);
    foreach ($operadores as $u) {
        DB::table('pcp_posto_operadores')->insert(['business_id' => $biz, 'posto_id' => $id, 'user_id' => $u, 'created_at' => now(), 'updated_at' => now()]);
    }

    return $id;
}

function lnOs(int $biz, ?string $prazo): int
{
    $orc = (int) DB::table('comvis_orcamentos')->insertGetId(['business_id' => $biz, 'numero' => 'ORC-LN-'.uniqid(),
        'data_emissao' => '2026-10-20', 'created_at' => now(), 'updated_at' => now()]);

    return (int) DB::table('comvis_os')->insertGetId(['business_id' => $biz, 'orcamento_id' => $orc, 'numero' => 'OS-LN'.random_int(10000, 99999),
        'status_etapa' => 'producao', 'data_prazo' => $prazo, 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now()]);
}

function lnOrdem(int $biz, ?int $stageId = null): int
{
    return (int) DB::table('pcp_ordens')->insertGetId(['business_id' => $biz, 'transaction_id' => random_int(800000, 899999),
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => $stageId, 'created_at' => now(), 'updated_at' => now()]);
}

function lnEtapa(int $biz, string $tipo, int $os, int $posto, string $inicio, string $fim, string $status, ?int $user = null): int
{
    return (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $biz, 'os_tipo' => $tipo, 'os_id' => $os,
        'posto_id' => $posto, 'nome' => 'Etapa', 'inicio_previsto' => $inicio, 'fim_previsto' => $fim,
        'status' => $status, 'user_id' => $user, 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
}

it('UC-PCV-08 · cada máquina mostra a carga do dia, o bloco travado por arte ou material, e o estouro do turno', function () {
    $d = '2026-11-03';
    $a = lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, '2026-11-10'), $this->roland, "$d 08:00:00", "$d 09:00:00", 'iniciada', $this->op->id);
    $ordemArte = lnOrdem($this->b98);
    $arte = lnEtapa($this->b98, 'pcp_ordem', $ordemArte, $this->roland, "$d 10:00:00", "$d 11:00:00", 'pendente');
    DB::table('pcp_aprovacoes_arte')->insert(['business_id' => $this->b98, 'ordem_id' => $ordemArte, 'programacao_id' => $arte, 'arte_programacao_id' => $arte,
        'token_hash' => hash('sha256', uniqid('', true)), 'arquivo' => 'https://arquivos.exemplo.test/prova.png', 'expira_em' => '2026-11-10 09:00:00',
        'created_by' => $this->gestor->id, 'created_at' => "$d 07:00:00", 'updated_at' => "$d 07:00:00"]);
    $proc = app(ProcessoDaOrdem::class)->garantir($this->b98);
    $aguarda = (int) DB::table('sale_process_stages')->where('process_id', $proc->id)->where('key', 'aguardando_material')->value('id');
    $mat = lnEtapa($this->b98, 'pcp_ordem', lnOrdem($this->b98, $aguarda), $this->roland, "$d 11:00:00", "$d 12:00:00", 'pendente');
    // Discriminante do estouro: termina 18:30, depois do fim do turno, no dia prometido.
    $tarde = lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, $d), $this->roland, "$d 17:30:00", "$d 18:30:00", 'pendente');
    $latex = lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, '2026-11-05'), $this->latex, "$d 08:00:00", "$d 09:00:00", 'pendente');
    // Fora: outro dia e outra empresa.
    lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, null), $this->roland, '2026-11-04 08:00:00', '2026-11-04 09:00:00', 'pendente');
    lnEtapa($this->b99, 'comvis_os', lnOs($this->b99, null), lnPosto($this->b99, 'Roland 99', 'Impressão'), "$d 08:00:00", "$d 09:00:00", 'pendente');

    $r = $this->linha->montar($this->b98, Carbon::parse($d), '18:00');
    $blocos = collect($r['blocos'])->keyBy('id');
    $postos = collect($r['postos'])->keyBy('id');

    expect($blocos->keys()->sort()->values()->all())->toBe(collect([$a, $arte, $mat, $tarde, $latex])->sort()->values()->all())
        ->and($blocos[$arte]['travas'])->toBe(['arte aguardando aprovação'])
        ->and($blocos[$mat]['travas'])->toBe(['aguardando material'])
        ->and($blocos[$a]['travas'])->toBe([])
        ->and($blocos[$a]['responsavel'])->toBe(trim($this->op->first_name.' '.$this->op->last_name))
        ->and($blocos[$tarde]['estouro'])->toBeTrue()
        ->and($blocos[$tarde]['passa_da_promessa'])->toBeTrue()
        ->and($blocos[$latex]['estouro'])->toBeFalse()
        ->and($blocos[$latex]['passa_da_promessa'])->toBeFalse()
        ->and($postos[$this->roland]['horas'])->toBe(4.0)
        ->and($postos[$this->roland]['capacidade'])->toBe(10.0)
        ->and($postos[$this->latex]['horas'])->toBe(1.0);
});

it('UC-PCV-08 · o dia seguinte da linha é o próximo dia útil do calendário de produção (UC-FLX-06)', function () {
    $sexta = Carbon::parse('2026-11-06');
    $regras = app(CalendarioDeProducao::class)->regras($this->b98, null, $sexta);
    $dias = $this->linha->dias($this->b98, $sexta);
    $prox = Carbon::parse($dias['proximo']);

    // O próximo dia é útil, e todo dia entre hoje e ele é folga ou feriado.
    expect($prox->gt($sexta))->toBeTrue()
        ->and(in_array($prox->dayOfWeek, $regras['folgas'], true) || isset($regras['feriados'][$prox->toDateString()]))->toBeFalse();
    for ($dia = $sexta->copy()->addDay(); $dia->lt($prox); $dia->addDay()) {
        expect(in_array($dia->dayOfWeek, $regras['folgas'], true) || isset($regras['feriados'][$dia->toDateString()]))->toBeTrue();
    }
    expect($dias['turno']['fim'])->toBe(sprintf('%02d:%02d', intdiv($regras['fecha'], 60), $regras['fecha'] % 60));
});

it('UC-PCV-09 · gargalo sugere a menor etapa que resolve, para máquina do setor que o responsável opera; aplicar e desfazer', function () {
    $d = '2026-11-03';
    // Máquina restrita a op2 vem antes por nome: a sugestão tem de pulá-la (op não a opera — UC-FLX-08).
    $uv = lnPosto($this->b98, 'Aaa UV', 'Impressão', [$this->op2->id]);
    lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, null), $this->roland, "$d 08:00:00", "$d 15:00:00", 'iniciada');
    $pequena = lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, null), $this->roland, "$d 15:00:00", "$d 16:00:00", 'pendente', $this->op->id);
    lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, null), $this->roland, "$d 16:00:00", "$d 19:00:00", 'pendente');
    // A Latex está ocupada às 15:00: a sugestão vai para o primeiro horário livre depois dele.
    lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, null), $this->latex, "$d 15:00:00", "$d 16:30:00", 'pendente');

    $s = $this->linha->montar($this->b98, Carbon::parse($d), '18:00')['sugestao'];

    expect($s['programacao_id'])->toBe($pequena)
        ->and($s['de']['posto_id'])->toBe($this->roland)
        ->and($s['de']['horas'])->toBe(11.0)
        ->and($s['de']['depois'])->toBe(10.0)
        ->and($s['para']['posto_id'])->toBe($this->latex)
        ->and($s['para']['posto_id'])->not->toBe($uv)
        ->and($s['para']['inicio'])->toBe("{$d}T16:30");

    // Aplicar = reprogramar para a sugestão; a Roland sai do vermelho e a sugestão some.
    $this->actingAs($this->gestor)->putJson("/producao/etapas/{$pequena}/reprogramar",
        ['posto_id' => $this->latex, 'inicio_previsto' => "$d 16:30:00"])->assertOk();
    $depois = $this->linha->montar($this->b98, Carbon::parse($d), '18:00');
    expect(collect($depois['postos'])->firstWhere('id', $this->roland)['horas'])->toBe(10.0)->and($depois['sugestao'])->toBeNull();

    // Desfazer = reprogramar de volta; o gargalo volta.
    $this->putJson("/producao/etapas/{$pequena}/reprogramar", ['posto_id' => $this->roland, 'inicio_previsto' => "$d 15:00:00"])->assertOk();
    expect($this->linha->montar($this->b98, Carbon::parse($d), '18:00')['sugestao']['programacao_id'])->toBe($pequena);
});

it('UC-PCV-09 · máquina dentro da capacidade não gera sugestão (não há alerta decorativo)', function () {
    $d = '2026-11-03';
    lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, null), $this->roland, "$d 08:00:00", "$d 17:00:00", 'pendente');

    expect($this->linha->montar($this->b98, Carbon::parse($d), '18:00')['sugestao'])->toBeNull();
});

it('UC-PCV-30 · reprogramar move horário e máquina do mesmo setor, registra o evento e avisa o responsável', function () {
    $d = '2026-11-03';
    $os = lnOs($this->b98, null);
    $e = lnEtapa($this->b98, 'comvis_os', $os, $this->roland, "$d 14:30:00", "$d 15:30:00", 'pendente', $this->op->id);

    $this->actingAs($this->gestor)->putJson("/producao/etapas/{$e}/reprogramar", ['posto_id' => $this->latex, 'inicio_previsto' => "$d 15:00:00"])
        ->assertOk()->assertJson(['posto_id' => $this->latex, 'user_id' => $this->op->id, 'inicio_previsto' => "$d 15:00:00", 'fim_previsto' => "$d 16:00:00"]);

    $ev = DB::table('pcp_eventos')->where('business_id', $this->b98)->where('tipo', 'etapa_alocada')->orderByDesc('id')->first();
    $dados = json_decode((string) $ev->dados, true);
    expect($dados['origem'])->toBe('linha')
        ->and($dados['antes'])->toBe(['posto_id' => $this->roland, 'user_id' => (int) $this->op->id, 'inicio_previsto' => "$d 14:30:00"])
        ->and($dados['depois'])->toBe(['posto_id' => $this->latex, 'user_id' => (int) $this->op->id, 'inicio_previsto' => "$d 15:00:00"])
        ->and(DB::table('pcp_eventos')->where('business_id', $this->b98)->where('tipo', 'aviso_reprogramada')->where('user_id', $this->op->id)->exists())->toBeTrue();

    // Passar do fim do turno não recusa: a linha mostra o estouro.
    $this->putJson("/producao/etapas/{$e}/reprogramar", ['posto_id' => $this->latex, 'inicio_previsto' => "$d 17:30:00"])->assertOk();
});

it('UC-PCV-30 · recusa outro setor, choque de horário e etapa que já começou, sem mudar nada', function () {
    $d = '2026-11-03';
    $e = lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, null), $this->roland, "$d 14:30:00", "$d 15:30:00", 'pendente');
    $outra = lnOs($this->b98, null);
    lnEtapa($this->b98, 'comvis_os', $outra, $this->latex, "$d 15:00:00", "$d 16:00:00", 'pendente');
    $rodando = lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, null), $this->roland, "$d 08:00:00", "$d 09:00:00", 'iniciada');
    $this->actingAs($this->gestor);

    $this->putJson("/producao/etapas/{$e}/reprogramar", ['posto_id' => $this->cnc, 'inicio_previsto' => "$d 14:30:00"])
        ->assertStatus(422)->assertJson(['codigo' => 'outro_setor']);
    $this->putJson("/producao/etapas/{$e}/reprogramar", ['posto_id' => $this->latex, 'inicio_previsto' => "$d 15:15:00"])
        ->assertStatus(422)->assertJson(['codigo' => 'choque_de_horario', 'contexto' => ['os_id' => $outra]]);
    $this->putJson("/producao/etapas/{$rodando}/reprogramar", ['posto_id' => $this->roland, 'inicio_previsto' => "$d 09:00:00"])
        ->assertStatus(422)->assertJson(['codigo' => 'etapa_encerrada']);

    $linha = DB::table('pcp_programacoes')->find($e);
    expect((int) $linha->posto_id)->toBe($this->roland)->and((string) $linha->inicio_previsto)->toBe("$d 14:30:00");
});

it('UC-PCV-30 · trocar para máquina com operadores cadastrados pede um operador dela (UC-FLX-08)', function () {
    $d = '2026-11-03';
    $uv = lnPosto($this->b98, 'Mimaki', 'Impressão', [$this->op2->id]);
    $e = lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, null), $this->roland, "$d 10:00:00", "$d 11:00:00", 'pendente', $this->op->id);
    $this->actingAs($this->gestor);

    $this->putJson("/producao/etapas/{$e}/reprogramar", ['posto_id' => $uv, 'inicio_previsto' => "$d 10:00:00"])
        ->assertStatus(422)->assertJson(['codigo' => 'operador_destino', 'contexto' => ['posto_id' => $uv, 'operadores' => [$this->op2->id]]]);
    expect((int) DB::table('pcp_programacoes')->find($e)->posto_id)->toBe($this->roland);

    $this->putJson("/producao/etapas/{$e}/reprogramar", ['posto_id' => $uv, 'inicio_previsto' => "$d 10:00:00", 'user_id' => $this->op2->id])
        ->assertOk()->assertJson(['posto_id' => $uv, 'user_id' => $this->op2->id]);
});

it('UC-PCV-30 · reprogramar exige producao.etapa.mover; etapa de outra empresa é 404 (Tier 0)', function () {
    $d = '2026-11-03';
    $e = lnEtapa($this->b98, 'comvis_os', lnOs($this->b98, null), $this->roland, "$d 10:00:00", "$d 11:00:00", 'pendente');

    $this->actingAs($this->op)->putJson("/producao/etapas/{$e}/reprogramar", ['posto_id' => $this->roland, 'inicio_previsto' => "$d 12:00:00"])->assertForbidden();
    $this->flushSession();
    $this->actingAs($this->g99)->putJson("/producao/etapas/{$e}/reprogramar", ['posto_id' => $this->roland, 'inicio_previsto' => "$d 12:00:00"])->assertNotFound();

    expect((string) DB::table('pcp_programacoes')->find($e)->inicio_previsto)->toBe("$d 10:00:00");
});

it('UC-PCV-08 · a tela abre com producao.access e ?dia=proximo pede o próximo dia útil; a linha vem deferida', function () {
    $this->flushSession();
    $this->actingAs($this->usuarioComPermissoes([], $this->B98))->get('/producao/linha')->assertForbidden();

    $this->flushSession();
    $dias = $this->linha->dias($this->b98, now());
    $this->actingAs($this->op)->get('/producao/linha?dia=proximo&so=arte')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Producao/Linha/Index', false)
            ->where('dia', 'proximo')->where('dias.proximo', $dias['proximo'])->where('pode_mover', false)->where('filtro.so', 'arte')
            ->missing('linha'));

    $this->flushSession();
    $this->actingAs($this->gestor);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $this->get('/producao/linha', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'linha', 'X-Inertia-Partial-Component' => 'Producao/Linha/Index']);
    $r->assertOk();
    expect(collect($r->json('props.linha.postos'))->pluck('id'))->toContain($this->roland);
});
