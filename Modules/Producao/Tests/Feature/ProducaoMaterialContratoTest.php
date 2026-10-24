<?php
// @covers-us US-PCP-021

declare(strict_types=1);
// Cobre UC-PCMAT-01..07 (Producao/Apontamento/Index.casos.md) — UC-FLX-37 do catálogo de fluxos.

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
use Modules\Producao\Services\CustoRealService;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-PCP-021 · UC-FLX-37 — a OS baixa o material apontado em cada etapa, pelo custo do lote que saiu.
 * Decisão [W] 2026-10-19. ESTOQUE e VALOR (regra mestre): cada número é feito à mão sobre o arranjo e
 * conferido por um segundo caminho (SQL sobre o vínculo lote × baixa, sem o serviço).
 *
 * Arranjo, tenant 98, método FIFO, sem vender sem estoque:
 *   local da venda (L) · lote A 01/03 · 2 un × 10,00 · lote B 10/03 · 5 un × 14,00 · saldo em L = 10
 *   outro local (M)    · lote C 05/03 · 100 un × 1,00   ← discriminante: mais barato, NÃO pode sair
 *   baixa de 3 un na etapa → sai 2 de A e 1 de B → custo = 2 × 10,00 + 1 × 14,00 = 34,0000
 *
 * Tenant 99 é o adversário (material de outra empresa). ⚠️ SKIP em SQLite: leia assertions (LC-13).
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

it('UC-PCMAT-01 · o material apontado sai do estoque do local da venda que gerou a OS', function () {
    ($this->iniciar)();
    $c = ($this->baixar)('3');

    expect(($this->saldo)($this->localL))->toBe('7.0000');                   // 10 − 3
    $saida = DB::table('transactions')->where('id', $c->transaction_id)->first(['business_id', 'type', 'status', 'location_id']);
    expect((int) $saida->business_id)->toBe($this->b98)->and($saida->type)->toBe('production_sell')
        ->and($saida->status)->toBe('final')->and((int) $saida->location_id)->toBe($this->localL);
    expect((int) $c->programacao_id)->toBe($this->etapa->id)->and((int) $c->user_id)->toBe($this->op1->id)
        ->and((string) $c->quantidade)->toBe('3.0000');
});

it('UC-PCMAT-02 · o custo é o do lote que saiu (FIFO), não o preço do dia nem o lote de outro local', function () {
    ($this->iniciar)();
    $c = ($this->baixar)('3');

    // Caminho 1, à mão: 2 × 10,00 (lote A) + 1 × 14,00 (lote B) = 34,0000.
    expect((string) $c->custo)->toBe('34.0000')->and((string) $c->quantidade_sem_lote)->toBe('0.0000');
    // Caminho 2, sem o serviço: o vínculo lote × baixa gravado pelo core.
    $sql = (string) DB::table('transaction_sell_lines_purchase_lines as m')->join('purchase_lines as pl', 'pl.id', '=', 'm.purchase_line_id')
        ->where('m.sell_line_id', $c->transaction_sell_line_id)->selectRaw('ROUND(SUM(m.quantity * pl.purchase_price_inc_tax), 4) as c')->value('c');
    expect($sql)->toBe('34.0000');
    expect((string) DB::table('purchase_lines')->where('id', $this->loteA)->value('mfg_quantity_used'))->toBe('2.0000')
        ->and((string) DB::table('purchase_lines')->where('id', $this->loteB)->value('mfg_quantity_used'))->toBe('1.0000')
        ->and((string) DB::table('purchase_lines')->where('id', $this->loteC)->value('mfg_quantity_used'))->toBe('0.0000');

    // Compra nova depois: não reescreve a baixa já feita.
    ($this->compra)($this->localL, '2026-02-01 09:00:00', '50', '1.00');
    expect((string) $c->fresh()->custo)->toBe('34.0000');

    // Segunda baixa do MESMO material na mesma OS: outra production_sell (o core não aceita a mesma
    // variação duas vezes numa transação — uk_tsl_dup_prevent). Sai do que sobrou: lote novo
    // (02/02, mais antigo) 2 × 1,00 = 2,0000. Cada saída tem o total do próprio custo.
    $d = ($this->baixar)('2');
    expect((string) $d->custo)->toBe('2.0000')->and((int) $d->transaction_id)->not->toBe((int) $c->transaction_id);
    expect((string) DB::table('transactions')->where('id', $c->transaction_id)->value('final_total'))->toBe('34.0000')
        ->and((string) DB::table('transactions')->where('id', $d->transaction_id)->value('final_total'))->toBe('2.0000');
    expect(app(CustoRealService::class)->daOs($this->b98, Ordem::OS_TIPO, $this->ordem->id)['material'])->toBe('36.00');
});

