<?php
// @covers-us US-PCP-027

declare(strict_types=1);
// Cobre UC-PCREF-01..06 (Producao/Apontamento/Index.casos.md) — UC-FLX-16 do catálogo de fluxos.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\CustoRealService;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\RefugoService;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-PCP-027 · UC-FLX-16 — refugo e retrabalho com motivo somam custo à OS. ESTOQUE e VALOR (regra
 * mestre): cada número é feito à mão sobre o arranjo e conferido por um segundo caminho.
 *
 * Arranjo, tenant 98, FIFO, sem vender sem estoque:
 *   local da venda · lote A 01/03 · 2 un × 10,00 · lote B 10/03 · 5 un × 14,00 · saldo = 10
 *   OS de 10 peças · etapa 1 Impressão (posto Impressão) · etapa 2 Acabamento (posto Acabamento)
 *   motivos de refugo: "Corte" no Acabamento, "Cor" na Impressão (discriminante: posto errado recusa)
 *   material reposto de 3 un → sai 2 de A e 1 de B → 2 × 10,00 + 1 × 14,00 = 34,0000
 *
 * Tenant 99 é o adversário (motivo de outra empresa). ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_refugos')) {
        $this->markTestSkipped('pcp_refugos ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->freezeTime();
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    DB::table('business')->where('id', $this->b98)->update(['accounting_method' => 'fifo', 'pos_settings' => json_encode(['allow_overselling' => 0])]);
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98);
    $this->op1 = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->op2 = $this->usuarioComPermissoes(['producao.access'], $this->B98);

    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $this->b98, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $this->b98, 'name' => 'Lay '.uniqid()]);
    $this->local = DB::table('business_locations')->insertGetId(['business_id' => $this->b98, 'name' => 'Loja '.uniqid(), 'country' => 'Brasil',
        'state' => 'SC', 'city' => 'Tubarão', 'zip_code' => '8870000', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1]);
    $unit = (int) (DB::table('units')->where('business_id', $this->b98)->value('id') ?? DB::table('units')->insertGetId([
        'business_id' => $this->b98, 'actual_name' => 'Unidade', 'short_name' => 'un', 'allow_decimal' => 1, 'created_by' => $this->op1->id,
        'created_at' => now(), 'updated_at' => now()]));
    $sku = 'REF-'.uniqid();
    $this->pid = DB::table('products')->insertGetId(['business_id' => $this->b98, 'name' => 'Lona '.$sku, 'type' => 'single', 'unit_id' => $unit,
        'sku' => $sku, 'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive', 'barcode_type' => 'C128',
        'created_by' => $this->op1->id, 'created_at' => now(), 'updated_at' => now()]);
    $grupo = DB::table('product_variations')->insertGetId(['product_id' => $this->pid, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->vid = DB::table('variations')->insertGetId(['product_id' => $this->pid, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
        'default_purchase_price' => 99, 'dpp_inc_tax' => 99, 'profit_percent' => 0, 'default_sell_price' => 150, 'sell_price_inc_tax' => 150,
        'created_at' => now(), 'updated_at' => now()]);
    DB::table('variation_location_details')->insert(['product_id' => $this->pid, 'product_variation_id' => $grupo, 'variation_id' => $this->vid,
        'location_id' => $this->local, 'qty_available' => 10, 'created_at' => now(), 'updated_at' => now()]);
    foreach ([['2026-03-01 09:00:00', '2', '10.00'], ['2026-03-10 09:00:00', '5', '14.00']] as [$data, $qtd, $preco]) {
        $tid = DB::table('transactions')->insertGetId(['business_id' => $this->b98, 'location_id' => $this->local, 'type' => 'purchase', 'status' => 'received',
            'payment_status' => 'due', 'transaction_date' => $data, 'final_total' => 0, 'essentials_duration' => 0, 'created_by' => $this->op1->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('purchase_lines')->insert(['transaction_id' => $tid, 'product_id' => $this->pid, 'variation_id' => $this->vid, 'quantity' => $qtd,
            'quantity_returned' => 0, 'pp_without_discount' => $preco, 'purchase_price' => $preco, 'purchase_price_inc_tax' => $preco, 'item_tax' => 0,
            'created_at' => now(), 'updated_at' => now()]);
    }
    $venda = DB::table('transactions')->insertGetId(['business_id' => $this->b98, 'location_id' => $this->local, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 500, 'essentials_duration' => 0, 'created_by' => $this->pcp->id, 'created_at' => now(), 'updated_at' => now()]);

    // SUPERADMIN: fixture da empresa 98; o escopo é provado nos asserts.
    $posto = fn (string $nome) => Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'P-'.uniqid(), 'nome' => $nome,
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
    $this->postoImp = $posto('Impressão');
    $this->postoAcab = $posto('Acabamento');
    $mot = fn (int $biz, ?int $postoId, string $tipo, string $nome) => DB::table('pcp_motivos')->insertGetId(['business_id' => $biz,
        'posto_id' => $postoId, 'tipo' => $tipo, 'nome' => $nome, 'ordem' => 0, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->motCorte = $mot($this->b98, $this->postoAcab->id, 'refugo', 'Corte');
    $this->motCor = $mot($this->b98, $this->postoImp->id, 'refugo', 'Cor');
    $this->motPausaImp = $mot($this->b98, $this->postoImp->id, 'pausa', 'Falta material');
    $this->motAlheio = $mot($this->b99, null, 'refugo', 'Corte (outra empresa)');

    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => $venda,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => $this->pid, 'quantidade' => '10.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    $et = fn ($bloco, $nome, $posto) => Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO,
        'os_id' => $this->ordem->id, 'posto_id' => $posto->id, 'bloco' => $bloco, 'trilha' => 0, 'nome' => $nome, 'user_id' => $this->op1->id,
        'inicio_previsto' => now()->subHours(3), 'fim_previsto' => now()->subHours(2), 'status' => 'pendente', 'created_by' => $this->pcp->id]);
    $this->e1 = $et(1, 'Impressão', $this->postoImp);
    $this->e2 = $et(2, 'Acabamento', $this->postoAcab);
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp);

    $this->apont = app(ApontamentoOperadorService::class);
    $this->rodar = function (Programacao $e, int $de, int $ate) {   // minutos atrás
        $i = $this->apont->iniciar($this->b98, $this->op1->id, $e->id, 'i-'.uniqid(), now()->subMinutes($de));
        if ($ate >= 0) {
            $this->apont->concluir($this->b98, $this->op1->id, $i->id, 'c-'.uniqid(), now()->subMinutes($ate));
        }
    };
    $this->refugar = fn (Programacao $e, int $motivo, string $qtd, array $mat = [], bool $retrab = false, ?string $chave = null, ?int $user = null) =>
        app(RefugoService::class)->registrar($this->b98, $user ?? $this->op1->id, $e->id, $motivo, $qtd, $chave ?? 'r-'.uniqid(), now(), $mat, $retrab);
    $this->saldo = fn () => (string) DB::table('variation_location_details')->where('variation_id', $this->vid)->where('location_id', $this->local)->value('qty_available');
    $this->recusa = function (callable $f): string {
        try {
            $f();
        } catch (ApontamentoRecusado $e) {
            return $e->codigo;
        }

        return 'nenhuma';
    };
});

it('UC-PCREF-01 · o responsável aponta o refugo na etapa com quantidade, motivo do posto, quem e quando', function () {
    ($this->rodar)($this->e1, 60, -1);
    $r = ($this->refugar)($this->e1, $this->motCor, '2');

    expect((int) $r->business_id)->toBe($this->b98)->and((int) $r->ordem_id)->toBe($this->ordem->id)
        ->and((int) $r->programacao_id)->toBe($this->e1->id)->and((int) $r->user_id)->toBe($this->op1->id)
        ->and($r->motivo)->toBe('Cor')->and((string) $r->quantidade)->toBe('2.0000')
        ->and($r->momento->toDateTimeString())->toBe(now()->toDateTimeString())->and($r->retrabalho_programacao_id)->toBeNull();
    // Sem material e sem retrabalho, o refugo só registra: estoque e situação não mudam.
    expect(($this->saldo)())->toBe('10.0000')->and($this->ordem->fresh()->currentStage->key)->toBe('em_execucao');
});

it('UC-PCREF-02 · o material reposto para as peças refugadas sai pelo lote, como o material da etapa', function () {
    ($this->rodar)($this->e1, 60, -1);
    $r = ($this->refugar)($this->e1, $this->motCor, '1', [['variation_id' => $this->vid, 'quantidade' => '3']]);

    expect(($this->saldo)())->toBe('7.0000');                                // 10 − 3
    $c = DB::table('pcp_consumos')->where('business_id', $this->b98)->where('ordem_id', $this->ordem->id)->sole();
    // Caminho 1, à mão: 2 × 10,00 + 1 × 14,00 = 34,0000. Caminho 2: o vínculo lote × baixa do core.
    expect((string) $c->custo)->toBe('34.0000')->and((int) $c->programacao_id)->toBe($this->e1->id);
    expect((string) DB::table('transaction_sell_lines_purchase_lines as m')->join('purchase_lines as pl', 'pl.id', '=', 'm.purchase_line_id')
        ->where('m.sell_line_id', $c->transaction_sell_line_id)->selectRaw('ROUND(SUM(m.quantity * pl.purchase_price_inc_tax), 4) as c')->value('c'))->toBe('34.0000');
    expect((int) $r->ordem_id)->toBe((int) $c->ordem_id);
});

it('UC-PCREF-03 · refugo com retrabalho manda a OS para Retrabalho e as horas e o material do refugo entram no custo real', function () {
    $this->op1->assignRole('producao.qualidade#'.$this->b98);
    ($this->rodar)($this->e1, 180, 120);                                     // 1 h × 30,00 = 30,00
    $r = ($this->refugar)($this->e1, $this->motCor, '2', [['variation_id' => $this->vid, 'quantidade' => '3']], true);

    expect($this->ordem->fresh()->currentStage->key)->toBe('retrabalho');
    $nova = Programacao::withoutGlobalScopes()->where('business_id', $this->b98)->findOrFail($r->retrabalho_programacao_id);
    expect($nova->nome)->toBe('Impressão (retrabalho)')->and($nova->status)->toBe('pendente')->and((int) $nova->os_id)->toBe($this->ordem->id);

    app(SituacaoDaOrdem::class)->refazer($this->b98, $this->ordem->id, $this->pcp);
    ($this->rodar)($nova, 110, 80);                                          // retrabalho 0,5 h × 30,00 = 15,00
    ($this->rodar)($this->e2, 70, 40);                                     // acabamento 0,5 h × 30,00 = 15,00

    // À mão: pessoa 30 + 15 + 15 = 60,00 · material 34,00 · total 94,00 (sem o refugo seriam 45,00).
    $o = $this->ordem->fresh();
    expect($o->currentStage->key)->toBe('concluida')->and((string) $o->custo_real)->toBe('94.0000');
    expect(app(CustoRealService::class)->daOs($this->b98, Ordem::OS_TIPO, $o->id))
        ->toBe(['maquina' => '0.00', 'pessoa' => '60.00', 'material' => '34.00', 'terceiros' => '0.00', 'total' => '94.00']);
});

it('UC-PCREF-04 · o reenvio da fila com a mesma chave não grava nem baixa duas vezes', function () {
    ($this->rodar)($this->e1, 60, -1);
    $mat = [['variation_id' => $this->vid, 'quantidade' => '3']];
    $a = ($this->refugar)($this->e1, $this->motCor, '2', $mat, false, 'fila-r1');
    $b = ($this->refugar)($this->e1, $this->motCor, '2', $mat, false, 'fila-r1');

    expect($b->id)->toBe($a->id)->and($b->wasRecentlyCreated)->toBeFalse();
    expect(DB::table('pcp_refugos')->where('business_id', $this->b98)->where('ordem_id', $this->ordem->id)->count())->toBe(1)
        ->and(DB::table('pcp_consumos')->where('ordem_id', $this->ordem->id)->count())->toBe(1)->and(($this->saldo)())->toBe('7.0000');
    expect(($this->recusa)(fn () => ($this->refugar)($this->e1, $this->motCor, '3', [], false, 'fila-r1')))->toBe(ApontamentoRecusado::IDEMPOTENCIA_CONFLITO);
});

it('UC-PCREF-05 · motivo de outra empresa, de pausa ou de outro posto, etapa alheia e quantidade acima da OS não gravam nada', function () {
    expect(($this->recusa)(fn () => ($this->refugar)($this->e1, $this->motCor, '1')))->toBe(ApontamentoRecusado::ETAPA_ENCERRADA); // não iniciada
    ($this->rodar)($this->e1, 60, -1);
    expect(($this->recusa)(fn () => ($this->refugar)($this->e1, $this->motAlheio, '1')))->toBe(ApontamentoRecusado::MOTIVO_OBRIGATORIO);
    expect(($this->recusa)(fn () => ($this->refugar)($this->e1, $this->motPausaImp, '1')))->toBe(ApontamentoRecusado::MOTIVO_OBRIGATORIO);
    expect(($this->recusa)(fn () => ($this->refugar)($this->e1, $this->motCorte, '1')))->toBe(ApontamentoRecusado::MOTIVO_OBRIGATORIO); // posto Acabamento
    expect(($this->recusa)(fn () => ($this->refugar)($this->e1, $this->motCor, '1', [], false, null, $this->op2->id)))->toBe(ApontamentoRecusado::ETAPA_ALHEIA);
    expect(($this->recusa)(fn () => ($this->refugar)($this->e1, $this->motCor, '11')))->toBe(ApontamentoRecusado::QUANTIDADE_INVALIDA);
    expect(($this->recusa)(fn () => ($this->refugar)($this->e1, $this->motCor, '1', [['variation_id' => $this->vid, 'quantidade' => '8']])))
        ->toBe(ApontamentoRecusado::ESTOQUE_INSUFICIENTE);                   // só 7 em lote: nada fica, nem o refugo

    expect(DB::table('pcp_refugos')->where('ordem_id', $this->ordem->id)->count())->toBe(0)->and(($this->saldo)())->toBe('10.0000');
    // Controle positivo: o mesmo arranjo grava quando o pedido é válido.
    expect((string) ($this->refugar)($this->e1, $this->motCor, '10')->quantidade)->toBe('10.0000');
});

it('UC-PCREF-06 · pela PWA o operador aponta refugo sem ver custo; retrabalho sem o papel de qualidade é negado e nada fica', function () {
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    ($this->rodar)($this->e1, 180, 120);

    $corpo = ['programacao_id' => $this->e1->id, 'motivo_id' => $this->motCor, 'quantidade' => '2', 'materiais' => [['variation_id' => $this->vid, 'quantidade' => '3']],
        'chave' => 'pwa-'.uniqid(), 'momento' => now()->toIso8601String()];
    $this->actingAs($this->op1)->postJson('/producao/apontamento/refugo', ['retrabalho' => true] + $corpo)->assertForbidden()
        ->assertJson(['erro' => 'retrabalho_negado']);
    expect(DB::table('pcp_refugos')->where('ordem_id', $this->ordem->id)->count())->toBe(0)->and(($this->saldo)())->toBe('10.0000')
        ->and($this->ordem->fresh()->currentStage->key)->toBe('em_execucao');

    $r = $this->actingAs($this->op1)->postJson('/producao/apontamento/refugo', $corpo)->assertCreated();
    expect($r->json())->not->toHaveKey('custo')->and($r->json('quantidade'))->toBe('2.0000')->and($r->json('motivo'))->toBe('Cor');
    $this->actingAs($this->op1)->postJson('/producao/apontamento/refugo', $corpo)->assertOk();
    expect(($this->saldo)())->toBe('7.0000');
});
