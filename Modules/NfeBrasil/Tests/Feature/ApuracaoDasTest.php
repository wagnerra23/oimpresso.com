<?php

declare(strict_types=1);

// @covers-us US-FISCAL-030 — Apuração do DAS pelo Anexo (LC 123/2006 art. 18 §1º e §1º-A, Anexos I a V na redação da LC 155/2016).

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\Tributacao\ApuracaoDasService;

uses(Tests\TestCase::class);

/**
 * UC-DAS-01..05 (memory/requisitos/Fiscal/_telas/apuracao-das.casos.md).
 *
 * Leiaute legal citado: LC 123/2006, Anexos I a V, redação da LC 155/2016 (vigente desde
 * 01/01/2018). Dupla prova de valor: (1) contas feitas à mão, com a conta no comentário;
 * (2) continuidade das faixas tirada da própria tabela — na fronteira das faixas 1 a 5,
 * RBT12 × Aliq − PD tem de dar o mesmo valor nas duas faixas vizinhas, então um dígito
 * errado na tabela quebra este teste sem depender das contas do item (1).
 *
 * Tenant 98 (ADR 0358) · 99 como adversário cross-tenant.
 */
beforeEach(function () {
    $this->svc = new ApuracaoDasService();
});

it('UC-DAS-01 Anexo I, RBT12 500.000, mês 50.000 → faixa 3, efetiva 6,728%, DAS 3.364,00', function () {
    // (500.000 × 9,50% − 13.860) ÷ 500.000 = 33.640 ÷ 500.000 = 6,728% · 50.000 × 6,728% = 3.364,00
    $r = $this->svc->calcular('I', 500000, 50000);

    expect($r['faixa'])->toBe(3);
    expect($r['aliquota_nominal'])->toBe(0.095);
    expect($r['parcela_deduzir'])->toBe(13860.0);
    expect($r['aliquota_efetiva'])->toBe(0.06728);
    expect($r['das'])->toBe(3364.0);
    expect($r['acima_sublimite'])->toBeFalse();
});

it('UC-DAS-01 Anexo III, RBT12 280.000, mês 25.000 → faixa 2, DAS 1.964,29 sem arredondar a efetiva no meio', function () {
    // (280.000 × 11,20% − 9.360) ÷ 280.000 = 22.000 ÷ 280.000 = 7,857142…% · 25.000 × 22.000 ÷ 280.000 = 1.964,2857 → 1.964,29
    $r = $this->svc->calcular('III', 280000, 25000);

    expect($r['faixa'])->toBe(2);
    expect($r['aliquota_efetiva'])->toBe(0.078571);
    expect($r['das'])->toBe(1964.29);
});

it('UC-DAS-01 Anexo V, RBT12 1.000.000, mês 80.000 → faixa 4, efetiva 18,79%, DAS 15.032,00', function () {
    // (1.000.000 × 20,50% − 17.100) ÷ 1.000.000 = 18,79% · 80.000 × 18,79% = 15.032,00
    $r = $this->svc->calcular('V', 1000000, 80000);

    expect($r['faixa'])->toBe(4);
    expect($r['das'])->toBe(15032.0);
});

it('UC-DAS-02 RBT12 exatamente 180.000 é faixa 1 ("até"); 1 centavo acima é faixa 2 com a mesma efetiva', function () {
    $ate = $this->svc->calcular('I', 180000, 10000);
    $acima = $this->svc->calcular('I', 180000.01, 10000);

    expect($ate['faixa'])->toBe(1);
    expect($ate['das'])->toBe(400.0);   // 10.000 × 4%
    expect($acima['faixa'])->toBe(2);
    expect($acima['aliquota_efetiva'])->toBe(0.04);
    expect($acima['das'])->toBe(400.0);
});

