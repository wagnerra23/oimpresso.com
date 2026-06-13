<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
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
 *  001. Cada Tool retorna JSON string parseável (5 smoke tests)
 *  002. VendasPeriodoTool IGNORA args do Request — Tier 0 mecânico
 *  003. Tier 0 cross-tenant — Tool(biz=1) NÃO vê biz=99 mesmo se LLM tentar
 *  004. Agent declara 5 tools + instructions contém business_id literal
 *  005. Agent com fakeAgent retorna response controlada (loop fechado)
 *
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

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

it('R-COPI-202-001 — cada Tool retorna JSON parseável com shape estável', function () {
    DB::table('transactions')->insert([
        ['business_id' => 1, 'type' => 'sell', 'status' => 'final', 'final_total' => 100, 'transaction_date' => now()->subHours(2), 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)],
    ]);

    $tools = [
        new VendasPeriodoTool(1),
        new InadimplenciaTool(1),
        new TicketsTopTool(1),
        new NfeStatusTool(1),
        new OportunidadesTool(1),
    ];

    foreach ($tools as $tool) {
        $json = (string) $tool->handle(new ToolRequest([]));

        $data = json_decode($json, true);

        expect($json)->toBeString();
        expect($data)->toBeArray()
            ->and($data)->toHaveKey('ok')
            // Cada source tem chave `ok` boolean — contract estável pro LLM
            ->and($data['ok'])->toBeBool();
    }
});

it('R-COPI-202-002 — VendasPeriodoTool IGNORA args do Request (Tier 0 mecânico)', function () {
    DB::table('transactions')->insert([
        // biz=1 com venda hoje
        ['business_id' => 1, 'type' => 'sell', 'status' => 'final', 'final_total' => 500, 'transaction_date' => now()->subHours(1), 'created_at' => now()->subHours(1), 'updated_at' => now()->subHours(1)],
        // biz=99 com venda gigante — NÃO pode aparecer
        ['business_id' => 99, 'type' => 'sell', 'status' => 'final', 'final_total' => 99999, 'transaction_date' => now()->subHours(1), 'created_at' => now()->subHours(1), 'updated_at' => now()->subHours(1)],
    ]);

    $tool = new VendasPeriodoTool(1); // biz=1 hardcoded no constructor

    // LLM "alucinado" tenta passar business_id=99 ou outros campos — Tool deve ignorar
    $maliciousRequest = new ToolRequest([
        'business_id' => 99,
        'override_tenant' => true,
        'sql' => 'DROP TABLE transactions',
    ]);

    $json = (string) $tool->handle($maliciousRequest);
    $data = json_decode($json, true);

    // Só vê venda de biz=1 (R$ [redacted Tier 0]) — nunca R$ [redacted Tier 0] de biz=99
    // toEqual (loose) porque JSON encode/decode pode promover float→int em valores .00
    expect((float) $data['hoje']['total'])->toEqual(500.0);
    expect((float) $data['hoje']['total'])->not->toEqual(99999.0);
});

it('R-COPI-202-003 — Tier 0 cross-tenant: 5 Tools(biz=1) NUNCA expoem dados de biz=99', function () {
    // Setup cross-tenant: biz=99 cheio de dados, biz=1 vazio.
    // Inserts separados pra SQLite aceitar diferentes shapes (payment_status/due_date).
    DB::table('transactions')->insert([
        'business_id' => 99, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid', 'final_total' => 1000, 'transaction_date' => now()->subHours(2), 'due_date' => null, 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2),
    ]);
    DB::table('transactions')->insert([
        'business_id' => 99, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due', 'final_total' => 5000, 'due_date' => now()->subDays(45), 'transaction_date' => now()->subDays(50), 'created_at' => now()->subDays(50), 'updated_at' => now()->subDays(50),
    ]);
    DB::table('contacts')->insert([
        ['id' => 99, 'business_id' => 99, 'name' => 'Cliente Alien', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('channels')->insert([
        ['id' => 99, 'business_id' => 99, 'label' => 'X', 'type' => 'whatsapp_baileys', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('conversations')->insert([
        ['id' => 999, 'business_id' => 99, 'channel_id' => 99, 'customer_external_id' => '+99', 'contact_name' => 'Vazamento', 'unread_count' => 50, 'status' => 'open', 'is_blocked' => false, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('nfe_emissoes')->insert([
        ['business_id' => 99, 'cstat' => 100, 'created_at' => now()->subDays(2), 'updated_at' => now()],
    ]);

    $tools = [
        'vendas' => new VendasPeriodoTool(1),
        'inadimplencia' => new InadimplenciaTool(1),
        'tickets' => new TicketsTopTool(1),
        'nfe' => new NfeStatusTool(1),
        'oportunidades' => new OportunidadesTool(1),
    ];

    foreach ($tools as $nome => $tool) {
        $json = (string) $tool->handle(new ToolRequest([]));

        // Nome do contato cross-tenant não pode aparecer no JSON
        expect($json)->not->toContain('Vazamento', "Tool {$nome} vazou contact_name de biz=99");
        expect($json)->not->toContain('Cliente Alien', "Tool {$nome} vazou name de biz=99");
        // Valores monetários cross-tenant não podem aparecer
        expect($json)->not->toContain('99999', "Tool {$nome} vazou valor de biz=99");
    }
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

it('R-COPI-202-005 — Agent com fakeAgent retorna response controlada (loop fechado)', function () {
    Ai::fakeAgent(BriefDiarioAgent::class, [
        '## ☀️ Bom dia!'.PHP_EOL.PHP_EOL.'### 📊 Vendas'.PHP_EOL.'Brief gerado em modo fake.',
    ]);

    $agent = new BriefDiarioAgent(businessId: 1);
    $response = $agent->prompt('Gere o brief diário de hoje.');

    expect((string) $response)->toContain('Bom dia')
        ->and((string) $response)->toContain('Brief gerado em modo fake');

    // Garantia que o prompt do user chegou no agent fake
    Ai::assertAgentWasPrompted(BriefDiarioAgent::class, function ($p) {
        return str_contains((string) $p->prompt, 'brief diário');
    });
});
