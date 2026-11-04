<?php

declare(strict_types=1);

// @covers-us US-PG-008 — boleto pago no banco baixa o título migrado do Delphi (Sabor Brasil).
// Contrato da tela: resources/js/Pages/Financeiro/Unificado/Index.casos.md — UC-FBOL-01..06

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Services\BaixaDeBoletoMigrado;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Derivado do pedido, não do código: "o Ricardo esquece de baixar alguns boletos; o financeiro não
 * pode ficar desorganizado". Boleto pago no banco tem de baixar o título que já existe (veio do
 * Delphi com o nosso número) e nunca criar um segundo; o que não casa com certeza vai para
 * conferência, sem baixa.
 *
 * REGRA MESTRE valor: convenção da baixa = valor_baixa é o principal; o que o cliente pagou é
 * valor_baixa + juros − desconto. Cada caso confere à mão E pela soma gravada.
 *
 * MySQL · tenant 98 (ADR 0358), adversário = outro business semeado. DatabaseTransactions.
 */

function bolBiz(): int { return test()->seededTenant()->id; }

function bolConta(int $biz): int
{
    $acc = DB::table('accounts')->insertGetId([
        'business_id' => $biz, 'name' => 'Inter BOL', 'account_number' => 'BOL-' . uniqid(),
        'created_by' => DB::table('business')->where('id', $biz)->value('owner_id'), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('fin_contas_bancarias')->insertGetId([
        'business_id' => $biz, 'account_id' => $acc, 'banco_codigo' => '077', 'agencia' => '0001', 'carteira' => '112',
        'beneficiario_documento' => 'DOC-TESTE-BOL', 'beneficiario_razao_social' => 'BOL', 'ativo_para_boleto' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function bolTitulo(int $biz, float $valor, ?string $nosso, ?string $banco, string $legacy = ''): int
{
    return (int) DB::table('fin_titulos')->insertGetId([
        'business_id' => $biz, 'numero' => 'BOL-' . substr(uniqid(), -8), 'legacy_id' => $legacy ?: null,
        'tipo' => 'receber', 'status' => 'aberto', 'valor_total' => $valor, 'valor_aberto' => $valor,
        'emissao' => '2026-05-01', 'vencimento' => '2026-06-01', 'competencia_mes' => '2026-06', 'origem' => 'manual',
        'metadata' => json_encode(['boleto_nosso_numero' => $nosso, 'boleto_banco' => $banco]),
        'created_by' => DB::table('business')->where('id', $biz)->value('owner_id'), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function bolRec(string $ref, string $seu, float $nominal, float $pago): array
{
    return ['ref' => $ref, 'seu_numero' => $seu, 'nosso_numero' => '', 'banco' => '077', 'valor_nominal' => $nominal,
        'valor_pago' => $pago, 'vencimento' => '2026-06-01', 'pago_em' => '2026-06-05', 'pagador_doc' => null];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasColumn('fin_titulos', 'legacy_id')) {
        $this->markTestSkipped('Schema Financeiro MySQL ausente — roda na lane paymentgateway-pest.');
    }
    $this->biz = bolBiz();
    $this->conta = bolConta($this->biz);
    $this->svc = app(BaixaDeBoletoMigrado::class);
});

it('UC-FBOL-01 [V0] boleto pago no valor certo quita o título migrado, sem criar outro', function () {
    $id = bolTitulo($this->biz, 100.00, '0052', '077');
    $antes = DB::table('fin_titulos')->where('business_id', $this->biz)->count();

    $r = $this->svc->baixar($this->biz, bolRec('INT-A', '52', 100.00, 100.00), $this->conta);

    expect($r['resultado'])->toBe(BaixaDeBoletoMigrado::BAIXADO)->and($r['titulo_id'])->toBe($id);
    $t = DB::table('fin_titulos')->find($id);
    expect($t->status)->toBe('quitado')->and((float) $t->valor_aberto)->toBe(0.0);
    expect(DB::table('fin_titulos')->where('business_id', $this->biz)->count())->toBe($antes);
    $b = DB::table('fin_titulo_baixas')->where('titulo_id', $id)->first();
    // À mão: pagou 100,00 = 100,00 + 0 − 0. Pela soma gravada: valor_baixa + juros − desconto.
    expect((float) $b->valor_baixa + (float) $b->juros - (float) $b->desconto)->toBe(100.0);
    expect((int) $b->conta_bancaria_id)->toBe($this->conta);
});

it('UC-FBOL-02 [V0] pago com juros: principal 100,00 e juros 3,50', function () {
    $id = bolTitulo($this->biz, 100.00, '53', '077');

    $r = $this->svc->baixar($this->biz, bolRec('INT-B', '53', 100.00, 103.50), $this->conta);

    expect($r['resultado'])->toBe(BaixaDeBoletoMigrado::BAIXADO);
    $b = DB::table('fin_titulo_baixas')->where('titulo_id', $id)->first();
    expect((float) $b->valor_baixa)->toBe(100.0)->and((float) $b->juros)->toBe(3.5)->and((float) $b->desconto)->toBe(0.0);
    expect((float) $b->valor_baixa + (float) $b->juros - (float) $b->desconto)->toBe(103.5);
});

it('UC-FBOL-03 [V0] pago com desconto até 10% baixa; acima disso vai para conferência', function () {
    $ok = bolTitulo($this->biz, 100.00, '54', '077');
    $r = $this->svc->baixar($this->biz, bolRec('INT-C', '54', 100.00, 95.00), $this->conta);
    // À mão: 100,00 − 5% = 95,00 = 100,00 + 0 − 5,00.
    expect($r['resultado'])->toBe(BaixaDeBoletoMigrado::BAIXADO)->and($r['desconto'])->toBe(5.0);
    $b = DB::table('fin_titulo_baixas')->where('titulo_id', $ok)->first();
    expect((float) $b->valor_baixa + (float) $b->juros - (float) $b->desconto)->toBe(95.0);

    $div = bolTitulo($this->biz, 100.00, '55', '077');
    $r2 = $this->svc->baixar($this->biz, bolRec('INT-D', '55', 100.00, 85.00), $this->conta);
    expect($r2['resultado'])->toBe(BaixaDeBoletoMigrado::DIVERGENTE);
    expect(DB::table('fin_titulos')->find($div)->status)->toBe('aberto');
    expect(DB::table('fin_titulo_baixas')->where('titulo_id', $div)->exists())->toBeFalse();
});

it('UC-FBOL-04 nosso número de outro banco ou com outro valor não casa', function () {
    $bb = bolTitulo($this->biz, 100.00, '56', '001');
    $outroValor = bolTitulo($this->biz, 250.00, '57', '077');

    expect($this->svc->baixar($this->biz, bolRec('INT-E', '56', 100.00, 100.00), $this->conta)['resultado'])->toBe(BaixaDeBoletoMigrado::NENHUM);
    expect($this->svc->baixar($this->biz, bolRec('INT-F', '57', 100.00, 100.00), $this->conta)['resultado'])->toBe(BaixaDeBoletoMigrado::NENHUM);
    expect(DB::table('fin_titulos')->whereIn('id', [$bb, $outroValor])->pluck('status')->unique()->all())->toBe(['aberto']);
});

it('UC-FBOL-05 dois títulos possíveis: ambíguo, não baixa nenhum', function () {
    $a = bolTitulo($this->biz, 100.00, '58', null);
    $b = bolTitulo($this->biz, 100.00, '58', '077');

    $r = $this->svc->baixar($this->biz, bolRec('INT-G', '58', 100.00, 100.00), $this->conta);

    expect($r['resultado'])->toBe(BaixaDeBoletoMigrado::AMBIGUO);
    expect(DB::table('fin_titulos')->whereIn('id', [$a, $b])->pluck('status')->unique()->all())->toBe(['aberto']);
});

it('UC-FBOL-06 [T0] título de outra empresa não é baixado', function () {
    $outro = (int) DB::table('business')->where('id', '!=', $this->biz)->orderBy('id')->value('id');
    $alheio = bolTitulo($outro, 100.00, '59', '077');
    // Controle positivo: o da própria empresa, com a mesma referência, baixa.
    $proprio = bolTitulo($this->biz, 100.00, '59', '077');

    $r = $this->svc->baixar($this->biz, bolRec('INT-H', '59', 100.00, 100.00), $this->conta);

    expect($r['titulo_id'])->toBe($proprio);
    expect(DB::table('fin_titulos')->find($alheio)->status)->toBe('aberto');
});
