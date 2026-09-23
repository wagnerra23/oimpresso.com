<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\PlanoConta;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\DreService;

uses(Tests\TestCase::class);

/**
 * De-para explícito conta → linha da DRE (`fin_planos_conta.dre_linha`, decisão [W]
 * 2026-09-23).
 *
 * Origem: a WR2 usa o plano de contas legado (`1.x` entradas / `2.x` saídas) e a DRE só
 * reconhecia o prefixo BR (`3.1.01.` / `3.1.02.` / `4.` / `5.`) — veio zerada, com o
 * aviso "categorias não mapeadas", apesar de 108 títulos no mês.
 *
 * Regra mestre de valor: cada caso confere o total da DRE contra a SOMA INDEPENDENTE
 * dos títulos da fixture (dois caminhos). Tenant 98 (ADR 0358), mês isolado (2031-01)
 * e transação desfeita no fim — `fin_titulos` não aceita delete.
 */

const DRE_DEPARA_MES = '2031-01';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('DreService usa schema MySQL do Financeiro.');
    }
    if (! Schema::hasColumn('fin_planos_conta', 'dre_linha')) {
        $this->markTestSkipped('Coluna dre_linha ausente — rode migrate.');
    }

    $biz = $this->seededTenant();
    $this->bizId = (int) $biz->id;
    $this->userId = (int) (User::where('business_id', $this->bizId)->value('id') ?? 0);
    if ($this->userId === 0) {
        $this->markTestSkipped('Sem usuário no tenant de teste.');
    }
    session(['user.business_id' => $this->bizId, 'business.id' => $this->bizId]);

    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function dreDeParaPlano(int $biz, string $codigo, string $tipo, ?string $dreLinha): PlanoConta
{
    return PlanoConta::create([
        'business_id' => $biz,
        'codigo' => $codigo,
        'nome' => 'ZZ DE-PARA '.$codigo,
        'tipo' => $tipo,
        'nivel' => substr_count($codigo, '.') + 1,
        'natureza' => $tipo === 'receita' ? 'credito' : 'debito',
        'dre_linha' => $dreLinha,
        'aceita_lancamento' => true,
        'protegido' => false,
        'ativo' => true,
    ]);
}

function dreDeParaTitulo(int $biz, int $userId, int $planoId, string $tipo, float $valor): void
{
    Titulo::create([
        'business_id' => $biz,
        'numero' => 'ZZDP-'.uniqid(),
        'tipo' => $tipo,
        'status' => 'aberto',
        'cliente_descricao' => 'fixture de-para DRE',
        'valor_total' => $valor,
        'valor_aberto' => $valor,
        'moeda' => 'BRL',
        'emissao' => '2031-01-10',
        'vencimento' => '2031-01-20',
        'competencia_mes' => DRE_DEPARA_MES,
        'origem' => 'manual',
        'plano_conta_id' => $planoId,
        'created_by' => $userId,
    ]);
}

function dreDeParaLinha(array $dre, string $label): float
{
    foreach ($dre['linhas'] as $l) {
        if (($l['label'] ?? null) === $label) {
            return (float) $l['v'];
        }
    }

    throw new RuntimeException("Linha '{$label}' não existe na DRE.");
}

function dreDeParaSomaIndependente(int $biz, int $planoId): float
{
    return (float) DB::table('fin_titulos')
        ->where('business_id', $biz)
        ->where('competencia_mes', DRE_DEPARA_MES)
        ->where('plano_conta_id', $planoId)
        ->where('status', '!=', 'cancelado')
        ->sum('valor_total');
}

it('conta legada SEM de-para não entra na DRE e liga o aviso (o sintoma medido)', function () {
    $plano = dreDeParaPlano($this->bizId, 'ZZ1.2.1', 'receita', null);
    dreDeParaTitulo($this->bizId, $this->userId, $plano->id, 'receber', 100.10);
    dreDeParaTitulo($this->bizId, $this->userId, $plano->id, 'receber', 50.05);

    $dre = app(DreService::class)->montar($this->bizId, 'mes', DRE_DEPARA_MES);

    expect(dreDeParaLinha($dre, 'Receita operacional bruta'))->toBe(0.0);
    expect($dre['meta']['aviso_sem_mapping'])->toBeTrue();
});

it('conta legada com dre_linha=receita_bruta entra na receita bruta pelo valor exato', function () {
    $plano = dreDeParaPlano($this->bizId, 'ZZ1.2.1', 'receita', 'receita_bruta');
    dreDeParaTitulo($this->bizId, $this->userId, $plano->id, 'receber', 100.10);
    dreDeParaTitulo($this->bizId, $this->userId, $plano->id, 'receber', 50.05);

    $dre = app(DreService::class)->montar($this->bizId, 'mes', DRE_DEPARA_MES);

    // Dois caminhos: valor escrito à mão (150.15) e SUM independente no banco.
    expect(dreDeParaLinha($dre, 'Receita operacional bruta'))->toBe(150.15);
    expect(dreDeParaSomaIndependente($this->bizId, $plano->id))->toBe(150.15);
    expect(dreDeParaLinha($dre, 'Resultado operacional'))->toBe(150.15);
    expect($dre['meta']['aviso_sem_mapping'])->toBeFalse();
    // Top categorias de receita usa o MESMO critério (2º chamador de itemsPorPrefix).
    expect(collect($dre['top_categorias_receita'])->pluck('label')->all())->toContain('ZZ DE-PARA ZZ1.2.1');
});

it('conta legada com dre_linha=despesas entra negativa nas despesas operacionais', function () {
    $rec = dreDeParaPlano($this->bizId, 'ZZ1.2.1', 'receita', 'receita_bruta');
    $desp = dreDeParaPlano($this->bizId, 'ZZ2.3.3', 'despesa', 'despesas');
    dreDeParaTitulo($this->bizId, $this->userId, $rec->id, 'receber', 1000.00);
    dreDeParaTitulo($this->bizId, $this->userId, $desp->id, 'pagar', 230.40);

    $dre = app(DreService::class)->montar($this->bizId, 'mes', DRE_DEPARA_MES);

    expect(dreDeParaLinha($dre, '(−) Despesas operacionais'))->toBe(-230.40);
    expect(dreDeParaLinha($dre, 'Resultado operacional'))->toBe(769.60);
});

it('dre_linha=fora tira a conta da DRE mas conta como mapeada', function () {
    $plano = dreDeParaPlano($this->bizId, 'ZZ17', 'receita', 'fora');
    dreDeParaTitulo($this->bizId, $this->userId, $plano->id, 'receber', 300.00);

    $dre = app(DreService::class)->montar($this->bizId, 'mes', DRE_DEPARA_MES);

    expect(dreDeParaLinha($dre, 'Receita operacional bruta'))->toBe(0.0);
    expect(dreDeParaLinha($dre, 'Resultado operacional'))->toBe(0.0);
    expect($dre['meta']['aviso_sem_mapping'])->toBeFalse();
});

it('conta do padrão BR sem de-para segue caindo pelo prefixo (regressão)', function () {
    $plano = dreDeParaPlano($this->bizId, '3.1.01.9'.random_int(100, 999), 'receita', null);
    dreDeParaTitulo($this->bizId, $this->userId, $plano->id, 'receber', 42.00);

    $dre = app(DreService::class)->montar($this->bizId, 'mes', DRE_DEPARA_MES);

    expect(dreDeParaLinha($dre, 'Receita operacional bruta'))->toBe(42.0);
    expect($dre['meta']['aviso_sem_mapping'])->toBeFalse();
});
