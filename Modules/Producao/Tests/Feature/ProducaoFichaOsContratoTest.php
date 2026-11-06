<?php

declare(strict_types=1);
// Cobre UC-PCV-10, UC-PCOSF-01, UC-PCOSF-02 (Producao/Os/Show.casos.md) — a Ficha da OS por item.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Auditoria Producao lote 3.2. Tenant 98 × 99 (ADR 0358). Previsto = fim − início programados; apontado =
 * pares início→fim do apontamento da etapa. ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens')) {
        $this->markTestSkipped('pcp_ordens ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->pacote = true;
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $this->pacote && $perm === 'producao_module'));
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98);
    $this->gerente = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->gerente->assignRole('vendas.gerente#'.$this->b98);
    $this->op = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    // SUPERADMIN: fixture da empresa 98; o escopo é provado nos asserts.
    $this->posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'IMP-'.uniqid(), 'nome' => 'Roland',
        'setor' => 'Impressão', 'tipo_custo' => 'maquina', 'custo_hora' => '80.00']);
    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => 990021,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    $et = fn ($bloco, $nome, $de, $ate) => Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO,
        'os_id' => $this->ordem->id, 'posto_id' => $this->posto->id, 'bloco' => $bloco, 'trilha' => 0, 'nome' => $nome, 'user_id' => null,
        'inicio_previsto' => "2026-11-10 {$de}:00", 'fim_previsto' => "2026-11-10 {$ate}:00", 'status' => 'pendente', 'created_by' => $this->pcp->id]);
    $this->e1 = $et(1, 'Impressão', '08:00', '10:00');
    $this->e2 = $et(2, 'Recorte', '10:00', '10:30');
    $this->url = "/producao/ordens/{$this->ordem->id}";
    $this->parcial = function ($user, string $props) {
        $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return $this->actingAs($user)->get($this->url, ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) $versao, 'X-Inertia-Partial-Data' => $props, 'X-Inertia-Partial-Component' => 'Producao/Os/Show']);
    };
});

it('UC-PCV-10 · a Ficha mostra a situação e as etapas com previsto × apontado', function () {
    $this->actingAs($this->pcp)->get($this->url)->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Producao/Os/Show', false)
            ->where('os.numero', 'OP '.$this->ordem->id)->where('os.situacao', 'aguardando_liberacao'));

    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp, [$this->e1->id => $this->op->id, $this->e2->id => $this->op->id]);
    $svc = app(ApontamentoOperadorService::class);
    $ap = $svc->iniciar($this->b98, $this->op->id, $this->e1->id, 'i-'.uniqid(), now()->subMinutes(90));
    $svc->concluir($this->b98, $this->op->id, $ap->id, 'c-'.uniqid(), now());

    $etapas = ($this->parcial)($this->pcp, 'etapas,os')->assertOk()->json('props.etapas');
    expect(array_column($etapas, 'id'))->toBe([$this->e1->id, $this->e2->id])
        ->and($etapas[0]['previsto_h'])->toEqual(2.0)->and($etapas[1]['previsto_h'])->toEqual(0.5)
        // Discriminante: a etapa apontada tem 1,5 h; a outra, nenhuma.
        ->and($etapas[0]['apontado_h'])->toEqual(1.5)->and($etapas[1]['apontado_h'])->toEqual(0.0)
        ->and($etapas[0]['status'])->toBe('concluida')->and($etapas[0]['responsavel'])->not->toBeNull()
        ->and($etapas[0]['setor'])->toBe('Impressão');
});

it('UC-PCOSF-01 · os botões seguem o papel: Liberar para producao.liberar, Cancelar item para vendas.gerente', function () {
    $pode = fn ($u) => ($this->parcial)($u, 'pode')->assertOk()->json('props.pode');
    expect($pode($this->pcp))->toBe(['liberar' => true, 'cancelar' => false])
        ->and($pode($this->gerente))->toBe(['liberar' => false, 'cancelar' => true])
        ->and($pode($this->op))->toBe(['liberar' => false, 'cancelar' => false]);

    // Quem escolhe os responsáveis recebe a lista; quem não libera recebe vazia.
    $ids = array_column(($this->parcial)($this->pcp, 'pessoas')->assertOk()->json('props.pessoas.pessoas'), 'id');
    expect($ids)->toContain($this->op->id);
    expect(($this->parcial)($this->op, 'pessoas')->assertOk()->json('props.pessoas.pessoas'))->toBe([]);
});

it('UC-PCOSF-02 · Tier 0 e portões: OS de outra empresa 404; sem acesso ou sem o pacote 403', function () {
    // SUPERADMIN: fixture da empresa 99.
    $os99 = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b99, 'transaction_id' => 990022,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000']);
    $this->actingAs($this->pcp)->get("/producao/ordens/{$os99->id}")->assertNotFound();
    $this->actingAs($this->pcp)->get($this->url)->assertOk(); // controle: a OS da própria empresa abre

    $this->actingAs($this->usuarioComPermissoes([], $this->B98))->get($this->url)->assertForbidden();
    $this->pacote = false;
    $this->actingAs($this->pcp)->get($this->url)->assertForbidden();
});
