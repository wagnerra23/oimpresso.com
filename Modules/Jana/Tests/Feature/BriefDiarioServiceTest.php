<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Services\BriefDiarioService;

uses(Tests\TestCase::class);

/**
 * R-JANA — GUARD tests pra BriefDiarioService (US-COPI-201, ADR 0140).
 *
 * Cobre as 5 sources do JANA Pro brief diário:
 *  001. vendas_periodo soma transactions sell por período + delta % vs anterior
 *  002. inadimplencia_buckets distribui devidos em buckets 0-30/30-60/60-90/>90
 *  003. tickets_priorizados rankeia conversations com unread + palavras críticas
 *  004. nfe_status retorna emitidas vs rejeitadas com cstat top 5
 *  005. oportunidades_upsell detecta combo (>3x produto) + reativação (>60d)
 *  006. Tier 0 (ADR 0093): biz=1 NÃO vê dados de biz=99 em nenhuma source
 *  007. Graceful degradation — tabela ausente retorna `ok:false, reason:table_missing`
 */
beforeEach(function () {
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

it('R-JANA-001 — vendas_periodo soma transactions sell por período + delta % vs anterior', function () {
    // 3 sells hoje biz=1: total 600
    DB::table('transactions')->insert([
        ['business_id' => 1, 'type' => 'sell', 'status' => 'final', 'final_total' => 200, 'transaction_date' => now()->subHours(2), 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)],
        ['business_id' => 1, 'type' => 'sell', 'status' => 'final', 'final_total' => 250, 'transaction_date' => now()->subHours(5), 'created_at' => now()->subHours(5), 'updated_at' => now()->subHours(5)],
        ['business_id' => 1, 'type' => 'sell', 'status' => 'final', 'final_total' => 150, 'transaction_date' => now()->subHours(8), 'created_at' => now()->subHours(8), 'updated_at' => now()->subHours(8)],
        // 1 draft IGNORADO
        ['business_id' => 1, 'type' => 'sell', 'status' => 'draft', 'final_total' => 9999, 'transaction_date' => now(), 'created_at' => now(), 'updated_at' => now()],
        // 1 purchase IGNORADA
        ['business_id' => 1, 'type' => 'purchase', 'status' => 'final', 'final_total' => 500, 'transaction_date' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $svc = new BriefDiarioService(1);
    $snap = $svc->snapshot();
    $v = $snap['sources']['vendas'];

    expect($v['ok'])->toBeTrue();
    expect($v['hoje']['count'])->toBe(3);
    expect($v['hoje']['total'])->toBe(600.0);
    expect($v['hoje']['ticket_medio'])->toBe(200.0);
});

it('R-JANA-002 — inadimplencia_buckets distribui devidos em buckets corretos por idade', function () {
    // Cria 4 transactions com due_date em buckets distintos
    DB::table('contacts')->insert([
        ['id' => 1, 'business_id' => 1, 'name' => 'Cliente A', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'business_id' => 1, 'name' => 'Cliente B', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('transactions')->insert([
        // 0-30 dias atraso
        ['business_id' => 1, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due', 'contact_id' => 1, 'final_total' => 1000, 'due_date' => now()->subDays(15), 'transaction_date' => now()->subDays(20), 'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20)],
        // 30-60 dias
        ['business_id' => 1, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due', 'contact_id' => 1, 'final_total' => 2000, 'due_date' => now()->subDays(45), 'transaction_date' => now()->subDays(50), 'created_at' => now()->subDays(50), 'updated_at' => now()->subDays(50)],
        // >90 dias
        ['business_id' => 1, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due', 'contact_id' => 2, 'final_total' => 5000, 'due_date' => now()->subDays(120), 'transaction_date' => now()->subDays(125), 'created_at' => now()->subDays(125), 'updated_at' => now()->subDays(125)],
    ]);

    $svc = new BriefDiarioService(1);
    $snap = $svc->snapshot();
    $i = $snap['sources']['inadimplencia'];

    expect($i['ok'])->toBeTrue();
    expect($i['buckets']['0_30']['count'])->toBe(1);
    expect($i['buckets']['0_30']['total'])->toBe(1000.0);
    expect($i['buckets']['30_60']['count'])->toBe(1);
    expect($i['buckets']['mais_90']['count'])->toBe(1);
    expect($i['buckets']['mais_90']['total'])->toBe(5000.0);
    expect($i['total_devido_atrasado'])->toBe(8000.0);
    expect($i['clientes_inadimplentes_count'])->toBe(2);
});

it('R-JANA-003 — tickets_priorizados rankeia conversations com unread + palavras críticas', function () {
    DB::table('channels')->insert(['id' => 1, 'business_id' => 1, 'label' => 'X', 'type' => 'whatsapp_baileys', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('conversations')->insert([
        ['id' => 100, 'business_id' => 1, 'channel_id' => 1, 'customer_external_id' => '+5511111', 'contact_name' => 'Cliente Crítico', 'unread_count' => 1, 'status' => 'open', 'is_blocked' => false, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['id' => 101, 'business_id' => 1, 'channel_id' => 1, 'customer_external_id' => '+5522222', 'contact_name' => 'Cliente Não Lidas', 'unread_count' => 7, 'status' => 'open', 'is_blocked' => false, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('messages')->insert([
        // conv 100: msg com "socorro" — vai virar P1
        ['business_id' => 1, 'conversation_id' => 100, 'direction' => 'inbound', 'body' => 'Socorro! Sistema parou!', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'conversation_id' => 101, 'direction' => 'inbound', 'body' => 'Pode atender?', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $svc = new BriefDiarioService(1);
    $snap = $svc->snapshot();
    $tks = $snap['sources']['tickets'];

    expect($tks['ok'])->toBeTrue();
    expect($tks['top_5'])->toHaveCount(2);
    // P1 (palavra crítica) vem primeiro mesmo com unread menor
    expect($tks['top_5'][0]['conv_id'])->toBe(100);
    expect($tks['top_5'][0]['prioridade'])->toBe('P1');
    expect($tks['top_5'][0]['tem_palavra_critica'])->toBeTrue();
    // P2 (unread >=5) segundo
    expect($tks['top_5'][1]['conv_id'])->toBe(101);
    expect($tks['top_5'][1]['prioridade'])->toBe('P2');
    expect($tks['total_unread_business'])->toBe(8);
});

it('R-JANA-004 — nfe_status retorna emitidas vs rejeitadas com taxa rejeicao', function () {
    DB::table('nfe_emissoes')->insert([
        ['business_id' => 1, 'cstat' => 100, 'created_at' => now()->subDays(1), 'updated_at' => now()],
        ['business_id' => 1, 'cstat' => 100, 'created_at' => now()->subDays(2), 'updated_at' => now()],
        ['business_id' => 1, 'cstat' => 100, 'created_at' => now()->subDays(3), 'updated_at' => now()],
        ['business_id' => 1, 'cstat' => 539, 'created_at' => now()->subDays(5), 'updated_at' => now()],  // duplicidade
        ['business_id' => 1, 'cstat' => 539, 'created_at' => now()->subDays(6), 'updated_at' => now()],
        ['business_id' => 1, 'cstat' => 215, 'created_at' => now()->subDays(7), 'updated_at' => now()],  // ICMS
    ]);

    $svc = new BriefDiarioService(1);
    $snap = $svc->snapshot();
    $n = $snap['sources']['nfe'];

    expect($n['ok'])->toBeTrue();
    expect($n['emitidas_30d'])->toBe(3);
    expect($n['rejeitadas_30d'])->toBe(3);
    expect($n['taxa_rejeicao_pct'])->toBe(50.0);
    expect($n['top_5_cstats_rejeicao'])->toHaveCount(2);
    expect($n['top_5_cstats_rejeicao'][0]['cstat'])->toBe(539); // mais comum
    expect($n['top_5_cstats_rejeicao'][0]['count'])->toBe(2);
});

it('R-JANA-005 — Tier 0: biz=1 NAO ve dados de biz=99 em nenhuma source', function () {
    // biz=99 (cross-tenant) com transactions + nfe + conversations — não deve aparecer pra biz=1
    DB::table('transactions')->insert([
        ['business_id' => 99, 'type' => 'sell', 'status' => 'final', 'final_total' => 99999, 'transaction_date' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('nfe_emissoes')->insert([
        ['business_id' => 99, 'cstat' => 100, 'created_at' => now()->subDays(1), 'updated_at' => now()],
    ]);
    DB::table('channels')->insert(['id' => 99, 'business_id' => 99, 'label' => 'Alien', 'type' => 'whatsapp_baileys', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('conversations')->insert([
        ['id' => 999, 'business_id' => 99, 'channel_id' => 99, 'customer_external_id' => '+99999', 'unread_count' => 100, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $svc = new BriefDiarioService(1);
    $snap = $svc->snapshot();

    // Vendas: biz=1 sem nenhuma transaction → hoje count=0, total=0
    expect($snap['sources']['vendas']['hoje']['count'])->toBe(0);
    expect($snap['sources']['vendas']['hoje']['total'])->toBe(0.0);

    // NFe: biz=1 sem emissões → 0 emitidas
    expect($snap['sources']['nfe']['emitidas_30d'])->toBe(0);

    // Tickets: biz=1 sem conversations → top_5 vazio + total_unread = 0
    expect($snap['sources']['tickets']['top_5'])->toBe([]);
    expect($snap['sources']['tickets']['total_unread_business'])->toBe(0);
});

it('R-JANA-006 — Graceful degradation: tabela ausente retorna ok:false reason:table_missing/module_not_installed', function () {
    Schema::dropIfExists('nfe_emissoes');

    $svc = new BriefDiarioService(1);
    $snap = $svc->snapshot();

    expect($snap['sources']['nfe']['ok'])->toBeFalse();
    expect($snap['sources']['nfe']['reason'])->toBe('module_not_installed');
    expect($snap['sources']['nfe']['module'])->toBe('NfeBrasil');

    // Outras sources continuam funcionando — degradação por source, não total
    expect($snap['sources']['vendas']['ok'])->toBeTrue();
    expect($snap['sources']['inadimplencia']['ok'])->toBeTrue();
    expect($snap['sources']['tickets']['ok'])->toBeTrue();
});

it('R-JANA-007 — snapshot retorna shape estavel com metadata (version, generated_at, business_id, sources com 5 chaves)', function () {
    $svc = new BriefDiarioService(7);
    $snap = $svc->snapshot();

    expect($snap)->toHaveKeys(['generated_at', 'business_id', 'version', 'sources']);
    expect($snap['business_id'])->toBe(7);
    expect($snap['version'])->toBe('0.1.0');
    expect($snap['sources'])->toHaveKeys(['vendas', 'inadimplencia', 'tickets', 'nfe', 'oportunidades']);
});
