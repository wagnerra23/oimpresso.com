<?php

declare(strict_types=1);
// Decisão [W] 2026-10-16 (FSM do pedido: ERP × app): fim do apontamento conclui a produção da venda.

use App\Domain\Fsm\Models\StockReservation;
use App\User;
use Database\Seeders\FsmProcessoVendaComProducaoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\ProducaoDaVendaService;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EstoqueFixture;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Última etapa do PCP da venda (`os_tipo = venda`) concluída → `concluir_producao` pelo executor
 * da FSM, com `ConsumirEstoque`, trilha antes → depois e RBAC preservado (sem a role, pendência).
 *
 * Tenant 98 (seededTenant, ADR 0358) contra o 99. NUNCA biz=4. Fila `sync` (phpunit.xml).
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
const PCV_DIA = '2026-10-15';

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('pcp_programacoes') || ! Schema::hasTable('sale_processes')) {
        $this->markTestSkipped('Schema MySQL do PCP/FSM/estoque ausente nesta lane.');
    }
    Carbon::setTestNow(PCV_DIA.' 10:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    (new FsmProcessoVendaComProducaoSeeder)->runForBusiness($this->b98);
    $this->svc = app(ApontamentoOperadorService::class);
    $this->posto = (int) DB::table('pcp_postos')->insertGetId(['business_id' => $this->b98, 'codigo' => 'PCV-'.uniqid(),
        'nome' => 'Acabamento', 'tipo_custo' => 'pessoa', 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now()]);
    $this->diego = pcvUsuario($this->b98, 'diego', ['producao.concluir']);
    $this->semRole = pcvUsuario($this->b98, 'sem-role', []);

    $this->loc = EstoqueFixture::locationId($this->b98, 'pcv');
    $this->prod = EstoqueFixture::singleProduct($this->b98);
    EstoqueFixture::setStock($this->prod, 0, $this->loc, 10);
});

afterEach(fn () => Carbon::setTestNow());

