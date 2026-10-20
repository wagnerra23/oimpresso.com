<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\CustoDoInsumoService;
use Tests\Support\WithSeededTenant;

/**
 * US-COM-024 · UC-FLX-38 — custo do insumo numa data, lido de `purchase_lines`.
 *
 * Âncora de contrato: memory/requisitos/Compras/SPEC.md US-COM-024 (critérios abaixo citados
 * pelo nome do caso). Os valores esperados NÃO vêm do serviço: vêm da conta feita à mão sobre
 * as compras do arranjo, e cada caso refaz a mesma conta por um segundo caminho
 * (custoInsumoEsperado, em PHP, sem SQL) antes de comparar. Regra mestre de valor
 * (proibicoes.md): dois caminhos independentes, números concretos.
 *
 * Arranjo — variação V do tenant 98:
 *   A  2026-02-01  opening_stock   100 un × 1,00   fora (não é compra)
 *   B  2026-03-01  compra recebida  10 un × 12,00
 *   C  2026-03-10  compra recebida  30 un × 16,00, 10 devolvidas → líquido 20
 *   D  2026-03-20  compra pedida     5 un × 30,00  fora (não recebida)
 *   E  2026-04-05 18:00  recebida   10 un × 20,00
 * e uma compra do tenant 99 na MESMA variação (1000 un × 1,00), que nunca pode entrar.
 *
 * Em 31/03: médio = (10×12 + 20×16) / 30 = 440 / 30 = 14,6667 · último = 16,0000 (C)
 * Em 05/04: médio = (120 + 320 + 200) / 40 = 16,0000 · último = 20,0000 (E, às 18h)
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage().' — rode no MySQL real (lane compras-pest ou CT 100).');
    }

    $this->user = User::factory()->create(['business_id' => $this->biz->id, 'username' => 'custo_insumo_'.uniqid()]);
    $sku = 'CUSTOINS-'.uniqid();
    $agora = Carbon::now();

    $unitId = (int) (DB::table('units')->where('business_id', $this->biz->id)->value('id')
        ?? DB::table('units')->insertGetId([
            'business_id' => $this->biz->id, 'actual_name' => 'Unidade '.$sku, 'short_name' => 'un',
            'allow_decimal' => 0, 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]));
    $productId = DB::table('products')->insertGetId([
        'business_id' => $this->biz->id, 'name' => 'Lona '.$sku, 'type' => 'single', 'unit_id' => $unitId,
        'sku' => $sku, 'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive',
        'barcode_type' => 'C128', 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $grupo = DB::table('product_variations')->insertGetId([
        'product_id' => $productId, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    // dpp_inc_tax de propósito diferente de tudo: se o serviço o lesse, nenhum caso fecharia.
    $this->variationId = DB::table('variations')->insertGetId([
        'product_id' => $productId, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
        'default_purchase_price' => 99, 'dpp_inc_tax' => 99, 'profit_percent' => 0,
        'default_sell_price' => 150, 'sell_price_inc_tax' => 150, 'created_at' => $agora, 'updated_at' => $agora,
    ]);

    $this->compras = [
        ['biz' => $this->biz->id, 'tipo' => 'opening_stock', 'status' => 'received', 'data' => '2026-02-01 09:00:00', 'qtd' => 100, 'dev' => 0, 'preco' => 1],
        ['biz' => $this->biz->id, 'tipo' => 'purchase', 'status' => 'received', 'data' => '2026-03-01 09:00:00', 'qtd' => 10, 'dev' => 0, 'preco' => 12],
        ['biz' => $this->biz->id, 'tipo' => 'purchase', 'status' => 'received', 'data' => '2026-03-10 09:00:00', 'qtd' => 30, 'dev' => 10, 'preco' => 16],
        ['biz' => $this->biz->id, 'tipo' => 'purchase', 'status' => 'ordered', 'data' => '2026-03-20 09:00:00', 'qtd' => 5, 'dev' => 0, 'preco' => 30],
        ['biz' => $this->biz->id, 'tipo' => 'purchase', 'status' => 'received', 'data' => '2026-04-05 18:00:00', 'qtd' => 10, 'dev' => 0, 'preco' => 20],
        ['biz' => $this->outra->id, 'tipo' => 'purchase', 'status' => 'received', 'data' => '2026-03-05 09:00:00', 'qtd' => 1000, 'dev' => 0, 'preco' => 1],
    ];
    foreach ($this->compras as $c) {
        $tid = DB::table('transactions')->insertGetId([
            'business_id' => $c['biz'], 'type' => $c['tipo'], 'status' => $c['status'], 'payment_status' => 'due',
            'transaction_date' => $c['data'], 'ref_no' => 'CUSTOINS-'.uniqid(), 'final_total' => $c['qtd'] * $c['preco'],
            'total_before_tax' => $c['qtd'] * $c['preco'], 'tax_amount' => 0, 'discount_amount' => 0,
            'created_by' => $this->user->id, 'essentials_duration' => 0, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $tid, 'product_id' => $productId, 'variation_id' => $this->variationId,
            'quantity' => $c['qtd'], 'quantity_returned' => $c['dev'], 'pp_without_discount' => $c['preco'],
            'purchase_price' => $c['preco'], 'purchase_price_inc_tax' => $c['preco'], 'item_tax' => 0,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
    }

    $this->servico = app(CustoDoInsumoService::class);
});

/** Segundo caminho: a mesma regra do serviço, refeita em PHP sobre o arranjo, sem SQL. */
function custoInsumoEsperado(array $compras, int $biz, string $data, string $metodo): ?string
{
    $ate = $data.' 23:59:59';
    $validas = array_values(array_filter($compras, fn ($c) => $c['biz'] === $biz && $c['tipo'] === 'purchase'
        && $c['status'] === 'received' && $c['data'] <= $ate && $c['qtd'] - $c['dev'] > 0));
    if ($validas === []) {
        return null;
    }
    if ($metodo === 'ultimo') {
        usort($validas, fn ($a, $b) => strcmp($b['data'], $a['data']));

        return number_format($validas[0]['preco'], 4, '.', '');
    }
    $valor = array_sum(array_map(fn ($c) => $c['preco'] * ($c['qtd'] - $c['dev']), $validas));
    $qtd = array_sum(array_map(fn ($c) => $c['qtd'] - $c['dev'], $validas));

    return number_format(round($valor / $qtd, 4), 4, '.', '');
}

