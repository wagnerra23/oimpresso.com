<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\Financeiro\Services\InsightsService;

uses(Tests\TestCase::class);

/**
 * InsightsService — camada de consulta read-only do histórico financeiro
 * (PR1 da feature "perguntar ao histórico financeiro" · ADR ARQ-0006).
 *
 * Padrão de teste herdado de MultiTenantComprehensiveTest: SQLite in-memory,
 * tabelas criadas no beforeEach (sem depender de seed UltimatePOS — robusto CI
 * + local). Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093). Tests biz=1 (ADR 0101).
 *
 * Três eixos de cobertura:
 *  1. Tier 0 (anti-leak)        — biz=1 NUNCA enxerga dado de biz=99.
 *  2. Correctness (anti-halluc) — agregado == valor computado à mão / SQL direto.
 *  3. Fontes (citação)          — resultado devolve os IDs de origem corretos.
 *
 * @see Modules\Financeiro\Services\InsightsService
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
    config()->set('activitylog.enabled', false);

    if (config('database.default') !== 'sqlite' && ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('InsightsServiceTest roda apenas em SQLite in-memory.');
    }

    Schema::dropIfExists('fin_titulo_baixas');
    Schema::dropIfExists('fin_titulos');

    Schema::create('fin_titulos', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('numero', 50)->nullable();
        $t->string('tipo', 20);
        $t->string('status', 20)->default('aberto');
        $t->unsignedBigInteger('cliente_id')->nullable();
        $t->string('cliente_descricao', 150)->nullable();
        $t->decimal('valor_total', 14, 4)->default(0);
        $t->decimal('valor_aberto', 14, 4)->default(0);
        $t->string('moeda', 3)->default('BRL');
        $t->date('emissao')->nullable();
        $t->date('vencimento')->nullable();
        $t->string('competencia_mes', 7)->nullable();
        $t->string('origem', 20)->default('manual');
        $t->unsignedBigInteger('origem_id')->nullable();
        $t->unsignedInteger('parcela_numero')->nullable();
        $t->unsignedInteger('parcela_total')->nullable();
        $t->unsignedBigInteger('titulo_pai_id')->nullable();
        $t->unsignedBigInteger('plano_conta_id')->nullable();
        $t->unsignedBigInteger('categoria_id')->nullable();
        $t->text('observacoes')->nullable();
        $t->json('metadata')->nullable();
        $t->unsignedInteger('created_by')->nullable();
        $t->unsignedInteger('updated_by')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('fin_titulo_baixas', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('titulo_id');
        $t->unsignedBigInteger('conta_bancaria_id')->nullable();
        $t->decimal('valor_baixa', 14, 4)->default(0);
        $t->decimal('juros', 14, 4)->nullable();
        $t->decimal('multa', 14, 4)->nullable();
        $t->decimal('desconto', 14, 4)->nullable();
        $t->date('data_baixa');
        $t->string('meio_pagamento', 30);
        $t->string('idempotency_key', 100)->nullable();
        $t->unsignedBigInteger('transaction_payment_id')->nullable();
        $t->unsignedBigInteger('estorno_de_id')->nullable();
        $t->text('observacoes')->nullable();
        $t->unsignedInteger('created_by')->nullable();
        $t->timestamp('created_at')->nullable();
    });

    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function ($t) {
            $t->id();
            $t->string('log_name')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->string('causer_type')->nullable();
            $t->json('properties')->nullable();
            $t->string('event')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->timestamps();
        });
    }
});

afterEach(function () {
    Schema::dropIfExists('fin_titulo_baixas');
    Schema::dropIfExists('fin_titulos');
});

// ─────────────────────────── Helpers de seed ───────────────────────────

/**
 * Cria título. Sem sessão (CLI), BusinessScope global é inerte; business_id
 * vem explícito no array (Tier 0 — sempre fornecido pelo chamador/servidor).
 *
 * @param  array<string, mixed>  $attrs
 */
function seedTitulo(array $attrs): Titulo
{
    return Titulo::query()->create(array_merge([
        'tipo'              => 'receber',
        'status'            => 'aberto',
        'cliente_descricao' => 'Cliente Teste',
        'valor_total'       => 100.0,
        'valor_aberto'      => 100.0,
        'moeda'             => 'BRL',
        'origem'            => 'manual',
    ], $attrs));
}

/**
 * Cria baixa (append-only). business_id explícito (Tier 0).
 *
 * @param  array<string, mixed>  $attrs
 */
function seedBaixa(array $attrs): TituloBaixa
{
    return TituloBaixa::query()->create(array_merge([
        'meio_pagamento' => 'pix',
        'valor_baixa'    => 100.0,
        'estorno_de_id'  => null,
    ], $attrs));
}

