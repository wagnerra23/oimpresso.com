<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — tela de naturezas de operação na Tributação, onda 3a (RUNBOOK-tributacao §Naturezas).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Casos derivados do protótipo aprovado (`prototipo-ui/cowork/Felipe/Configuracao de Tributos.html`,
 * funções `vazia()` e `validar()`) e do plano da onda 3 (não muda imposto), não do controller.
 * Rotas: MySQL-only, tenant fictício 98 e adversário 99 (ADR 0358).
 */

const NOT_URL = '/nfe-brasil/tributacao/naturezas';

/** A natureza vazia do protótipo (`vazia()`), Lucro Presumido. */
function notrNatureza(array $mudar = []): array
{
    $n = [
        'nome' => 'Venda de mercadoria de terceiros', 'tipo' => 'S', 'fin' => '1', 'ativo' => true, 'ref' => false,
        'cfop' => ['in' => '5102', 'out' => '6102', 'ext' => ''],
        'icms' => ['cst' => '00', 'csosn' => '102', 'origem' => 'uf', 'p' => 0, 'modbc' => '3', 'red' => 0, 'pDif' => 0,
            'pCredSN' => 0, 'bFrete' => true, 'bIPI' => false, 'bDesc' => true,
            'st' => ['modbcst' => '4', 'mvaOrig' => 'produto', 'mva' => 0, 'redB' => 0], 'difal' => true, 'fcp' => true],
        'ipi' => ['on' => false, 'cst' => '53', 'cEnq' => '999', 'origem' => 'produto', 'p' => 0],
        'pis' => ['cst' => '01', 'cstSN' => '49', 'origem' => 'regime', 'p' => 0, 'exclICMS' => true],
        'cofins' => ['cst' => '01', 'cstSN' => '49', 'origem' => 'regime', 'p' => 0, 'exclICMS' => true],
        'ibs' => ['on' => true, 'cst' => '000', 'cClass' => '000001', 'origem' => 'padrao', 'pUF' => 0.1, 'pMun' => 0, 'pCBS' => 0.9, 'pRed' => 0],
    ];

    return array_replace_recursive($n, $mudar);
}

// --- Rotas (MySQL, tenant 98) -----------------------------------------------------------

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: tenant 98/99 e nfe_operacoes_fiscais com as colunas das ondas 2 e 3.');
    }
    if (! Schema::hasColumn('nfe_operacoes_fiscais', 'config_tela')) {
        $this->markTestSkipped('Migração 2026_11_15_000002 não rodou — rode as migrations do módulo.');
    }
    $bizs = [$this->seededTenant()->id, $this->seededSupportClientTenant()->id];
    DB::table('nfe_operacoes_fiscais')->whereIn('business_id', $bizs)->where('slug', 'like', 'tela-%')->delete();
    DB::table('nfe_operacoes_fiscais')->whereIn('business_id', $bizs)->where('slug', 'like', 'not-%')->delete();
    $this->actingAs($this->usuarioComPermissoes(['nfe.tributacao.manage']));
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasColumn('nfe_operacoes_fiscais', 'config_tela')) {
        return;
    }
    $bizs = [$this->seededTenant()->id, $this->seededSupportClientTenant()->id];
    DB::table('nfe_operacoes_fiscais')->whereIn('business_id', $bizs)
        ->where(fn ($q) => $q->where('slug', 'like', 'tela-%')->orWhere('slug', 'like', 'not-%'))->delete();
});

it('UC-NFTR-45 · rota: com a flag desligada a seção não existe (404)', function () {
    config(['feature-flags.forced_on' => '']);

    $this->getJson(NOT_URL)->assertNotFound();
    $this->postJson(NOT_URL, notrNatureza())->assertNotFound();
    expect(DB::table('nfe_operacoes_fiscais')->where('business_id', $this->seededTenant()->id)
        ->where('slug', 'like', 'tela-%')->count())->toBe(0);
});

it('UC-NFTR-46 · rota: salvar grava a forma da tela e um erro de conferência não grava nada', function () {
    config(['feature-flags.forced_on' => 'useV2NfeNaturezas']);
    $biz = $this->seededTenant()->id;

    $this->postJson(NOT_URL, notrNatureza(['fin' => '4', 'cfop' => ['in' => '5202', 'out' => '6202']]))
        ->assertStatus(422)->assertJsonPath('alertas.0.nivel', 'erro');
    expect(DB::table('nfe_operacoes_fiscais')->where('business_id', $biz)->where('slug', 'like', 'tela-%')->count())->toBe(0);

    $id = $this->postJson(NOT_URL, notrNatureza(['nome' => 'Bonificacao']))->assertCreated()->json('id');
    $row = DB::table('nfe_operacoes_fiscais')->where('id', $id)->first();

    // O slug nunca coincide com o que a emissão procura pelo nome (`bonificacao`).
    expect((int) $row->business_id)->toBe($biz)
        ->and($row->slug)->toBe('tela-bonificacao')
        ->and($row->cfop)->toBe('5102')
        ->and($row->cfop_interestadual)->toBe('6102')
        ->and($row->regra_geral)->toBeNull()
        ->and((bool) $row->padrao)->toBeFalse()
        ->and(json_decode($row->config_tela, true)['ibs']['cClass'])->toBe('000001');
});

it('UC-NFTR-47 · rota: natureza que o cálculo já usa não é editada pela tela (409, nada muda)', function () {
    config(['feature-flags.forced_on' => 'useV2NfeNaturezas']);
    $geral = json_encode(['cfop' => '5910', 'csosn' => '900', 'aliquota_icms' => 0.0]);
    $id = (int) DB::table('nfe_operacoes_fiscais')->insertGetId([
        'business_id' => $this->seededTenant()->id, 'slug' => 'not-csv', 'nome' => 'Vinda do CSV',
        'finalidade' => 1, 'cfop' => '5910', 'regra_geral' => $geral, 'padrao' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $antes = (array) DB::table('nfe_operacoes_fiscais')->where('id', $id)->first();

    $this->putJson(NOT_URL . "/{$id}", notrNatureza(['nome' => 'Outro nome']))->assertStatus(409);

    expect((array) DB::table('nfe_operacoes_fiscais')->where('id', $id)->first())->toBe($antes);
});

it('UC-NFTR-48 · rota: natureza de outra empresa não aparece nem é editada', function () {
    config(['feature-flags.forced_on' => 'useV2NfeNaturezas']);
    $daOutra = (int) DB::table('nfe_operacoes_fiscais')->insertGetId([
        'business_id' => $this->seededSupportClientTenant()->id, 'slug' => 'not-vizinha', 'nome' => 'Da vizinha',
        'finalidade' => 1, 'cfop' => '5102', 'padrao' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $ids = collect($this->getJson(NOT_URL)->assertOk()->json('naturezas'))->pluck('id')->all();
    expect($ids)->not->toContain($daOutra);

    $this->putJson(NOT_URL . "/{$daOutra}", notrNatureza())->assertNotFound();
    expect(DB::table('nfe_operacoes_fiscais')->where('id', $daOutra)->value('nome'))->toBe('Da vizinha');
});
