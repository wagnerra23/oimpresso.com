<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Ai;
use Laravel\Ai\Tools\Request as ToolRequest;
use Modules\Jana\Ai\Agents\BriefDiarioAgent;
use Modules\Jana\Ai\Tools\BriefDiario\InadimplenciaTool;
use Modules\Jana\Ai\Tools\BriefDiario\NfeStatusTool;
use Modules\Jana\Ai\Tools\BriefDiario\OportunidadesTool;
use Modules\Jana\Ai\Tools\BriefDiario\TicketsTopTool;
use Modules\Jana\Ai\Tools\BriefDiario\VendasPeriodoTool;

uses(Tests\TestCase::class);

/**
 * R-COPI-202 — GUARD tests pra BriefDiarioAgent (US-COPI-202, ADR 0141).
 *
 * Cobre pattern "Claude Code" (HasTools laravel/ai):
 *  001. Cada Tool responde ok=true com shape estável (5 smoke tests)
 *  002. VendasPeriodoTool IGNORA args do Request — Tier 0 mecânico
 *  003. Tier 0 cross-tenant — Tool(biz=98) NÃO vê biz=99 mesmo se LLM tentar
 *  004. Agent declara 5 tools + instructions contém business_id literal
 *  005. Agent com fakeAgent retorna response controlada (loop fechado)
 *
 * TENANTS (ADR 0358): 98 = tenant canônico de teste (empresa FICTÍCIA); 99 = a outra
 * empresa fictícia, adversário de isolamento. biz=1 é a WR2 Sistemas — empresa REAL —
 * e deixou de ser default de teste; biz=4 (ROTA LIVRE) é proibido sem exceção.
 *
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */

/** Tenant canônico de teste — empresa fictícia (ADR 0358). */
const BRIEF_DIARIO_BIZ = 98;

/** A outra empresa fictícia — adversário de isolamento cross-tenant (ADR 0358). */
const BRIEF_DIARIO_BIZ_ADVERSARIO = 99;

/**
 * As 5 tools do brief, nomeadas, apontadas pro business dado.
 *
 * Existe pra que o teste 003 possa instanciar o MESMO conjunto duas vezes — uma como
 * vítima (98) e uma como adversário (99) — e assim ter controle positivo.
 */
function briefDiarioTools(int $businessId): array
{
    return [
        'vendas' => new VendasPeriodoTool($businessId),
        'inadimplencia' => new InadimplenciaTool($businessId),
        'tickets' => new TicketsTopTool($businessId),
        'nfe' => new NfeStatusTool($businessId),
        'oportunidades' => new OportunidadesTool($businessId),
    ];
}

