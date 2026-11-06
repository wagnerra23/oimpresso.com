<?php
// @covers-us US-OFICINA-014

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\AprovacaoOsService;
use Modules\OficinaAuto\Services\ServiceOrderItemService;
use Tests\Support\WithSeededTenant;

/**
 * Lote 2.1 da auditoria OficinaAuto (2026-11-05) — o que a página pública mostra antes e
 * depois do PIN (UC-OAP-08..10 do AprovacaoPublica.casos.md).
 *
 * Âncoras: CDC art. 40 (orçamento prévio discriminado, com valor, antes da aprovação) ·
 * charter Non-Goal "cliente só aprova/rejeita o que tá" · auditoria eixo 5 A7 (notas internas
 * e placa antes do PIN) · ADR 0093. Tenant 98 contra 99 (ADR 0358).
 *
 * OS de exemplo (valor provado por dois caminhos no PR):
 *   peça      2 × 150,00 = 300,00
 *   mão obra  3 × 120,00 = 360,00
 *   terceiro  1 ×  85,50 =  85,50
 *   total                 = 745,50
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS.');
    }
    foreach (['service_orders', 'vehicles', 'oficina_service_order_items'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    Cache::flush();

    $this->osComItens = function (int $biz): ServiceOrder {
        // SUPERADMIN: teste monta a OS direto, fora de sessão HTTP.
        $v = Vehicle::withoutGlobalScopes()->create([
            'business_id' => $biz, 'plate' => 'ORC' . random_int(1000, 9999), 'vehicle_type' => 'caminhao',
        ]);
        $os = ServiceOrder::withoutGlobalScopes()->create([
            'business_id' => $biz, 'vehicle_id' => $v->id, 'order_type' => 'manutencao',
            'status' => 'orcamento', 'entered_at' => now(),
            'notes' => 'NOTA INTERNA: cliente devedor, cobrar adiantado',
        ]);
        foreach ([
            ['peca', 'Bomba hidráulica', 2, 150.00],
            ['mao_obra', 'Hora de mecânico', 3, 120.00],
            ['servico_terceiro', 'Retífica do cilindro', 1, 85.50],
        ] as [$tipo, $descricao, $qtd, $unit]) {
            ServiceOrderItem::withoutGlobalScopes()->create([
                'business_id' => $biz, 'service_order_id' => $os->id, 'tipo' => $tipo,
                'descricao' => $descricao, 'quantidade' => $qtd, 'valor_unitario' => $unit,
                'notes' => 'NOTA INTERNA DO ITEM',
            ]);
        }

        return $os;
    };
});

it('UC-OAP-08 · antes do PIN a página não traz notas internas, itens nem valor', function () {
    $os = ($this->osComItens)($this->biz);
    $g = app(AprovacaoOsService::class)->gerarTokenAprovacao($os);

    $resp = $this->get('/aprovar-os/' . $g['token']);

    $resp->assertOk()->assertInertia(fn ($p) => $p
        ->component('OficinaAuto/AprovacaoPublica')
        ->where('os.id', $os->id)
        ->missing('os.notes')
        ->missing('os.valor_total')
        ->where('orcamento', null)
    );
    expect($resp->getContent())->not->toContain('NOTA INTERNA');
});

it('UC-OAP-09 · depois do PIN a página traz os itens e o total da tela interna', function () {
    $os = ($this->osComItens)($this->biz);
    $g = app(AprovacaoOsService::class)->gerarTokenAprovacao($os);

    $this->post('/aprovar-os/' . $g['token'], ['pin' => $g['pin'], 'decisao' => 'conferir'])
        ->assertRedirect();

    $resp = $this->get('/aprovar-os/' . $g['token']);
    $props = $resp->viewData('page')['props'];
    $orc = $props['orcamento'];

    // Caminho 1: soma dos itens que a página exibe.
    $somaDosItens = round(array_sum(array_column($orc['itens'], 'valor_total')), 2);
    // Caminho 2: o total que a tela interna (total_items) e o serviço calculam.
    $daTelaInterna = (float) ServiceOrder::withoutGlobalScopes()->find($os->id)->total_items;
    $doServico = app(ServiceOrderItemService::class)->recalcularTotal((int) $os->id);

    expect(count($orc['itens']))->toBe(3)
        ->and($orc['total'])->toBe(745.5)
        ->and($somaDosItens)->toBe(745.5)
        ->and($daTelaInterna)->toBe(745.5)
        ->and($doServico)->toBe(745.5)
        ->and(array_column($orc['itens'], 'valor_total'))->toBe([300.0, 360.0, 85.5])
        ->and(array_key_exists('notes', $orc['itens'][0]))->toBeFalse()
        ->and(array_key_exists('notes', $props['os']))->toBeFalse();
    expect($resp->getContent())->not->toContain('NOTA INTERNA');

    // Com o orçamento aberto, aprovar não pede o PIN de novo (ele já foi consumido).
    $this->post('/aprovar-os/' . $g['token'], ['decisao' => 'aprovar'])->assertRedirect();
    expect(ServiceOrder::withoutGlobalScopes()->find($os->id)->status)->toBe('aprovada');
});

it('UC-OAP-09 · decidir sem PIN e sem orçamento aberto é recusado', function () {
    $os = ($this->osComItens)($this->biz);
    $g = app(AprovacaoOsService::class)->gerarTokenAprovacao($os);

    $this->post('/aprovar-os/' . $g['token'], ['decisao' => 'aprovar'])
        ->assertRedirect()
        ->assertSessionHasErrors('pin');

    expect(ServiceOrder::withoutGlobalScopes()->find($os->id)->status)->toBe('orcamento');
});

it('UC-OAP-10 · PIN conferido numa OS não abre o orçamento de OS de outra empresa', function () {
    $svc = app(AprovacaoOsService::class);
    $daqui = ($this->osComItens)($this->biz);
    $dali = ($this->osComItens)($this->outra);
    $gDaqui = $svc->gerarTokenAprovacao($daqui);
    $gDali = $svc->gerarTokenAprovacao($dali);

    $this->post('/aprovar-os/' . $gDaqui['token'], ['pin' => $gDaqui['pin'], 'decisao' => 'conferir']);

    $this->get('/aprovar-os/' . $gDali['token'])->assertOk()->assertInertia(fn ($p) => $p
        ->where('os.id', $dali->id)
        ->where('orcamento', null)
    );

    // E aprovar a de lá sem PIN continua recusado.
    $this->post('/aprovar-os/' . $gDali['token'], ['decisao' => 'aprovar'])->assertSessionHasErrors('pin');
    expect(ServiceOrder::withoutGlobalScopes()->find($dali->id)->status)->toBe('orcamento');
});