it('UC-PCMAT-03 · concluir o item congela horas e material juntos; material depois da conclusão é recusado', function () {
    $inicio = ($this->iniciar)(60);
    ($this->baixar)('3');
    app(ApontamentoOperadorService::class)->concluir($this->b98, $this->op1->id, $inicio->id, 'c-'.uniqid(), now());

    $o = $this->ordem->fresh();
    expect($o->currentStage->key)->toBe('concluida');
    // À mão: 1 h × 30,00 + material 34,00 = 64,00. Segundo caminho: o serviço de custo, campo a campo.
    expect((string) $o->custo_real)->toBe('64.0000');
    expect(app(CustoRealService::class)->daOs($this->b98, Ordem::OS_TIPO, $o->id))
        ->toBe(['maquina' => '0.00', 'pessoa' => '30.00', 'material' => '34.00', 'terceiros' => '0.00', 'total' => '64.00']);

    expect(($this->recusa)(fn () => ($this->baixar)('1')))->toBe(ApontamentoRecusado::OS_NAO_LIBERADA);
    expect(($this->saldo)($this->localL))->toBe('7.0000')->and((string) $o->fresh()->custo_real)->toBe('64.0000');
});

it('UC-PCMAT-04 · o reenvio da fila com a mesma chave não baixa duas vezes', function () {
    ($this->iniciar)();
    $a = ($this->baixar)('3', 'fila-1');
    $b = ($this->baixar)('3', 'fila-1');

    expect($b->id)->toBe($a->id)->and($b->wasRecentlyCreated)->toBeFalse();
    expect(($this->saldo)($this->localL))->toBe('7.0000');
    expect(DB::table('pcp_consumos')->where('business_id', $this->b98)->where('ordem_id', $this->ordem->id)->count())->toBe(1);
    expect(($this->recusa)(fn () => ($this->baixar)('4', 'fila-1')))->toBe(ApontamentoRecusado::IDEMPOTENCIA_CONFLITO);
});

it('UC-PCMAT-05 · etapa alheia, etapa não iniciada e material de outra empresa não baixam nada', function () {
    expect(($this->recusa)(fn () => ($this->baixar)('1')))->toBe(ApontamentoRecusado::ETAPA_ENCERRADA); // não iniciada
    ($this->iniciar)();
    expect(($this->recusa)(fn () => ($this->baixar)('1', null, $this->op2->id)))->toBe(ApontamentoRecusado::ETAPA_ALHEIA);
    expect(($this->recusa)(fn () => ($this->baixar)('1', null, null, $this->vid99)))->toBe(ApontamentoRecusado::MATERIAL_INVALIDO);
    expect(($this->recusa)(fn () => ($this->baixar)('0')))->toBe(ApontamentoRecusado::QUANTIDADE_INVALIDA);
    expect(($this->recusa)(fn () => ($this->baixar)('1,5')))->toBe(ApontamentoRecusado::QUANTIDADE_INVALIDA);

    expect(($this->saldo)($this->localL))->toBe('10.0000');
    expect(DB::table('pcp_consumos')->where('ordem_id', $this->ordem->id)->count())->toBe(0);
    // Controle positivo: o mesmo arranjo baixa quando o pedido é válido.
    expect((string) ($this->baixar)('1')->custo)->toBe('10.0000');
});

