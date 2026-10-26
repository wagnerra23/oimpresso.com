<?php
// @covers-us US-COMVIS-003

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Http\Controllers\RoteiroPadraoController;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Modules\ComunicacaoVisual\Services\OrcamentoViraOs;
use Modules\ComunicacaoVisual\Services\RoteiroPadraoDaOs;
use Modules\Producao\Services\QuadroDaProducao;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-003 — a OS da gráfica nasce com o roteiro padrão da empresa e entra no quadro do PCP
 * (UC-CVOS-09 e UC-CVOS-10 do Index.casos.md).
 *
 * Âncora: decisão [W] 2026-10-26 registrada no SPEC ("toda OS de comunicação visual nasce com o
 * roteiro padrão da empresa, e o operador pode trocar à mão depois"). Tenant 98 contra 99 (ADR 0358).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    foreach (['pcp_roteiros', 'pcp_roteiro_etapas', 'pcp_programacoes', 'comvis_os', 'comvis_os_itens'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    try {
        $this->B98 = $this->seededTenant();
        $this->biz = (int) $this->B98->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $this->settingsAntes = DB::table('business')->where('id', $this->biz)->value('common_settings');
    $this->roteiro = function (int $biz, bool $ativo = true): int {
        $id = DB::table('pcp_roteiros')->insertGetId(['business_id' => $biz, 'familia' => 'Comunicação visual',
            'nome' => 'Banner ' . uniqid(), 'ativo' => $ativo, 'created_at' => now(), 'updated_at' => now()]);
        $e = fn ($bloco, $ordem, $nome, $horas, $opc = null) => ['business_id' => $biz, 'roteiro_id' => $id, 'bloco' => $bloco,
            'trilha' => 0, 'ordem' => $ordem, 'nome' => $nome, 'horas' => $horas, 'junta' => null, 'opcional_por' => $opc,
            'created_at' => now(), 'updated_at' => now()];
        DB::table('pcp_roteiro_etapas')->insert([
            $e(1, 0, 'Pré-impressão', 1), $e(2, 0, 'Impressão', 2), $e(3, 0, 'Acabamento', 1),
            $e(3, 1, 'Laminação', 0.5, 'com laminação'), $e(4, 0, 'Expedição', 0.5),
        ]);

        return $id;
    };
    $this->orcamento = function (): int {
        $item = ['material_id' => null, 'descricao' => 'Banner', 'largura_m' => 3.0, 'altura_m' => 1.0, 'quantidade' => 1,
            'area_m2' => 3.0, 'preco_unitario_m2' => 50.0, 'subtotal' => 150.0, 'observacoes' => null];

        return (int) app(OrcamentoGravador::class)->gravar($this->biz, [
            'data_emissao' => now()->toDateString(), 'data_validade' => now()->addDays(5)->toDateString(), 'contato_id' => null,
            'vendedor_id' => null, 'observacoes' => null, 'subtotal' => 150.0, 'desconto' => 0.0, 'extras' => 0.0,
            'custo_instalacao' => 0.0, 'custo_entrega' => 0.0, 'total' => 150.0, 'itens' => [$item],
        ])->id;
    };
    $this->etapasDaOs = fn (int $osId) => DB::table('pcp_programacoes')->where('business_id', $this->biz)
        ->where('os_tipo', 'comvis_os')->where('os_id', $osId)->orderBy('bloco')->pluck('nome')->all();
});

afterEach(function () {
    if (isset($this->biz)) {
        DB::table('business')->where('id', $this->biz)->update(['common_settings' => $this->settingsAntes]);
    }
});