beforeEach(function () {
    // ─────────────────────────────────────────────────────────────────────────────
    // GUARD SQLITE-ONLY — É PROTEÇÃO, NÃO DÍVIDA. Não troque por "rodar em MySQL".
    //
    // Este arquivo monta schema SINTÉTICO com `dropIfExists` nas 9 tabelas abaixo.
    // Em sqlite `:memory:` isso é inócuo (banco por processo, descartado no fim).
    // Em MySQL seria DESTRUTIVO: o `scripts/tests/ct100-fullsuite.sh` roda a suíte
    // inteira, sharded, contra o `oimpresso-staging`, cuja base é PERSISTENTE
    // (proibicoes.md §Ambiente). Ele CARREGA este arquivo — é este skip que impede
    // `dropIfExists('transactions')` de dropar a tabela real de lá.
    //
    // Onde ele RODA de verdade: lane `PHP / Pest (Unit)` do ci.yml, via
    // `.github/ci-sqlite-pest.list:34` — e essa lane é REQUIRED
    // (governance/required-checks-baseline.json). Ele NÃO está na allowlist MySQL do
    // jana-pest.yml de propósito, e aquela lane é advisory: mover pra lá rebaixaria
    // esta trava Tier 0 de required pra advisory.
    //
    // ⚠️ A redação anterior desta mensagem prometia "burn-down converte depois" —
    // uma migração pra MySQL que não deve acontecer nesta forma. Foi essa frase que
    // gerou, em 2026-09-02, uma tarefa pra "fazer o teste rodar em MySQL". LC-15:
    // artefato não anuncia o que não deve entregar.
    // ─────────────────────────────────────────────────────────────────────────────
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('sqlite-only por PROTEÇÃO: monta schema sintético com dropIfExists e a base do CT 100 é persistente. Roda na lane required `PHP / Pest (Unit)`. Ver comentário no beforeEach.');
    }

    // RELÓGIO CONGELADO — este teste semeia `transaction_date => now()->subHours(1)` e afirma
    // o total de HOJE. Com o relógio vivo, rodar entre 00:00 e 01:00 no fuso da app
    // (`Europe/London`, UTC+1 no verão) joga a venda pra ONTEM e o brief devolve 0.0.
    // Não é regressão: é uma janela de UMA HORA POR DIA em que o teste falha sempre.
    // MEDIDO 2026-08-15/16 em main, MESMO commit: runs 21:58 / 22:10 / 22:25 UTC verdes;
    // 23:25 UTC (= 00:25 na app) vermelha, com `Failed asserting that 0.0 matches expected
    // 500.0`; 00:25 UTC (= 01:25, já fora da janela) verde de novo. Mesmo código, relógios
    // diferentes, resultados opostos.
    // A falha nasce na lane `PHP / Pest (Unit)` — se ela é required hoje, quem sabe é
    // `governance/required-checks-baseline.json`, não este comentário.
    // Mesmo padrão de `IsolatedStatesBaselineTest.php:68`. Meio-dia é folgado dos dois
    // lados: `subHours(1)` continua no mesmo dia com 11h de margem.
    Carbon::setTestNow('2026-06-11 12:00:00');

    foreach (['transactions', 'transaction_payments', 'conversations', 'messages', 'channels', 'contacts', 'nfe_emissoes', 'transaction_sell_lines', 'products'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('transactions', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->string('type', 20);
        $t->string('status', 20)->default('final');
        $t->string('payment_status', 20)->default('paid');
        $t->unsignedInteger('contact_id')->nullable();
        $t->decimal('final_total', 20, 2)->default(0);
        $t->timestamp('transaction_date')->nullable();
        $t->timestamp('due_date')->nullable();
        $t->timestamps();
    });

    Schema::create('transaction_payments', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('transaction_id');
        $t->decimal('amount', 20, 2);
        $t->timestamps();
    });

    Schema::create('contacts', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('business_id');
        $t->string('name', 191);
        // `is_default` marca walk-in no UltimatePOS ("Cliente Balcão") e é LIDA pelo
        // BriefDiarioService::oportunidadesUpsell, nos dois whereRaw
        // `(c.is_default IS NULL OR c.is_default <> 1)`.
        //
        // Ela FALTAVA aqui, e o comentário do Service diz que o raw serve pra "tolerar"
        // a ausência da coluna em ambiente de teste — o que é FALSO: o raw tolera valor
        // NULL, não coluna inexistente. O sqlite estourava "no such column: c.is_default",
        // o try/catch do Service devolvia `ok:false`, e as asserções de ausência do
        // teste 003 passavam sobre um payload de ERRO. Espelha o schema real:
        // `contacts.is_default` = tinyint(1) NOT NULL DEFAULT 0.
        $t->boolean('is_default')->default(0);
        $t->timestamps();
    });

    Schema::create('channels', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->string('label', 80);
        $t->string('type', 30);
        $t->timestamps();
    });

    Schema::create('conversations', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedBigInteger('channel_id');
        $t->string('customer_external_id', 150);
        $t->string('contact_name', 120)->nullable();
        $t->string('status', 20)->default('open');
        $t->boolean('is_blocked')->default(false);
        $t->unsignedInteger('unread_count')->default(0);
        $t->timestamp('last_message_at')->nullable();
        $t->timestamps();
    });

    Schema::create('messages', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedBigInteger('conversation_id');
        $t->string('direction', 10);
        $t->text('body')->nullable();
        $t->timestamp('created_at')->useCurrent();
        $t->timestamp('updated_at')->nullable();
    });

    Schema::create('nfe_emissoes', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->integer('cstat')->nullable();
        $t->timestamps();
    });

    Schema::create('products', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('business_id');
        $t->string('name', 191);
        $t->timestamps();
    });

    Schema::create('transaction_sell_lines', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('transaction_id');
        $t->unsignedInteger('product_id');
        $t->timestamps();
    });
});

afterEach(fn () => Carbon::setTestNow());

