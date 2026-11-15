<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * financeiro:corrigir-tipo-legado — título do Delphi com TIPO=RECEBIDA que o importador
 * gravou como "pagar" passa a "receber" (achado 2026-11-13, empresa 199: 2.099 títulos).
 *
 * Contrato:
 *  - sem --apply nada muda (dry-run);
 *  - com --apply só o alvo muda: RECEBIDA + legacy_id + tipo pagar, do business informado;
 *  - PAGA do Delphi, título digitado no oimpresso (sem legacy_id) e título de outro business
 *    ficam como estão;
 *  - a soma a receber sobe exatamente o que a soma a pagar desce (regra de cálculo de valor);
 *  - rodar de novo não muda nada (idempotente);
 *  - --business é obrigatório.
 *
 * Tenant 98 (ADR 0358); adversário 99. Tudo dentro de transação desfeita no fim
 * (`fin_titulos` não aceita delete).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Financeiro usa schema MySQL (JSON_EXTRACT/JSON_SET).');
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBiz = (int) (DB::table('business')->where('id', '<>', $this->bizId)->orderByDesc('id')->value('id') ?? 0);
    if ($this->outroBiz === 0) {
        $this->markTestSkipped('Sem segundo business para o controle cross-tenant.');
    }

    $this->userId = (int) (DB::table('users')->where('business_id', $this->bizId)->value('id') ?? 0);
    if ($this->userId === 0) {
        $this->markTestSkipped('Sem usuário no tenant de teste.');
    }
    $GLOBALS['ctlUserId'] = $this->userId;

    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function ctlTitulo(int $biz, string $tipo, ?string $tipoDelphi, float $valor, string $status = 'quitado', bool $legado = true): int
{
    $legacy = $legado ? 'ZZCTL'.Str::random(8) : null;

    return (int) DB::table('fin_titulos')->insertGetId([
        'business_id' => $biz,
        'numero' => 'ZZ-CTL-'.Str::random(10),
        'legacy_id' => $legacy,
        'tipo' => $tipo,
        'status' => $status,
        'valor_total' => $valor,
        'valor_aberto' => $status === 'quitado' ? 0 : $valor,
        'moeda' => 'BRL',
        'emissao' => '2031-01-10',
        'vencimento' => '2031-02-10',
        'competencia_mes' => '2031-02',
        'origem' => 'manual',
        'created_by' => $GLOBALS['ctlUserId'],
        'metadata' => json_encode($tipoDelphi === null ? new stdClass() : ['delphi_tipo_raw' => $tipoDelphi]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function ctlTipo(int $id): string
{
    return (string) DB::table('fin_titulos')->where('id', $id)->value('tipo');
}

function ctlSoma(int $biz, string $tipo): float
{
    return round((float) DB::table('fin_titulos')->where('business_id', $biz)->where('tipo', $tipo)->whereNull('deleted_at')->sum('valor_total'), 2);
}

it('sem --apply não grava nada', function () {
    $recebida = ctlTitulo($this->bizId, 'pagar', 'RECEBIDA', 100.10);

    expect(Artisan::call('financeiro:corrigir-tipo-legado', ['--business' => $this->bizId]))->toBe(0);
    expect(ctlTipo($recebida))->toBe('pagar');
});

it('com --apply corrige só o alvo e as contas batem', function () {
    $quitada = ctlTitulo($this->bizId, 'pagar', 'RECEBIDA', 100.10);
    $cancelada = ctlTitulo($this->bizId, 'pagar', 'RECEBIDA', 50.05, 'cancelado');
    $paga = ctlTitulo($this->bizId, 'pagar', 'PAGA', 30.00);
    $digitado = ctlTitulo($this->bizId, 'pagar', 'RECEBIDA', 20.00, 'aberto', legado: false);
    $outro = ctlTitulo($this->outroBiz, 'pagar', 'RECEBIDA', 70.00);

    $receberAntes = ctlSoma($this->bizId, 'receber');
    $pagarAntes = ctlSoma($this->bizId, 'pagar');

    expect(Artisan::call('financeiro:corrigir-tipo-legado', ['--business' => $this->bizId, '--apply' => true]))->toBe(0);

    expect(ctlTipo($quitada))->toBe('receber');
    expect(ctlTipo($cancelada))->toBe('receber');
    expect(ctlTipo($paga))->toBe('pagar');
    expect(ctlTipo($digitado))->toBe('pagar');
    expect(ctlTipo($outro))->toBe('pagar');

    // Dois caminhos: o que entrou em receber é o que saiu de pagar, e é a soma do alvo.
    expect(round(ctlSoma($this->bizId, 'receber') - $receberAntes, 2))->toBe(150.15);
    expect(round($pagarAntes - ctlSoma($this->bizId, 'pagar'), 2))->toBe(150.15);

    $marca = json_decode((string) DB::table('fin_titulos')->where('id', $quitada)->value('metadata'), true);
    expect($marca['tipo_corrigido']['de'] ?? null)->toBe('pagar');
    expect($marca['tipo_corrigido']['para'] ?? null)->toBe('receber');
    expect($marca['delphi_tipo_raw'] ?? null)->toBe('RECEBIDA');
});

it('rodar de novo não muda nada', function () {
    $quitada = ctlTitulo($this->bizId, 'pagar', 'RECEBIDA', 100.10);
    Artisan::call('financeiro:corrigir-tipo-legado', ['--business' => $this->bizId, '--apply' => true]);
    $depois = DB::table('fin_titulos')->where('id', $quitada)->value('metadata');

    expect(Artisan::call('financeiro:corrigir-tipo-legado', ['--business' => $this->bizId, '--apply' => true]))->toBe(0);
    expect(Artisan::output())->toContain('Nada a corrigir');
    expect(DB::table('fin_titulos')->where('id', $quitada)->value('metadata'))->toBe($depois);
});

it('exige --business', function () {
    expect(Artisan::call('financeiro:corrigir-tipo-legado'))->toBe(1);
});
