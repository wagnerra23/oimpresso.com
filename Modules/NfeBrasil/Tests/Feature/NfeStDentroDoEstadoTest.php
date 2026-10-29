<?php

declare(strict_types=1);

// @covers-us US-SELL-078 — o ICMS-ST também vale dentro do estado, para contribuinte (pedido P-153).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * P-153 · Sabor Brasil vende tempero para supermercado da mesma UF (SP→SP) com ICMS-ST (CST 10,
 * CFOP 5405). Antes, a emissão só buscava a interna quando o destino era outra UF, e o ST dentro
 * do estado saía sempre 0. Agora a interna da própria UF vai ao motor quando o destinatário é
 * contribuinte (quem revende); consumidor final da mesma UF segue sem ST.
 *
 * O cálculo do ST em si (base pela MVA, ST = base × interna − ICMS próprio) já tem prova no
 * MotorTributarioServiceTest; aqui se prova só que a interna chega — ou não — ao motor.
 *
 * MySQL-only · tenant fictício 98 (`seededTenant`) — nunca biz=1 nem biz=4 (ADR 0358). As linhas
 * criadas levam a fonte `P153-TESTE` e só elas são apagadas.
 */

const P153_FONTE = 'P153-TESTE';

function p153Svc(): NfeService
{
    return new NfeService(\Mockery::mock(CertificadoService::class));
}

function p153Linha(string $origem, string $destino, ?float $interna): void
{
    DB::table('nfe_icms_uf')->insert([
        'business_id' => test()->seededTenant()->id, 'uf_origem' => $origem, 'uf_destino' => $destino,
        'aliquota_interestadual' => $origem === $destino ? null : 0.12, 'aliquota_interna' => $interna,
        'fonte' => P153_FONTE, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_icms_uf')) {
        $this->markTestSkipped('MySQL-only: tabela nfe_icms_uf vem da migração 2026_10_07_000003.');
    }
    // Só as linhas deste teste. A busca pega a vigente mais nova (`orderByDesc('id')`), então a
    // linha inserida aqui ganha de qualquer outra do tenant para o mesmo par de UFs.
    DB::table('nfe_icms_uf')->where('fonte', P153_FONTE)->delete();
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasTable('nfe_icms_uf')) {
        DB::table('nfe_icms_uf')->where('fonte', P153_FONTE)->delete();
    }
    \Mockery::close();
});

it('P-153 · contribuinte da mesma UF: a interna da própria UF vai ao motor', function () {
    p153Linha('SP', 'SP', 0.18);

    expect(p153Svc()->internaDoSt(test()->seededTenant()->id, 'SP', 'SP', true))->toBe(0.18);
});

it('P-153 · consumidor final da mesma UF segue sem ST, como antes', function () {
    p153Linha('SP', 'SP', 0.18);

    expect(p153Svc()->internaDoSt(test()->seededTenant()->id, 'SP', 'SP', false))->toBeNull();
    expect(p153Svc()->internaDoSt(test()->seededTenant()->id, 'SP', 'SP', null))->toBeNull();
});

it('P-153 · contribuinte da mesma UF sem a interna cadastrada: sem ST (o motor não inventa número)', function () {
    p153Linha('SP', 'SP', null);

    expect(p153Svc()->internaDoSt(test()->seededTenant()->id, 'SP', 'SP', true))->toBeNull();
});

it('P-153 · controle: outra UF continua lendo a interna do destino, contribuinte ou não', function () {
    p153Linha('SP', 'RJ', 0.20);

    expect(p153Svc()->internaDoSt(test()->seededTenant()->id, 'SP', 'RJ', true))->toBe(0.2);
    expect(p153Svc()->internaDoSt(test()->seededTenant()->id, 'SP', 'RJ', false))->toBe(0.2);
});

it('P-153 · a interna é da empresa: a linha de outro business não vale', function () {
    // Valor que só a linha do tenant 98 tem; o 99 pode ter a dele, mas nunca este.
    p153Linha('SP', 'SP', 0.1777);

    expect(p153Svc()->internaDoSt(test()->seededTenant()->id, 'SP', 'SP', true))->toBe(0.1777);
    expect(p153Svc()->internaDoSt(test()->seededSupportClientTenant()->id, 'SP', 'SP', true))->not->toBe(0.1777);
});
