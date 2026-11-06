<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato da tabela `legacy_archive_rows` (arquivo do legado Delphi).
 *
 * Âncora: decisão [F] 2026-10-30 na migração da Sabor Brasil — log e histórico do legado
 * são preservados em tabela de arquivo, só leitura, sem migração parcial; e ADR 0093
 * (multi-tenant Tier 0). Roda na lane MySQL officeimpresso-pest (dona da migração
 * legada, ADR 0203).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('legacy_archive_rows') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema ausente — roda na lane MySQL (officeimpresso-pest).');
    }
    $this->biz = $this->seededTenant()->id;
    $this->outro = $this->seededSupportClientTenant()->id;
    $this->linha = fn (int $biz, array $c = []) => $c + [
        'business_id' => $biz,
        'legacy_source' => 'wr-comercial-delphi',
        'source_table' => 'HISTORICO',
        'ref_table' => 'VENDA',
        'ref_key' => '103917',
        'legacy_empresa' => '5',
        'occurred_at' => '2026-03-25 17:16:51',
        'payload' => json_encode(['EVENTO' => 'Editado', 'TABELA' => 'VENDA', 'CODIGO' => 103917]),
        'row_hash' => sha1('linha-fixa-do-teste'),
    ];
});

it('tem as colunas do contrato e é append-only (sem updated_at)', function () {
    foreach (['business_id', 'legacy_source', 'source_table', 'source_pk', 'legacy_empresa',
        'ref_table', 'ref_key', 'occurred_at', 'payload', 'row_hash', 'importer_version', 'imported_at'] as $col) {
        expect(Schema::hasColumn('legacy_archive_rows', $col))->toBeTrue();
    }
    expect(Schema::hasColumn('legacy_archive_rows', 'updated_at'))->toBeFalse();
});

it('re-importar a mesma linha na mesma empresa não duplica', function () {
    expect(DB::table('legacy_archive_rows')->insertOrIgnore(($this->linha)($this->biz)))->toBe(1);
    expect(DB::table('legacy_archive_rows')->insertOrIgnore(($this->linha)($this->biz)))->toBe(0);

    expect(fn () => DB::table('legacy_archive_rows')->insert(($this->linha)($this->biz)))
        ->toThrow(QueryException::class);
});

it('a mesma linha em outra empresa é outro registro (isolamento por business_id)', function () {
    DB::table('legacy_archive_rows')->insert(($this->linha)($this->biz));
    DB::table('legacy_archive_rows')->insert(($this->linha)($this->outro));

    $porEmpresa = fn (int $biz) => DB::table('legacy_archive_rows')
        ->where('business_id', $biz)->where('row_hash', sha1('linha-fixa-do-teste'))->count();
    expect($porEmpresa($this->biz))->toBe(1);
    expect($porEmpresa($this->outro))->toBe(1);
});

it('acha o histórico de um registro migrado pela referência', function () {
    DB::table('legacy_archive_rows')->insert(($this->linha)($this->biz));
    DB::table('legacy_archive_rows')->insert(($this->linha)($this->biz, ['ref_key' => '999', 'row_hash' => sha1('outra')]));

    $achados = DB::table('legacy_archive_rows')
        ->where('business_id', $this->biz)->where('ref_table', 'VENDA')->where('ref_key', '103917')->get();
    expect($achados)->toHaveCount(1);
    expect(json_decode($achados[0]->payload, true)['EVENTO'])->toBe('Editado');
});

it('recusa business_id que não existe (FK)', function () {
    expect(fn () => DB::table('legacy_archive_rows')->insert(($this->linha)(2147483000)))
        ->toThrow(QueryException::class);
});