// ═══════════════════════ recebidoPorPeriodo ═══════════════════════

it('recebidoPorPeriodo soma EXATAMENTE as baixas semeadas (anti-alucinação)', function () {
    // biz=1: 3 baixas a-receber no período (100 + 250.50 + 49.50 = 400.00),
    // + 1 fora do período (ignorada) + 1 estornada (ignorada).
    $tA = seedTitulo(['business_id' => 1, 'tipo' => 'receber']);
    $tB = seedTitulo(['business_id' => 1, 'tipo' => 'receber']);
    $tC = seedTitulo(['business_id' => 1, 'tipo' => 'receber']);
    $tPagar = seedTitulo(['business_id' => 1, 'tipo' => 'pagar']);

    $b1 = seedBaixa(['business_id' => 1, 'titulo_id' => $tA->id, 'valor_baixa' => 100.00, 'data_baixa' => '2026-05-10']);
    $b2 = seedBaixa(['business_id' => 1, 'titulo_id' => $tB->id, 'valor_baixa' => 250.50, 'data_baixa' => '2026-05-15']);
    $b3 = seedBaixa(['business_id' => 1, 'titulo_id' => $tC->id, 'valor_baixa' => 49.50, 'data_baixa' => '2026-05-31']);
    // fora do período (junho):
    seedBaixa(['business_id' => 1, 'titulo_id' => $tA->id, 'valor_baixa' => 999.00, 'data_baixa' => '2026-06-02']);
    // estornada (estorno_de_id preenchido) — não conta:
    seedBaixa(['business_id' => 1, 'titulo_id' => $tB->id, 'valor_baixa' => 70.00, 'data_baixa' => '2026-05-20', 'estorno_de_id' => $b1->id]);
    // baixa de título a-pagar — não entra em "recebido":
    seedBaixa(['business_id' => 1, 'titulo_id' => $tPagar->id, 'valor_baixa' => 500.00, 'data_baixa' => '2026-05-12']);

    $r = (new InsightsService())->recebidoPorPeriodo(1, '2026-05-01', '2026-05-31');

    expect($r['valor'])->toBe(400.00)
        ->and($r['qtd'])->toBe(3)
        ->and($r['periodo'])->toBe(['de' => '2026-05-01', 'ate' => '2026-05-31']);

    // Fontes = exatamente os 3 IDs das baixas válidas (citação).
    expect($r['fontes'])->toEqualCanonicalizing([$b1->id, $b2->id, $b3->id]);
});

it('recebidoPorPeriodo NÃO vaza baixas de outro tenant (Tier 0 · ADR 0093)', function () {
    $t1 = seedTitulo(['business_id' => 1, 'tipo' => 'receber']);
    $b1 = seedBaixa(['business_id' => 1, 'titulo_id' => $t1->id, 'valor_baixa' => 123.45, 'data_baixa' => '2026-05-10']);

    // biz=99: dado que JAMAIS pode aparecer pra biz=1.
    $t99 = seedTitulo(['business_id' => 99, 'tipo' => 'receber']);
    seedBaixa(['business_id' => 99, 'titulo_id' => $t99->id, 'valor_baixa' => 9999.99, 'data_baixa' => '2026-05-11']);

    $r = (new InsightsService())->recebidoPorPeriodo(1, '2026-05-01', '2026-05-31');

    expect($r['valor'])->toBe(123.45)
        ->and($r['qtd'])->toBe(1)
        ->and($r['fontes'])->toBe([$b1->id]);

    // E o inverso: biz=99 também só vê o seu.
    $r99 = (new InsightsService())->recebidoPorPeriodo(99, '2026-05-01', '2026-05-31');
    expect($r99['valor'])->toBe(9999.99)->and($r99['qtd'])->toBe(1);
});

// ═══════════════════════ agingResumo ═══════════════════════