it('R-COPI-202-001 — cada Tool responde ok=true com shape estável', function () {
    DB::table('transactions')->insert([
        ['business_id' => BRIEF_DIARIO_BIZ, 'type' => 'sell', 'status' => 'final', 'final_total' => 100, 'transaction_date' => now()->subHours(2), 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)],
    ]);

    foreach (briefDiarioTools(BRIEF_DIARIO_BIZ) as $nome => $tool) {
        $json = (string) $tool->handle(new ToolRequest([]));

        $data = json_decode($json, true);

        expect($json)->toBeString();
        expect($data)->toBeArray()->toHaveKey('ok');

        // ANTI-VÁCUO. A asserção anterior era `toBeBool()` — satisfeita por `ok:false`.
        // Como TODA source do BriefDiarioService é `try { ok:true } catch { ok:false }`,
        // qualquer exceção de schema saía VERDE aqui. Era exatamente o caso do
        // OportunidadesTool (ver `is_default` no beforeEach). `ok` é o contrato do LLM:
        // se a source não respondeu, o teste tem que doer.
        expect($data['ok'])->toBeTrue(
            "Tool {$nome} devolveu ok=false — reason=".($data['reason'] ?? '?')
            .' / '.($data['error_message'] ?? 'sem error_message')
        );
    }
});

it('R-COPI-202-002 — VendasPeriodoTool IGNORA args do Request (Tier 0 mecânico)', function () {
    DB::table('transactions')->insert([
        // biz=98 (tenant de teste) com venda hoje
        ['business_id' => BRIEF_DIARIO_BIZ, 'type' => 'sell', 'status' => 'final', 'final_total' => 500, 'transaction_date' => now()->subHours(1), 'created_at' => now()->subHours(1), 'updated_at' => now()->subHours(1)],
        // biz=99 com venda gigante — NÃO pode aparecer
        ['business_id' => BRIEF_DIARIO_BIZ_ADVERSARIO, 'type' => 'sell', 'status' => 'final', 'final_total' => 99999, 'transaction_date' => now()->subHours(1), 'created_at' => now()->subHours(1), 'updated_at' => now()->subHours(1)],
    ]);

    $tool = new VendasPeriodoTool(BRIEF_DIARIO_BIZ); // business hardcoded no constructor

    // LLM "alucinado" tenta passar business_id=99 ou outros campos — Tool deve ignorar
    $maliciousRequest = new ToolRequest([
        'business_id' => BRIEF_DIARIO_BIZ_ADVERSARIO,
        'override_tenant' => true,
        'sql' => 'DROP TABLE transactions',
    ]);

    $json = (string) $tool->handle($maliciousRequest);
    $data = json_decode($json, true);

    // Só vê a venda de biz=98 — nunca a de biz=99
    // toEqual (loose) porque JSON encode/decode pode promover float→int em valores .00
    expect((float) $data['hoje']['total'])->toEqual(500.0);
    expect((float) $data['hoje']['total'])->not->toEqual(99999.0);
});