function pcvUsuario(int $biz, string $nome, array $roles): int
{
    $id = (int) DB::table('users')->insertGetId(['first_name' => 'PCV '.$nome, 'username' => 'pcv-'.$nome.'-'.uniqid(),
        'password' => bcrypt('x'), 'business_id' => $biz, 'created_at' => now(), 'updated_at' => now()]);
    foreach ($roles as $r) {
        User::findOrFail($id)->assignRole("{$r}#{$biz}");
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $id;
}

function pcvEtapa(string $chave, int $biz): int
{
    return (int) DB::table('sale_process_stages as s')->join('sale_processes as p', 'p.id', '=', 's.process_id')
        ->where('p.business_id', $biz)->where('p.key', 'venda_com_producao')->where('s.key', $chave)->value('s.id');
}

/** Venda em produção com 3 un. reservadas do produto da fixture. */
function pcvVenda($t, string $etapa = 'in_production', ?int $biz = null): int
{
    $biz ??= $t->b98;
    $id = (int) DB::table('transactions')->insertGetId(['business_id' => $biz, 'location_id' => $t->loc, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 30,
        'created_by' => $t->diego, 'essentials_duration' => 0, 'current_stage_id' => pcvEtapa($etapa, $biz),
        'created_at' => now(), 'updated_at' => now()]);
    StockReservation::query()->withoutGlobalScopes()->create(['business_id' => $biz, 'transaction_id' => $id, // SUPERADMIN: fixture
        'product_id' => $t->prod->productId, 'variation_id' => $t->prod->variations[0]['variation_id'],
        'location_id' => $t->loc, 'qty_reserved' => 3, 'status' => StockReservation::STATUS_ACTIVE]);

    return $id;
}

function pcvProgramar($t, int $venda, int $userId, string $hora = '08:00:00'): int
{
    return (int) Programacao::query()->create(['business_id' => $t->b98, 'os_tipo' => Programacao::OS_VENDA, 'os_id' => $venda,
        'posto_id' => $t->posto, 'inicio_previsto' => PCV_DIA.' '.$hora, 'fim_previsto' => Carbon::parse(PCV_DIA.' '.$hora)->addHour(),
        'status' => 'pendente', 'user_id' => $userId, 'created_by' => $userId])->id;
}

/** Inicia e conclui a etapa pelo serviço do apontamento (o mesmo caminho do app e da PWA). */
function pcvApontar($t, int $etapa, int $userId): void
{
    $ini = $t->svc->iniciar($t->b98, $userId, $etapa, 'pcv-i-'.$etapa, Carbon::parse(PCV_DIA.' 09:00:00'));
    $t->svc->concluir($t->b98, $userId, $ini->id, 'pcv-f-'.$etapa, Carbon::parse(PCV_DIA.' 09:30:00'));
}

function pcvEtapaDaVenda(int $venda): ?string
{
    return DB::table('transactions as t')->join('sale_process_stages as s', 's.id', '=', 't.current_stage_id')
        ->where('t.id', $venda)->value('s.key');
}

function pcvSaldo($t): float
{
    return EstoqueFixture::currentStock($t->prod, 0, $t->loc);
}

function pcvTrilha(int $biz, int $venda): array
{
    return DB::table('sale_stage_history')->where('business_id', $biz)->where('transaction_id', $venda)->orderBy('id')
        ->pluck('payload_snapshot')->map(fn ($p) => json_decode((string) $p, true) ?: [])->all();
}

it('conclui sozinho: última etapa do PCP concluída leva a venda a ready_for_invoice e baixa o estoque uma vez', function () {
    $venda = pcvVenda($this);
    pcvApontar($this, pcvProgramar($this, $venda, $this->diego), $this->diego);

    expect(pcvEtapaDaVenda($venda))->toBe('ready_for_invoice')
        ->and(pcvSaldo($this))->toBe(7.0);

    $trilha = pcvTrilha($this->b98, $venda);
    $impacto = collect($trilha)->firstWhere('tipo', ProducaoDaVendaService::TIPO_IMPACTO);
    expect(collect($trilha)->where('origem', ProducaoDaVendaService::ORIGEM)->count())->toBe(2)
        ->and($impacto['estoque'][0]['antes'])->toEqual(10)
        ->and($impacto['estoque'][0]['depois'])->toEqual(7)
        ->and($impacto['estoque'][0]['reservado'])->toEqual(3);
});

it('não conclui enquanto houver etapa da venda pendente (venda com vários itens/OPs)', function () {
    $venda = pcvVenda($this);
    $primeira = pcvProgramar($this, $venda, $this->diego);
    pcvProgramar($this, $venda, $this->diego, '11:00:00');
    pcvApontar($this, $primeira, $this->diego);

    expect(pcvEtapaDaVenda($venda))->toBe('in_production')
        ->and(pcvSaldo($this))->toBe(10.0)
        ->and(pcvTrilha($this->b98, $venda))->toBe([]);
});

it('não roda com a venda em on_hold', function () {
    $venda = pcvVenda($this, 'on_hold');
    $etapa = pcvProgramar($this, $venda, $this->diego);
    DB::table('pcp_programacoes')->where('id', $etapa)->update(['status' => 'concluida']);

    expect(app(ProducaoDaVendaService::class)->concluirSeTerminou($this->b98, $venda, $this->diego, $etapa))->toBe('nada')
        ->and(pcvEtapaDaVenda($venda))->toBe('on_hold')
        ->and(pcvSaldo($this))->toBe(10.0);
});

it('idempotente: rodar de novo depois de concluída não baixa o estoque em dobro', function () {
    $venda = pcvVenda($this);
    $etapa = pcvProgramar($this, $venda, $this->diego);
    pcvApontar($this, $etapa, $this->diego);
    $linhas = count(pcvTrilha($this->b98, $venda));

    expect(app(ProducaoDaVendaService::class)->concluirSeTerminou($this->b98, $venda, $this->diego, $etapa))->toBe('nada')
        ->and(pcvSaldo($this))->toBe(7.0)
        ->and(count(pcvTrilha($this->b98, $venda)))->toBe($linhas);
});

it('sem a role producao.concluir não contorna o RBAC: grava conclusao_pendente uma vez e não baixa', function () {
    $venda = pcvVenda($this);
    $etapa = pcvProgramar($this, $venda, $this->semRole);
    pcvApontar($this, $etapa, $this->semRole);
    app(ProducaoDaVendaService::class)->concluirSeTerminou($this->b98, $venda, $this->semRole, $etapa);

    $trilha = pcvTrilha($this->b98, $venda);
    expect(pcvEtapaDaVenda($venda))->toBe('in_production')
        ->and(pcvSaldo($this))->toBe(10.0)
        ->and($trilha)->toHaveCount(1)
        ->and($trilha[0]['tipo'])->toBe(ProducaoDaVendaService::TIPO_PENDENTE);
    expect($trilha[0]['motivo'])->toContain('falta concluir');
});

it('venda sem roteiro no PCP (só comvis_os) segue manual', function () {
    $venda = pcvVenda($this);
    $comvis = (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $this->b98, 'os_tipo' => 'comvis_os', 'os_id' => $venda,
        'posto_id' => $this->posto, 'inicio_previsto' => PCV_DIA.' 08:00:00', 'fim_previsto' => PCV_DIA.' 09:00:00',
        'status' => 'pendente', 'user_id' => $this->diego, 'created_by' => $this->diego, 'created_at' => now(), 'updated_at' => now()]);
    pcvApontar($this, $comvis, $this->diego);

    expect(pcvEtapaDaVenda($venda))->toBe('in_production')->and(pcvSaldo($this))->toBe(10.0);
});

it('Tier 0: programação os_tipo=venda não aceita venda de outra empresa, e o serviço não age fora do business', function () {
    $b99 = (int) (DB::table('business')->where('id', '!=', $this->b98)->orderBy('id')->value('id') ?? 0);
    if ($b99 === 0) {
        $this->markTestSkipped('Sem segundo business nesta base.');
    }
    $alheia = (int) DB::table('transactions')->insertGetId(['business_id' => $b99, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 1, 'created_by' => $this->diego,
        'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => pcvProgramar($this, $alheia, $this->diego))->toThrow(DomainException::class, 'Venda não encontrada');

    $venda = pcvVenda($this);
    $etapa = pcvProgramar($this, $venda, $this->diego);
    DB::table('pcp_programacoes')->where('id', $etapa)->update(['status' => 'concluida']);
    expect(app(ProducaoDaVendaService::class)->concluirSeTerminou($b99, $venda, $this->diego, $etapa))->toBe('nada')
        ->and(pcvEtapaDaVenda($venda))->toBe('in_production')
        ->and(pcvSaldo($this))->toBe(10.0);
});
