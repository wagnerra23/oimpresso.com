<?php

declare(strict_types=1);

use App\TaxRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Listeners\SyncFiscalRuleToTaxRate;
use Modules\NfeBrasil\Models\NfeFiscalRule;

uses(Tests\TestCase::class);

/**
 * ADR ARQ-0005 · Bridge listener — sincroniza nfe_fiscal_rules com tax_rates.
 *
 * Pattern dual-mode (PR #486 reference + skip-em-MySQL):
 *   - SQLite (CI sanity): drop+create as 3 tabelas isolado em :memory:
 *   - MySQL (Pest local — gate Wagner): SKIP — `tax_rates` é referenciada por ~8
 *     FKs em produção (products.tax, transactions.tax_id, purchase_lines.tax_id,
 *     transaction_sell_lines.tax_id, business.default_sales_tax, group_sub_taxes,
 *     etc). Drop é catastrófico; cleanup parcial vazaria tax_rates do biz=1
 *     real (Wagner WR2). Cobertura genuína do listener vem via integration
 *     tests E2E (FiscalRuleCreated dispatch em prod path).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('SyncFiscalRuleToTaxRateTest dropa `tax_rates` — catastrófico em MySQL UPos. Pest local SQLite é o canal de cobertura.');
    }

    Schema::dropIfExists('nfe_fiscal_rule_tax_rate_links');
    Schema::dropIfExists('tax_rates');
    Schema::dropIfExists('nfe_fiscal_rules');

    Schema::create('nfe_fiscal_rules', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->char('ncm', 8);
        $t->char('uf_origem', 2);
        $t->char('uf_destino', 2)->nullable();
        $t->char('cfop', 4);
        $t->char('csosn', 3)->nullable();
        $t->char('cst', 3)->nullable();
        $t->decimal('aliquota_icms', 7, 4)->default(0);
        $t->decimal('aliquota_pis', 7, 4)->default(0);
        $t->decimal('aliquota_cofins', 7, 4)->default(0);
        $t->decimal('aliquota_ipi', 7, 4)->default(0);
        $t->decimal('mva', 7, 4)->nullable();
        $t->decimal('fcp', 7, 4)->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('tax_rates', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('business_id');
        $t->string('name');
        $t->float('amount', 22, 4);
        $t->boolean('is_tax_group')->default(false);
        $t->unsignedInteger('created_by');
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('nfe_fiscal_rule_tax_rate_links', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('fiscal_rule_id');
        $t->unsignedInteger('tax_rate_id');
        $t->timestamps();
        $t->unique('fiscal_rule_id');
        $t->unique('tax_rate_id');
    });
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('nfe_fiscal_rule_tax_rate_links');
    Schema::dropIfExists('tax_rates');
    Schema::dropIfExists('nfe_fiscal_rules');
});

// ── helpers ───────────────────────────────────────────────────────────────

function ruleFresh(array $overrides = []): NfeFiscalRule
{
    return NfeFiscalRule::create(array_merge([
        'business_id'     => 1,
        'ncm'             => '22021000',
        'uf_origem'       => 'SP',
        'uf_destino'      => null,
        'cfop'            => '5102',
        'csosn'           => '102',
        'aliquota_icms'   => 0.18,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
        'aliquota_ipi'    => 0,
    ], $overrides));
}

// ── tests ─────────────────────────────────────────────────────────────────

it('FiscalRuleCreated event cria TaxRate auto + insere link bridge', function () {
    $rule = ruleFresh();

    (new SyncFiscalRuleToTaxRate())->handleCreated(new FiscalRuleCreated($rule));

    $tax = TaxRate::where('business_id', 1)->first();
    expect($tax)->not()->toBeNull()
        ->and($tax->name)->toBe('[NfeBrasil] NCM 22021000 SP->all')
        ->and(round($tax->amount, 4))->toBe(round(0.18 + 0.0065 + 0.03, 4))
        ->and((bool) $tax->is_tax_group)->toBeFalse();

    $link = DB::table('nfe_fiscal_rule_tax_rate_links')
        ->where('fiscal_rule_id', $rule->id)
        ->first();
    expect($link)->not()->toBeNull()
        ->and((int) $link->tax_rate_id)->toBe((int) $tax->id)
        ->and((int) $link->business_id)->toBe(1);
});

it('FiscalRuleUpdated atualiza TaxRate vinculada (amount + name)', function () {
    $rule = ruleFresh();
    (new SyncFiscalRuleToTaxRate())->handleCreated(new FiscalRuleCreated($rule));

    // Update — alíquota ICMS 18% → 22% e UF destino RJ específica
    $rule->update(['aliquota_icms' => 0.22, 'uf_destino' => 'RJ']);
    (new SyncFiscalRuleToTaxRate())->handleUpdated(new FiscalRuleUpdated($rule->fresh()));

    $tax = TaxRate::where('business_id', 1)->first();
    expect($tax->name)->toBe('[NfeBrasil] NCM 22021000 SP->RJ')
        ->and(round($tax->amount, 4))->toBe(round(0.22 + 0.0065 + 0.03, 4));

    // Apenas 1 TaxRate (não duplicou)
    expect(TaxRate::where('business_id', 1)->count())->toBe(1);
});

it('FiscalRuleDeleted remove TaxRate vinculada', function () {
    $rule = ruleFresh();
    (new SyncFiscalRuleToTaxRate())->handleCreated(new FiscalRuleCreated($rule));

    $taxRateId = (int) DB::table('nfe_fiscal_rule_tax_rate_links')
        ->where('fiscal_rule_id', $rule->id)
        ->value('tax_rate_id');

    (new SyncFiscalRuleToTaxRate())->handleDeleted(new FiscalRuleDeleted($rule->id, 1));

    expect(TaxRate::where('id', $taxRateId)->whereNull('deleted_at')->count())->toBe(0);
});

it('TaxRate manual (sem link bridge) não é afetada por FiscalRuleDeleted', function () {
    // Cria TaxRate manual avulsa
    $taxManual = TaxRate::create([
        'business_id' => 1,
        'name'        => 'ICMS Manual',
        'amount'      => 0.18,
        'is_tax_group' => false,
        'created_by'  => 1,
    ]);

    // Cria fiscal_rule + sincroniza (gera tax_rate vinculada)
    $rule = ruleFresh();
    (new SyncFiscalRuleToTaxRate())->handleCreated(new FiscalRuleCreated($rule));

    // Deleta a fiscal_rule
    (new SyncFiscalRuleToTaxRate())->handleDeleted(new FiscalRuleDeleted($rule->id, 1));

    // Manual sobrevive
    expect(TaxRate::where('id', $taxManual->id)->whereNull('deleted_at')->count())->toBe(1);
});

it('multi-tenant: TaxRate de business 1 não vaza pra business 99', function () {
    $ruleA = ruleFresh(['business_id' => 1]);
    $ruleB = ruleFresh(['business_id' => 99, 'ncm' => '49019900']);

    (new SyncFiscalRuleToTaxRate())->handleCreated(new FiscalRuleCreated($ruleA));
    (new SyncFiscalRuleToTaxRate())->handleCreated(new FiscalRuleCreated($ruleB));

    expect(TaxRate::where('business_id', 1)->count())->toBe(1);
    expect(TaxRate::where('business_id', 99)->count())->toBe(1);

    // Delete rule do biz 99 não afeta tax_rate de biz 1
    (new SyncFiscalRuleToTaxRate())->handleDeleted(new FiscalRuleDeleted($ruleB->id, 99));

    expect(TaxRate::where('business_id', 1)->count())->toBe(1);
    expect(TaxRate::where('business_id', 99)->whereNull('deleted_at')->count())->toBe(0);
});

it('listener segura defensivo: deleted sem link prévio é no-op (não explode)', function () {
    // Sem criar fiscal_rule nem link — direto delete
    $listener = new SyncFiscalRuleToTaxRate();

    expect(fn () => $listener->handleDeleted(new FiscalRuleDeleted(99999, 1)))
        ->not()->toThrow(\Throwable::class);

    expect(TaxRate::count())->toBe(0);
});

it('cargaEfetiva soma ICMS + PIS + COFINS + IPI corretamente em decimal', function () {
    $rule = ruleFresh([
        'aliquota_icms'   => 0.18,
        'aliquota_pis'    => 0.0165,   // Lucro Real
        'aliquota_cofins' => 0.076,    // Lucro Real
        'aliquota_ipi'    => 0.05,
    ]);

    (new SyncFiscalRuleToTaxRate())->handleCreated(new FiscalRuleCreated($rule));

    $tax = TaxRate::where('business_id', 1)->first();
    expect(round($tax->amount, 4))->toBe(round(0.18 + 0.0165 + 0.076 + 0.05, 4));
});

it('integração via Eloquent observer: criar NfeFiscalRule dispara event + listener cria TaxRate', function () {
    // Não chama listener manualmente — confia no boot() do model
    \Illuminate\Support\Facades\Event::listen(
        \Modules\NfeBrasil\Events\FiscalRuleCreated::class,
        [\Modules\NfeBrasil\Listeners\SyncFiscalRuleToTaxRate::class, 'handleCreated'],
    );

    ruleFresh();

    expect(TaxRate::where('business_id', 1)->count())->toBe(1);
});