it('UC-PCMAT-06 · sem lote suficiente no local da venda a baixa é recusada inteira', function () {
    ($this->iniciar)();
    // Em L há 2 + 5 = 7 em lote; o lote C (100 un) é de outro local e não conta.
    expect(($this->recusa)(fn () => ($this->baixar)('8')))->toBe(ApontamentoRecusado::ESTOQUE_INSUFICIENTE);

    expect(($this->saldo)($this->localL))->toBe('10.0000');
    expect(DB::table('pcp_consumos')->where('ordem_id', $this->ordem->id)->count())->toBe(0);
    expect(DB::table('transactions')->where('business_id', $this->b98)->where('type', 'production_sell')->where('ref_no', 'OS-'.$this->ordem->id)->count())->toBe(0);
    expect((string) DB::table('purchase_lines')->where('id', $this->loteA)->value('mfg_quantity_used'))->toBe('0.0000');
});

it('UC-PCMAT-07 · PWA e app apontam o material sem mostrar o custo ao operador', function () {
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    ($this->iniciar)();

    $corpo = ['programacao_id' => $this->etapa->id, 'variation_id' => $this->vid, 'quantidade' => '2', 'chave' => 'pwa-'.uniqid(), 'momento' => now()->toIso8601String()];
    $r = $this->actingAs($this->op1)->postJson('/producao/apontamento/material', $corpo)->assertCreated();
    expect($r->json())->not->toHaveKey('custo')->and($r->json('quantidade'))->toBe('2.0000');
    $this->actingAs($this->op1)->postJson('/producao/apontamento/material', $corpo)->assertOk();
    $this->actingAs($this->op2)->postJson('/producao/apontamento/material', ['chave' => 'pwa-'.uniqid()] + $corpo)->assertStatus(409);

    // O checkout não traz storage/oauth-*.key (mesmo contorno do AppProducaoApontamentoContratoTest).
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    Passport::actingAs($this->op1, [], 'api');
    $app = $this->withHeaders(['Idempotency-Key' => 'app-'.uniqid()])->postJson('/api/app/producao/apontamentos/material',
        ['programacao_id' => $this->etapa->id, 'variation_id' => $this->vid, 'quantidade' => '1'])->assertCreated();
    expect($app->json())->not->toHaveKey('custo');
    $this->withHeaders(['Idempotency-Key' => 'app-'.uniqid()])->postJson('/api/app/producao/apontamentos/material',
        ['programacao_id' => $this->etapa->id, 'variation_id' => $this->vid, 'quantidade' => '50'])
        ->assertStatus(422)->assertJson(['erro' => 'estoque_insuficiente']);

    expect(($this->saldo)($this->localL))->toBe('7.0000');                    // 10 − 2 (PWA) − 1 (app)
});

/**
 * UC-PCMAT-09/10 — a tela do operador (UC-FLX-18: "o campo do rolo na tela do operador"). ESTOQUE, regra
 * mestre: o saldo de cada rolo é feito à mão sobre o arranjo e conferido por SQL direto em purchase_lines.
 *
 * Receita fingida (o contrato é a interface InsumosDaReceita): 0,5 de lona por unidade; OS de 4 un → 2,0000.
 * Ela também devolve a lona da empresa 99: material alheio não pode aparecer na lista.
 */
function receitaFingida(int $vid, int $vid99): void
{
    app()->instance(\App\Contracts\Fabricacao\InsumosDaReceita::class, new class($vid, $vid99) implements \App\Contracts\Fabricacao\InsumosDaReceita
    {
        public function __construct(private int $vid, private int $vid99) {}

        public function paraQuantidade(int $businessId, int $variationId, string $quantidade): array
        {
            expect($quantidade)->toBe('4.0000');

            return [['product_id' => 0, 'variation_id' => $this->vid, 'quantidade' => '2.0000'], ['product_id' => 0, 'variation_id' => $this->vid99, 'quantidade' => '9.0000']];
        }
    });
}

