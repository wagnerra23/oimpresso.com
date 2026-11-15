<?php

declare(strict_types=1);
// Cobre UC-PCESC-01..03 (Producao/Os/Show.casos.md). O UC-PCESC-04 é o guard estático em
// tests/Unit/Guards/WithoutGlobalScopesCommentGuardTest.php.

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\OsDaVenda;
use Modules\Producao\Services\PrevisaoCustoDaOrdem;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\ProgramacaoDoRoteiro;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Quando a empresa da consulta vem do registro (rota pública, gancho do núcleo, job), o escopo da
 * sessão não pode esconder a linha. Quando ela vem da própria sessão, a dispensa não muda nada e saiu.
 * Tenant 98 × 99 (ADR 0358), NUNCA biz=4.
 *
 * ⚠️ SKIP em SQLite / sem tabelas: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens') || ! Schema::hasTable('pcp_roteiro_etapas')) {
        $this->markTestSkipped('Tabelas pcp_* ausentes nesta lane.');
    }
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->B99 = $this->seededSupportClientTenant();
    $this->b99 = (int) $this->B99->id;
    $this->processo = app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->venda = random_int(90000000, 99999999);
    // SUPERADMIN: fixture sem usuário logado; o escopo é provado nos asserts.
    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => $this->venda,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => SaleProcessStage::where('process_id', $this->processo->id)->where('key', 'aguardando_liberacao')->value('id')]);

    $this->logarNa = function ($tenant): void {
        $this->actingAs($this->usuarioComPermissoes(['producao.access'], $tenant));
        session(['user.business_id' => (int) $tenant->id]);
    };
});

it('UC-PCESC-01 · com a sessão de outra empresa, a consulta que vem do registro ainda acha a OS da empresa dele', function () {
    ($this->logarNa)($this->B99);

    // Controle: o escopo está ativo e esconderia a OS e o processo da 98.
    expect(Ordem::query()->find($this->ordem->id))->toBeNull()
        ->and(SaleProcess::query()->find($this->processo->id))->toBeNull();

    expect((int) app(SituacaoDaOrdem::class)->ordem($this->b98, $this->ordem->id)->id)->toBe((int) $this->ordem->id)
        ->and(app(OsDaVenda::class)->daVenda($this->b98, $this->venda)->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([(int) $this->ordem->id])
        // firstOrCreate: sem a dispensa ele não veria o processo e tentaria duplicar.
        ->and((int) app(ProcessoDaOrdem::class)->garantir($this->b98)->id)->toBe((int) $this->processo->id);
});

it('UC-PCESC-02 · a leitura que vem da sessão acha a OS da própria empresa e nunca a de outra', function () {
    $previsao = app(PrevisaoCustoDaOrdem::class);

    ($this->logarNa)($this->B98);
    expect($previsao->daOrdem($this->b98, $this->ordem->id)['ordem_id'])->toBe((int) $this->ordem->id);

    ($this->logarNa)($this->B99);
    expect(fn () => $previsao->daOrdem($this->b98, $this->ordem->id))->toThrow(ApontamentoRecusado::class)
        ->and(fn () => $previsao->daOrdem($this->b99, $this->ordem->id))->toThrow(ApontamentoRecusado::class);
});

it('UC-PCESC-03 · gravar a etapa na empresa recebida não depende da sessão', function () {
    $roteiro = DB::table('pcp_roteiros')->insertGetId(['business_id' => $this->b98, 'familia' => 'Projeto', 'nome' => 'Escopo '.uniqid(),
        'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('pcp_roteiro_etapas')->insert(['business_id' => $this->b98, 'roteiro_id' => $roteiro, 'bloco' => 1, 'trilha' => 0, 'ordem' => 0,
        'nome' => 'Arte', 'horas' => 1, 'created_at' => now(), 'updated_at' => now()]);

    ($this->logarNa)($this->B99);
    expect(app(ProgramacaoDoRoteiro::class)->programar($this->b98, Ordem::OS_TIPO, (int) $this->ordem->id, (int) $roteiro, 1))->toBe(1);

    // SUPERADMIN: conferência da linha gravada, com a sessão de outra empresa de propósito.
    $gravadas = Programacao::withoutGlobalScopes()->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $this->ordem->id)->get();
    expect($gravadas)->toHaveCount(1)
        ->and((int) $gravadas->first()->business_id)->toBe($this->b98);
});
