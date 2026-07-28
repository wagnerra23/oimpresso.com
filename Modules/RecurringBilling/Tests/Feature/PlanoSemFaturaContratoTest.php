<?php

declare(strict_types=1);

/*
 * Contrato de tela — RecurringBilling/Index (o drawer "Nova assinatura").
 *
 * @covers-us US-RB-002
 *
 * UC (resources/js/Pages/RecurringBilling/Index.casos.md · CU-RB-02 item 5 do SDD §6.1):
 *   UC-RBSUB-05 — a assinatura criada tem que ser faturável [V0]
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FAILING-FIRST DE PROPÓSITO. Este arquivo NÃO defende um comportamento — ele
 * DENUNCIA um (SDD §9.1). O que a análise achou:
 *
 *   RecurringBillingController@store  → resolvePlanIdFromCiclo() só casa um Plan
 *                                       quando ciclo E valor batem EXATAMENTE.
 *                                       Sem match → plan_id = null (coluna nullable).
 *   InvoiceGeneratorService
 *     ::processarSubscription         → if ($plan === null) { errors++; Log::error; return; }
 *
 * Consequência: assinatura com valor negociado (override do plano — que a DoD da
 * US-RB-002 exige suportar) fica ATIVA, com next_due_date vencendo, e NUNCA gera
 * fatura. Sem alarme: uma linha no log 'single' e pronto. É a mesma família da
 * US-RB-052 ("109 assinaturas com gateway=NULL — cobranças dormentes").
 *
 * A CORREÇÃO É DECISÃO [V0] DE [W] — há >=2 remédios válidos e eles SE ANULAM:
 *   (a) o gerador cair pra subscription.metadata.valor quando não há plano;
 *   (b) o store() RECUSAR valor que não casa com plano nenhum;
 *   (c) criar plano implícito no store().
 * Escolher remédio antes do diagnóstico é o erro catalogado em proibicoes §5
 * (2026-07-15). Este teste existe pra o diagnóstico ter recibo.
 *
 * VEREDITO: não rodado (CT 100 — ADR 0062). "Vermelho" abaixo é PREDIÇÃO.
 *
 * PRÉ-CONDIÇÃO ANTI-VÁCUO (proibicoes §5 2026-07-24): o teste 1 prova primeiro que
 * o gerador ESTÁ FUNCIONANDO no caso com plano — senão um verde/vermelho aqui
 * mediria "o job não rodou", não "a assinatura não faturou".
 * ─────────────────────────────────────────────────────────────────────────────
 */

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Services\InvoiceGeneratorService;

uses(Tests\TestCase::class);

/**
 * Schema sintético (mesmo idioma do InvoiceGeneratorServiceTest): sqlite-safe,
 * sem RefreshDatabase nem migration MySQL-only.
 */