it('UC-CVOS-09 · com roteiro padrão, a OS aprovada nasce com as etapas dele e aparece no quadro', function () {
    $roteiro = ($this->roteiro)($this->biz);
    app(RoteiroPadraoDaOs::class)->definir($this->biz, $roteiro);
    $user = $this->usuarioComPermissoes(['comvis.orcamento.create'], $this->B98);

    [$os] = app(OrcamentoViraOs::class)->aprovar($this->biz, ($this->orcamento)(), (int) $user->id);

    // a etapa opcional (Laminação) fica de fora, igual à OS da venda
    expect(($this->etapasDaOs)((int) $os->id))->toBe(['Pré-impressão', 'Impressão', 'Acabamento', 'Expedição']);
    expect((int) DB::table('pcp_programacoes')->where('os_id', $os->id)->where('os_tipo', 'comvis_os')->value('created_by'))->toBe((int) $user->id);
    $cartao = collect(app(QuadroDaProducao::class)->cartoes($this->biz, now()))
        ->first(fn ($c) => $c['os_tipo'] === 'comvis_os' && $c['os_id'] === (int) $os->id);
    expect($cartao)->not->toBeNull();
});

it('UC-CVOS-09 · aprovada sem usuário (o cliente aceita pelo link), as etapas ficam no nome do dono da empresa', function () {
    app(RoteiroPadraoDaOs::class)->definir($this->biz, ($this->roteiro)($this->biz));

    [$os] = app(OrcamentoViraOs::class)->aprovar($this->biz, ($this->orcamento)(), null);

    expect(($this->etapasDaOs)((int) $os->id))->toHaveCount(4);
    $dono = (int) DB::table('business')->where('id', $this->biz)->value('owner_id');
    expect((int) DB::table('pcp_programacoes')->where('os_id', $os->id)->where('os_tipo', 'comvis_os')->value('created_by'))->toBe($dono);
});

it('UC-CVOS-10 · sem padrão, ou com roteiro de outra empresa gravado na mão, a OS nasce sem etapas [T0]', function () {
    $user = $this->usuarioComPermissoes(['comvis.orcamento.create'], $this->B98);

    [$semPadrao] = app(OrcamentoViraOs::class)->aprovar($this->biz, ($this->orcamento)(), (int) $user->id);
    expect(($this->etapasDaOs)((int) $semPadrao->id))->toBe([]);

    app(RoteiroPadraoDaOs::class)->definir($this->biz, ($this->roteiro)($this->outra));
    [$alheio] = app(OrcamentoViraOs::class)->aprovar($this->biz, ($this->orcamento)(), (int) $user->id);
    expect(($this->etapasDaOs)((int) $alheio->id))->toBe([])
        ->and(DB::table('pcp_programacoes')->where('os_tipo', 'comvis_os')->where('os_id', $alheio->id)->exists())->toBeFalse();
});

it('UC-CVOS-10 · escolher o padrão só aceita roteiro ativo da própria empresa', function () {
    $meu = ($this->roteiro)($this->biz);
    $inativo = ($this->roteiro)($this->biz, false);
    $daOutra = ($this->roteiro)($this->outra);
    $user = $this->usuarioComPermissoes(['comvis.material.manage'], $this->B98);
    $this->actingAs($user);
    session(['user.business_id' => $this->biz, 'business.id' => $this->biz, 'user.id' => $user->id]);
    $put = fn ($id) => app()->call([app(RoteiroPadraoController::class), 'update'], [
        'request' => \Illuminate\Http\Request::create('/comunicacao-visual/api/roteiro-padrao', 'PUT', ['roteiro_id' => $id]),
    ]);

    expect($put($daOutra)->getStatusCode())->toBe(422);
    expect($put($inativo)->getStatusCode())->toBe(422);
    expect(app(RoteiroPadraoDaOs::class)->atual($this->biz))->toBeNull();

    expect($put($meu)->getStatusCode())->toBe(200);
    expect(app(RoteiroPadraoDaOs::class)->atual($this->biz))->toBe($meu);
    $lista = app()->call([app(RoteiroPadraoController::class), 'show'])->getData(true);
    expect(array_column($lista['roteiros'], 'id'))->toContain($meu)
        ->not->toContain($daOutra)
        ->not->toContain($inativo);

    expect($put(null)->getStatusCode())->toBe(200);
    expect(app(RoteiroPadraoDaOs::class)->atual($this->biz))->toBeNull();
});
