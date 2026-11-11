<?php
// @covers-us US-COMVIS-023

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;
use Modules\ComunicacaoVisual\Services\AceiteDoOrcamento;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Tests\Support\WithSeededTenant;

/**
 * O aceite guarda o que foi aceito, e editar fecha o link (UC-CVACEP-05..06 · UC-CVACE-04).
 *
 * Âncora: US-COMVIS-023 ("nenhum valor é recalculado: a OS leva o total gravado") + auditoria
 * 2026-11-05 S7: a gráfica editava com link aberto e não sobrava prova da versão aceita.
 *
 * Conta à mão do arranjo: Adesivo 2,00 × 1,00 × 3 = 6,00 m² × 40,00 = 240,00 + instalação 60,00 = 300,00.
 * Editado: Placa 3,00 × 1,00 × 1 = 3,00 m² × 50,00 = 150,00 + instalação 10,00 = 160,00.
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP sem as colunas: leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('comvis_orcamento_aceites', 'retrato_json')) {
        $this->markTestSkipped('Requer MySQL com comvis_orcamento_aceites.retrato_json.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: '.$e->getMessage());
    }

    $this->orcamento = function (int $biz): int {
        $item = ['material_id' => null, 'descricao' => 'Adesivo vitrine', 'largura_m' => 2.0, 'altura_m' => 1.0,
            'quantidade' => 3, 'area_m2' => 6.0, 'preco_unitario_m2' => 40.0, 'subtotal' => 240.0, 'observacoes' => null];

        return (int) app(OrcamentoGravador::class)->gravar($biz, [
            'data_emissao' => now()->toDateString(), 'data_validade' => now()->addDays(10)->toDateString(),
            'contato_id' => null, 'vendedor_id' => null, 'observacoes' => null, 'subtotal' => 240.0,
            'desconto' => 0.0, 'extras' => 0.0, 'custo_instalacao' => 60.0, 'custo_entrega' => 0.0,
            'total' => 300.0, 'itens' => [$item],
        ])->id;
    };
    $this->svc = app(AceiteDoOrcamento::class);
    $this->linha = fn (string $token) => DB::table('comvis_orcamento_aceites')->where('token_hash', AceiteDoOrcamento::hash($token))->first();

    $this->editar = function (int $id): \Illuminate\Http\JsonResponse {
        $user = $this->usuarioComPermissoes(['comvis.orcamento.create'], $this->biz);
        $this->actingAs($user);
        session(['user.business_id' => $this->biz->id, 'business.id' => $this->biz->id, 'user.id' => $user->id]);

        return app()->call([app(OrcamentoController::class), 'update'], [
            'id' => $id,
            'request' => Request::create('/comunicacao-visual/api/orcamentos/'.$id, 'PUT', [
                'data_emissao' => now()->toDateString(), 'data_validade' => now()->addDays(10)->toDateString(),
                'custo_instalacao' => 10,
                'itens' => [['descricao' => 'Placa', 'largura_m' => 3, 'altura_m' => 1, 'quantidade' => 1, 'preco_unitario_m2' => 50]],
            ]),
        ]);
    };
});

it('UC-CVACEP-05 · aceitar grava o retrato, o IP e o navegador, e os valores batem por dois caminhos', function () {
    $id = ($this->orcamento)($this->biz->id);
    $token = $this->svc->gerar($this->biz->id, $id)['token'];
    $totalAntes = DB::table('comvis_orcamentos')->where('id', $id)->value('total');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->withHeader('User-Agent', 'NavegadorDeTeste/1.0')
        ->post("/orcamento-cv/{$token}/aceitar")
        ->assertRedirect("/orcamento-cv/{$token}")
        ->assertSessionHas('aceite_feito');

    $linha = ($this->linha)($token);
    $retrato = json_decode($linha->retrato_json, true);
    $os = DB::table('comvis_os')->where('business_id', $this->biz->id)->where('orcamento_id', $id)->first();

    expect($linha->ip)->toBe('203.0.113.7')
        ->and($linha->user_agent)->toBe('NavegadorDeTeste/1.0')
        ->and($retrato['itens'])->toHaveCount(1)
        ->and($retrato['itens'][0]['descricao'])->toBe('Adesivo vitrine');

    // 1º caminho: a soma feita à mão a partir do próprio retrato. 2º: o total gravado e o da OS.
    $aMao = array_sum(array_map(fn (array $i) => (float) $i['subtotal'], $retrato['itens']))
        - (float) $retrato['desconto'] + (float) $retrato['extras'] + (float) $retrato['acabamentos']
        + (float) $retrato['custo_instalacao'] + (float) $retrato['custo_entrega'];
    expect(round($aMao, 2))->toBe(300.0)
        ->and(round((float) $retrato['total'], 2))->toBe(300.0)
        ->and(round((float) $os->valor_total, 2))->toBe(300.0);

    // Nenhum valor do orçamento muda ao aceitar.
    expect(DB::table('comvis_orcamentos')->where('id', $id)->value('total'))->toBe($totalAntes);
});

it('UC-CVACEP-05 · o retrato não muda depois, mesmo que a linha do orçamento mude', function () {
    $id = ($this->orcamento)($this->biz->id);
    $token = $this->svc->gerar($this->biz->id, $id)['token'];
    $this->svc->decidir($token, 'aceito');

    DB::table('comvis_orcamentos')->where('id', $id)->update(['total' => 999.0]);

    expect(round((float) json_decode(($this->linha)($token)->retrato_json, true)['total'], 2))->toBe(300.0);
});

it('UC-CVACEP-06 · o retrato não leva custo, e pedir ajuste não guarda IP nem navegador', function () {
    $id = ($this->orcamento)($this->biz->id);
    $token = $this->svc->gerar($this->biz->id, $id)['token'];

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post("/orcamento-cv/{$token}/ajuste", ['comentario' => 'Trocar a lona'])
        ->assertRedirect("/orcamento-cv/{$token}");

    $linha = ($this->linha)($token);
    $texto = (string) $linha->retrato_json;
    expect($linha->decisao)->toBe('ajuste')
        ->and(round((float) json_decode($texto, true)['total'], 2))->toBe(300.0)
        ->and($linha->ip)->toBeNull()
        ->and($linha->user_agent)->toBeNull()
        ->and($texto)->not->toContain('custo_previsto')
        ->and($texto)->not->toContain('margem');
});

it('UC-CVACE-04 · editar fecha o link aberto: o cliente não aceita a versão antiga', function () {
    $id = ($this->orcamento)($this->biz->id);
    $token = $this->svc->gerar($this->biz->id, $id)['token'];
    expect($this->svc->paraCliente($token)['situacao'])->toBe('aberto');

    $resposta = ($this->editar)($id);

    expect($resposta->getStatusCode())->toBe(200)
        ->and(round((float) DB::table('comvis_orcamentos')->where('id', $id)->value('total'), 2))->toBe(160.0)
        ->and($this->svc->paraCliente($token)['situacao'])->toBe('vencido');

    expect(fn () => $this->svc->decidir($token, 'aceito'))
        ->toThrow(\DomainException::class, 'Este link venceu');
    expect(DB::table('comvis_os')->where('business_id', $this->biz->id)->where('orcamento_id', $id)->exists())->toBeFalse();
});

it('UC-CVACE-04 · o link novo depois da edição aceita o valor novo', function () {
    $id = ($this->orcamento)($this->biz->id);
    $this->svc->gerar($this->biz->id, $id);
    ($this->editar)($id);

    $novo = $this->svc->gerar($this->biz->id, $id)['token'];
    $this->svc->decidir($novo, 'aceito');

    expect(round((float) json_decode(($this->linha)($novo)->retrato_json, true)['total'], 2))->toBe(160.0)
        ->and(round((float) DB::table('comvis_os')->where('orcamento_id', $id)->value('valor_total'), 2))->toBe(160.0);
});

it('UC-CVACE-04 · fechar os links de um orçamento não toca os da outra empresa', function () {
    $meu = ($this->orcamento)($this->biz->id);
    $daOutra = ($this->orcamento)($this->outra->id);
    $tokenMeu = $this->svc->gerar($this->biz->id, $meu)['token'];
    $tokenOutra = $this->svc->gerar($this->outra->id, $daOutra)['token'];

    // Mesmo id do orçamento, empresa errada: não fecha nada.
    expect($this->svc->fecharLinksAbertos($this->outra->id, $meu))->toBe(0);
    expect($this->svc->paraCliente($tokenMeu)['situacao'])->toBe('aberto');

    expect($this->svc->fecharLinksAbertos($this->biz->id, $meu))->toBe(1);
    expect($this->svc->paraCliente($tokenMeu)['situacao'])->toBe('vencido')
        ->and($this->svc->paraCliente($tokenOutra)['situacao'])->toBe('aberto');
});
