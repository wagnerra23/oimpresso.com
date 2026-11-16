<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Migration de backfill dos consumers (2026_05_10_000020_backfill_consumers_arquivos).
 *
 * Contrato:
 * - SPEC US-ARQ-023: `fin_boleto_remessas.pdf_path` vira `arquivos` (sub_destination
 *   `fin-boleto-pdf`, MIME application/pdf) — o SPEC aponta este arquivo como prova;
 * - SPEC §"Ordem de execução Sprint 4": a mesma migration faz o backfill de
 *   `cms_pages.feature_image` (`cms-featured`);
 * - ADR 0123 Sprint 4/5 + ADR 0093: fotos de OS (`media` de JobSheet) viram `arquivos`
 *   `repair-foto`, e `arquivos.business_id` HERDA a empresa da origem;
 * - docblock da migration: idempotente (rodar 2× não duplica).
 *
 * ⚠️ IDs: as TAGS gravadas pela migration se chamam `backfill-us-arq-026/027/028`, mas
 * no SPEC esses números são outra coisa (US-ARQ-026 = SRS/DocSource, cancelada). A tag
 * é dado já gravado em produção e não muda; quem prova o boleto é a US-ARQ-023.
 *
 * Antes este arquivo só olhava linhas que a migration já tivesse produzido no banco do
 * CI — que não tem consumer nenhum — e pulava ("Sem dados backfilled") em toda run. O
 * teste de idempotência rodava `migrate`, que é no-op numa migration já registrada.
 * Agora a fixture é deste teste: semeia a origem no tenant 98 e no vizinho 99 e chama
 * o `up()` da migration direto.
 *
 * TENANT: 98 canônico + 99 vizinho (ADR 0358). NUNCA biz=4.
 * Tudo dentro de DatabaseTransactions: nada fica gravado no CT 100. ⚠️ O backfill do
 * CMS grava `business_id=1` fixo (código da migration: "CMS é singleton site landing");
 * aqui essa linha nasce e morre dentro da transação, e o teste NÃO afirma o tenant dela.
 * NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente).
 */
const BFC_JOBSHEET = 'Modules\\Repair\\Entities\\JobSheet';
const BFC_BOLETO   = 'Modules\\Financeiro\\Models\\BoletoRemessa';
const BFC_CMS      = 'Modules\\Cms\\Entities\\CmsPage';

function bfcRodarMigration(): void
{
    $migration = require base_path('Modules/Arquivos/Database/Migrations/2026_05_10_000020_backfill_consumers_arquivos.php');
    ob_start();
    try {
        $migration->up();
    } finally {
        ob_end_clean();
    }
}

