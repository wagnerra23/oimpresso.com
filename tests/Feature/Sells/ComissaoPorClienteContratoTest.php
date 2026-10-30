<?php
// @covers-us US-COMM-016

declare(strict_types=1);

use App\Business;
use App\Services\Sells\ComissaoPorCliente;
use App\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\EstoqueFixture;

/**
 * UC-SBCOM-01..04 · P-156 — comissão do representante por cliente, liberada por parcela recebida.
 *
 * Âncora: resources/js/Pages/Report/SalesRepresentative/Index.casos.md (UC-SBCOM-*), derivada do
 * pedido P-156 e da medição no Delphi do Sabor Brasil (`COMISSAO_FINANCEIRO`: comissão = parcela
 * × % da venda, base com ST).
 *
 * Números feitos à mão (fictícios), venda de 3.000,00 com ST incluído, cliente a 10%:
 *   parcela 1 · 1.200,00 · quitada            → liberada 120,00
 *   parcela 2 ·   900,00 · venceu sem pagar   → vencida   90,00
 *   parcela 3 ·   900,00 · vence no futuro    → aberta    90,00
 *   soma = 300,00 = 3.000,00 × 10% (segundo caminho)
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('transactions', 'comissao_cliente_percentual') || ! Schema::hasTable('fin_titulos')) {
        $this->markTestSkipped('Schema ausente — roda na lane MySQL (sells-pest).');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->negocio = Business::findOrFail($this->biz);
    $this->dono = EstoqueFixture::userId($this->biz);
    $this->loc = EstoqueFixture::locationId($this->biz, 'SBCOM');
    $this->hoje = Carbon::parse('2031-05-15');
    $this->rep = $this->usuarioComPermissoes(['sales_representative.view'], $this->negocio);

    $this->cliente = fn (int $biz, ?float $pct) => (int) DB::table('contacts')->insertGetId(['business_id' => $biz, 'type' => 'customer',
        'name' => 'Cliente SBCOM', 'contact_id' => 'SBCOM-' . uniqid(), 'comissao_representante_percentual' => $pct,
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now()]);
    $this->venda = fn (int $biz, int $rep, ?float $pct, float $total = 3000.0) => (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => EstoqueFixture::locationId($biz, 'SBCOM'), 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'partial', 'transaction_date' => '2031-05-02 10:00:00', 'total_before_tax' => $total,
        'final_total' => $total, 'commission_agent' => $rep, 'comissao_cliente_percentual' => $pct,
        'created_by' => EstoqueFixture::userId($biz), 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $this->parcela = fn (int $biz, int $venda, int $n, float $valor, string $venc, string $status) => DB::table('fin_titulos')->insert([
        'business_id' => $biz, 'numero' => 'SBCOM-' . strtoupper(Str::random(8)), 'tipo' => 'receber', 'status' => $status,
        'valor_total' => $valor, 'valor_aberto' => $status === 'quitado' ? 0 : $valor, 'emissao' => '2031-05-02', 'vencimento' => $venc,
        'competencia_mes' => '2031-05', 'origem' => 'venda', 'origem_id' => $venda, 'parcela_numero' => $n, 'parcela_total' => 3,
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now()]);

    $this->vendaId = ($this->venda)($this->biz, $this->rep->id, 10.0);
    ($this->parcela)($this->biz, $this->vendaId, 1, 1200.0, '2031-05-10', 'quitado');
    ($this->parcela)($this->biz, $this->vendaId, 2, 900.0, '2031-05-12', 'aberto');
    ($this->parcela)($this->biz, $this->vendaId, 3, 900.0, '2031-06-10', 'aberto');
});

it('UC-SBCOM-01 a venda nasce com o % do cliente congelado; cliente sem % fica sem regra', function () {
    $novo = function (int $contato): Transaction {
        $t = new Transaction(['business_id' => $this->biz, 'location_id' => $this->loc, 'type' => 'sell', 'status' => 'draft',
            'payment_status' => 'due', 'contact_id' => $contato, 'transaction_date' => now(), 'final_total' => 10,
            'created_by' => $this->dono]);
        $t->save();

        return $t;
    };

    $comPct = ($this->cliente)($this->biz, 7.5);
    $venda = $novo($comPct);
    DB::table('contacts')->where('id', $comPct)->update(['comissao_representante_percentual' => 15]);

    expect((float) $venda->fresh()->comissao_cliente_percentual)->toBe(7.5);
    expect($novo(($this->cliente)($this->biz, null))->fresh()->comissao_cliente_percentual)->toBeNull();
});

it('UC-SBCOM-02 cada parcela gera parcela × % da venda: quitada libera, vencida e aberta ficam pendentes', function () {
    $r = app(ComissaoPorCliente::class)->doPeriodo($this->biz, '2031-05-01', '2031-05-31', $this->hoje);

    expect($r)->toHaveCount(1);
    expect($r[0]['representante_id'])->toBe($this->rep->id);
    expect([$r[0]['liberada'], $r[0]['vencida'], $r[0]['aberta']])->toEqual([120.0, 90.0, 90.0]);
    expect(collect($r[0]['parcelas'])->pluck('situacao')->all())->toEqual(['liberada', 'vencida', 'aberta']);
    // Segundo caminho: a soma das três situações é o total da venda × % (a base é a parcela, com ST).
    expect(round($r[0]['liberada'] + $r[0]['vencida'] + $r[0]['aberta'], 2))->toBe(round(3000.0 * 10 / 100, 2));
});

it('UC-SBCOM-03 venda sem % do cliente não entra; a vencida vira liberada quando o título é quitado', function () {
    ($this->venda)($this->biz, $this->rep->id, null, 5000.0);
    DB::table('fin_titulos')->where('business_id', $this->biz)->where('origem_id', $this->vendaId)->where('parcela_numero', 2)
        ->update(['status' => 'quitado', 'valor_aberto' => 0]);

    $r = app(ComissaoPorCliente::class)->doPeriodo($this->biz, '2031-05-01', '2031-05-31', $this->hoje);

    expect($r)->toHaveCount(1);
    expect(count($r[0]['parcelas']))->toBe(3);
    expect([$r[0]['liberada'], $r[0]['vencida'], $r[0]['aberta']])->toEqual([210.0, 0.0, 90.0]);
});

it('UC-SBCOM-04 a rota exige sales_representative.view; outra empresa não entra na soma e representante de fora é 404', function () {
    $outra = EstoqueFixture::secondBusinessId();
    expect($outra)->not->toBeNull();
    $repFora = $this->usuarioComPermissoes(['sales_representative.view'], Business::findOrFail($outra));
    $vendaFora = ($this->venda)($outra, $this->rep->id, 50.0);
    ($this->parcela)($outra, $vendaFora, 1, 1000.0, '2031-05-10', 'quitado');
    $sessao = ['user.business_id' => $this->biz, 'business.id' => $this->biz];
    $url = '/reports/comissao-por-cliente?inicio=2031-05-01&fim=2031-05-31';

    $this->actingAs($this->usuarioComPermissoes([], $this->negocio))->withSession($sessao)->getJson($url)->assertForbidden();
    $resp = $this->actingAs($this->rep)->withSession($sessao)->getJson($url)->assertOk();
    expect($resp->json('representantes.0.liberada'))->toEqual(120.0);
    expect(count($resp->json('representantes.0.parcelas')))->toBe(3);
    $this->actingAs($this->rep)->withSession($sessao)->getJson($url . '&representante_id=' . $repFora->id)->assertNotFound();
});
