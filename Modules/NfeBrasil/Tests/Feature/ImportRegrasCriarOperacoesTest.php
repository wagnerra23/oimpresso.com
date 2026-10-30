<?php

declare(strict_types=1);

// @covers UC-NFIM-07 — --criar-operacoes: cria a operação citada no CSV, só com --apply, uma vez, só na empresa pedida.

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/** MySQL-only · tenant fictício 98 × 99 (ADR 0358). Nunca biz=1 nem biz=4. */
const OP_CSV = __DIR__ . '/../../../../scripts/legacy-migration/data/sabor-brasil-regras-fiscais-bonificacao.csv';

function opContar(int $biz): int
{
    return DB::table('nfe_operacoes_fiscais')->where('business_id', $biz)->where('slug', 'bonificacao')->count();
}

function opLimpar(): void
{
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_operacoes_fiscais')) {
        return;
    }
    $ids = [test()->seededTenant()->id, test()->seededSupportClientTenant()->id];
    $op = DB::table('nfe_operacoes_fiscais')->whereIn('business_id', $ids)->where('slug', 'bonificacao')->pluck('id');
    DB::table('nfe_fiscal_rules')->whereIn('operacao_id', $op)->delete();
    DB::table('nfe_operacoes_fiscais')->whereIn('id', $op)->delete();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: nfe_operacoes_fiscais.');
    }
    opLimpar();
});

afterEach(fn () => opLimpar());

it('UC-NFIM-07 · dry-run lista a operação a criar e não grava nada', function () {
    $biz = test()->seededTenant()->id;

    $codigo = Artisan::call('nfe:importar-regras-csv', ['business' => $biz, 'arquivo' => OP_CSV, '--criar-operacoes' => true]);

    expect($codigo)->toBe(0);
    expect(Artisan::output())->toContain('criaria operação bonificacao (Bonificação)')->toContain('DRY-RUN');
    expect(opContar($biz))->toBe(0);
});

it('UC-NFIM-07 · --apply cria a operação uma vez só e não toca a outra empresa', function () {
    $biz = test()->seededTenant()->id;
    $outra = test()->seededSupportClientTenant()->id;

    foreach ([1, 2] as $_) {
        Artisan::call('nfe:importar-regras-csv', ['business' => $biz, 'arquivo' => OP_CSV, '--apply' => true, '--criar-operacoes' => true]);
    }

    expect(opContar($biz))->toBe(1);
    expect(opContar($outra))->toBe(0);
    expect(DB::table('nfe_operacoes_fiscais')->where('business_id', $biz)->where('slug', 'bonificacao')->value('nome'))->toBe('Bonificação');
    expect(DB::table('nfe_fiscal_rules')->where('business_id', $outra)->whereNotNull('operacao_id')->count())->toBe(0);
});

it('UC-NFIM-07 · sem --criar-operacoes a operação ausente continua falhando', function () {
    $biz = test()->seededTenant()->id;

    Artisan::call('nfe:importar-regras-csv', ['business' => $biz, 'arquivo' => OP_CSV, '--apply' => true]);

    expect(opContar($biz))->toBe(0);
});
