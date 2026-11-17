<?php

declare(strict_types=1);
// Cobre UC-PCV-10, UC-PCOSF-01..05 (Producao/Os/Show.casos.md) — a Ficha da OS por item.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Auditoria Producao lote 3.2. Tenant 98 × 99 (ADR 0358). Previsto = fim − início programados; apontado =
 * pares início→fim do apontamento da etapa. ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens')) {
        $this->markTestSkipped('pcp_ordens ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->pacote = true;
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $this->pacote && $perm === 'producao_module'));
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98);
    $this->gerente = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->gerente->assignRole('vendas.gerente#'.$this->b98);
    $this->op = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    // SUPERADMIN: fixture da empresa 98; o escopo é provado nos asserts.
    $this->posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'IMP-'.uniqid(), 'nome' => 'Roland',
        'setor' => 'Impressão', 'tipo_custo' => 'maquina', 'custo_hora' => '80.00']);
    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => 990021,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    $et = fn ($bloco, $nome, $de, $ate) => Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO,
        'os_id' => $this->ordem->id, 'posto_id' => $this->posto->id, 'bloco' => $bloco, 'trilha' => 0, 'nome' => $nome, 'user_id' => null,
        'inicio_previsto' => "2026-11-10 {$de}:00", 'fim_previsto' => "2026-11-10 {$ate}:00", 'status' => 'pendente', 'created_by' => $this->pcp->id]);
    $this->e1 = $et(1, 'Impressão', '08:00', '10:00');
    $this->e2 = $et(2, 'Recorte', '10:00', '10:30');
    $this->url = "/producao/ordens/{$this->ordem->id}";
    $this->parcial = function ($user, string $props) {
        $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return $this->actingAs($user)->get($this->url, ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) $versao, 'X-Inertia-Partial-Data' => $props, 'X-Inertia-Partial-Component' => 'Producao/Os/Show']);
    };
});

it('UC-PCV-10 · a Ficha mostra a situação e as etapas com previsto × apontado', function () {
    $this->actingAs($this->pcp)->get($this->url)->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Producao/Os/Show', false)
            ->where('os.numero', 'OP '.$this->ordem->id)->where('os.situacao', 'aguardando_liberacao'));

    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp, [$this->e1->id => $this->op->id, $this->e2->id => $this->op->id]);
    $svc = app(ApontamentoOperadorService::class);
    $ap = $svc->iniciar($this->b98, $this->op->id, $this->e1->id, 'i-'.uniqid(), now()->subMinutes(90));
    $svc->concluir($this->b98, $this->op->id, $ap->id, 'c-'.uniqid(), now());

    $etapas = ($this->parcial)($this->pcp, 'etapas,os')->assertOk()->json('props.etapas');
    expect(array_column($etapas, 'id'))->toBe([$this->e1->id, $this->e2->id])
        ->and($etapas[0]['previsto_h'])->toEqual(2.0)->and($etapas[1]['previsto_h'])->toEqual(0.5)
        // Discriminante: a etapa apontada tem 1,5 h; a outra, nenhuma.
        ->and($etapas[0]['apontado_h'])->toEqual(1.5)->and($etapas[1]['apontado_h'])->toEqual(0.0)
        ->and($etapas[0]['status'])->toBe('concluida')->and($etapas[0]['responsavel'])->not->toBeNull()
        ->and($etapas[0]['setor'])->toBe('Impressão');
});

it('UC-PCOSF-01 · os botões seguem o papel: Liberar para producao.liberar, Cancelar item para vendas.gerente', function () {
    $pode = fn ($u) => ($this->parcial)($u, 'pode')->assertOk()->json('props.pode');
    expect($pode($this->pcp))->toBe(['liberar' => true, 'cancelar' => false, 'custo' => false])
        ->and($pode($this->gerente))->toBe(['liberar' => false, 'cancelar' => true, 'custo' => false])
        ->and($pode($this->op))->toBe(['liberar' => false, 'cancelar' => false, 'custo' => false]);

    // Quem escolhe os responsáveis recebe a lista; quem não libera recebe vazia.
    $ids = array_column(($this->parcial)($this->pcp, 'pessoas')->assertOk()->json('props.pessoas.pessoas'), 'id');
    expect($ids)->toContain($this->op->id);
    expect(($this->parcial)($this->op, 'pessoas')->assertOk()->json('props.pessoas.pessoas'))->toBe([]);
});

it('UC-PCOSF-02 · Tier 0 e portões: OS de outra empresa 404; sem acesso ou sem o pacote 403', function () {
    // SUPERADMIN: fixture da empresa 99.
    $os99 = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b99, 'transaction_id' => 990022,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000']);
    $this->actingAs($this->pcp)->get("/producao/ordens/{$os99->id}")->assertNotFound();
    $this->actingAs($this->pcp)->get($this->url)->assertOk(); // controle: a OS da própria empresa abre

    $this->actingAs($this->usuarioComPermissoes([], $this->B98))->get($this->url)->assertForbidden();
    $this->pacote = false;
    $this->actingAs($this->pcp)->get($this->url)->assertForbidden();
});

