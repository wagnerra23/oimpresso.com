<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

/**
 * PR-fix — fiação do comando design:ingest-zip, que os testes unit do Planner não cobrem.
 * Codifica o furo mais grave do smoke CT100: o diff via `git diff --no-index` saía VAZIO
 * no container (sem git) → PLANO dizia "sem mudanças" mesmo com arquivo novo. Agora o diff
 * é por CONTEÚDO (sha) — sem git. Fixture em tempdir (`jana.dossie_root`); zip real via
 * ZipArchive. Continua prepare-only: nada é aplicado no protótipo commitado.
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

/** @param array<string,string> $files name => content */
function ingestZipFile(string $path, array $files): void
{
    $z = new ZipArchive();
    $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $z->addFromString($name, $content);
    }
    $z->close();
}

function ingestSeedMap(): void
{
    ingestSeed('prototipo-ui/cowork-map.json', json_encode([
        'screens' => ['vendas' => ['module' => 'Sells', 'page_id' => 'sells-index', 'routes' => [
            ['glob' => '*-page.jsx', 'to' => 'prototipo-ui/prototipos/vendas/'],
            ['glob' => '*.css', 'to' => 'prototipo-ui/prototipos/vendas/'],
        ]]],
    ]));
}

test('diff por CONTEÚDO (sem git): add/mod/del + extra no PLANO', function () {
    ingestSeedMap();
    // tela commitada: 1 arquivo que será modificado + 1 que será removido
    ingestSeed('prototipo-ui/prototipos/vendas/vendas-page.jsx', 'v1');
    ingestSeed('prototipo-ui/prototipos/vendas/old.css', 'x');
    // zip do Cowork: page modificado + css novo + extra fora do map
    $zip = test()->root . '/export.zip';
    ingestZipFile($zip, [
        'vendas-page.jsx' => 'v2',          // modified
        'nova.css' => 'z',                  // added (roteado)
        'NOTES-cowork.md' => 'ideia solta', // added + extra (fora do map)
    ]);

    $code = Artisan::call('design:ingest-zip', ['--zip' => $zip, '--tela' => 'vendas']);
    expect($code)->toBe(0);

    $plano = File::get(test()->root . '/prototipo-ui/_incoming/vendas/_prepared/PLANO-MUDANCAS-vendas.md');
    expect($plano)
        ->toContain('| `vendas-page.jsx` | mod |')   // <- antes saía vazio (sem git)
        ->toContain('| `nova.css` | add |')
        ->toContain('| `NOTES-cowork.md` | add |')
        ->toContain('| `old.css` | del |')
        ->toContain('⚠️ `NOTES-cowork.md` — **fora do cowork-map**')
        ->not->toContain('sem mudanças vs a tela commitada');
});

test('prepare-only: NÃO aplica no protótipo commitado', function () {
    ingestSeedMap();
    ingestSeed('prototipo-ui/prototipos/vendas/vendas-page.jsx', 'v1');
    $zip = test()->root . '/export.zip';
    ingestZipFile($zip, ['vendas-page.jsx' => 'v2-NOVO']);

    Artisan::call('design:ingest-zip', ['--zip' => $zip, '--tela' => 'vendas']);

    // o commitado segue intocado (a aplicação é gate Wagner/CT100)
    expect(File::get(test()->root . '/prototipo-ui/prototipos/vendas/vendas-page.jsx'))->toBe('v1');
    expect(File::exists(test()->root . '/prototipo-ui/prototipos/vendas/_prepared'))->toBeFalse();
    // mas o PLANO + a memória de sessão foram preparados
    expect(File::exists(test()->root . '/prototipo-ui/_incoming/vendas/_prepared/PLANO-MUDANCAS-vendas.md'))->toBeTrue();
    expect(File::exists(test()->root . '/prototipo-ui/_incoming/vendas/_prepared/SESSION-design-ingest-vendas.md'))->toBeTrue();
});