it('UC-DAS-02 segunda prova: a tabela é contínua na fronteira das faixas 1 a 5 em todos os anexos', function () {
    $conferidas = 0;
    foreach (ApuracaoDasService::ANEXOS as $anexo => $faixas) {
        for ($i = 0; $i < 4; $i++) {
            [$limite, $aliq, $pd] = $faixas[$i];
            [, $aliqProx, $pdProx] = $faixas[$i + 1];

            // Em centavos: limite × aliq(centésimos de ponto) ÷ 100 − PD × 100.
            $aqui = $limite * $aliq / 100 - $pd * 100;
            $proxima = $limite * $aliqProx / 100 - $pdProx * 100;
            expect($proxima)->toBe($aqui, "Anexo {$anexo}, fronteira da faixa ".($i + 1));
            $conferidas++;
        }
    }

    expect($conferidas)->toBe(20);
});

it('UC-DAS-03 acima de 3,6 milhões marca o sublimite; acima de 4,8 milhões não calcula', function () {
    $sub = $this->svc->calcular('I', 3600000.01, 100000);
    $fora = $this->svc->calcular('I', 4800000.01, 100000);

    expect($sub['acima_sublimite'])->toBeTrue();
    expect($sub['faixa'])->toBe(6);
    expect($fora['das'])->toBeNull();
    expect($fora['motivo'])->toContain('fora do Simples');
});

it('UC-DAS-04 sem RBT12 (início de atividade) não inventa valor e anexo inválido é recusado', function () {
    $r = $this->svc->calcular('III', 0, 20000);

    expect($r['das'])->toBeNull();
    expect($r['motivo'])->toContain('art. 18 §2º');
    expect(fn () => $this->svc->calcular('VI', 100000, 1000))->toThrow(InvalidArgumentException::class);
});

describe('apurar() lê transactions do próprio tenant', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('MySQL: transactions real exige location/contact/created_by; o cálculo puro acima roda nos dois lanes.');
        }

        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function ($t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('type');
                $t->string('status');
                $t->dateTime('transaction_date');
                $t->decimal('final_total', 22, 4)->default(0);
            });
        }

        $linha = fn (int $biz, string $type, string $data, float $total, string $status = 'final') => DB::table('transactions')->insert([
            'business_id' => $biz, 'type' => $type, 'status' => $status,
            'transaction_date' => $data, 'final_total' => $total,
        ]);

        // Tenant 98 — competência 2026-10: RBT12 de 2025-10-01 a 2026-09-30.
        $linha(98, 'sell', '2025-10-01 00:00:00', 300000);
        $linha(98, 'sell', '2026-09-30 23:59:59', 220000);
        $linha(98, 'sell_return', '2026-03-10 10:00:00', 20000);
        $linha(98, 'sell', '2025-09-30 23:59:59', 999999);              // antes da janela
        $linha(98, 'sell', '2026-10-01 00:00:00', 30000);               // receita do mês
        $linha(98, 'sell', '2026-10-31 23:59:59', 25000);               // receita do mês
        $linha(98, 'sell_return', '2026-10-20 00:00:00', 5000);         // devolução do mês
        $linha(98, 'sell', '2026-11-01 00:00:00', 999999);              // mês seguinte
        $linha(98, 'sell', '2026-10-05 00:00:00', 777777, 'draft');     // rascunho não é receita

        $linha(99, 'sell', '2026-01-15 00:00:00', 4000000);            // adversário
        $linha(99, 'sell', '2026-10-15 00:00:00', 900000);
    });

    it('UC-DAS-05 RBT12 dos 12 meses anteriores e receita do mês, sem o tenant 99', function () {
        // RBT12 = 300.000 + 220.000 − 20.000 = 500.000 · mês = 30.000 + 25.000 − 5.000 = 50.000
        $r = $this->svc->apurar(98, CarbonImmutable::parse('2026-10-15'), 'I');

        expect($r['rbt12'])->toBe(500000.0);
        expect($r['receita_mes'])->toBe(50000.0);
        expect($r['das'])->toBe(3364.0);
        expect($r['competencia'])->toBe('2026-10');
        expect($r['rbt12_de'])->toBe('2025-10-01');
        expect($r['rbt12_ate'])->toBe('2026-09-30');
    });
});