function rbSemPlanoBootstrapSchema(): void
{
    Schema::dropIfExists('rb_invoices');
    Schema::dropIfExists('rb_subscriptions');
    Schema::dropIfExists('rb_plans');

    Schema::create('rb_plans', function ($t): void {
        $t->increments('id');
        $t->unsignedInteger('business_id');
        $t->string('name');
        $t->string('slug')->nullable();
        $t->decimal('valor', 22, 4)->default(0);
        $t->string('ciclo')->default('monthly');
        $t->unsignedInteger('trial_days')->default(0);
        $t->boolean('ativo')->default(true);
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('rb_subscriptions', function ($t): void {
        $t->increments('id');
        $t->unsignedInteger('business_id');
        $t->unsignedInteger('plan_id')->nullable();
        $t->unsignedInteger('contact_id')->nullable();
        $t->unsignedInteger('conta_bancaria_id')->nullable();
        $t->string('status')->default('active');
        $t->date('start_date')->nullable();
        $t->date('next_due_date')->nullable();
        $t->date('billing_anchor_date')->nullable();
        $t->string('payment_method')->nullable();
        $t->json('metadata')->nullable();
        // v9.75 (2026_05_16_120000_recurring_v975_schema): o Model usa SoftDeletes e o
        // schema real tem os 3 contadores. Sem eles a fixture morria em
        // "no such column: rb_subscriptions.deleted_at" ANTES de alcançar o branch sob
        // teste — vermelho pelo motivo errado, que é pior que vermelho nenhum: quem lesse
        // a cor confirmaria a predição do casos.md sem que o caso tivesse rodado.
        // As TRÊS moram em rb_subscriptions (migration 2026_05_16_120000_recurring_v975_schema
        // linhas 71-81), com estes tipos exatos. O gerador faz UPDATE nelas ao faturar —
        // sem elas o try/catch engole "no such column: total_revenue_cached" e o sintoma
        // aparece como generated=0, que parece comportamento e é infra.
        $t->unsignedSmallInteger('total_paid_cached')->default(0);
        $t->unsignedSmallInteger('failed_count_cached')->default(0);
        $t->decimal('total_revenue_cached', 14, 2)->default(0);
        $t->timestamps();
        $t->softDeletes();
    });

    // Invoice e Subscription usam Spatie LogsActivity: todo create/update escreve em
    // `activity_log`. Sem a tabela, o insert lança — e o gerador tem try/catch que
    // converte a exceção em `errors++` (InvoiceGeneratorService:89-95), então a falha
    // aparece como "generated=0" e parece comportamento, não infra.
    // CONDICIONAL de propósito: o `tests/Pest.php` do projeto JÁ provisiona esta tabela
    // (idioma do bloco RecurringBilling, citado na .github/ci-sqlite-pest.list). Criar
    // incondicionalmente derruba tudo com "table already exists" — medido no CT 100.
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function ($t): void {
            $t->increments('id');
            $t->string('log_name')->nullable();
            $t->text('description')->nullable();
            $t->nullableMorphs('subject', 'subject');
            $t->nullableMorphs('causer', 'causer');
            $t->json('properties')->nullable();
            $t->string('event')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->timestamps();
        });
    }

    // O gerador registra evento de timeline ao faturar. Colunas espelham a migration
    // v9.75 (linhas 176-186): `kind`/`by_actor`/`body`/`occurred_at` — NÃO invente nomes,
    // errar aqui reaparece como "generated=0" porque o try/catch do gerador engole o
    // "has no column named X" (foi o que aconteceu com `tipo` em vez de `kind`).
    Schema::create('rb_subscription_events', function ($t): void {
        $t->increments('id');
        $t->unsignedInteger('business_id');
        $t->unsignedInteger('subscription_id');
        $t->string('kind', 32)->nullable();
        $t->string('by_actor', 64)->nullable();
        $t->text('body')->nullable();
        $t->dateTime('occurred_at')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
    });

    Schema::create('rb_invoices', function ($t): void {
        $t->increments('id');
        $t->unsignedInteger('business_id');
        $t->unsignedInteger('subscription_id')->nullable();
        $t->unsignedInteger('contact_id')->nullable();
        $t->unsignedInteger('conta_bancaria_id')->nullable();
        $t->string('numero_documento')->nullable();
        $t->decimal('valor', 22, 4)->default(0);
        $t->string('status')->default('open');
        $t->date('vencimento')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();   // Model Invoice usa SoftDeletes — idem rb_subscriptions
    });
}

/** ADR 0101: biz=1 (Wagner/WR2), NUNCA biz=4 (ROTA LIVRE, cliente real). */
function rbSemPlanoAssinatura(?int $planId, float $valorNegociado): Subscription
{
    return Subscription::withoutGlobalScopes()->create([
        'business_id'         => 1,
        'plan_id'             => $planId,
        'contact_id'          => 77,
        'status'              => 'active',
        'start_date'          => '2026-06-10',
        'next_due_date'       => '2026-07-10',
        'billing_anchor_date' => '2026-07-10',
        'payment_method'      => 'boleto',
        // Exatamente o que RecurringBillingController@store grava.
        'metadata'            => [
            'valor'       => $valorNegociado,
            'ciclo'       => 'mensal',
            'gateway'     => 'inter',
            'created_via' => 'recurring-billing.store',
        ],
    ]);
}