it('R-COPI-202-003 — Tier 0 cross-tenant: 5 Tools(biz=98) NUNCA expoem dados de biz=99', function () {
    $adv = BRIEF_DIARIO_BIZ_ADVERSARIO;

    // Setup cross-tenant: biz=99 cheio de dados, biz=98 vazio.
    // Inserts separados pra SQLite aceitar diferentes shapes (payment_status/due_date).
    //
    // ⚠️ Cada uma das 5 sources tem que ter dado do adversário, com um MARCADOR PRÓPRIO,
    // senão a asserção de ausência lá embaixo é vácuo. Antes desta revisão só 2 das 5
    // tinham (`tickets` e nada mais): a transação vencida não trazia `contact_id`, então
    // `top_5_devedores` vinha vazio e "não vazou Cliente Alien" passava por ausência de
    // dado; e não havia sell_line nenhuma, então `oportunidades` nunca teve o que vazar.
    $vendaAdversario = DB::table('transactions')->insertGetId([
        'business_id' => $adv, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid', 'contact_id' => 99, 'final_total' => 4242.42, 'transaction_date' => now()->subHours(2), 'due_date' => null, 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2),
    ]);
    DB::table('transactions')->insert([
        'business_id' => $adv, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due', 'contact_id' => 99, 'final_total' => 5000, 'due_date' => now()->subDays(45), 'transaction_date' => now()->subDays(50), 'created_at' => now()->subDays(50), 'updated_at' => now()->subDays(50),
    ]);
    DB::table('contacts')->insert([
        // is_default=0 → NÃO é walk-in, então entra no ranking de oportunidades.
        ['id' => 99, 'business_id' => $adv, 'name' => 'Cliente Alien', 'is_default' => 0, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('products')->insert([
        ['id' => 99, 'business_id' => $adv, 'name' => 'Produto Alien', 'created_at' => now(), 'updated_at' => now()],
    ]);
    // 3 sell_lines do MESMO (contact 99, product 99) em 90d → combo (having repetes >= 3).
    DB::table('transaction_sell_lines')->insert([
        ['transaction_id' => $vendaAdversario, 'product_id' => 99, 'created_at' => now(), 'updated_at' => now()],
        ['transaction_id' => $vendaAdversario, 'product_id' => 99, 'created_at' => now(), 'updated_at' => now()],
        ['transaction_id' => $vendaAdversario, 'product_id' => 99, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('channels')->insert([
        ['id' => 99, 'business_id' => $adv, 'label' => 'X', 'type' => 'whatsapp_baileys', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('conversations')->insert([
        ['id' => 999, 'business_id' => $adv, 'channel_id' => 99, 'customer_external_id' => '+99', 'contact_name' => 'Vazamento', 'unread_count' => 50, 'status' => 'open', 'is_blocked' => false, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('nfe_emissoes')->insert([
        ['business_id' => $adv, 'cstat' => 100, 'created_at' => now()->subDays(2), 'updated_at' => now()],
    ]);

    // ── CONTROLE POSITIVO ────────────────────────────────────────────────────────
    // As MESMAS 5 tools, apontadas pro adversário, TÊM que enxergar cada marcador.
    // Sem este bloco, o bloco negativo abaixo não prova isolamento — provaria apenas
    // que a query voltou vazia (ou estourou e virou payload de erro).
    $doAdversario = [];
    foreach (briefDiarioTools($adv) as $nome => $tool) {
        $json = (string) $tool->handle(new ToolRequest([]));
        $data = json_decode($json, true);
        expect($data['ok'])->toBeTrue(
            "controle positivo: tool {$nome} devolveu ok=false — ".($data['error_message'] ?? ($data['reason'] ?? '?'))
        );
        $doAdversario[$nome] = ['json' => $json, 'data' => $data];
    }

    expect((float) $doAdversario['vendas']['data']['hoje']['total'])->toEqual(4242.42);
    expect($doAdversario['inadimplencia']['json'])->toContain('Cliente Alien');
    expect($doAdversario['tickets']['json'])->toContain('Vazamento');
    expect($doAdversario['nfe']['data']['emitidas_30d'])->toBe(1);
    expect($doAdversario['oportunidades']['json'])->toContain('Produto Alien');

    // ── O TESTE ──────────────────────────────────────────────────────────────────
    // Mesmo dado, mesmas tools, business diferente: nada disso pode aparecer.
    // Só entram marcadores que o controle positivo acima PROVOU existir do lado do
    // adversário. Marcador sem dado por trás é asserção decorativa: passa sempre.
    $marcadores = ['Cliente Alien', 'Produto Alien', 'Vazamento', '4242.42'];
    $daVitima = [];

    foreach (briefDiarioTools(BRIEF_DIARIO_BIZ) as $nome => $tool) {
        $json = (string) $tool->handle(new ToolRequest([]));
        $data = json_decode($json, true);

        // ANTI-VÁCUO: source que estourou devolve payload de erro, e payload de erro
        // não contém marcador nenhum — passaria em todas as asserções abaixo sem ter
        // provado isolamento. Era assim que `oportunidades` vinha passando.
        expect($data['ok'])->toBeTrue(
            "Tool {$nome} devolveu ok=false — as asserções de ausência abaixo seriam vácuo"
        );

        foreach ($marcadores as $marcador) {
            expect($json)->not->toContain($marcador, "Tool {$nome} vazou '{$marcador}' de biz=99");
        }

        $daVitima[$nome] = $data;
    }

    // E o outro lado da moeda: biz=98 enxerga ZERO, não "alguma coisa menor".
    expect((float) $daVitima['vendas']['hoje']['total'])->toEqual(0.0);
    expect($daVitima['nfe']['emitidas_30d'])->toBe(0);
    expect($daVitima['tickets']['top_5'])->toBe([]);
    expect($daVitima['oportunidades']['combo_candidatos'])->toBe([]);
});

it('R-COPI-202-004 — Agent declara 5 tools + instructions contém business_id literal', function () {
    $agent = new BriefDiarioAgent(businessId: 42, businessName: 'Empresa Teste LTDA');

    $tools = iterator_to_array($agent->tools(), false);

    expect($tools)->toHaveCount(5);

    // Cada tool é instância da classe esperada (ordem importa pro debug)
    expect($tools[0])->toBeInstanceOf(VendasPeriodoTool::class);
    expect($tools[1])->toBeInstanceOf(InadimplenciaTool::class);
    expect($tools[2])->toBeInstanceOf(TicketsTopTool::class);
    expect($tools[3])->toBeInstanceOf(NfeStatusTool::class);
    expect($tools[4])->toBeInstanceOf(OportunidadesTool::class);

    // Instructions citam business literal (visibilidade pra LLM saber escopo)
    $instructions = (string) $agent->instructions();
    expect($instructions)->toContain('Empresa Teste LTDA')
        ->and($instructions)->toContain('42')
        // Mensagem de segurança Tier 0 presente — defesa contra prompt injection
        ->and($instructions)->toContain('TIER 0')
        // Regras anti-fabricação
        ->and($instructions)->toContain('NUNCA invente');
});

/**
 * Regressão do smoke real de 2026-08-09 (biz=1, chat /ia/conversa).
 *
 * O brief entregue ao cliente trazia três defeitos que NASCEM neste prompt:
 *   1. rodapé prometendo "próximo brief: amanhã, 8h" — NÃO existe cron pro brief
 *      do negócio. Medido: `brief:generate` no Kernel alimenta `mcp_briefs`
 *      (ADR 0091, brief de GOVERNANÇA); o `BriefDiarioAgent` só é invocado por
 *      `BriefDiarioChatTrigger`/`ProController`, sob demanda. Classe LC-15
 *      (mecanismo anuncia o que não implementa) — e esta estava na cara do cliente.
 *   2. tabela com o texto de exemplo ("PRODUTO BEST-SELLER", saídas 0) + sugestão
 *      de campanha escrita POR CIMA da linha zerada.
 *   3. animação de compensação sobre zeros + projeção "0 vendas/dia → ±0%".
 *
 * A raiz do (2) e do (3) era CONTRADIÇÃO no próprio prompt: ele manda
 * "ESTRUTURA CANÔNICA OBRIGATÓRIA" e, 100 linhas abaixo, "se tool retornou
 * vazio/zero, OMITA seção". O modelo resolveu a favor da estrutura. O conserto
 * declara a precedência em vez de repetir a regra.
 *
 * O assert FORTE aqui é a AUSÊNCIA da promessa (é o defeito que shipou); os de
 * presença apenas pinam o conserto e são deliberadamente secundários.
 */
it('R-COPI-202-006 — instructions não prometem cadência e mandam omitir seção sem dado', function () {
    $instructions = (string) (new BriefDiarioAgent(businessId: 42))->instructions();

    // (1) A promessa exata que foi entregue ao cliente não pode voltar.
    expect($instructions)->not->toContain('próximo brief: amanhã');

    // ...nem variantes de cadência — não existe agendamento deste brief.
    foreach (['amanhã, 8h', 'toda segunda', 'diariamente às', 'todo dia às'] as $promessa) {
        expect(str_contains($instructions, $promessa))->toBeFalse();
    }

    // (2) e (3): a precedência e as duas travas do conserto.
    expect($instructions)->toContain('VENCE a "ESTRUTURA CANÔNICA OBRIGATÓRIA"')
        ->and($instructions)->toContain('SEM MOVIMENTO')
        ->and($instructions)->toContain('NÃO PROMETA ENTREGA FUTURA')
        ->and($instructions)->toContain('OMITA a seção inteira');
});

it('R-COPI-202-005 — Agent com fakeAgent retorna response controlada (loop fechado)', function () {
    Ai::fakeAgent(BriefDiarioAgent::class, [
        '## ☀️ Bom dia!'.PHP_EOL.PHP_EOL.'### 📊 Vendas'.PHP_EOL.'Brief gerado em modo fake.',
    ]);

    $agent = new BriefDiarioAgent(businessId: BRIEF_DIARIO_BIZ);
    $response = $agent->prompt('Gere o brief diário de hoje.');

    expect((string) $response)->toContain('Bom dia')
        ->and((string) $response)->toContain('Brief gerado em modo fake');

    // Garantia que o prompt do user chegou no agent fake
    Ai::assertAgentWasPrompted(BriefDiarioAgent::class, function ($p) {
        return str_contains((string) $p->prompt, 'brief diário');
    });
});
