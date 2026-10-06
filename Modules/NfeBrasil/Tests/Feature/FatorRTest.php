<?php

declare(strict_types=1);

// @covers-us R-NFE-029 — Fator R (LC 123/2006 art. 18 §5º-J/§5º-K/§5º-M/§24): folha ÷ receita 12m, ≥28% Anexo III, <28% Anexo V, aviso ao cruzar.

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\FatorRService;

uses(Tests\TestCase::class);

/**
 * R-NFE-029 · playbook Fiscal thread 24 · UC-TRB-22.
 *
 * Números do aceite = bateria C26/C27 do protótipo (`fiscal-tributacao.jsx`,
 * `trFatorR`). Tenant 98 (ADR 0358) · 99 como adversário cross-tenant.
 *
 * O bloco de `apurar()` cria o schema mínimo de `transactions` quando roda no
 * lane sqlite :memory: (modules-pest). No MySQL a tabela real exige
 * location/created_by/contact — o bloco declara o skip com o motivo e as
 * asserções puras seguem rodando.
 */
beforeEach(function () {
    $this->svc = new FatorRService();
});

it('R-NFE-029 folha 84.000 / receita 280.000 = 30% → Anexo III (bateria C26)', function () {
    $r = $this->svc->calcular(84000, 280000);

    expect($r['razao'])->toBe(0.3);
    expect($r['anexo'])->toBe('III');
    expect($r['sem_dado'])->toBeFalse();
});

it('R-NFE-029 folha 50.000 / receita 280.000 = 17,86% → Anexo V (bateria C27)', function () {
    $r = $this->svc->calcular(50000, 280000);

    expect($r['razao'])->toBe(0.1786);
    expect($r['anexo'])->toBe('V');
});

it('R-NFE-029 exatamente 28% é Anexo III ("igual ou superior", §5º-J) e 1 centavo abaixo é V', function () {
    expect($this->svc->calcular(78400, 280000)['anexo'])->toBe('III');
    expect($this->svc->calcular(78399.99, 280000)['anexo'])->toBe('V');
});

it('R-NFE-029 controle positivo: receita zero não divide por zero → sem dado', function () {
    $r = $this->svc->calcular(84000, 0);

    expect($r['sem_dado'])->toBeTrue();
    expect($r['razao'])->toBeNull();
    expect($r['anexo'])->toBeNull();
});

it('R-NFE-029 mês novo derruba de 29% para 27% → pendência "Fator R abaixo de 28%"', function () {
    $antes = $this->svc->calcular(81200, 280000); // 29%
    $depois = $this->svc->calcular(75600, 280000); // 27%

    expect($antes['anexo'])->toBe('III');
    expect($depois['anexo'])->toBe('V');
    expect($this->svc->pendencia($antes, $depois))->toBe(FatorRService::PENDENCIA_ABAIXO);
    expect(FatorRService::PENDENCIA_ABAIXO)->toBe('Fator R abaixo de 28%');
});

it('R-NFE-029 subir de 27% para 29% avisa o cruzamento; sem mudança ou sem dado não avisa', function () {
    $v = $this->svc->calcular(75600, 280000);
    $iii = $this->svc->calcular(81200, 280000);
    $semDado = $this->svc->calcular(1000, 0);

    expect($this->svc->pendencia($v, $iii))->toBe(FatorRService::PENDENCIA_ACIMA);
    expect($this->svc->pendencia($iii, $iii))->toBeNull();
    expect($this->svc->pendencia(null, $v))->toBeNull();
    expect($this->svc->pendencia($iii, $semDado))->toBeNull();
});

describe('apurar() lê transactions do próprio tenant', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('MySQL: transactions real exige location/contact/created_by; o cálculo puro acima roda nos dois lanes.');
        }

        Schema::dropIfExists('transactions');
        Schema::create('transactions', function ($t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('type');
            $t->string('status');
            $t->dateTime('transaction_date');
            $t->decimal('final_total', 22, 4)->default(0);
        });

        $linha = fn (int $biz, string $type, string $data, float $total, string $status = 'final') => DB::table('transactions')->insert([
            'business_id' => $biz, 'type' => $type, 'status' => $status,
            'transaction_date' => $data, 'final_total' => $total,
        ]);

        // Tenant 98 — janela de 2026-10: 2025-10-01 .. 2026-09-30.
        $linha(98, 'sell', '2025-10-01 00:00:00', 200000);   // primeiro dia: entra
        $linha(98, 'sell', '2026-09-30 23:59:59', 100000);   // último dia: entra
        $linha(98, 'sell_return', '2026-03-10 10:00:00', 20000);
        $linha(98, 'sell', '2025-09-30 23:59:59', 999999);   // antes da janela
        $linha(98, 'sell', '2026-10-01 00:00:00', 999999);   // a própria competência não entra (§5º-K "anteriores")
        $linha(98, 'sell', '2026-05-05 00:00:00', 555555, 'draft'); // rascunho não é receita
        $linha(98, 'payroll', '2026-01-01 00:00:00', 60000);
        $linha(98, 'payroll', '2026-06-01 00:00:00', 24000);

        // Tenant 99 — adversário: nada disso pode vazar para o 98.
        $linha(99, 'sell', '2026-01-15 00:00:00', 777777);
        $linha(99, 'payroll', '2026-01-15 00:00:00', 777777);
    });

    it('R-NFE-029 soma 12 meses anteriores, desconta devolução e marca folha parcial sem complemento', function () {
        $r = $this->svc->apurar(98, CarbonImmutable::parse('2026-10-15'));

        expect($r['receita'])->toBe(280000.0);   // 200.000 + 100.000 − 20.000
        expect($r['folha'])->toBe(84000.0);      // 60.000 + 24.000
        expect($r['razao'])->toBe(0.3);
        expect($r['anexo'])->toBe('III');
        expect($r['folha_parcial'])->toBeTrue();
        expect($r['de'])->toBe('2025-10-01');
        expect($r['ate'])->toBe('2026-09-30');
    });

    it('R-NFE-029 complemento do §24 (encargos, FGTS, pró-labore) entra na folha e tira o parcial', function () {
        // Folha gerencial 50.000 sozinha daria V; com 28.400 do contador vira 78.400 = 28% → III.
        DB::table('transactions')->where('business_id', 98)->where('type', 'payroll')->delete();
        DB::table('transactions')->insert([
            'business_id' => 98, 'type' => 'payroll', 'status' => 'final',
            'transaction_date' => '2026-02-01 00:00:00', 'final_total' => 50000,
        ]);

        $sem = $this->svc->apurar(98, CarbonImmutable::parse('2026-10-01'));
        $com = $this->svc->apurar(98, CarbonImmutable::parse('2026-10-01'), 28400);

        expect($sem['anexo'])->toBe('V');
        expect($com['folha'])->toBe(78400.0);
        expect($com['anexo'])->toBe('III');
        expect($com['folha_parcial'])->toBeFalse();
    });

    it('R-NFE-029 tenant sem movimento → sem dado, e o 99 não vaza para o 98', function () {
        $vazio = $this->svc->apurar(97, CarbonImmutable::parse('2026-10-01'));
        $adversario = $this->svc->apurar(99, CarbonImmutable::parse('2026-10-01'));

        expect($vazio['sem_dado'])->toBeTrue();
        expect($vazio['anexo'])->toBeNull();
        expect($adversario['receita'])->toBe(777777.0);
        expect($adversario['folha'])->toBe(777777.0);
    });
});