beforeEach(function (): void {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('Schema sintético — lane sqlite.');
    }
    rbSemPlanoBootstrapSchema();
    Carbon::setTestNow('2026-07-10');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * PRÉ-CONDIÇÃO ANTI-VÁCUO — sem isto, o teste 2 mediria não-execução.
 * Prova que o gerador está vivo e faturando no caminho COM plano.
 */
it('pré-condição: com plano casado, o gerador FATURA (o motor está vivo)', function (): void {
    $plan = Plan::withoutGlobalScopes()->create([
        'business_id' => 1,
        'name'        => 'Mensal Padrão',
        'slug'        => 'mensal-padrao',
        'valor'       => 150.00,
        'ciclo'       => 'monthly',
        'ativo'       => true,
    ]);

    $sub = rbSemPlanoAssinatura((int) $plan->getKey(), 150.00);

    $stats = (new InvoiceGeneratorService())->run(1, '2026-07-10');

    expect($stats['generated'])->toBe(1)
        ->and($stats['errors'])->toBe(0);

    $faturas = Invoice::withoutGlobalScopes()
        ->where('subscription_id', $sub->getKey())
        ->get();

    expect($faturas)->toHaveCount(1);
    // Contrato de VALOR (CU-RB-03 item 1): a fatura sai pelo valor do plano.
    expect((float) $faturas->first()->valor)->toBe(150.00);
});

/**
 * UC-RBSUB-05 — o denúncia.
 *
 * O assert é de COMPORTAMENTO ("a assinatura ativa e vencida faturou"), não de
 * chave de payload — proibicoes §5: assert acoplado a nome de campo é tautologia
 * e reprova arbitrariamente um dos remédios válidos. Aqui, qualquer um dos 3
 * remédios de [W] faz este teste passar.
 */
it('UC-RBSUB-05 · assinatura com valor negociado (sem plano casado) TEM que gerar fatura', function (): void {
    // Plano existe no catálogo, mas por R$ 150 — o contrato foi fechado por R$ 187.
    Plan::withoutGlobalScopes()->create([
        'business_id' => 1,
        'name'        => 'Mensal Padrão',
        'slug'        => 'mensal-padrao',
        'valor'       => 150.00,
        'ciclo'       => 'monthly',
        'ativo'       => true,
    ]);

    // É EXATAMENTE o que o store() produz quando resolvePlanIdFromCiclo() não casa:
    // plan_id = null, valor negociado só em metadata.
    $sub = rbSemPlanoAssinatura(null, 187.00);

    expect($sub->status)->toBe('active');
    expect($sub->next_due_date)->not->toBeNull();

    $stats = (new InvoiceGeneratorService())->run(1, '2026-07-10');

    $faturas = Invoice::withoutGlobalScopes()
        ->where('subscription_id', $sub->getKey())
        ->get();

    // O CONTRATO: assinatura ativa e vencida fatura. Ponto.
    expect($faturas->count())->toBeGreaterThan(
        0,
        'US-RB-002 DoD exige suportar "customizações de valor (override do plano)". '
        .'Hoje plan_id=null faz InvoiceGeneratorService::processarSubscription descartar '
        .'a assinatura em silêncio (SDD §9.1). stats='.json_encode($stats)
    );

    // E não pode ser "faturou" à custa de virar erro do batch.
    expect($stats['errors'])->toBe(
        0,
        'A assinatura não pode cair no branch de erro do gerador — cobrança dormente sem alarme.'
    );
});

/**
 * Guarda do raio de explosão: seja qual for o remédio de [W], ele NÃO pode
 * quebrar o Tier 0 nem o caso normal.
 */
it('UC-RBSUB-05 · o gerador de um business nunca toca assinatura de outro [T0]', function (): void {
    Plan::withoutGlobalScopes()->create([
        'business_id' => 1,
        'name'        => 'Mensal Padrão',
        'valor'       => 150.00,
        'ciclo'       => 'monthly',
        'ativo'       => true,
    ]);
    $subBiz1 = rbSemPlanoAssinatura(null, 187.00);

    // Roda pro business 99 — nada de biz=1 pode ser tocado.
    (new InvoiceGeneratorService())->run(99, '2026-07-10');

    expect(Invoice::withoutGlobalScopes()->where('business_id', 1)->count())->toBe(0);
    expect($subBiz1->fresh()->next_due_date->toDateString())->toBe('2026-07-10');
});