function bfcMedia(int $businessId, int $jobSheetId, string $fileName): void
{
    DB::table('media')->insert([
        'business_id' => $businessId,
        'file_name'   => $fileName,
        'model_type'  => BFC_JOBSHEET,
        'model_id'    => $jobSheetId,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

function bfcBoleto(int $businessId, string $pdfPath): int
{
    // fin_boleto_remessas tem FK para fin_titulos e fin_contas_bancarias; montar a cadeia
    // inteira não acrescenta nada ao que se prova aqui. FK desligada só neste INSERT.
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    try {
        return DB::table('fin_boleto_remessas')->insertGetId([
            'business_id'       => $businessId,
            'titulo_id'         => 987650500,
            'conta_bancaria_id' => 987650500,
            'nosso_numero'      => 'L1TESTE'.$businessId,
            'linha_digitavel'   => str_repeat('0', 47),
            'codigo_barras'     => str_repeat('0', 44),
            'valor_total'       => 0,
            'vencimento'        => now()->toDateString(),
            'strategy'          => 'cnab_direct',
            'idempotency_key'   => 'l1-teste-'.uniqid(),
            'pdf_path'          => $pdfPath,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    } finally {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}

function bfcArquivosDe(string $type, int $id): \Illuminate\Support\Collection
{
    return DB::table('arquivos')
        ->where('arquivable_type', $type)
        ->where('arquivable_id', $id)
        ->get();
}

beforeEach(function () {
    foreach (['arquivos', 'media', 'cms_pages', 'fin_boleto_remessas'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Tabela {$tabela} ausente — lane sem schema MySQL.");
        }
    }
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Fixture com FOREIGN_KEY_CHECKS é MySQL-only.');
    }
});

it('foto de OS vira arquivo repair-foto e herda a empresa da origem (98 e 99)', function () {
    $biz = $this->seededTenant()->id;
    $vizinho = $this->seededSupportClientTenant()->id;
    bfcMedia($biz, 987650401, 'os-98-frente.jpg');
    bfcMedia($vizinho, 987650402, 'os-99-frente.jpg');

    bfcRodarMigration();

    $do98 = bfcArquivosDe(BFC_JOBSHEET, 987650401);
    $do99 = bfcArquivosDe(BFC_JOBSHEET, 987650402);

    // Controle positivo: a migration produziu exatamente uma linha por foto.
    expect($do98)->toHaveCount(1);
    expect($do99)->toHaveCount(1);

    expect($do98[0]->business_id)->toBe($biz);
    expect($do99[0]->business_id)->toBe($vizinho);
    expect($do98[0]->sub_destination)->toBe('repair-foto');
    expect($do98[0]->mime_type)->toBe('image/jpeg');
    expect($do98[0]->bucket)->toBe('active');
    expect($do98[0]->classified_by)->toBe('backfill-us-arq-026');
});

it('PDF de boleto vira arquivo fin-boleto-pdf com MIME application/pdf no tenant da remessa (US-ARQ-023)', function () {
    $biz = $this->seededTenant()->id;
    $remessaId = bfcBoleto($biz, 'boletos/98/l1-teste.pdf');

    bfcRodarMigration();

    $linhas = bfcArquivosDe(BFC_BOLETO, $remessaId);
    expect($linhas)->toHaveCount(1);
    expect($linhas[0]->business_id)->toBe($biz);
    expect($linhas[0]->sub_destination)->toBe('fin-boleto-pdf');
    expect($linhas[0]->mime_type)->toBe('application/pdf');
    expect($linhas[0]->storage_path)->toBe('boletos/98/l1-teste.pdf');
});

it('feature_image do CMS vira arquivo cms-featured em uploads/cms/', function () {
    $pageId = DB::table('cms_pages')->insertGetId([
        'type'          => 'page',
        'title'         => 'Pagina de teste L1',
        'feature_image' => 'l1-teste-capa.png',
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    bfcRodarMigration();

    $linhas = bfcArquivosDe(BFC_CMS, $pageId);
    expect($linhas)->toHaveCount(1);
    expect($linhas[0]->sub_destination)->toBe('cms-featured');
    expect($linhas[0]->storage_path)->toBe('uploads/cms/l1-teste-capa.png');
    expect($linhas[0]->mime_type)->toBe('image/png');
});

it('rodar a migration 2× não duplica as linhas', function () {
    $biz = $this->seededTenant()->id;
    bfcMedia($biz, 987650403, 'os-98-idem.jpg');
    $remessaId = bfcBoleto($biz, 'boletos/98/l1-idem.pdf');

    bfcRodarMigration();
    $depoisDa1a = bfcArquivosDe(BFC_JOBSHEET, 987650403)->count()
        + bfcArquivosDe(BFC_BOLETO, $remessaId)->count();

    // Controle positivo: a 1ª run criou as duas (senão "não cresceu" é verde por vácuo).
    expect($depoisDa1a)->toBe(2);

    bfcRodarMigration();
    $depoisDa2a = bfcArquivosDe(BFC_JOBSHEET, 987650403)->count()
        + bfcArquivosDe(BFC_BOLETO, $remessaId)->count();

    expect($depoisDa2a)->toBe(2);
});
