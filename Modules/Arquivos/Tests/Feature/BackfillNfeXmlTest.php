<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Migration de backfill NFe XML/DANFE (2026_05_10_000010_backfill_nfe_xml_arquivos).
 *
 * Contrato: SPEC US-ARQ-020 ("NFe XMLs existentes → `arquivos`", aponta este arquivo
 * como prova) + ADR 0093 (Tier 0: a linha herda a empresa da NFe) + docblock da
 * migration (idempotente; down() apaga só o que tem a tag `backfill-us-arq-020`).
 *
 * Antes este arquivo lia linhas que a migration já tivesse produzido no banco do CI —
 * que não tem NFe nenhuma — e pulava ("Sem dados backfilled") ou passava sobre zero
 * linhas. O teste de idempotência rodava `migrate`, no-op numa migration já registrada.
 * Agora semeia `nfe_emissoes` no tenant 98 e no vizinho 99 e chama `up()`/`down()` da
 * migration direto.
 *
 * TENANT: 98 canônico + 99 vizinho (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 * DatabaseTransactions: nada fica gravado no CT 100.
 * NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente).
 */
const BFN_EMISSAO = 'Modules\\NfeBrasil\\Models\\NfeEmissao';
const BFN_TAG     = 'backfill-us-arq-020';

function bfnMigration(): object
{
    return require base_path('Modules/Arquivos/Database/Migrations/2026_05_10_000010_backfill_nfe_xml_arquivos.php');
}

function bfnRodar(string $metodo): void
{
    $migration = bfnMigration();
    ob_start();
    try {
        $migration->{$metodo}();
    } finally {
        ob_end_clean();
    }
}

function bfnEmissao(int $businessId, int $numero, ?string $xml, ?string $danfe): int
{
    return DB::table('nfe_emissoes')->insertGetId([
        'business_id' => $businessId,
        'modelo'      => '55',
        'serie'       => '987',
        'numero'      => $numero,
        'valor_total' => 0,
        'xml_path'    => $xml,
        'danfe_path'  => $danfe,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

function bfnArquivosDe(int $emissaoId): \Illuminate\Support\Collection
{
    return DB::table('arquivos')
        ->where('arquivable_type', BFN_EMISSAO)
        ->where('arquivable_id', $emissaoId)
        ->orderBy('sub_destination')
        ->get();
}

beforeEach(function () {
    if (! Schema::hasTable('arquivos') || ! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('Tables ausentes — rode migrate primeiro');
    }
});

it('XML e DANFE viram arquivos na empresa da NFe — 98 e 99 não se misturam', function () {
    $biz = $this->seededTenant()->id;
    $vizinho = $this->seededSupportClientTenant()->id;
    $doc98 = bfnEmissao($biz, 987650601, 'nfe/98/l1-teste.xml', 'nfe/98/l1-teste.pdf');
    $doc99 = bfnEmissao($vizinho, 987650602, 'nfe/99/l1-teste.xml', null);

    bfnRodar('up');

    $do98 = bfnArquivosDe($doc98);
    $do99 = bfnArquivosDe($doc99);

    // Controle positivo: duas linhas (xml + danfe) no 98, uma (só xml) no 99.
    expect($do98)->toHaveCount(2);
    expect($do99)->toHaveCount(1);

    expect($do98->pluck('business_id')->unique()->all())->toBe([$biz]);
    expect($do99[0]->business_id)->toBe($vizinho);
    expect($do98->pluck('classified_by')->unique()->all())->toBe([BFN_TAG]);
    expect($do98->pluck('bucket')->unique()->all())->toBe(['active']);
});

it('MIME segue a extensão: .xml → application/xml, .pdf → application/pdf', function () {
    $doc = bfnEmissao($this->seededTenant()->id, 987650603, 'nfe/98/l1-mime.xml', 'nfe/98/l1-mime.pdf');

    bfnRodar('up');

    $porDestino = bfnArquivosDe($doc)->keyBy('sub_destination');
    expect($porDestino->keys()->all())->toBe(['nfe-danfe', 'nfe-xml']);
    expect($porDestino['nfe-xml']->mime_type)->toBe('application/xml');
    expect($porDestino['nfe-danfe']->mime_type)->toBe('application/pdf');
});

it('rodar a migration 2× não duplica as linhas', function () {
    $doc = bfnEmissao($this->seededTenant()->id, 987650604, 'nfe/98/l1-idem.xml', 'nfe/98/l1-idem.pdf');

    bfnRodar('up');
    expect(bfnArquivosDe($doc))->toHaveCount(2); // controle: a 1ª criou

    bfnRodar('up');
    expect(bfnArquivosDe($doc))->toHaveCount(2);
});

it('down() apaga só as linhas com a tag do backfill', function () {
    $biz = $this->seededTenant()->id;
    $doc = bfnEmissao($biz, 987650605, 'nfe/98/l1-down.xml', null);

    $avulso = DB::table('arquivos')->insertGetId([
        'business_id'     => $biz,
        'arquivable_type' => BFN_EMISSAO,
        'arquivable_id'   => $doc,
        'disk'            => 'local',
        'storage_path'    => 'nfe/98/l1-down-anexo-manual.xml',
        'original_name'   => 'l1-down-anexo-manual.xml',
        'mime_type'       => 'application/xml',
        'size_bytes'      => 10,
        'md5'             => str_repeat('b', 32),
        'classified_by'   => 'upload-manual',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    bfnRodar('up');
    expect(bfnArquivosDe($doc))->toHaveCount(2); // controle: avulso + backfill

    bfnRodar('down');

    $restante = bfnArquivosDe($doc);
    expect($restante)->toHaveCount(1);
    expect($restante[0]->id)->toBe($avulso);
});
