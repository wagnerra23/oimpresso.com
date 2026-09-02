<?php

declare(strict_types=1);

// @covers-us US-NFE-006 — Modo contingência (EPEC / off-line NFC-e).
// Contrato: memory/requisitos/NfeBrasil/adr/tech/0002-contingencia-epec-fsda-retentativa-ordenada.md
// Os casos derivam da ADR TECH-0002 (§Schema additions) e da US-NFE-006, NÃO das migrations
// que eu mesmo escrevi — teste derivado da implementação é tautológico (proibicoes.md §5 2026-06-05).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * US-NFE-006 fase 1 (schema) — as colunas que sustentam contingência.
 *
 * POR QUE MYSQL-ONLY
 * ------------------
 * O objeto sob teste É o schema: ENUM, DEFAULT e PRIMARY KEY natural. No lane sqlite
 * (:memory:) o schema é recriado à mão e ENUM vira TEXT sem constraint — o "verde"
 * MENTE, exatamente a razão declarada da lane nfebrasil-pest.yml. Aqui a suíte SKIPa
 * em sqlite em vez de fingir cobertura (LC-13: `0 failed` numa suíte que não rodou).
 *
 * O QUE ESTE ARQUIVO PROVA, E O QUE NÃO PROVA
 * -------------------------------------------
 * PROVA: as colunas existem, os defaults são INERTES (ninguém entra em contingência por
 * efeito da migration) e o ENUM de fato CONSTRANGE.
 * NÃO PROVA: que a emissão usa tp_emis corretamente — isso é da fase de Service, e o
 * teste dela nasce no PR que a construir. Schema verde não é feature entregue.
 *
 * biz=1 é o tenant semeado por pest-mysql-setup. NUNCA biz=4 — ROTA LIVRE / Larissa,
 * cliente real em produção (ADR 0101 / ADR 0358 R6).
 */
const CONT_BIZ = 1;
const CONT_SERIE = '999';   // série reservada ao teste — não colide com sequência real

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped(
            'Schema de contingência (ENUM/DEFAULT/PK natural) só é verificável no MySQL real. '
            . 'No sqlite :memory: o ENUM vira TEXT e o verde seria falso.'
        );
    }
});

/** Insere uma emissão mínima e devolve o id. `numero` alto evita colidir com dado semeado. */
function contInsereEmissao(array $extra = []): int
{
    static $seq = 990001;

    return (int) DB::table('nfe_emissoes')->insertGetId(array_merge([
        'business_id' => CONT_BIZ,
        'transaction_id' => null,
        'modelo' => '65',
        'serie' => CONT_SERIE,
        'numero' => $seq++,
        'valor_total' => 10.00,
        'created_at' => now(),
        'updated_at' => now(),
    ], $extra));
}