it('agingResumo distribui títulos nos buckets de Titulo::agingBucket (correctness)', function () {
    $hoje = '2026-05-31';

    // em_dia (vence no futuro):
    $tEmDia = seedTitulo(['business_id' => 1, 'vencimento' => '2026-06-15', 'valor_aberto' => 100.00, 'status' => 'aberto']);
    // <30 (vencido há 10 dias):
    $t10 = seedTitulo(['business_id' => 1, 'vencimento' => '2026-05-21', 'valor_aberto' => 200.00, 'status' => 'aberto']);
    // 30-60 (vencido há 45 dias):
    $t45 = seedTitulo(['business_id' => 1, 'vencimento' => '2026-04-16', 'valor_aberto' => 300.00, 'status' => 'parcial']);
    // 90-180 (vencido há 120 dias):
    $t120 = seedTitulo(['business_id' => 1, 'vencimento' => '2026-01-31', 'valor_aberto' => 400.00, 'status' => 'aberto']);
    // >180 (vencido há ~365 dias):
    $t365 = seedTitulo(['business_id' => 1, 'vencimento' => '2025-05-31', 'valor_aberto' => 500.00, 'status' => 'aberto']);

    // Ruído ignorado: quitado + cancelado não entram (só aberto/parcial).
    seedTitulo(['business_id' => 1, 'vencimento' => '2026-01-01', 'valor_aberto' => 0.0, 'status' => 'quitado']);
    seedTitulo(['business_id' => 1, 'vencimento' => '2026-01-01', 'valor_aberto' => 777.0, 'status' => 'cancelado']);

    $r = (new InsightsService())->agingResumo(1, 'receber', $hoje);

    expect($r['buckets']['em_dia'])->toBe(['qtd' => 1, 'valor' => 100.00])
        ->and($r['buckets']['<30'])->toBe(['qtd' => 1, 'valor' => 200.00])
        ->and($r['buckets']['30-60'])->toBe(['qtd' => 1, 'valor' => 300.00])
        ->and($r['buckets']['60-90'])->toBe(['qtd' => 0, 'valor' => 0.0])
        ->and($r['buckets']['90-180'])->toBe(['qtd' => 1, 'valor' => 400.00])
        ->and($r['buckets']['>180'])->toBe(['qtd' => 1, 'valor' => 500.00]);

    // Total = só os 5 abertos/parciais (1500), não o quitado/cancelado.
    expect($r['total'])->toBe(['qtd' => 5, 'valor' => 1500.00]);

    // Fontes = exatamente os 5 títulos considerados (citação).
    expect($r['fontes'])->toEqualCanonicalizing([$tEmDia->id, $t10->id, $t45->id, $t120->id, $t365->id]);
});

it('agingResumo NÃO vaza títulos de outro tenant (Tier 0 · ADR 0093)', function () {
    $t1 = seedTitulo(['business_id' => 1, 'vencimento' => '2026-05-21', 'valor_aberto' => 200.00, 'status' => 'aberto']);
    // biz=99: título vencido que não pode contaminar o aging de biz=1.
    seedTitulo(['business_id' => 99, 'vencimento' => '2026-05-21', 'valor_aberto' => 9999.00, 'status' => 'aberto']);

    $r = (new InsightsService())->agingResumo(1, 'receber', '2026-05-31');

    expect($r['total'])->toBe(['qtd' => 1, 'valor' => 200.00])
        ->and($r['fontes'])->toBe([$t1->id])
        ->and($r['buckets']['<30'])->toBe(['qtd' => 1, 'valor' => 200.00]);
});

// ═══════════════════════ historicoAtrasoContraparte ═══════════════════════

it('historicoAtrasoContraparte calcula % e dias médios de atraso (correctness)', function () {
    $hoje = '2026-05-31';
    $nome = 'Padaria do Zé';

    // Quitado em dia (pago antes do vencimento) — NÃO atrasado.
    $tOk = seedTitulo(['business_id' => 1, 'cliente_descricao' => $nome, 'status' => 'quitado', 'vencimento' => '2026-05-20', 'valor_aberto' => 0.0]);
    seedBaixa(['business_id' => 1, 'titulo_id' => $tOk->id, 'data_baixa' => '2026-05-18']);

    // Quitado com 10 dias de atraso (pago depois do vencimento).
    $tLate1 = seedTitulo(['business_id' => 1, 'cliente_descricao' => $nome, 'status' => 'quitado', 'vencimento' => '2026-05-01', 'valor_aberto' => 0.0]);
    seedBaixa(['business_id' => 1, 'titulo_id' => $tLate1->id, 'data_baixa' => '2026-05-11']);

    // Aberto e vencido há 20 dias (vencimento 2026-05-11) — atrasado.
    $tLate2 = seedTitulo(['business_id' => 1, 'cliente_descricao' => $nome, 'status' => 'aberto', 'vencimento' => '2026-05-11', 'valor_aberto' => 50.0]);

    // Outra contraparte — não deve casar no LIKE.
    $tOutro = seedTitulo(['business_id' => 1, 'cliente_descricao' => 'Mercado da Ana', 'status' => 'aberto', 'vencimento' => '2026-01-01', 'valor_aberto' => 80.0]);

    $r = (new InsightsService())->historicoAtrasoContraparte(1, 'Padaria', $hoje);

    // 3 títulos da Padaria; 2 atrasados (10d + 20d) → 30 / 2 = 15 dias médio.
    expect($r['qtd_titulos'])->toBe(3)
        ->and($r['qtd_atrasados'])->toBe(2)
        ->and($r['pct_atraso'])->toBe(round((2 / 3) * 100, 2))
        ->and($r['dias_medio_atraso'])->toBe(15.00)
        ->and($r['contraparte'])->toBe('Padaria');

    // Fontes = os 3 títulos da Padaria (não o da Ana).
    expect($r['fontes'])->toEqualCanonicalizing([$tOk->id, $tLate1->id, $tLate2->id])
        ->and($r['fontes'])->not->toContain($tOutro->id);
});

