<?php
// @covers-us US-COM-025

declare(strict_types=1);
// Cobre UC-RLT-04..05 (resources/js/Pages/Relatorios/Lotes/Index.casos.md) e UC-PCMAT-08
// (Producao/Apontamento/Index.casos.md) — UC-FLX-18 do catálogo de fluxos.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\ConsumoMaterialService;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\RastreioDoRolo;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-COM-025 · UC-FLX-18 — rolo fracionado: em que OS cada fração do lote foi usada, o saldo do rolo, e a
 * baixa da OS saindo do rolo escolhido. ESTOQUE (regra mestre): cada número é feito à mão sobre o arranjo e
 * conferido por um segundo caminho (SQL direto no vínculo lote × baixa e nas colunas da linha da compra).
 *
 * Arranjo (o mesmo do ProducaoMaterialContratoTest), tenant 98, FIFO, sem vender sem estoque:
 *   local da venda (L) · lote A 01/03 · 2 un × 10,00 · lote B 10/03 · 5 un × 14,00 · saldo em L = 10
 *   outro local (M)    · lote C 05/03 · 100 un × 1,00
 * Tenant 99 é o adversário (lote de outra empresa). ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_consumos')) {
        $this->markTestSkipped('pcp_consumos ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->freezeTime();
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    DB::table('business')->where('id', $this->b98)->update(['accounting_method' => 'fifo', 'pos_settings' => json_encode(['allow_overselling' => 0])]);
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98);
    $this->op1 = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->op2 = $this->usuarioComPermissoes(['producao.access'], $this->B98);

    $local = function (int $biz): int {
        $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $biz, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
        $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $biz, 'name' => 'Lay '.uniqid()]);

        return DB::table('business_locations')->insertGetId(['business_id' => $biz, 'name' => 'Loja '.uniqid(), 'country' => 'Brasil',
            'state' => 'SC', 'city' => 'Tubarão', 'zip_code' => '8870000', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1]);
    };
    $this->localL = $local($this->b98);
    $this->localM = $local($this->b98);

    $produto = function (int $biz, string $nome) {
        $unit = (int) (DB::table('units')->where('business_id', $biz)->value('id') ?? DB::table('units')->insertGetId([
            'business_id' => $biz, 'actual_name' => 'Unidade', 'short_name' => 'un', 'allow_decimal' => 1, 'created_by' => $this->op1->id,
            'created_at' => now(), 'updated_at' => now()]));
        $sku = 'MAT-'.uniqid();
        $pid = DB::table('products')->insertGetId(['business_id' => $biz, 'name' => $nome.' '.$sku, 'type' => 'single', 'unit_id' => $unit,
            'sku' => $sku, 'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive', 'barcode_type' => 'C128',
            'created_by' => $this->op1->id, 'created_at' => now(), 'updated_at' => now()]);
        $grupo = DB::table('product_variations')->insertGetId(['product_id' => $pid, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
        // dpp 99,00 de propósito: se o custo viesse do preço do dia (como no Manufacturing), daria 297,00.
        $vid = DB::table('variations')->insertGetId(['product_id' => $pid, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
            'default_purchase_price' => 99, 'dpp_inc_tax' => 99, 'profit_percent' => 0, 'default_sell_price' => 150, 'sell_price_inc_tax' => 150,
            'created_at' => now(), 'updated_at' => now()]);

        return [$pid, $vid, $grupo];
    };
    [$this->pid, $this->vid, $grupo] = $produto($this->b98, 'Lona');
    DB::table('variation_location_details')->insert(['product_id' => $this->pid, 'product_variation_id' => $grupo, 'variation_id' => $this->vid,
        'location_id' => $this->localL, 'qty_available' => 10, 'created_at' => now(), 'updated_at' => now()]);
    [, $this->vid99] = $produto((int) DB::table('business')->where('id', '!=', $this->b98)->value('id'), 'Lona alheia');

    $this->compra = function (int $localId, string $data, string $qtd, string $preco): int {
        $tid = DB::table('transactions')->insertGetId(['business_id' => $this->b98, 'location_id' => $localId, 'type' => 'purchase', 'status' => 'received',
            'payment_status' => 'due', 'transaction_date' => $data, 'final_total' => 0, 'essentials_duration' => 0, 'created_by' => $this->op1->id, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('purchase_lines')->insertGetId(['transaction_id' => $tid, 'product_id' => $this->pid, 'variation_id' => $this->vid,
            'quantity' => $qtd, 'quantity_returned' => 0, 'pp_without_discount' => $preco, 'purchase_price' => $preco,
            'purchase_price_inc_tax' => $preco, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);
    };
    $this->loteA = ($this->compra)($this->localL, '2026-03-01 09:00:00', '2', '10.00');
    $this->loteB = ($this->compra)($this->localL, '2026-03-10 09:00:00', '5', '14.00');
    $this->loteC = ($this->compra)($this->localM, '2026-03-05 09:00:00', '100', '1.00');

    $venda = DB::table('transactions')->insertGetId(['business_id' => $this->b98, 'location_id' => $this->localL, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 500, 'essentials_duration' => 0, 'created_by' => $this->pcp->id, 'created_at' => now(), 'updated_at' => now()]);
    // SUPERADMIN: fixture da empresa 98.
    $this->posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'IMP-'.uniqid(), 'nome' => 'Impressão',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => $venda,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => $this->pid, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    $this->etapa = Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $this->ordem->id,
        'posto_id' => $this->posto->id, 'bloco' => 1, 'trilha' => 0, 'nome' => 'Impressão', 'user_id' => $this->op1->id,
        'inicio_previsto' => now()->subHours(2), 'fim_previsto' => now(), 'status' => 'pendente', 'created_by' => $this->pcp->id]);
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp);

    $this->svc = app(ConsumoMaterialService::class);
    $this->iniciar = fn (int $minutosAtras = 60) => app(ApontamentoOperadorService::class)
        ->iniciar($this->b98, $this->op1->id, $this->etapa->id, 'i-'.uniqid(), now()->subMinutes($minutosAtras));
    $this->baixar = fn (string $qtd, ?string $chave = null, ?int $userId = null, ?int $vid = null) => $this->svc->registrar($this->b98,
        $userId ?? $this->op1->id, $this->etapa->id, $vid ?? $this->vid, $qtd, $chave ?? 'm-'.uniqid(), now());
    $this->saldo = fn (int $localId) => (string) DB::table('variation_location_details')->where('variation_id', $this->vid)->where('location_id', $localId)->value('qty_available');
    $this->recusa = function (callable $f): string {
        try {
            $f();
        } catch (ApontamentoRecusado $e) {
            return $e->codigo;
        }

        return 'nenhuma';
    };
});

