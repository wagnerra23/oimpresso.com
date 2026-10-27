<?php
// @covers-us US-OFICINA-014

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Services\AprovacaoOsService;
use Modules\OficinaAuto\Services\PagamentoDaOs;
use Tests\Support\WithSeededTenant;

/**
 * US-OFICINA-014 · pagar pelo link da aprovação (UC-OAPAG-01..04 do AprovacaoPublica.casos.md).
 *
 * Âncora: decisão [W] 2026-10-27 — opção (a): "o botão aparece na página da aprovação quando a OS
 * fica pronta e a venda tem título em aberto", reaproveitando o link de pagamento do Financeiro
 * (US-FIN-081). Tenant 98 × 99. Nunca biz=4 nem biz=164 (piloto da oficina).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    foreach (['service_orders', 'vehicles', 'fin_titulos', 'fin_titulo_links_pagamento'] as $t) {
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
    $this->venda = random_int(900000000, 999999999);
    $this->parcela = 0;
    $this->os = function (int $biz, string $status, ?int $venda) {
        $veiculo = (int) DB::table('vehicles')->insertGetId([
            'business_id' => $biz, 'plate' => 'PAG' . random_int(1000, 9999), 'vehicle_type' => 'auto',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = (int) DB::table('service_orders')->insertGetId([
            'business_id' => $biz, 'vehicle_id' => $veiculo, 'order_type' => 'mecanica', 'status' => $status,
            'transaction_id' => $venda, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ServiceOrder::withoutGlobalScopes()->findOrFail($id); // SUPERADMIN: rota pública, sem sessão
    };
    $this->titulo = function (int $biz, int $venda, string $status = 'aberto', float $aberto = 150.0) {
        return (int) DB::table('fin_titulos')->insertGetId([
            'business_id' => $biz, 'numero' => 'OSP-' . bin2hex(random_bytes(3)), 'tipo' => 'receber', 'status' => $status,
            'cliente_descricao' => 'Cliente OS', 'valor_total' => 150.0, 'valor_aberto' => $aberto, 'moeda' => 'BRL',
            'emissao' => now()->toDateString(), 'vencimento' => now()->addDays(3)->toDateString(),
            'competencia_mes' => now()->format('Y-m'), 'origem' => 'venda', 'origem_id' => $venda,
            'parcela_numero' => ++$this->parcela, 'parcela_total' => 9,
            'created_by' => (int) DB::table('business')->where('id', $biz)->value('owner_id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    $this->aprov = new AprovacaoOsService();
    $this->pag = app(PagamentoDaOs::class);
});

it('UC-OAPAG-01 · depois de aprovada, o mesmo link identifica a OS e mostra o valor em aberto da venda', function () {
    $os = ($this->os)($this->biz, 'orcamento', $this->venda);
    $token = $this->aprov->gerarTokenAprovacao($os)['token'];
    DB::table('service_orders')->where('id', $os->id)->update(['status' => 'pronto']);
    $titulo = ($this->titulo)($this->biz, $this->venda, 'parcial', 90.0);

    $paga = $this->aprov->osDoTokenParaPagamento($token);
    expect($this->aprov->validarToken($token))->toBeNull()          // aprovar/recusar seguem só em orçamento
        ->and($paga?->id)->toBe($os->id)
        ->and($this->pag->tituloEmAberto($paga))->toBe(['titulo_id' => $titulo, 'valor_aberto' => 90.0]);
});

it('UC-OAPAG-02 · título quitado, cancelado, zerado ou de outra empresa não aparece `[T0]`', function () {
    $os = ($this->os)($this->biz, 'pronto', $this->venda);
    ($this->titulo)($this->biz, $this->venda, 'quitado', 0.0);
    ($this->titulo)($this->biz, $this->venda, 'cancelado', 150.0);
    ($this->titulo)($this->biz, $this->venda, 'aberto', 0.0);
    ($this->titulo)($this->outra, $this->venda, 'aberto', 150.0);   // mesma venda-id, outra empresa

    expect($this->pag->tituloEmAberto($os))->toBeNull()
        ->and($this->pag->tituloEmAberto(($this->os)($this->biz, 'pronto', null)))->toBeNull();
});

it('UC-OAPAG-03 · pagar gera o link do Financeiro para o título e manda o cliente para /pagar/{token}', function () {
    config(['oficina-auto.pagar_pelo_link.enabled' => true]);
    $os = ($this->os)($this->biz, 'orcamento', $this->venda);
    $token = $this->aprov->gerarTokenAprovacao($os)['token'];
    DB::table('service_orders')->where('id', $os->id)->update(['status' => 'pronto']);
    $titulo = ($this->titulo)($this->biz, $this->venda);

    $resp = $this->post("/aprovar-os/{$token}/pagar");

    $resp->assertRedirect();
    expect((string) $resp->headers->get('Location'))->toMatch('#/pagar/[A-Za-z0-9]{48}$#')
        ->and(DB::table('fin_titulo_links_pagamento')->where('business_id', $this->biz)->where('titulo_id', $titulo)->count())->toBe(1)
        ->and((float) DB::table('fin_titulos')->where('id', $titulo)->value('valor_aberto'))->toBe(150.0); // nada muda no título
});

it('UC-OAPAG-04 · desligado, pagar dá 404; sem título em aberto, volta para a página sem gerar link', function () {
    $os = ($this->os)($this->biz, 'orcamento', $this->venda);
    $token = $this->aprov->gerarTokenAprovacao($os)['token'];

    expect($this->pag->ligado())->toBeFalse();
    $this->post("/aprovar-os/{$token}/pagar")->assertNotFound();

    config(['oficina-auto.pagar_pelo_link.enabled' => true]);
    $this->post("/aprovar-os/{$token}/pagar")->assertRedirect("/aprovar-os/{$token}");
    expect(DB::table('fin_titulo_links_pagamento')->where('business_id', $this->biz)->count())->toBe(0);
});
