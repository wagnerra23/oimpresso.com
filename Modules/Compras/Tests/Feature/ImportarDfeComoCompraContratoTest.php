<?php

declare(strict_types=1);

// @covers-us US-COM-003 — a NF-e do fornecedor vira compra recebida, com o de-para lembrado.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Compras\Services\CompraDaNotaRecusadaException;
use Modules\Compras\Services\ImportarDfeComoCompraService;
use Modules\NfeBrasil\Models\NfeDfeItem;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\Entrada\LeitorNfeEntrada;
use Tests\Support\WithSeededTenant;

/**
 * Âncora: memory/requisitos/Compras/SPEC.md US-COM-003 (R-COM-201..206) + ADR
 * `proposals/2026-10-06-entrada-xml-compra.md` D3/D4. Tenant 98, vizinho 99 (ADR 0358).
 *
 * Conta à mão da nota do arranjo (regra mestre de valor, dois caminhos):
 *   item A  TS-001  10 un × 25,50 = 255,00  (ICMS 45,90 por dentro — Simples: fica no custo)
 *   item B  TS-777   2 un × 60,00 = 120,00
 *   Σ produtos 375,00 − desconto 5,00 + frete 15,00 + IPI 7,00 = vNF 392,00
 *   compra: total_before_tax 375,00 · desconto 5,00 · frete 15,00 · despesa 7,00 · final_total 392,00
 *   estoque: A 0 → 10 · B 0 → 2 · custo unitário A 25,5000 · B 60,0000
 * O 2º caminho é `compraEsperadaDaNota`, que refaz a conta a partir do XML em PHP, sem SQL.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

const CMP_CNPJ_FORN = '11222333000181'; # pii-allowlist: CNPJ fictício de fixture

function notaCompraXml(string $chave, int $vNF = 392, string $codigoA = 'TS-001'): string
{
    $f = CMP_CNPJ_FORN;

    return <<<XML
    <nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><NFe><infNFe Id="NFe{$chave}" versao="4.00">
      <ide><dhEmi>2026-10-20T10:00:00-03:00</dhEmi></ide>
      <emit><CNPJ>{$f}</CNPJ><xNome>Tintas Sul Ltda</xNome></emit><dest><CNPJ>44555666000172</CNPJ></dest>
      <det nItem="1"><prod><cProd>{$codigoA}</cProd><cEAN>SEM GTIN</cEAN><xProd>Tinta solvente 1L</xProd><NCM>32089010</NCM><CFOP>5102</CFOP>
        <uCom>UN</uCom><qCom>10.0000</qCom><vUnCom>25.5000</vUnCom><vProd>255.00</vProd></prod>
        <imposto><ICMS><ICMSSN101><orig>0</orig><CSOSN>101</CSOSN></ICMSSN101></ICMS></imposto></det>
      <det nItem="2"><prod><cProd>TS-777</cProd><cEAN>SEM GTIN</cEAN><xProd>Diluente 5L</xProd><NCM>38140000</NCM><CFOP>5102</CFOP>
        <uCom>GL</uCom><qCom>2.0000</qCom><vUnCom>60.0000</vUnCom><vProd>120.00</vProd></prod>
        <imposto><ICMS><ICMS00><orig>0</orig><CST>00</CST><vICMS>45.90</vICMS></ICMS00></ICMS></imposto></det>
      <total><ICMSTot><vProd>375.00</vProd><vDesc>5.00</vDesc><vFrete>15.00</vFrete><vSeg>0.00</vSeg>
        <vOutro>0.00</vOutro><vIPI>7.00</vIPI><vST>0.00</vST><vNF>{$vNF}.00</vNF></ICMSTot></total>
    </infNFe></NFe><protNFe><infProt><nProt>135260000012345</nProt></infProt></protNFe></nfeProc>
    XML;
}

/** 2º caminho: a compra que a nota deve gerar, refeita do XML em PHP. */
function compraEsperadaDaNota(string $xml): array
{
    $n = LeitorNfeEntrada::ler($xml);
    $t = $n['totais'];

    return [
        'total_before_tax' => round(array_sum(array_column($n['itens'], 'valor_total')), 2),
        'final_total' => round($t['produtos'] - $t['desconto'] + $t['frete'] + $t['ipi'] + $t['st'] + $t['seguro'] + $t['outras'], 2),
        'unitarios' => array_map(fn ($i) => round($i['valor_total'] / $i['quantidade'], 4), $n['itens']),
        'quantidades' => array_column($n['itens'], 'quantidade'),
    ];
}

