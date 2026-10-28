<?php
// @covers-us US-CRM-098

declare(strict_types=1);

use App\Contracts\Crm\SegmentacaoRfm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Services\Fidelidade\SegmentacaoRfmDoCrm;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-RFM-01..05 · US-CRM-098 — segmentação RFM por cliente. Âncora: SPEC Crm US-CRM-098.
 *
 * Contas à mão:
 *   quintis [10,20,30,40,50]  → notas 1,2,3,4,5 (quantos piores: 0..4; 1 + ⌊5×piores/5⌋)
 *   quintis [10,10,30]        → 10 → 1 (ninguém pior) · 30 → 1 + ⌊5×2/3⌋ = 4 (empate não separa)
 *   cliente com vendas há 10 dias (100,00), 100 dias (50,50) e 400 dias (999,00)
 *     → R = 10 dias · F = 2 no ano · M = 150,50 (a de 400 dias fica fora do ano)
 *
 * Tenant 98 (fictício, ADR 0358); 99 é a outra empresa. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    foreach (['transactions', 'contacts', 'crm_rfm_clientes'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente (roda na lane MySQL).");
        }
    }
    $this->biz = (int) $this->seededTenant()->id;
    $this->outro = (int) $this->seededSupportClientTenant()->id;
    $this->uid = (int) $this->usuarioComPermissoes([], $this->seededTenant())->id;
    $this->cliente = fn (int $biz, bool $padrao = false) => (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente RFM Teste', 'contact_id' => 'RFM-' . uniqid(),
        'is_default' => $padrao ? 1 : 0, 'created_by' => $this->uid, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->venda = fn (int $biz, int $cliente, int $diasAtras, string $total) => DB::table('transactions')->insert([
        'business_id' => $biz, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid', 'contact_id' => $cliente,
        'invoice_no' => 'RFM-' . uniqid(), 'transaction_date' => now()->subDays($diasAtras)->setTime(15, 0),
        'final_total' => $total, 'total_before_tax' => $total, 'tax_amount' => 0, 'discount_amount' => 0,
        'created_by' => $this->uid, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

it('UC-RFM-01 nota por quintil dentro da empresa, com empate na mesma nota', function () {
    expect(SegmentacaoRfmDoCrm::quintis(['a' => 10, 'b' => 20, 'c' => 30, 'd' => 40, 'e' => 50], 5))
        ->toBe(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]);
    expect(SegmentacaoRfmDoCrm::quintis(['a' => 10, 'b' => 10, 'c' => 30], 3))
        ->toBe(['a' => 1, 'b' => 1, 'c' => 4]);
});

it('UC-RFM-02 o segmento sai da recência e da média de frequência e valor', function () {
    expect(SegmentacaoRfmDoCrm::segmento(5, 5, 9))->toBe('campeoes');
    expect(SegmentacaoRfmDoCrm::segmento(5, 2, 1))->toBe('novos');
    expect(SegmentacaoRfmDoCrm::segmento(3, 3, 4))->toBe('leais');
    expect(SegmentacaoRfmDoCrm::segmento(4, 2, 2))->toBe('promissores');
    expect(SegmentacaoRfmDoCrm::segmento(2, 4, 6))->toBe('em_risco');
    expect(SegmentacaoRfmDoCrm::segmento(2, 1, 1))->toBe('hibernando');
    expect(SegmentacaoRfmDoCrm::segmento(1, 2, 0))->toBe('perdidos');
});

it('UC-RFM-03 o cálculo mede recência, frequência e valor no ano, só da empresa e sem o Cliente padrão', function () {
    $c = ($this->cliente)($this->biz);
    ($this->venda)($this->biz, $c, 10, '100.00');
    ($this->venda)($this->biz, $c, 100, '50.50');
    ($this->venda)($this->biz, $c, 400, '999.00');
    $padrao = ($this->cliente)($this->biz, true);
    ($this->venda)($this->biz, $padrao, 1, '10.00');
    $deFora = ($this->cliente)($this->outro);
    ($this->venda)($this->outro, $deFora, 1, '10.00');

    app(SegmentacaoRfmDoCrm::class)->calcular($this->biz, CarbonImmutable::now());

    $linha = DB::table('crm_rfm_clientes')->where('business_id', $this->biz)->where('contact_id', $c)->first();
    expect((int) $linha->recencia_dias)->toBe(10);
    expect((int) $linha->frequencia)->toBe(2);
    expect((string) $linha->valor)->toBe('150.5000');
    expect(array_keys(SegmentacaoRfmDoCrm::SEGMENTOS))->toContain($linha->segmento);
    expect(DB::table('crm_rfm_clientes')->whereIn('contact_id', [$padrao, $deFora])->count())->toBe(0);
});

it('UC-RFM-04 o contrato devolve só contatos da empresa pedida', function () {
    $nosso = ($this->cliente)($this->biz);
    $deFora = ($this->cliente)($this->outro);
    foreach ([[$this->biz, $nosso], [$this->outro, $deFora]] as [$biz, $contato]) {
        DB::table('crm_rfm_clientes')->insert([
            'business_id' => $biz, 'contact_id' => $contato, 'recencia_dias' => 5, 'frequencia' => 9, 'valor' => '900.0000',
            'nota_r' => 5, 'nota_f' => 5, 'nota_m' => 5, 'segmento' => 'campeoes', 'calculado_em' => now(),
        ]);
    }

    $ids = app(SegmentacaoRfm::class)->contatosDoSegmento($this->biz, 'campeoes');

    expect($ids)->toContain($nosso);
    expect($ids)->not->toContain($deFora);
    expect(app(SegmentacaoRfm::class)->segmentos())->toHaveKey('em_risco');
});

it('UC-RFM-05 o comando recalcula a empresa e substitui o cálculo anterior', function () {
    $c = ($this->cliente)($this->biz);
    ($this->venda)($this->biz, $c, 3, '80.00');
    DB::table('crm_rfm_clientes')->insert([
        'business_id' => $this->biz, 'contact_id' => $c, 'recencia_dias' => 999, 'frequencia' => 0, 'valor' => '0',
        'nota_r' => 1, 'nota_f' => 1, 'nota_m' => 1, 'segmento' => 'perdidos', 'calculado_em' => now()->subDay(),
    ]);

    expect(Artisan::call('crm:rfm-calcular', ['--business' => $this->biz]))->toBe(0);

    $linhas = DB::table('crm_rfm_clientes')->where('business_id', $this->biz)->where('contact_id', $c)->get();
    expect($linhas)->toHaveCount(1);
    expect((int) $linhas->first()->recencia_dias)->toBe(3);
});