it('UC-PCOSF-03 · aba Custo só leitura: o mesmo número da Ficha e do previsto × realizado, e nada é gravado', function () {
    $gestor = $this->usuarioComPermissoes(['producao.access', 'producao.desempenho.ver'], $this->B98);
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp, [$this->e1->id => $this->op->id, $this->e2->id => $this->op->id]);
    $svc = app(ApontamentoOperadorService::class);
    $ap = $svc->iniciar($this->b98, $this->op->id, $this->e1->id, 'i-'.uniqid(), now()->subMinutes(90));
    $svc->concluir($this->b98, $this->op->id, $ap->id, 'c-'.uniqid(), now());
    $antes = [DB::table('pcp_ordens')->where('id', $this->ordem->id)->value('custo_real'), DB::table('pcp_apontamentos')->where('os_id', $this->ordem->id)->count()];

    // Caminho 1: a prop da Ficha. Conta à mão: 90 min × 80,00 por hora de máquina = 120,00; sem material.
    $c = ($this->parcial)($gestor, 'custo')->assertOk()->json('props.custo');
    expect($c['composicao'])->toBe(['maquina' => '120.00', 'pessoa' => '0.00', 'material' => '0.00', 'terceiros' => '0.00', 'total' => '120.00'])
        ->and($c['previsto_realizado']['realizado'])->toBe('120.0000')->and($c['previsto_realizado']['status'])->toBe('em_andamento');
    // Caminho 2: o endpoint JSON do previsto × realizado devolve o mesmo realizado.
    $json = $this->actingAs($gestor)->getJson("/producao/ordens/{$this->ordem->id}/previsto-realizado")->assertOk()->json();
    expect($json['realizado'])->toBe($c['previsto_realizado']['realizado']);
    expect([DB::table('pcp_ordens')->where('id', $this->ordem->id)->value('custo_real'), DB::table('pcp_apontamentos')->where('os_id', $this->ordem->id)->count()])->toBe($antes);

    // Controle: sem producao.desempenho.ver a prop vem nula e a aba não aparece.
    expect(($this->parcial)($this->pcp, 'custo')->assertOk()->json('props.custo'))->toBeNull();
    expect(($this->parcial)($gestor, 'pode')->json('props.pode.custo'))->toBeTrue();
});