/** Lote de outra empresa (99), do material dela: mesmo formato do lote da 98. */
function loteDaOutraEmpresa(int $vid99, int $criadoPor): int
{
    $v = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')->where('v.id', $vid99)->first(['p.id as pid', 'p.business_id']);
    $loc = (int) (DB::table('business_locations')->where('business_id', $v->business_id)->value('id') ?? 0);
    $tid = DB::table('transactions')->insertGetId(['business_id' => $v->business_id, 'location_id' => $loc, 'type' => 'purchase', 'status' => 'received',
        'payment_status' => 'due', 'transaction_date' => '2026-03-02 09:00:00', 'final_total' => 0, 'essentials_duration' => 0, 'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now()]);

    return DB::table('purchase_lines')->insertGetId(['transaction_id' => $tid, 'product_id' => $v->pid, 'variation_id' => $vid99,
        'quantity' => '50', 'quantity_returned' => 0, 'pp_without_discount' => '9.00', 'purchase_price' => '9.00',
        'purchase_price_inc_tax' => '9.00', 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);
}

it('UC-RLT-04 · o rolo lista cada OS que usou fração dele e o saldo bate por dois caminhos', function () {
    ($this->iniciar)();
    ($this->baixar)('3');                                                              // FIFO: 2 de A + 1 de B
    $escolhida = $this->svc->registrar($this->b98, $this->op1->id, $this->etapa->id, $this->vid, '2', 'm-'.uniqid(), now(), $this->loteB);

    $r = app(RastreioDoRolo::class)->doLote($this->b98, $this->loteB);
    expect(array_column($r['fracoes'], 'quantidade'))->toBe(['1.0000', '2.0000'])
        ->and(array_unique(array_column($r['fracoes'], 'ordem_id')))->toBe([$this->ordem->id])
        ->and($r['fracoes'][1]['consumo_id'])->toBe((int) $escolhida->id)
        ->and($r['fracoes'][1]['etapa'])->toBe('Impressão')
        ->and($r['fracoes'][1]['user_id'])->toBe($this->op1->id);

    // Caminho 1, à mão: recebido 5 − devolvido 0 − frações (1 + 2) = 2.
    expect($r['quantidades']['saldo'])->toBe('2.0000')->and($r['quantidades']['usado_em_os'])->toBe('3.0000');
    // Caminho 2, sem o serviço: soma direta do vínculo lote × baixa com production_purchase, e a coluna da compra.
    $vinculo = (string) DB::table('transaction_sell_lines_purchase_lines as m')
        ->join('transaction_sell_lines as l', 'l.id', '=', 'm.sell_line_id')->join('transactions as t', 't.id', '=', 'l.transaction_id')
        ->where('m.purchase_line_id', $this->loteB)->where('t.business_id', $this->b98)->where('t.type', 'production_sell')
        ->selectRaw('SUM(m.quantity) as q')->value('q');
    expect($vinculo)->toBe('3.0000');
    expect((string) DB::table('purchase_lines')->where('id', $this->loteB)->selectRaw('quantity - mfg_quantity_used as s')->value('s'))->toBe('2.0000');

    // O lote A saiu inteiro na primeira baixa: uma fração, saldo zero.
    $a = app(RastreioDoRolo::class)->doLote($this->b98, $this->loteA);
    expect(array_column($a['fracoes'], 'quantidade'))->toBe(['2.0000'])->and($a['quantidades']['saldo'])->toBe('0.0000');
    // Controle negativo: o lote do outro local não foi tocado.
    $c = app(RastreioDoRolo::class)->doLote($this->b98, $this->loteC);
    expect($c['fracoes'])->toBe([])->and($c['quantidades']['saldo'])->toBe('100.0000');
});

it('UC-RLT-05 · só quem vê compras abre o rastreio; lote de outra empresa responde 404', function () {
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    ($this->iniciar)();
    ($this->baixar)('3');
    $comprador = $this->usuarioComPermissoes(['purchase.view'], $this->B98);

    $this->actingAs($comprador)->getJson("/producao/lotes/{$this->loteB}/rastreio")->assertOk()
        ->assertJsonPath('quantidades.saldo', '4.0000')->assertJsonPath('fracoes.0.quantidade', '1.0000');
    $this->actingAs($this->op1)->getJson("/producao/lotes/{$this->loteB}/rastreio")->assertForbidden();

    $alheio = loteDaOutraEmpresa($this->vid99, $this->op1->id);
    $this->actingAs($comprador)->getJson("/producao/lotes/{$alheio}/rastreio")->assertNotFound();
    expect(fn () => app(RastreioDoRolo::class)->doLote($this->b98, $alheio))->toThrow(ApontamentoRecusado::class);
});

it('UC-PCMAT-08 · a baixa sai do rolo escolhido; sem escolha segue o FIFO; rolo inválido não baixa nada', function () {
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    ($this->iniciar)();
    $comLote = fn (string $q, int $lote) => $this->svc->registrar($this->b98, $this->op1->id, $this->etapa->id, $this->vid, $q, 'm-'.uniqid(), now(), $lote);
    $mfg = fn (int $pl) => (string) DB::table('purchase_lines')->where('id', $pl)->value('mfg_quantity_used');

    // Recusas primeiro, com o estoque intacto: outro local (C), sem saldo (B tem 5), outra empresa, outro material.
    $outroMaterial = DB::table('purchase_lines')->insertGetId(['transaction_id' => DB::table('purchase_lines')->where('id', $this->loteA)->value('transaction_id'),
        'product_id' => $this->pid, 'variation_id' => $this->vid99, 'quantity' => '9', 'quantity_returned' => 0, 'pp_without_discount' => '1.00',
        'purchase_price' => '1.00', 'purchase_price_inc_tax' => '1.00', 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);
    // Compra ainda não recebida (pending), no local da venda e do mesmo material: o núcleo não baixa dela.
    $pendente = ($this->compra)($this->localL, '2026-02-15 09:00:00', '50', '2.00');
    DB::table('transactions')->where('id', DB::table('purchase_lines')->where('id', $pendente)->value('transaction_id'))->update(['status' => 'pending']);
    foreach ([[$this->loteC, '1'], [$this->loteB, '6'], [loteDaOutraEmpresa($this->vid99, $this->op1->id), '1'], [$outroMaterial, '1'], [$pendente, '1']] as [$lote, $q]) {
        expect(($this->recusa)(fn () => $comLote($q, $lote)))->toBe(ApontamentoRecusado::LOTE_INVALIDO);
    }
    expect(($this->saldo)($this->localL))->toBe('10.0000')
        ->and(DB::table('pcp_consumos')->where('ordem_id', $this->ordem->id)->count())->toBe(0)
        ->and($mfg($this->loteA))->toBe('0.0000')->and($mfg($this->loteB))->toBe('0.0000')->and($mfg($this->loteC))->toBe('0.0000')
        ->and($mfg($pendente))->toBe('0.0000');

    // Escolhido B: as 3 saem de B (FIFO mandaria 2 de A). Custo à mão 3 × 14,00 = 42,0000; 2º caminho = o vínculo gravado.
    $c = $comLote('3', $this->loteB);
    expect((string) $c->custo)->toBe('42.0000')->and($mfg($this->loteB))->toBe('3.0000')->and($mfg($this->loteA))->toBe('0.0000');
    expect((int) DB::table('transaction_sell_lines')->where('id', $c->transaction_sell_line_id)->value('lot_no_line_id'))->toBe($this->loteB);
    expect((string) DB::table('transaction_sell_lines_purchase_lines as m')->join('purchase_lines as pl', 'pl.id', '=', 'm.purchase_line_id')
        ->where('m.sell_line_id', $c->transaction_sell_line_id)->selectRaw('ROUND(SUM(m.quantity * pl.purchase_price_inc_tax), 4) as c')->value('c'))->toBe('42.0000');
    expect(($this->saldo)($this->localL))->toBe('7.0000');

    // Sem escolha, como antes: FIFO tira do A, linha sem lote escolhido.
    $d = ($this->baixar)('1');
    expect((string) $d->custo)->toBe('10.0000')->and($mfg($this->loteA))->toBe('1.0000')
        ->and(DB::table('transaction_sell_lines')->where('id', $d->transaction_sell_line_id)->value('lot_no_line_id'))->toBeNull();

    // Reenvio da mesma chave com outro rolo é conflito, não segunda baixa.
    $chave = 'fila-'.uniqid();
    $this->svc->registrar($this->b98, $this->op1->id, $this->etapa->id, $this->vid, '1', $chave, now(), $this->loteB);
    expect(($this->recusa)(fn () => $this->svc->registrar($this->b98, $this->op1->id, $this->etapa->id, $this->vid, '1', $chave, now(), $this->loteA)))
        ->toBe(ApontamentoRecusado::IDEMPOTENCIA_CONFLITO);

    // Pela PWA e pelo app: rolo de outro local responde 422 e não baixa.
    $corpo = ['programacao_id' => $this->etapa->id, 'variation_id' => $this->vid, 'quantidade' => '1', 'lote_id' => $this->loteC,
        'chave' => 'pwa-'.uniqid(), 'momento' => now()->toIso8601String()];
    $this->actingAs($this->op1)->postJson('/producao/apontamento/material', $corpo)->assertStatus(422)->assertJson(['erro' => 'lote_invalido']);
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    Passport::actingAs($this->op1, [], 'api');
    $this->withHeaders(['Idempotency-Key' => 'app-'.uniqid()])->postJson('/api/app/producao/apontamentos/material',
        ['programacao_id' => $this->etapa->id, 'variation_id' => $this->vid, 'quantidade' => '1', 'lote_id' => $this->loteC])->assertStatus(422);
    expect(($this->saldo)($this->localL))->toBe('5.0000');                              // 10 − 3 − 1 − 1
});
