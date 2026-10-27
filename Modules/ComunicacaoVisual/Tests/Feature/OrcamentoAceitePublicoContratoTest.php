<?php
// @covers-us US-COMVIS-023

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\ComunicacaoVisual\Services\AceiteDoOrcamento;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-023 — a página pública /orcamento-cv/{token} (UC-CVACEP-01..04 do AceitePublico.casos.md).
 *
 * Âncora: a US no SPEC e o charter da tela. Pela pilha HTTP de verdade (rotas públicas, sem login),
 * porque o que se defende aqui é a rota: 404 para token desconhecido, cabeçalhos, redirect.
 * Tenant 98 (ADR 0358). Nunca biz=4.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    foreach (['comvis_orcamento_aceites', 'comvis_orcamentos', 'comvis_os', 'comvis_os_itens'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $item = ['material_id' => null, 'descricao' => 'Adesivo vitrine', 'largura_m' => 2.0, 'altura_m' => 1.0,
        'quantidade' => 3, 'area_m2' => 6.0, 'preco_unitario_m2' => 40.0, 'subtotal' => 240.0, 'observacoes' => null];
    $orc = app(OrcamentoGravador::class)->gravar($this->biz, [
        'data_emissao' => now()->toDateString(), 'data_validade' => now()->addDays(10)->toDateString(),
        'contato_id' => null, 'vendedor_id' => null, 'observacoes' => null, 'subtotal' => 240.0,
        'desconto' => 0.0, 'extras' => 0.0, 'custo_instalacao' => 60.0, 'custo_entrega' => 0.0,
        'total' => 300.0, 'itens' => [$item],
    ]);
    $this->orcId = (int) $orc->id;
    $this->token = app(AceiteDoOrcamento::class)->gerar($this->biz, $this->orcId)['token'];
});

it('UC-CVACEP-01 · link válido abre a página com itens e total, sem custo, sem indexar', function () {
    $resp = $this->get("/orcamento-cv/{$this->token}");

    $resp->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (AssertableInertia $p) => $p->component('ComunicacaoVisual/AceitePublico')
            ->where('orcamento.situacao', 'aberto')
            ->where('orcamento.total', 300)
            ->where('orcamento.itens.0.descricao', 'Adesivo vitrine')
            ->missing('orcamento.itens.0.custo_previsto')
            ->missing('orcamento.custo'));
});

it('UC-CVACEP-02 · token que não existe dá 404 e não grava nada', function () {
    $falso = str_repeat('A', 48);

    $this->get("/orcamento-cv/{$falso}")->assertNotFound();
    $this->post("/orcamento-cv/{$falso}/aceitar")->assertNotFound();
    expect(DB::table('comvis_os')->where('orcamento_id', $this->orcId)->exists())->toBeFalse();
});

it('UC-CVACEP-03 · aceitar pela página vira OS com o total gravado', function () {
    $this->post("/orcamento-cv/{$this->token}/aceitar")
        ->assertRedirect("/orcamento-cv/{$this->token}")
        ->assertSessionHas('aceite_feito');

    $os = DB::table('comvis_os')->where('business_id', $this->biz)->where('orcamento_id', $this->orcId)->first();
    expect($os)->not->toBeNull()
        ->and(number_format((float) $os->valor_total, 2, '.', ''))->toBe('300.00');

    $this->get("/orcamento-cv/{$this->token}")
        ->assertInertia(fn (AssertableInertia $p) => $p->where('orcamento.situacao', 'decidido'));
});

it('UC-CVACEP-04 · ajuste exige texto, volta a rascunho, e o link não responde de novo', function () {
    $this->post("/orcamento-cv/{$this->token}/ajuste", ['comentario' => ''])->assertSessionHasErrors('comentario');
    expect(DB::table('comvis_orcamentos')->where('id', $this->orcId)->value('status'))->toBe('enviado');

    $this->post("/orcamento-cv/{$this->token}/ajuste", ['comentario' => 'Trocar por lona blackout'])
        ->assertRedirect("/orcamento-cv/{$this->token}");
    expect(DB::table('comvis_orcamentos')->where('id', $this->orcId)->value('status'))->toBe('rascunho');

    $this->post("/orcamento-cv/{$this->token}/aceitar")
        ->assertRedirect("/orcamento-cv/{$this->token}")
        ->assertSessionHasErrors(['decisao' => 'Este link já foi usado.']);
    expect(DB::table('comvis_os')->where('orcamento_id', $this->orcId)->exists())->toBeFalse();
});