it('historicoAtrasoContraparte NÃO vaza títulos de outro tenant (Tier 0 · ADR 0093)', function () {
    $nome = 'ACME Ltda';
    $t1 = seedTitulo(['business_id' => 1, 'cliente_descricao' => $nome, 'status' => 'aberto', 'vencimento' => '2026-05-11', 'valor_aberto' => 50.0]);
    // biz=99 com MESMO nome de contraparte — clássico vetor de leak cross-tenant.
    seedTitulo(['business_id' => 99, 'cliente_descricao' => $nome, 'status' => 'aberto', 'vencimento' => '2026-05-11', 'valor_aberto' => 9999.0]);

    $r = (new InsightsService())->historicoAtrasoContraparte(1, 'ACME', '2026-05-31');

    expect($r['qtd_titulos'])->toBe(1)
        ->and($r['fontes'])->toBe([$t1->id]);
});

// ═══════════════════════ totaisPorCanal ═══════════════════════

it('totaisPorCanal agrupa recebido por origem ordenado por valor desc (correctness)', function () {
    // venda: 300 (200 + 100) · manual: 150 · recurring: 50.
    $tVenda1 = seedTitulo(['business_id' => 1, 'tipo' => 'receber', 'origem' => 'venda']);
    $tVenda2 = seedTitulo(['business_id' => 1, 'tipo' => 'receber', 'origem' => 'venda']);
    $tManual = seedTitulo(['business_id' => 1, 'tipo' => 'receber', 'origem' => 'manual']);
    $tRecur  = seedTitulo(['business_id' => 1, 'tipo' => 'receber', 'origem' => 'recurring']);

    $bv1 = seedBaixa(['business_id' => 1, 'titulo_id' => $tVenda1->id, 'valor_baixa' => 200.00, 'data_baixa' => '2026-05-10']);
    $bv2 = seedBaixa(['business_id' => 1, 'titulo_id' => $tVenda2->id, 'valor_baixa' => 100.00, 'data_baixa' => '2026-05-12']);
    $bm  = seedBaixa(['business_id' => 1, 'titulo_id' => $tManual->id, 'valor_baixa' => 150.00, 'data_baixa' => '2026-05-15']);
    $br  = seedBaixa(['business_id' => 1, 'titulo_id' => $tRecur->id, 'valor_baixa' => 50.00, 'data_baixa' => '2026-05-20']);

    $r = (new InsightsService())->totaisPorCanal(1, '2026-05-01', '2026-05-31');

    expect($r['total'])->toBe(500.00);

    // Ordenado por valor desc: venda (300) > manual (150) > recurring (50).
    expect($r['por_canal'])->toBe([
        ['canal' => 'venda', 'valor' => 300.00, 'qtd' => 2],
        ['canal' => 'manual', 'valor' => 150.00, 'qtd' => 1],
        ['canal' => 'recurring', 'valor' => 50.00, 'qtd' => 1],
    ]);

    // Fontes = todas as 4 baixas somadas (citação).
    expect($r['fontes'])->toEqualCanonicalizing([$bv1->id, $bv2->id, $bm->id, $br->id]);
});

it('totaisPorCanal NÃO vaza baixas de outro tenant (Tier 0 · ADR 0093)', function () {
    $t1 = seedTitulo(['business_id' => 1, 'tipo' => 'receber', 'origem' => 'venda']);
    $b1 = seedBaixa(['business_id' => 1, 'titulo_id' => $t1->id, 'valor_baixa' => 80.00, 'data_baixa' => '2026-05-10']);

    $t99 = seedTitulo(['business_id' => 99, 'tipo' => 'receber', 'origem' => 'venda']);
    seedBaixa(['business_id' => 99, 'titulo_id' => $t99->id, 'valor_baixa' => 5000.00, 'data_baixa' => '2026-05-10']);

    $r = (new InsightsService())->totaisPorCanal(1, '2026-05-01', '2026-05-31');

    expect($r['total'])->toBe(80.00)
        ->and($r['por_canal'])->toBe([['canal' => 'venda', 'valor' => 80.00, 'qtd' => 1]])
        ->and($r['fontes'])->toBe([$b1->id]);
});
