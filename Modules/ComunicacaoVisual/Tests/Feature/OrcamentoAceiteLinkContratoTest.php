<?php
// @covers-us US-COMVIS-023

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Services\AceiteDoOrcamento;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoAceiteController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-023 — o cliente aceita o orçamento da gráfica pelo link (UC-CVACE-01 e UC-CVACE-02).
 *
 * Âncora: a US no SPEC (token aleatório, só o sha256 no banco, 7 dias, uso único; aceitar = mesmo
 * `OrcamentoViraOs` da tela, sem recalcular valor; ajuste volta a rascunho com comentário).
 * Valor: a OS nasce com o `total` gravado no orçamento — o teste compara os dois números.
 *
 * Tenant 98 contra 99 (ADR 0358). Nunca biz=4.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    foreach (['comvis_orcamento_aceites', 'comvis_orcamentos', 'comvis_os', 'comvis_os_itens'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    try {
        $this->bizObj = $this->seededTenant();
        $this->biz = (int) $this->bizObj->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $this->orcamento = function (int $biz, array $extra = []): int {
        $item = ['material_id' => null, 'descricao' => 'Banner fachada', 'largura_m' => 3.0, 'altura_m' => 1.5,
            'quantidade' => 1, 'area_m2' => 4.5, 'preco_unitario_m2' => 60.0, 'subtotal' => 270.0, 'observacoes' => null];
        $orc = app(OrcamentoGravador::class)->gravar($biz, array_merge([
            'data_emissao' => now()->toDateString(), 'data_validade' => now()->addDays(10)->toDateString(),
            'contato_id' => null, 'vendedor_id' => null, 'observacoes' => null, 'subtotal' => 270.0,
            'desconto' => 0.0, 'extras' => 30.0, 'custo_instalacao' => 0.0, 'custo_entrega' => 0.0,
            'total' => 300.0, 'itens' => [$item],
        ], $extra));

        return (int) $orc->id;
    };
    $this->svc = app(AceiteDoOrcamento::class);
});

it('UC-CVACE-01 · gerar o link guarda só o sha256, manda o orçamento a enviado e a página mostra o que o cliente precisa', function () {
    $id = ($this->orcamento)($this->biz);
    $link = $this->svc->gerar($this->biz, $id);

    expect(strlen($link['token']))->toBe(48);
    $linha = DB::table('comvis_orcamento_aceites')->where('orcamento_id', $id)->first();
    expect($linha->token_hash)->toBe(hash('sha256', $link['token']))
        ->and($linha->token_hash)->not->toBe($link['token'])
        ->and((int) $linha->business_id)->toBe($this->biz);
    expect(DB::table('comvis_orcamentos')->where('id', $id)->value('status'))->toBe('enviado');

    $pagina = $this->svc->paraCliente($link['token']);
    expect($pagina['situacao'])->toBe('aberto')
        ->and($pagina['total'])->toBe(300.0)
        ->and($pagina['itens'][0]['descricao'])->toBe('Banner fachada')
        ->and($pagina['itens'][0])->not->toHaveKey('custo_previsto');
    expect($this->svc->paraCliente('token-que-nao-existe'))->toBeNull();
});

it('UC-CVACE-01 · gerar de novo fecha o link anterior', function () {
    $id = ($this->orcamento)($this->biz);
    $velho = $this->svc->gerar($this->biz, $id)['token'];
    $novo = $this->svc->gerar($this->biz, $id)['token'];

    expect($this->svc->paraCliente($velho)['situacao'])->toBe('vencido')
        ->and($this->svc->paraCliente($novo)['situacao'])->toBe('aberto');
    expect(fn () => $this->svc->decidir($velho, 'aceito'))->toThrow(\DomainException::class, 'venceu');
});

it('UC-CVACE-02 · aceitar cria a OS com o total gravado e o link não decide duas vezes', function () {
    $id = ($this->orcamento)($this->biz);
    $token = $this->svc->gerar($this->biz, $id)['token'];

    $r = $this->svc->decidir($token, 'aceito');

    $os = DB::table('comvis_os')->where('business_id', $this->biz)->where('orcamento_id', $id)->first();
    expect($os)->not->toBeNull()
        ->and($r['os_numero'])->toBe($os->numero)
        ->and(number_format((float) $os->valor_total, 2, '.', ''))->toBe('300.00')   // = total do orçamento, sem recálculo
        ->and(DB::table('comvis_orcamentos')->where('id', $id)->value('status'))->toBe('aprovado')
        ->and(DB::table('comvis_orcamento_aceites')->where('orcamento_id', $id)->value('decisao'))->toBe('aceito');
    expect(fn () => $this->svc->decidir($token, 'aceito'))->toThrow(\DomainException::class, 'já foi usado');
    expect(DB::table('comvis_os')->where('orcamento_id', $id)->count())->toBe(1);
});

it('UC-CVACE-02 · pedir ajuste volta o orçamento a rascunho com o comentário; sem comentário não vale', function () {
    $id = ($this->orcamento)($this->biz);
    $token = $this->svc->gerar($this->biz, $id)['token'];

    expect(fn () => $this->svc->decidir($token, 'ajuste', '   '))->toThrow(\DomainException::class, 'precisa mudar');
    $this->svc->decidir($token, 'ajuste', 'Trocar a lona por blackout');

    expect(DB::table('comvis_orcamentos')->where('id', $id)->value('status'))->toBe('rascunho')
        ->and(DB::table('comvis_orcamento_aceites')->where('orcamento_id', $id)->value('comentario'))->toBe('Trocar a lona por blackout')
        ->and(DB::table('comvis_os')->where('orcamento_id', $id)->exists())->toBeFalse();
});

it('UC-CVACE-02 · link vencido, validade passada e orçamento já aprovado na tela não decidem', function () {
    $vencido = ($this->orcamento)($this->biz);
    $t1 = $this->svc->gerar($this->biz, $vencido)['token'];
    DB::table('comvis_orcamento_aceites')->where('orcamento_id', $vencido)->update(['expira_em' => now()->subMinute()]);

    $foraValidade = ($this->orcamento)($this->biz);
    $t2 = $this->svc->gerar($this->biz, $foraValidade)['token'];
    DB::table('comvis_orcamentos')->where('id', $foraValidade)->update(['data_validade' => now()->subDays(2)->toDateString()]);

    $aprovado = ($this->orcamento)($this->biz);
    $t3 = $this->svc->gerar($this->biz, $aprovado)['token'];
    DB::table('comvis_orcamentos')->where('id', $aprovado)->update(['status' => 'aprovado']);

    expect(fn () => $this->svc->decidir($t1, 'aceito'))->toThrow(\DomainException::class, 'venceu');
    expect(fn () => $this->svc->decidir($t2, 'aceito'))->toThrow(\DomainException::class, 'validade');
    expect(fn () => $this->svc->decidir($t3, 'aceito'))->toThrow(\DomainException::class, 'não está mais disponível');
    expect(DB::table('comvis_os')->whereIn('orcamento_id', [$vencido, $foraValidade, $aprovado])->count())->toBe(0);
});

it('UC-CVACE-01 · orçamento de outra empresa não gera link, nem pelo serviço nem pelo controller [T0]', function () {
    $daOutra = ($this->orcamento)($this->outra);
    $meu = ($this->orcamento)($this->biz);

    expect(fn () => $this->svc->gerar($this->biz, $daOutra))->toThrow(ModelNotFoundException::class);
    expect(DB::table('comvis_orcamento_aceites')->where('orcamento_id', $daOutra)->exists())->toBeFalse();

    $user = $this->usuarioComPermissoes(['comvis.orcamento.create'], $this->bizObj);
    $this->actingAs($user);
    session(['user.business_id' => $this->biz, 'business.id' => $this->biz, 'user.id' => $user->id]);
    $chamar = fn (int $id) => app()->call([app(OrcamentoAceiteController::class), 'gerar'], ['id' => $id]);

    expect(fn () => $chamar($daOutra))->toThrow(HttpException::class);
    // âncora positiva: o orçamento da própria empresa gera, pelo mesmo controller
    $resp = $chamar($meu);
    expect($resp->getStatusCode())->toBe(201)
        ->and($resp->getData(true)['url'])->toContain('/orcamento-cv/');
});