it('US-COM-024 · médio ponderado até a data usa a quantidade líquida e ignora o que não é compra recebida', function () {
    expect(custoInsumoEsperado($this->compras, $this->biz->id, '2026-03-31', 'medio'))->toBe('14.6667');

    $r = $this->servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'medio');

    expect($r['custo'])->toBe('14.6667')
        ->and($r['compras'])->toBe(2)
        ->and($r['ultima_compra_em'])->toBe('2026-03-10')
        ->and($r['metodo'])->toBe('medio');
});

it('US-COM-024 · último preço até a data é o da compra recebida mais recente', function () {
    expect(custoInsumoEsperado($this->compras, $this->biz->id, '2026-03-31', 'ultimo'))->toBe('16.0000');

    expect($this->servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'ultimo')['custo'])->toBe('16.0000');
});

it('US-COM-024 · a compra do próprio dia entra, qualquer que seja a hora', function () {
    expect(custoInsumoEsperado($this->compras, $this->biz->id, '2026-04-05', 'medio'))->toBe('16.0000')
        ->and(custoInsumoEsperado($this->compras, $this->biz->id, '2026-04-05', 'ultimo'))->toBe('20.0000');

    expect($this->servico->naData($this->biz->id, $this->variationId, '2026-04-05', 'medio')['custo'])->toBe('16.0000')
        ->and($this->servico->naData($this->biz->id, $this->variationId, '2026-04-05', 'ultimo')['custo'])->toBe('20.0000');
});

it('US-COM-024 · compra nova não muda o custo de uma data anterior', function () {
    $antes = $this->servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'medio')['custo'];
    $depois = $this->servico->naData($this->biz->id, $this->variationId, '2026-04-30', 'medio')['custo'];

    expect($antes)->toBe('14.6667')->and($depois)->toBe('16.0000');
});

it('US-COM-024 · sem compra recebida até a data devolve custo nulo com o motivo', function () {
    $r = $this->servico->naData($this->biz->id, $this->variationId, '2026-02-15', 'medio');

    expect($r['custo'])->toBeNull()
        ->and($r['compras'])->toBe(0)
        ->and($r['motivo'])->toBe('Nenhuma compra recebida até a data.');
});

