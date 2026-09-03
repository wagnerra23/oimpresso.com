<?php

declare(strict_types=1);

// @covers-us US-NFE-006 — sinal de saúde da SEFAZ que SUGERE contingência.
// Contrato: memory/requisitos/NfeBrasil/adr/tech/0002-contingencia-epec-fsda-retentativa-ordenada.md
// §"Detecção: híbrida" — "3 falhas consecutivas → grava sefaz_status.degraded"; "Auto-ativação: rejeitada".

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeSefazStatus;
use Modules\NfeBrasil\Services\SefazStatusRecorder;

uses(Tests\TestCase::class);

/**
 * US-NFE-006 fase 3 — persistir a observação da SEFAZ.
 *
 * O QUE ESTE ARQUIVO DEFENDE
 * --------------------------
 * 1. Que o contador de falhas SUBA e, principalmente, que ZERE no sucesso — alarme que
 *    não desarma vira ruído que se aprende a ignorar.
 * 2. Que `last_response_ms` fique NULL na falha, nunca 0: não houve resposta, e 0 diria
 *    "respondeu instantaneamente" (o fail-open de proibicoes.md §5, 2026-07-29).
 * 3. Que observar NÃO ative contingência de ninguém — a ADR rejeitou auto-ativação.
 *
 * MYSQL-ONLY como o resto do módulo: a tabela tem PK natural (char) e defaults que o
 * sqlite :memory: não reproduz fielmente (ver nfebrasil-pest.yml).
 */
const SSR_UF = 'SC';
const SSR_BIZ = 1;

function ssrLimpar(): void
{
    DB::table('nfe_sefaz_status')->whereIn('uf', [SSR_UF, 'RS'])->delete();
    DB::table('nfe_business_configs')->where('business_id', SSR_BIZ)->delete();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: PK natural char(2) + defaults reais (ver nfebrasil-pest.yml).');
    }
    if (! Schema::hasTable('nfe_sefaz_status')) {
        $this->markTestSkipped('nfe_sefaz_status ausente — rode as migrations do NfeBrasil (US-NFE-006 fase 1).');
    }
    ssrLimpar();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_sefaz_status')) {
        return;
    }
    ssrLimpar();
});

describe('US-NFE-006 · sinal de saúde da SEFAZ (ADR TECH-0002)', function () {

    it('UC-CONT-20 · sucesso grava verde, zera falhas e registra a latência em ms', function () {
        $row = app(SefazStatusRecorder::class)->registrarSucesso(SSR_UF, 0.432);

        expect($row->status)->toBe(NfeSefazStatus::VERDE);
        expect($row->consecutive_failures)->toBe(0);
        expect($row->last_response_ms)->toBe(432);   // segundos → ms
        expect($row->last_check_at)->not->toBeNull();
    });

    it('UC-CONT-21 · 1 falha é amarelo e NÃO sugere contingência — 1 timeout é ruído', function () {
        $row = app(SefazStatusRecorder::class)->registrarFalha(SSR_UF);

        expect($row->status)->toBe(NfeSefazStatus::AMARELO);
        expect($row->consecutive_failures)->toBe(1);
        expect($row->sugereContingencia())->toBeFalse();
        // NULL, não 0: não houve resposta. 0 diria "respondeu instantaneamente".
        expect($row->last_response_ms)->toBeNull();
    });

    it('UC-CONT-22 · 3 falhas seguidas viram vermelho e passam a SUGERIR contingência', function () {
        $r = app(SefazStatusRecorder::class);
        $r->registrarFalha(SSR_UF);
        $r->registrarFalha(SSR_UF);
        $row = $r->registrarFalha(SSR_UF);

        expect($row->consecutive_failures)->toBe(3);
        expect($row->status)->toBe(NfeSefazStatus::VERMELHO);
        expect($row->sugereContingencia())->toBeTrue();
    });

    it('UC-CONT-23 · sucesso DEPOIS de falhas desarma o alarme (senão vira ruído permanente)', function () {
        $r = app(SefazStatusRecorder::class);
        $r->registrarFalha(SSR_UF);
        $r->registrarFalha(SSR_UF);
        $r->registrarFalha(SSR_UF);

        $row = $r->registrarSucesso(SSR_UF, 0.1);

        expect($row->consecutive_failures)->toBe(0);
        expect($row->status)->toBe(NfeSefazStatus::VERDE);
        expect($row->sugereContingencia())->toBeFalse();
    });

    it('UC-CONT-24 · a UF é normalizada — "sc" e "SC" são a MESMA linha, não duas', function () {
        $r = app(SefazStatusRecorder::class);
        $r->registrarFalha('sc');
        $r->registrarFalha('SC');

        // Se normalizasse errado, viraria 2 linhas com 1 falha cada e o limiar de 3
        // nunca seria atingido — o alarme não tocaria nunca.
        expect(DB::table('nfe_sefaz_status')->where('uf', 'SC')->count())->toBe(1);
        expect((int) DB::table('nfe_sefaz_status')->where('uf', 'SC')->first()->consecutive_failures)->toBe(2);
    });

    it('CONTROLE NEGATIVO · [T0] observar NÃO ativa contingência de tenant nenhum', function () {
        DB::table('nfe_business_configs')->insert([
            'business_id' => SSR_BIZ,
            'regime' => 'simples',
            'tributacao_default' => json_encode(['cfop' => '5102']),
            'em_contingencia' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $r = app(SefazStatusRecorder::class);
        $r->registrarFalha(SSR_UF);
        $r->registrarFalha(SSR_UF);
        $r->registrarFalha(SSR_UF);   // vermelho + sugere

        // A ADR TECH-0002 REJEITOU auto-ativação: "pode ativar em falsa-detecção (rede do
        // servidor caiu, não SEFAZ)". Se este assert cair, o sistema passou a mudar o modo
        // de emissão fiscal de uma empresa sozinho — exatamente o que a ADR proibiu.
        expect((bool) DB::table('nfe_business_configs')->where('business_id', SSR_BIZ)->first()->em_contingencia)
            ->toBeFalse();
    });

    it('UC-CONT-25 · UFs diferentes são linhas independentes (falha em SC não suja RS)', function () {
        $r = app(SefazStatusRecorder::class);
        $r->registrarFalha(SSR_UF);
        $r->registrarSucesso('RS', 0.2);

        expect(NfeSefazStatus::find('SC')->consecutive_failures)->toBe(1);
        expect(NfeSefazStatus::find('RS')->consecutive_failures)->toBe(0);
        expect(NfeSefazStatus::find('RS')->status)->toBe(NfeSefazStatus::VERDE);
    });
});