beforeEach(function () {
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    if (! Schema::hasTable('compras_produto_fornecedor') || ! Schema::hasColumn('nfe_dfe_recebidos', 'transaction_id')) {
        $this->markTestSkipped('precisa da fase 1 da entrada por XML');
    }
    config(['nfebrasil.entrada_xml_enabled' => true]);
    Storage::fake(config('nfebrasil.dfes_recebidos_disk', 'local'));
    session(['currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$'], 'business.currency_precision' => 2]);
    DB::table('nfe_business_configs')->where('business_id', $this->biz->id)->update(['regime' => 'simples']);

    $this->user = User::factory()->create(['business_id' => $this->biz->id, 'username' => 'nfcompra_'.uniqid()]);
    $this->local = (int) DB::table('business_locations')->where('business_id', $this->biz->id)->value('id');
    $this->fornecedor = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz->id, 'type' => 'supplier', 'name' => 'Tintas Sul', 'tax_number' => '11.222.333/0001-81', # pii-allowlist: fictício
        'contact_id' => 'NFC-'.uniqid(), 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->variacao = function (int $biz, string $nome): array {
        $agora = Carbon::now();
        $sku = 'NFCOMPRA-'.uniqid();
        $unit = (int) (DB::table('units')->where('business_id', $biz)->value('id') ?? DB::table('units')->insertGetId([
            'business_id' => $biz, 'actual_name' => 'Un '.$sku, 'short_name' => 'un', 'allow_decimal' => 1,
            'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]));
        $p = DB::table('products')->insertGetId([
            'business_id' => $biz, 'name' => $nome.' '.$sku, 'type' => 'single', 'unit_id' => $unit, 'sku' => $sku,
            'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive', 'barcode_type' => 'C128',
            'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $g = DB::table('product_variations')->insertGetId(['product_id' => $p, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => $agora, 'updated_at' => $agora]);
        $v = DB::table('variations')->insertGetId([
            'product_id' => $p, 'product_variation_id' => $g, 'name' => 'DUMMY', 'sub_sku' => $sku, 'default_purchase_price' => 0,
            'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 0, 'sell_price_inc_tax' => 0, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        return [$p, $v];
    };
    [, $this->varA] = ($this->variacao)($this->biz->id, 'Tinta');
    [, $this->varB] = ($this->variacao)($this->biz->id, 'Diluente');

    $this->nota = function (int $biz, string $xml): NfeDfeRecebido {
        $n = LeitorNfeEntrada::ler($xml);
        $path = 'nfe-brasil/'.$biz.'/dfes-recebidos/'.$n['chave_44'].'.xml';
        Storage::disk(config('nfebrasil.dfes_recebidos_disk', 'local'))->put($path, $xml);
        $r = NfeDfeRecebido::withoutGlobalScopes()->create([ // SUPERADMIN: fixture grava no tenant que o teste escolhe
            'business_id' => $biz, 'chave_44' => $n['chave_44'], 'origem' => 'upload', 'cnpj_emitente' => $n['cnpj_emitente'],
            'nome_emitente' => $n['nome_emitente'], 'valor_total' => $n['valor_total'], 'data_emissao' => $n['data_emissao'],
            'xml_path' => $path, 'status_manifestacao' => 'pendente',
        ]);
        NfeDfeItem::gravarDaNota($biz, $r->id, $n['itens']);

        return $r;
    };
    $this->itens = fn (NfeDfeRecebido $r) => NfeDfeItem::withoutGlobalScopes()->where('dfe_recebido_id', $r->id)->orderBy('id')->pluck('id')->all(); // SUPERADMIN: fixture
    $this->estoque = fn (int $v) => (float) DB::table('variation_location_details')->where('variation_id', $v)->where('location_id', $this->local)->value('qty_available');
    $this->svc = app(ImportarDfeComoCompraService::class);
});

it('UC-NFCOM-01 · a nota vira compra recebida com o total da nota, o estoque entra e o de-para fica lembrado', function () {
    $xml = notaCompraXml('42261011222333000181550010000012341000000001');
    $dfe = ($this->nota)($this->biz->id, $xml);
    [$a, $b] = ($this->itens)($dfe);
    $esperado = compraEsperadaDaNota($xml);

    $compra = $this->svc->executar($this->biz->id, $dfe->id, $this->user->id, $this->local, [$a => $this->varA, $b => $this->varB]);

    // 1º caminho: a conta à mão do docblock · 2º caminho: compraEsperadaDaNota (do XML).
    expect((float) $compra->final_total)->toBe(392.0)->toBe($esperado['final_total']);
    expect((float) $compra->total_before_tax)->toBe(375.0)->toBe($esperado['total_before_tax']);
    expect([(float) $compra->discount_amount, (float) $compra->shipping_charges, (float) $compra->additional_expense_value_1])->toBe([5.0, 15.0, 7.0]);
    expect([$compra->type, $compra->status, (int) $compra->contact_id])->toBe(['purchase', 'received', $this->fornecedor]);
    $linhas = DB::table('purchase_lines')->where('transaction_id', $compra->id)->orderBy('id')->get();
    expect($linhas->map(fn ($l) => round((float) $l->purchase_price, 4))->all())->toBe([25.5, 60.0])->toBe($esperado['unitarios']);
    expect($linhas->map(fn ($l) => (float) $l->quantity)->all())->toBe([10.0, 2.0])->toBe($esperado['quantidades']);
    expect([($this->estoque)($this->varA), ($this->estoque)($this->varB)])->toBe([10.0, 2.0]);
    expect((int) NfeDfeRecebido::withoutGlobalScopes()->find($dfe->id)->transaction_id)->toBe($compra->id); // SUPERADMIN: leitura do fixture
    expect(DB::table('compras_produto_fornecedor')->where('business_id', $this->biz->id)->where('codigo_fornecedor', 'TS-001')->value('variation_id'))->toEqual($this->varA);
});

it('UC-NFCOM-02 · a próxima nota do mesmo fornecedor já vem vinculada', function () {
    $d1 = ($this->nota)($this->biz->id, notaCompraXml('42261011222333000181550010000012341000000002'));
    [$a, $b] = ($this->itens)($d1);
    $this->svc->executar($this->biz->id, $d1->id, $this->user->id, $this->local, [$a => $this->varA, $b => $this->varB]);

    $d2 = ($this->nota)($this->biz->id, notaCompraXml('42261011222333000181550010000012341000000003'));
    $c2 = $this->svc->executar($this->biz->id, $d2->id, $this->user->id, $this->local);

    expect(DB::table('purchase_lines')->where('transaction_id', $c2->id)->orderBy('id')->pluck('variation_id')->map(fn ($v) => (int) $v)->all())->toBe([$this->varA, $this->varB]);
    expect(($this->estoque)($this->varA))->toBe(20.0);
});

it('UC-NFCOM-03 · item sem vínculo não vira compra e nada é gravado', function () {
    $dfe = ($this->nota)($this->biz->id, notaCompraXml('42261011222333000181550010000012341000000004', 392, 'NOVO-9'));
    [, $b] = ($this->itens)($dfe);

    expect(fn () => $this->svc->executar($this->biz->id, $dfe->id, $this->user->id, $this->local, [$b => $this->varB]))
        ->toThrow(CompraDaNotaRecusadaException::class, 'Vincule estes itens a um produto antes: Tinta solvente 1L');
    expect(NfeDfeRecebido::withoutGlobalScopes()->find($dfe->id)->transaction_id)->toBeNull(); // SUPERADMIN: leitura do fixture
    expect(($this->estoque)($this->varB))->toBe(0.0);
});

it('UC-NFCOM-04 · variação e nota de outra empresa são recusadas (Tier 0)', function () {
    [, $varOutra] = ($this->variacao)($this->outra->id, 'Alheio');
    $dfe = ($this->nota)($this->biz->id, notaCompraXml('42261011222333000181550010000012341000000005'));
    [$a, $b] = ($this->itens)($dfe);
    expect(fn () => $this->svc->executar($this->biz->id, $dfe->id, $this->user->id, $this->local, [$a => $varOutra, $b => $this->varB]))
        ->toThrow(CompraDaNotaRecusadaException::class, 'Vincule estes itens');

    $alheia = ($this->nota)($this->outra->id, notaCompraXml('42261011222333000181550010000012341000000006'));
    expect(fn () => $this->svc->executar($this->biz->id, $alheia->id, $this->user->id, $this->local))
        ->toThrow(CompraDaNotaRecusadaException::class, 'Nota não encontrada nesta empresa.');
});

it('UC-NFCOM-05 · a mesma nota não vira duas compras', function () {
    $dfe = ($this->nota)($this->biz->id, notaCompraXml('42261011222333000181550010000012341000000007'));
    [$a, $b] = ($this->itens)($dfe);
    $c = $this->svc->executar($this->biz->id, $dfe->id, $this->user->id, $this->local, [$a => $this->varA, $b => $this->varB]);

    expect(fn () => $this->svc->executar($this->biz->id, $dfe->id, $this->user->id, $this->local))
        ->toThrow(CompraDaNotaRecusadaException::class, 'já virou a compra #'.$c->id);
    expect(($this->estoque)($this->varA))->toBe(10.0);
});

it('UC-NFCOM-06 · totais que não fecham com os itens são recusados', function () {
    $dfe = ($this->nota)($this->biz->id, notaCompraXml('42261011222333000181550010000012341000000008', 999));
    [$a, $b] = ($this->itens)($dfe);
    expect(fn () => $this->svc->executar($this->biz->id, $dfe->id, $this->user->id, $this->local, [$a => $this->varA, $b => $this->varB]))
        ->toThrow(CompraDaNotaRecusadaException::class, 'não fecham');
});

it('UC-NFCOM-07 · regime normal e flag desligada são recusados', function () {
    $dfe = ($this->nota)($this->biz->id, notaCompraXml('42261011222333000181550010000012341000000009'));
    if (DB::table('nfe_business_configs')->where('business_id', $this->biz->id)->exists()) {
        DB::table('nfe_business_configs')->where('business_id', $this->biz->id)->update(['regime' => 'lucro_real']);
        expect(fn () => $this->svc->executar($this->biz->id, $dfe->id, $this->user->id, $this->local))
            ->toThrow(CompraDaNotaRecusadaException::class, 'regime normal');
    }
    config(['nfebrasil.entrada_xml_enabled' => false]);
    expect(fn () => $this->svc->executar($this->biz->id, $dfe->id, $this->user->id, $this->local))
        ->toThrow(CompraDaNotaRecusadaException::class, 'desligada');
});