it('US-COM-024 · Tier 0: a compra de outra empresa na mesma variação nunca entra na conta', function () {
    // Sem o filtro por empresa, o médio de 31/03 cairia para (440 + 1000) / 1030 = 1,3981.
    expect($this->servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'medio')['custo'])->toBe('14.6667');

    $daOutra = $this->servico->naData($this->outra->id, $this->variationId, '2026-03-31', 'medio');
    expect($daOutra['custo'])->toBe('1.0000')->and($daOutra['compras'])->toBe(1);
});

it('US-COM-024 · sem regra gravada, o método da empresa é o médio', function () {
    // Apaga só dentro da transação do teste (DatabaseTransactions desfaz no fim).
    DB::table('fsm_modelos_empresa')->where('business_id', $this->biz->id)->delete();

    expect($this->servico->metodoDaEmpresa($this->biz->id))->toBe('medio');

    $r = $this->servico->naData($this->biz->id, $this->variationId, '2026-03-31');
    expect($r['metodo'])->toBe('medio')->and($r['custo'])->toBe('14.6667');
});

/** Grava o método próprio do insumo na tabela-ponte (some no fim do teste, DatabaseTransactions). */
function metodoProprioDoInsumo(int $biz, int $variationId, string $metodo): void
{
    DB::table('compras_insumo_metodo_custo')->insert([
        'business_id' => $biz, 'variation_id' => $variationId, 'metodo' => $metodo,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('US-COM-024 · por insumo: o insumo marcado médio custa o médio ponderado', function () {
    // À mão: (10×12 + 20×16) / 30 = 14,6667. Segundo caminho: custoInsumoEsperado.
    expect(custoInsumoEsperado($this->compras, $this->biz->id, '2026-03-31', 'medio'))->toBe('14.6667');
    metodoProprioDoInsumo($this->biz->id, $this->variationId, 'medio');

    $r = $this->servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'por_insumo');

    expect($r['custo'])->toBe('14.6667')->and($r['metodo'])->toBe('medio')->and($r['motivo'])->toBeNull();
});

it('US-COM-024 · por insumo: o insumo marcado último custa o preço da compra mais recente', function () {
    // À mão: compra C (10/03), 16,00. O médio daria 14,6667 — o teste separa os dois.
    expect(custoInsumoEsperado($this->compras, $this->biz->id, '2026-03-31', 'ultimo'))->toBe('16.0000');
    metodoProprioDoInsumo($this->biz->id, $this->variationId, 'ultimo');

    $r = $this->servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'por_insumo');

    expect($r['custo'])->toBe('16.0000')->and($r['metodo'])->toBe('ultimo');
});

it('US-COM-024 · por insumo sem método definido devolve nulo com o motivo', function () {
    $r = $this->servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'por_insumo');

    expect($r['custo'])->toBeNull()
        ->and($r['metodo'])->toBe('por_insumo')
        ->and($r['motivo'])->toBe('O insumo não tem método definido.');
});

it('US-COM-024 · Tier 0: o método que outra empresa deu ao insumo não vale para esta', function () {
    metodoProprioDoInsumo($this->outra->id, $this->variationId, 'ultimo');

    $r = $this->servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'por_insumo');
    expect($r['custo'])->toBeNull()->and($r['motivo'])->toBe('O insumo não tem método definido.');

    // A própria outra empresa lê a sua linha: a única compra dela é 1000 un × 1,00.
    $daOutra = $this->servico->naData($this->outra->id, $this->variationId, '2026-03-31', 'por_insumo');
    expect($daOutra['custo'])->toBe('1.0000')->and($daOutra['metodo'])->toBe('ultimo');
});

it('US-COM-024 · empresa com método médio ignora o método próprio do insumo', function () {
    // A linha diria "último" (16,0000); a empresa sem regra gravada usa médio (14,6667).
    metodoProprioDoInsumo($this->biz->id, $this->variationId, 'ultimo');
    DB::table('fsm_modelos_empresa')->where('business_id', $this->biz->id)->delete();

    $r = $this->servico->naData($this->biz->id, $this->variationId, '2026-03-31');
    expect($r['metodo'])->toBe('medio')->and($r['custo'])->toBe('14.6667');

    expect($this->servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'ultimo')['custo'])->toBe('16.0000');
});

it('US-COM-024 · método desconhecido é recusado', function () {
    $this->servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'fifo');
})->throws(InvalidArgumentException::class);