describe('US-NFE-006 · schema de contingência (ADR TECH-0002 §Schema additions)', function () {

    it('UC-CONT-01 · nfe_business_configs ganhou o estado de contingência POR TENANT', function () {
        expect(Schema::hasColumn('nfe_business_configs', 'em_contingencia'))->toBeTrue();
        expect(Schema::hasColumn('nfe_business_configs', 'contingencia_ativada_em'))->toBeTrue();
        expect(Schema::hasColumn('nfe_business_configs', 'contingencia_motivo'))->toBeTrue();
    });

    it('UC-CONT-02 · em_contingencia nasce FALSE — a migration NÃO arrasta ninguém pra contingência', function () {
        DB::table('nfe_business_configs')->insertOrIgnore([
            'business_id' => CONT_BIZ,
            'regime' => 'simples',
            'tributacao_default' => json_encode(['cfop' => '5102']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cfg = DB::table('nfe_business_configs')->where('business_id', CONT_BIZ)->first();

        // O default é o contrato: quem nunca ativou segue emitindo normal. A ADR TECH-0002
        // REJEITOU auto-ativação — se este assert cair, a migration virou gatilho fiscal.
        expect((bool) $cfg->em_contingencia)->toBeFalse();
        expect($cfg->contingencia_ativada_em)->toBeNull();
        expect($cfg->contingencia_motivo)->toBeNull();
    });

    it('UC-CONT-03 · nfe_emissoes ganhou tp_emis, retry_count e last_retry_at', function () {
        expect(Schema::hasColumn('nfe_emissoes', 'tp_emis'))->toBeTrue();
        expect(Schema::hasColumn('nfe_emissoes', 'retry_count'))->toBeTrue();
        expect(Schema::hasColumn('nfe_emissoes', 'last_retry_at'))->toBeTrue();
    });

    it('UC-CONT-04 · emissão nasce tp_emis=1 (normal) e retry_count=0 — emissão legada intacta', function () {
        $id = contInsereEmissao();
        $row = DB::table('nfe_emissoes')->find($id);

        // 1 = Normal. Se virar 4 ou 9 por default, TODA nota do tenant sai marcada como
        // contingência sem ninguém ter pedido — erro fiscal, não erro de teste.
        expect((int) $row->tp_emis)->toBe(1);
        expect((int) $row->retry_count)->toBe(0);
        expect($row->last_retry_at)->toBeNull();
    });

    it('UC-CONT-05 · status aceita o valor contingencia', function () {
        $id = contInsereEmissao(['status' => 'contingencia']);

        expect(DB::table('nfe_emissoes')->find($id)->status)->toBe('contingencia');
    });

    it('CONTROLE NEGATIVO · o conjunto do ENUM é EXATAMENTE o canônico (sem isto, o UC-CONT-05 é vácuo)', function () {
        // 1ª versão deste caso esperava QueryException ao inserir valor inválido e FALHOU
        // no CI (run 33559072583): o MySQL da lane NÃO está em modo estrito, então valor
        // fora do ENUM é TRUNCADO com warning, não rejeitado com erro.
        //
        // A lição vale além deste arquivo: comportamento de rejeição de ENUM depende de
        // `sql_mode` e NÃO é portável entre ambientes (a lane pode diferir de produção).
        // O CONTRATO DE SCHEMA, esse sim, é o mesmo em qualquer sql_mode — então o controle
        // negativo mede o INFORMATION_SCHEMA, não o comportamento do INSERT.
        $tipo = (string) DB::selectOne(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'nfe_emissoes'
               AND COLUMN_NAME = 'status'"
        )->COLUMN_TYPE;

        expect($tipo)->toStartWith('enum(');

        preg_match_all("/'([^']+)'/", $tipo, $m);
        $valores = $m[1];
        sort($valores);

        $canonicos = [
            'autorizada', 'cancelada', 'contingencia', 'denegada', 'enviando',
            'erro_envio', 'inutilizada', 'pendente', 'rejeitada',
        ];
        sort($canonicos);

        // Igualdade EXATA nos dois sentidos: prova que 'contingencia' entrou E que nenhum
        // valor estranho mora ali. Só `toContain` deixaria passar lixo acumulado.
        expect($valores)->toBe($canonicos);
    });

    it('UC-CONT-06 · nfe_sefaz_status existe, com uf como chave primária natural', function () {
        expect(Schema::hasTable('nfe_sefaz_status'))->toBeTrue();

        $pk = DB::selectOne(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'nfe_sefaz_status'
               AND COLUMN_KEY = 'PRI'"
        );

        // A ADR TECH-0002 especifica PRIMARY KEY (uf): 1 linha por autorizador, sem
        // business_id — "SEFAZ-SP está fora" é o mesmo fato pra todo tenant.
        expect($pk)->not->toBeNull();
        expect(strtolower((string) $pk->COLUMN_NAME))->toBe('uf');
    });

    it('UC-CONT-07 · nfe_sefaz_status nasce sem falhas acumuladas e sem medição', function () {
        DB::table('nfe_sefaz_status')->insertOrIgnore([
            'uf' => 'SC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('nfe_sefaz_status')->where('uf', 'SC')->first();

        expect((int) $row->consecutive_failures)->toBe(0);
        expect($row->status)->toBe('verde');
        // last_check_at NULL = NUNCA MEDIDO. Não é "no ar" — confundir os dois é o
        // fail-open catalogado em proibicoes.md §5 (2026-07-29).
        expect($row->last_check_at)->toBeNull();
    });
});
