<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

/**
 * Fiação do comando design:ingest-zip (os testes unit do Planner não cobrem).
 *
 * Cobre os 2 descompassos achados ao ingerir o HANDOFF completo do Cowork (não um zip
 * de 1 tela): (A) o diff é sobre os arquivos ROTEADOS (pelo nome final do destino), não
 * o dump bruto — então um handoff aninhado (project/inbox-page.jsx) casa a baseline flat
 * (inbox-page.jsx); (B) extras de OUTRA tela conhecida do map são agregados (ruído), só
 * os DESCONHECIDOS são listados. Fixture em tempdir (jana.dossie_root); zip real; sem git.
 */

beforeEach(function () {
    $dir = sys_get_temp_dir() . '/ingest_cmd_' . uniqid();
    File::makeDirectory($dir, 0o755, recursive: true);
    test()->root = $dir;
    config(['jana.dossie_root' => $dir]);
});

afterEach(function () {
    if (isset(test()->root) && File::isDirectory(test()->root)) {
        File::deleteDirectory(test()->root);
    }
});

function ingestSeed(string $rel, string $content): void
{
    $abs = test()->root . '/' . $rel;
    File::ensureDirectoryExists(dirname($abs));
    File::put($abs, $content);
}

/** @param array<string,string> $files name(=path no zip) => content */
function ingestZipFile(string $path, array $files): void
{
    $z = new ZipArchive();
    $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $z->addFromString($name, $content);
    }
    $z->close();
}

/** map com 2 telas + globs ESPECÍFICOS por prefixo (caixa=inbox-*, vendas=vendas-*). */
function ingestSeedMap(): void
{
    ingestSeed('prototipo-ui/cowork-map.json', json_encode([
        'screens' => [
            'caixa-unificada' => ['module' => 'Atendimento', 'page_id' => 'atendimento-caixa-unificada', 'routes' => [
                ['glob' => 'inbox-*.jsx', 'to' => 'prototipo-ui/prototipos/caixa-unificada/'],
                ['glob' => 'inbox-*.css', 'to' => 'prototipo-ui/prototipos/caixa-unificada/'],
            ]],
            'vendas' => ['module' => 'Sells', 'page_id' => 'sells-index', 'routes' => [
                ['glob' => 'vendas-*.jsx', 'to' => 'prototipo-ui/prototipos/vendas/'],
            ]],
        ],
    ]));
}

function ingestPlano(string $tela): string
{
    return File::get(test()->root . "/prototipo-ui/_incoming/{$tela}/_prepared/PLANO-MUDANCAS-{$tela}.md");
}

test('diff é sobre os ROTEADOS: add/mod/del corretos; extra desconhecido listado', function () {
    ingestSeedMap();
    ingestSeed('prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx', 'v1'); // será modificado
    ingestSeed('prototipo-ui/prototipos/caixa-unificada/inbox-old.css', 'x');   // será removido
    $zip = test()->root . '/export.zip';
    ingestZipFile($zip, [
        'inbox-page.jsx' => 'v2',   // roteado → mod
        'inbox-novo.css' => 'z',    // roteado → add
        'lixo.txt' => 'nada',       // não casa NENHUMA tela → desconhecido
    ]);

    expect(Artisan::call('design:ingest-zip', ['--zip' => $zip, '--tela' => 'caixa-unificada']))->toBe(0);

    expect(ingestPlano('caixa-unificada'))
        ->toContain('| `inbox-page.jsx` | mod |')
        ->toContain('| `inbox-novo.css` | add |')
        ->toContain('| `inbox-old.css` | del |')
        ->toContain('⚠️ `lixo.txt` — **fora do cowork-map**')
        ->not->toContain('sem mudanças vs a tela commitada');
});

test('handoff ANINHADO idêntico → "sem mudanças"; arquivo de outra tela é agregado', function () {
    ingestSeedMap();
    ingestSeed('prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx', 'CONTEUDO_A');
    // zip no formato handoff: tudo sob oimpresso-x/project/, todas as telas juntas
    $zip = test()->root . '/handoff.zip';
    ingestZipFile($zip, [
        'oimpresso-x/project/inbox-page.jsx' => 'CONTEUDO_A',   // IDÊNTICO à baseline
        'oimpresso-x/project/vendas-page.jsx' => 'qualquer',    // de OUTRA tela (vendas)
    ]);

    expect(Artisan::call('design:ingest-zip', ['--zip' => $zip, '--tela' => 'caixa-unificada']))->toBe(0);

    $plano = ingestPlano('caixa-unificada');
    expect($plano)
        ->toContain('sem mudanças vs a tela commitada')         // aninhado casou a baseline flat
        ->toContain('de outras telas do handoff')               // vendas-page.jsx agregado
        ->not->toContain('⚠️ `oimpresso-x/project/vendas-page.jsx`'); // NÃO listado como desconhecido
});

test('handoff ANINHADO modificado → o diff PEGA o arquivo modificado', function () {
    ingestSeedMap();
    ingestSeed('prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx', 'CONTEUDO_A');
    $zip = test()->root . '/handoff.zip';
    ingestZipFile($zip, ['oimpresso-x/project/inbox-page.jsx' => 'CONTEUDO_B_MUDOU']);

    expect(Artisan::call('design:ingest-zip', ['--zip' => $zip, '--tela' => 'caixa-unificada']))->toBe(0);

    expect(ingestPlano('caixa-unificada'))->toContain('| `inbox-page.jsx` | mod |');
});

test('prepare-only: NÃO aplica no protótipo commitado', function () {
    ingestSeedMap();
    ingestSeed('prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx', 'v1');
    $zip = test()->root . '/handoff.zip';
    ingestZipFile($zip, ['oimpresso-x/project/inbox-page.jsx' => 'v2-NOVO']);

    Artisan::call('design:ingest-zip', ['--zip' => $zip, '--tela' => 'caixa-unificada']);

    // commitado intocado (aplicação é gate Wagner/CT100)
    expect(File::get(test()->root . '/prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx'))->toBe('v1');
    expect(File::exists(test()->root . '/prototipo-ui/_incoming/caixa-unificada/_prepared/PLANO-MUDANCAS-caixa-unificada.md'))->toBeTrue();
    expect(File::exists(test()->root . '/prototipo-ui/_incoming/caixa-unificada/_prepared/SESSION-design-ingest-caixa-unificada.md'))->toBeTrue();
});