it('UC-PCOSF-04 · aba Materiais: previsto pela receita × apontado, e o saldo que estoura aparece', function () {
    $unit = (int) (DB::table('units')->where('business_id', $this->b98)->value('id') ?? DB::table('units')->insertGetId(['business_id' => $this->b98,
        'actual_name' => 'Metro quadrado', 'short_name' => 'm²', 'allow_decimal' => 1, 'created_by' => $this->pcp->id, 'created_at' => now(), 'updated_at' => now()]));
    $sku = 'FOS-'.uniqid();
    $pid = DB::table('products')->insertGetId(['business_id' => $this->b98, 'name' => 'Lona '.$sku, 'type' => 'single', 'unit_id' => $unit, 'sku' => $sku,
        'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive', 'barcode_type' => 'C128', 'created_by' => $this->pcp->id, 'created_at' => now(), 'updated_at' => now()]);
    $grupo = DB::table('product_variations')->insertGetId(['product_id' => $pid, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $vid = DB::table('variations')->insertGetId(['product_id' => $pid, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku,
        'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 0, 'sell_price_inc_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $local = (int) DB::table('business_locations')->where('business_id', $this->b98)->value('id');
    $venda = DB::table('transactions')->insertGetId(['business_id' => $this->b98, 'location_id' => $local, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 0, 'essentials_duration' => 0, 'created_by' => $this->pcp->id, 'created_at' => now(), 'updated_at' => now()]);
    // SUPERADMIN: fixture da empresa 98.
    $ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => $venda, 'transaction_sell_line_id' => random_int(1000000, 9999999),
        'product_id' => $pid, 'variation_id' => $vid, 'quantidade' => '1.0000', 'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    $prog = Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $ordem->id, 'posto_id' => $this->posto->id,
        'bloco' => 1, 'trilha' => 0, 'nome' => 'Impressão', 'user_id' => $this->op->id, 'inicio_previsto' => now(), 'fim_previsto' => now()->addHour(), 'status' => 'iniciada', 'created_by' => $this->pcp->id]);
    // A receita (Manufacturing) é simulada pelo contrato do núcleo: 124 m² para a quantidade da OS.
    app()->instance(\App\Contracts\Fabricacao\InsumosDaReceita::class, new class($pid, $vid) implements \App\Contracts\Fabricacao\InsumosDaReceita {
        public function __construct(private int $pid, private int $vid) {}
        public function paraQuantidade(int $businessId, int $variationId, string $quantidade): array { return [['product_id' => $this->pid, 'variation_id' => $this->vid, 'quantidade' => '124.0000']]; }
    });
    DB::table('pcp_consumos')->insert(['business_id' => $this->b98, 'chave' => 'fos-'.uniqid(), 'ordem_id' => $ordem->id, 'programacao_id' => $prog->id,
        'product_id' => $pid, 'variation_id' => $vid, 'quantidade' => '133.4000', 'custo' => '0.0000', 'transaction_id' => $venda,
        'transaction_sell_line_id' => $ordem->transaction_sell_line_id, 'user_id' => $this->op->id, 'momento' => now(), 'created_at' => now(), 'updated_at' => now()]);

    $this->url = "/producao/ordens/{$ordem->id}";
    $m = ($this->parcial)($this->pcp, 'materiais')->assertOk()->json('props.materiais');
    // 124,0000 − 133,4000 = −9,4000 (conta à mão; o PHP faz em décimos de milésimo inteiros).
    expect($m['itens'])->toHaveCount(1)
        ->and($m['itens'][0]['previsto'])->toBe('124.0000')->and($m['itens'][0]['saldo'])->toBe('-9.4000')->and($m['itens'][0]['estourou'])->toBeTrue()
        ->and($m['falta'])->toBe([]); // a OS não está em Aguardando material
});

it('UC-PCOSF-05 · aba Arte: versões da mais nova à mais antiga, e a que vale é a última aprovada', function () {
    $arte = fn (string $arquivo, int $versao, ?string $decisao, ?string $em) => DB::table('pcp_aprovacoes_arte')->insertGetId(['business_id' => $this->b98,
        'ordem_id' => $this->ordem->id, 'programacao_id' => $this->e1->id, 'arte_programacao_id' => $this->e1->id, 'token_hash' => hash('sha256', uniqid()),
        'arquivo' => $arquivo, 'versao' => $versao, 'expira_em' => now()->addDays(7), 'decisao' => $decisao, 'decidido_em' => $em, 'created_by' => $this->pcp->id,
        'created_at' => now(), 'updated_at' => now()]);
    $arte('https://ex.test/arte-v1.pdf', 1, 'aprovada', '2026-11-05 16:40:00');
    $arte('https://ex.test/arte-v2.pdf', 2, null, null);
    // SUPERADMIN: pedido de arte de outra empresa na mesma OS-id não pode aparecer (Tier 0).
    DB::table('pcp_aprovacoes_arte')->insert(['business_id' => $this->b99, 'ordem_id' => $this->ordem->id, 'programacao_id' => $this->e1->id,
        'arte_programacao_id' => $this->e1->id, 'token_hash' => hash('sha256', uniqid()), 'arquivo' => 'https://ex.test/de-outra.pdf',
        'expira_em' => now()->addDays(7), 'created_by' => $this->pcp->id, 'created_at' => now(), 'updated_at' => now()]);

    $a = ($this->parcial)($this->op, 'artes')->assertOk()->json('props.artes');
    expect(array_column($a, 'arquivo'))->toBe(['https://ex.test/arte-v2.pdf', 'https://ex.test/arte-v1.pdf'])
        ->and(array_column($a, 'versao'))->toBe([2, 1])->and(array_column($a, 'vigente'))->toBe([false, true])
        ->and($a[1]['decidida_em'])->toBe('2026-11-05 16:40');
});

it('UC-PCOSF-05 · UC-PCART-09 · aba Arte mostra a versão gravada: o reenvio do mesmo arquivo não vira versão nova', function () {
    // Envios gravados como o AprovacaoArteService grava (VersaoDaArte): A, A reenviado, B = v1, v1, v2.
    // Contar os envios daria v3, v2, v1 e a Ficha discordaria do celular do operador.
    $arte = fn (string $arquivo, int $versao) => DB::table('pcp_aprovacoes_arte')->insertGetId(['business_id' => $this->b98,
        'ordem_id' => $this->ordem->id, 'programacao_id' => $this->e1->id, 'arte_programacao_id' => $this->e1->id, 'token_hash' => hash('sha256', uniqid()),
        'arquivo' => $arquivo, 'versao' => $versao, 'expira_em' => now()->addDays(7), 'created_by' => $this->pcp->id,
        'created_at' => now(), 'updated_at' => now()]);
    $ids = [$arte('https://ex.test/arte-a.pdf', 1), $arte('https://ex.test/arte-a.pdf', 1), $arte('https://ex.test/arte-b.pdf', 2)];

    $a = ($this->parcial)($this->op, 'artes')->assertOk()->json('props.artes');
    expect(array_column($a, 'versao'))->toBe([2, 1, 1])
        // A linha da tela é chaveada pelo envio: duas linhas da mesma versão não podem colidir.
        ->and(array_column($a, 'id'))->toBe(array_reverse($ids));
});
