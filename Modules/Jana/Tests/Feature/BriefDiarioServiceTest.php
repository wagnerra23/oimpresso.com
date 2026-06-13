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
        // US-COPI-202c — schema UltimatePOS marca walk-in com is_default=1.
        // Fix anti-falso-combo precisa testar exclusão deste flag.
        $t->boolean('is_default')->nullable()->default(0);
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
    // 3 sells hoje biz=1: total 600. Forçar dia atual no startOfDay+offset
    // pra evitar flakiness em runs cedo de manhã (subHours(8) caía em ontem).
    $hojeRef = now()->startOfDay()->addHours(10);
    DB::table('transactions')->insert([
        ['business_id' => 1, 'type' => 'sell', 'status' => 'final', 'final_total' => 200, 'transaction_date' => $hojeRef->copy()->addMinutes(10), 'created_at' => $hojeRef->copy()->addMinutes(10), 'updated_at' => $hojeRef->copy()->addMinutes(10)],
        ['business_id' => 1, 'type' => 'sell', 'status' => 'final', 'final_total' => 250, 'transaction_date' => $hojeRef->copy()->addMinutes(30), 'created_at' => $hojeRef->copy()->addMinutes(30), 'updated_at' => $hojeRef->copy()->addMinutes(30)],
        ['business_id' => 1, 'type' => 'sell', 'status' => 'final', 'final_total' => 150, 'transaction_date' => $hojeRef->copy()->addMinutes(50), 'created_at' => $hojeRef->copy()->addMinutes(50), 'updated_at' => $hojeRef->copy()->addMinutes(50)],
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

/*
|----------------------------------------------------------------------
| US-COPI-202c (Wagner 2026-05-12) — quality fixes pro brief virar produto
|----------------------------------------------------------------------
| Sessão de validação biz=4 ROTA LIVRE expôs 2 falhas operacionais:
|  1. Cliente Balcão (contacts.is_default=1) virava "combo candidato" porque
|     várias clientes anônimas compram o mesmo produto e o agregado fica
|     atribuído ao contact walk-in id=40. Vira ruído no brief.
|  2. delta_mes_pct comparava mês incompleto (ex: dia 12) com mês completo
|     anterior, gerando falso alarme "-26,8%". Brief precisa de projeção
|     normalizada por ritmo diário.
*/

it('R-COPI-202c-001 — walk-in is_default=1 NAO aparece em combo_candidatos', function () {
    DB::table('contacts')->insert([
        // Walk-in (Cliente Balcão) — deve ser EXCLUÍDO do combo
        ['id' => 40, 'business_id' => 1, 'name' => 'Cliente Balcão', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()],
        // Cliente real — deve aparecer
        ['id' => 50, 'business_id' => 1, 'name' => 'Maria Cliente Real', 'is_default' => 0, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('products')->insert([
        ['id' => 100, 'business_id' => 1, 'name' => 'BLUSA RE002', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 101, 'business_id' => 1, 'name' => 'VESTIDO NT008', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Walk-in id=40 com produto 100 comprado 6× — NÃO pode aparecer no combo
    for ($i = 0; $i < 6; $i++) {
        $txId = DB::table('transactions')->insertGetId([
            'business_id' => 1, 'type' => 'sell', 'status' => 'final',
            'contact_id' => 40, 'final_total' => 100,
            'transaction_date' => now()->subDays(10 + $i),
            'created_at' => now()->subDays(10 + $i), 'updated_at' => now()->subDays(10 + $i),
        ]);
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $txId, 'product_id' => 100,
            'created_at' => now()->subDays(10 + $i), 'updated_at' => now()->subDays(10 + $i),
        ]);
    }

    // Cliente real id=50 com produto 101 comprado 4× — DEVE aparecer
    for ($i = 0; $i < 4; $i++) {
        $txId = DB::table('transactions')->insertGetId([
            'business_id' => 1, 'type' => 'sell', 'status' => 'final',
            'contact_id' => 50, 'final_total' => 200,
            'transaction_date' => now()->subDays(5 + $i),
            'created_at' => now()->subDays(5 + $i), 'updated_at' => now()->subDays(5 + $i),
        ]);
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $txId, 'product_id' => 101,
            'created_at' => now()->subDays(5 + $i), 'updated_at' => now()->subDays(5 + $i),
        ]);
    }

    $svc = new BriefDiarioService(1);
    $snap = $svc->snapshot();
    $combo = $snap['sources']['oportunidades']['combo_candidatos'];

    $walkInIds = array_column($combo, 'contact_id');

    // Walk-in NÃO aparece (apesar de ter mais repetes)
    expect($walkInIds)->not->toContain(40);
    // Cliente real DEVE aparecer
    expect($walkInIds)->toContain(50);
});

it('R-COPI-202c-002 — walk-in is_default=1 NAO aparece em reativacao_candidatos', function () {
    DB::table('contacts')->insert([
        ['id' => 40, 'business_id' => 1, 'name' => 'Cliente Balcão', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 49, 'business_id' => 1, 'name' => 'Andreia Real', 'is_default' => 0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Walk-in com LTV gigante (R$ [redacted Tier 0]k) — NÃO pode aparecer em reativação
    DB::table('transactions')->insert([
        'business_id' => 1, 'type' => 'sell', 'status' => 'final',
        'contact_id' => 40, 'final_total' => 50000,
        'transaction_date' => now()->subDays(120),
        'created_at' => now()->subDays(120), 'updated_at' => now()->subDays(120),
    ]);
    // Cliente real com LTV R$ [redacted Tier 0]k — DEVE aparecer
    DB::table('transactions')->insert([
        'business_id' => 1, 'type' => 'sell', 'status' => 'final',
        'contact_id' => 49, 'final_total' => 5000,
        'transaction_date' => now()->subDays(108),
        'created_at' => now()->subDays(108), 'updated_at' => now()->subDays(108),
    ]);

    $svc = new BriefDiarioService(1);
    $snap = $svc->snapshot();
    $reat = $snap['sources']['oportunidades']['reativacao_candidatos'];

    $ids = array_column($reat, 'contact_id');
    // Walk-in NÃO aparece (apesar de ter LTV maior)
    expect($ids)->not->toContain(40);
    // Cliente real DEVE aparecer
    expect($ids)->toContain(49);
});

it('R-COPI-202c-003 — vendasPeriodo retorna projecao_fechamento_mes + delta_projetado_pct', function () {
    $svc = new BriefDiarioService(1);
    $snap = $svc->snapshot();
    $v = $snap['sources']['vendas'];

    // Novas chaves obrigatórias (US-COPI-202c)
    expect($v)->toHaveKeys([
        'dias_decorridos_mes',
        'dias_restantes_mes',
        'ritmo_diario',
        'projecao_fechamento_mes',
        'delta_projetado_pct',
    ]);

    // dias_decorridos + dias_restantes = total do mês (entre 28 e 31)
    $total = $v['dias_decorridos_mes'] + $v['dias_restantes_mes'];
    expect($total)->toBeGreaterThanOrEqual(28)
        ->and($total)->toBeLessThanOrEqual(31);

    // BC: delta_mes_pct cru continua presente (consumers legacy podem usar)
    expect($v)->toHaveKey('delta_mes_pct');
});

it('R-COPI-202c-004 — projecao normaliza ritmo diario (cobre falso alarme delta_mes)', function () {
    // Cenário ROTA LIVRE biz=4 real: mês corrente até dia 12 com 88 vendas R$ [redacted Tier 0]k,
    // mês anterior fechado em 145 vendas R$ [redacted Tier 0]k. delta_mes_pct cru daria -26,8%
    // (falso alarme). Projeção pelo ritmo deve indicar projeção MAIOR que mês ant.

    $diaAtual = (int) now()->day;
    $diasNoMes = (int) now()->daysInMonth;

    // Mês anterior fechado: 1 venda R$ [redacted Tier 0] (referência baixa pra forçar projeção > antes)
    DB::table('transactions')->insert([
        'business_id' => 1, 'type' => 'sell', 'status' => 'final',
        'final_total' => 1000,
        'transaction_date' => now()->subMonth()->startOfMonth()->addDays(5),
        'created_at' => now()->subMonth(), 'updated_at' => now()->subMonth(),
    ]);

    // Mês corrente: ritmo de R$ [redacted Tier 0]/dia × dias decorridos. Projeção =
    // R$ [redacted Tier 0] × diasNoMes. Se mês tem 31 dias → R$ [redacted Tier 0] (3.1x > R$ [redacted Tier 0] ant).
    for ($i = 0; $i < $diaAtual; $i++) {
        DB::table('transactions')->insert([
            'business_id' => 1, 'type' => 'sell', 'status' => 'final',
            'final_total' => 100,
            'transaction_date' => now()->startOfMonth()->addDays($i)->setTime(12, 0, 0),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $svc = new BriefDiarioService(1);
    $snap = $svc->snapshot();
    $v = $snap['sources']['vendas'];

    // Validações:
    // 1. ritmo_diario ≈ R$ [redacted Tier 0] (mes_corrente.total / dias_decorridos)
    expect($v['ritmo_diario'])->toBeGreaterThanOrEqual(90.0)
        ->and($v['ritmo_diario'])->toBeLessThanOrEqual(110.0);

    // 2. projecao_fechamento ≈ ritmo × diasNoMes
    $projEsperada = $v['ritmo_diario'] * $diasNoMes;
    expect(abs($v['projecao_fechamento_mes'] - $projEsperada))->toBeLessThan(1.0);

    // 3. delta_projetado deve ser POSITIVO (projeção 3000+ vs anterior 1000)
    expect($v['delta_projetado_pct'])->toBeGreaterThan(0,
        'delta_projetado_pct deve normalizar falso alarme do mês incompleto');
});