it('UC-PCMAT-09 · a tela oferece o material da receita e os rolos com saldo no local da venda, sem custo', function () {
    receitaFingida($this->vid, $this->vid99);
    DB::table('pcp_ordens')->where('id', $this->ordem->id)->update(['variation_id' => $this->vid, 'quantidade' => '4.0000']);
    $lista = fn (int $biz) => app(\Modules\Producao\Services\MateriaisDaEtapa::class)->paraEtapas($biz, [$this->etapa->id]);
    $saldoSql = fn (int $pl) => (string) DB::table('purchase_lines')->where('id', $pl)
        ->selectRaw('quantity - quantity_sold - quantity_adjusted - quantity_returned - mfg_quantity_used as s')->value('s');

    $antes = $lista($this->b98)[$this->etapa->id];
    expect(array_column($antes, 'variation_id'))->toBe([$this->vid])                       // a lona da 99 não entra
        ->and($antes[0]['previsto'])->toBe('2.0000')->and($antes[0]['apontado'])->toBe('0.0000')
        ->and(array_column($antes[0]['lotes'], 'id'))->toBe([$this->loteA, $this->loteB])  // C é de outro local; A primeiro (mais antigo)
        ->and(array_column($antes[0]['lotes'], 'saldo'))->toBe(['2.0000', '5.0000'])
        ->and(json_encode($antes))->not->toContain('custo')->not->toContain('price');

    ($this->iniciar)();
    ($this->baixar)('3');                                                                   // FIFO: 2 de A + 1 de B

    $depois = $lista($this->b98)[$this->etapa->id][0];
    // À mão: A 2 − 2 = 0 (sai da lista) · B 5 − 1 = 4. Segundo caminho: SQL direto na linha da compra.
    expect(array_column($depois['lotes'], 'id'))->toBe([$this->loteB])->and($depois['lotes'][0]['saldo'])->toBe('4.0000')
        ->and($saldoSql($this->loteB))->toBe('4.0000')->and($saldoSql($this->loteA))->toBe('0.0000')
        ->and($depois['apontado'])->toBe('3.0000')->and(($this->saldo)($this->localL))->toBe('7.0000');

    // Tier 0: a mesma etapa lida por outra empresa não devolve nada.
    expect($lista((int) DB::table('business')->where('id', '!=', $this->b98)->value('id')))->toBe([]);
});

it('UC-PCMAT-10 · o rolo que a tela lista é o que a baixa usa: escolher B tira de B, não do mais antigo', function () {
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    receitaFingida($this->vid, $this->vid99);
    DB::table('pcp_ordens')->where('id', $this->ordem->id)->update(['variation_id' => $this->vid, 'quantidade' => '4.0000']);
    ($this->iniciar)();

    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $materiais = $this->actingAs($this->op1)->get('/producao/apontamento', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao, 'X-Inertia-Partial-Data' => 'materiais', 'X-Inertia-Partial-Component' => 'Producao/Apontamento/Index'])
        ->assertOk()->json('props.materiais');
    expect(array_keys($materiais))->toBe([$this->etapa->id]);
    $rolos = array_column($materiais[$this->etapa->id][0]['lotes'], 'id');
    expect($rolos)->toBe([$this->loteA, $this->loteB]);

    $this->actingAs($this->op1)->postJson('/producao/apontamento/material', ['programacao_id' => $this->etapa->id, 'variation_id' => $this->vid,
        'quantidade' => '3', 'lote_id' => $rolos[1], 'chave' => 'tela-'.uniqid(), 'momento' => now()->toIso8601String()])->assertCreated();

    // À mão: B 5 − 3 = 2 · A intacto em 2 (o FIFO teria tirado 2 de A). Segundo caminho: o vínculo baixa × lote.
    $linhas = DB::table('purchase_lines')->whereIn('id', [$this->loteA, $this->loteB])->pluck('mfg_quantity_used', 'id');
    expect((string) $linhas[$this->loteA])->toBe('0.0000')->and((string) $linhas[$this->loteB])->toBe('3.0000')
        ->and(($this->saldo)($this->localL))->toBe('7.0000');
    $vinculo = DB::table('transaction_sell_lines_purchase_lines as m')->join('pcp_consumos as c', 'c.transaction_sell_line_id', '=', 'm.sell_line_id')
        ->where('c.business_id', $this->b98)->where('c.ordem_id', $this->ordem->id)->pluck('m.quantity', 'm.purchase_line_id');
    expect(array_keys($vinculo->all()))->toBe([$this->loteB])->and((string) $vinculo[$this->loteB])->toBe('3.0000');
});
