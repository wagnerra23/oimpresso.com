<?php

declare(strict_types=1);
// Cobre o Liberar com responsável por etapa — decisão [W] 16/10 (opção c), protótipo #9530 — ADR 0436.

use App\Domain\Fsm\Models\SaleStageHistory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: protótipo #9530 aprovado pelo [W] em 16/10/2026 — a prévia do Liberar lista as etapas
 * abertas e pede o responsável de cada uma. Tenant 98 × 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens')) {
        $this->markTestSkipped('pcp_ordens ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->B99 = $this->seededSupportClientTenant();
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98);
    $this->op = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->op99 = $this->usuarioComPermissoes(['producao.access'], $this->B99);
    // SUPERADMIN: fixture da empresa 98.
    $posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'P-'.uniqid(), 'nome' => 'Serralheria',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => 990003,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    $et = fn ($bloco, $nome) => Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO,
        'os_id' => $this->ordem->id, 'posto_id' => $posto->id, 'bloco' => $bloco, 'nome' => $nome, 'user_id' => null,
        'inicio_previsto' => now(), 'fim_previsto' => now()->addHour(), 'status' => 'pendente', 'created_by' => $this->pcp->id]);
    $this->e1 = $et(1, 'Corte CNC');
    $this->e2 = $et(2, 'Montagem');
});

function libSit($teste): string
{
    return $teste->ordem->fresh()->currentStage->key;
}

it('Liberar grava o responsável escolhido em cada etapa e põe a OS Na fila', function () {
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp, [$this->e1->id => $this->op->id, $this->e2->id => $this->pcp->id]);

    expect(libSit($this))->toBe('na_fila');
    expect((int) $this->e1->fresh()->user_id)->toBe((int) $this->op->id);
    expect((int) $this->e2->fresh()->user_id)->toBe((int) $this->pcp->id);
    $h = SaleStageHistory::withoutGlobalScopes()->where('business_id', $this->b98)->where('transaction_id', $this->ordem->id)->latest('id')->first();
    expect($h->payload_snapshot['responsaveis'] ?? null)->not->toBeNull();
});

it('etapa aberta sem responsável recusa o Liberar e nada muda', function () {
    expect(fn () => app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp, [$this->e1->id => $this->op->id]))
        ->toThrow(ApontamentoRecusado::class, 'Escolha o responsável de: Montagem.');

    expect(libSit($this))->toBe('aguardando_liberacao');
    expect($this->e1->fresh()->user_id)->toBeNull();
});

it('Tier 0: responsável de outra empresa é recusado', function () {
    expect(fn () => app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp, [$this->e1->id => $this->op99->id, $this->e2->id => $this->op->id]))
        ->toThrow(fn (ApontamentoRecusado $e) => expect($e->codigo)->toBe(ApontamentoRecusado::RESPONSAVEL_INVALIDO));

    expect(libSit($this))->toBe('aguardando_liberacao');
    expect($this->e1->fresh()->user_id)->toBeNull();
});

it('etapa que não é desta OS é recusada', function () {
    expect(fn () => app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp, [999999999 => $this->op->id]))
        ->toThrow(ApontamentoRecusado::class, 'não é desta OS');
});

it('etapa que já tem responsável o mantém se não vier no mapa', function () {
    $this->e2->forceFill(['user_id' => $this->op->id])->save();
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp, [$this->e1->id => $this->pcp->id]);

    expect(libSit($this))->toBe('na_fila');
    expect((int) $this->e2->fresh()->user_id)->toBe((int) $this->op->id);
});
