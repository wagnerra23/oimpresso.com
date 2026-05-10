<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * arquivos:export-zip — Pest tests Sprint 2 ADR 0123 (LGPD Art. 18 portabilidade).
 *
 * Cobertura (7 cenários):
 * 1. Command registrado em artisan list
 * 2. --business ausente → exit 1 + mensagem PT-BR
 * 3. Cria ZIP com files do business + manifest JSON correto
 * 4. --dry-run não cria file ZIP nem audit log
 * 5. --include-vault decrypta vault arquivos via VaultEncryptionService
 * 6. Multi-tenant: --business=1 não exporta arquivos biz=99
 * 7. Audit log "exported" inserido pra cada arquivo
 *
 * Padrão:
 *   - Storage::fake('local') pra testes de disk
 *   - ZipArchive PHP nativo para verificação
 *   - DB::table direto (sem GlobalScopes — CLI pattern ADR 0093)
 *   - Biz=1 (Wagner WR2), NUNCA biz=4 (ROTA LIVRE — ADR 0101)
 *   - Cleanup isolado via classified_by = 'test-pr36-export-zip' no afterEach
 *
 * @see Modules/Arquivos/Console/Commands/ExportZipCommand.php
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 2
 * @see LGPD Art. 18 (portabilidade de dados pessoais)
 */

// ---------------------------------------------------------------------------
// Setup & Teardown
// ---------------------------------------------------------------------------

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatible: requer MySQL UltimatePOS schema');
    }

    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode Modules/Arquivos migrate primeiro');
    }
});

afterEach(function () {
    // afterEach roda mesmo em tests pulados (PHPUnit tearDown). Em SQLite CI
    // sem migrate, DELETE estoura — bail antes.
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }

    // Limpa apenas rows de teste — não afeta dados de outros suites
    DB::table('arquivos_audit_log')
        ->whereIn('arquivo_id', function ($q) {
            $q->select('id')
              ->from('arquivos')
              ->where('classified_by', 'test-pr36-export-zip');
        })
        ->delete();

    DB::table('arquivos')->where('classified_by', 'test-pr36-export-zip')->delete();
});

// ---------------------------------------------------------------------------
// Helper — insere row em arquivos
// ---------------------------------------------------------------------------

/**
 * Insere uma row em arquivos com os campos mínimos obrigatórios.
 *
 * @param  array  $overrides  Campos a sobrescrever nos defaults
 * @return int  ID inserido
 */
function insertArquivoExport(array $overrides = []): int
{
    static $seq = 9600;
    $seq++;

    $defaults = [
        'business_id'     => 1,
        'arquivable_type' => 'Modules\NfeBrasil\Models\NfeEmissao',
        'arquivable_id'   => $seq,
        'disk'            => 'local',
        'storage_path'    => "biz-1/2026/05/test-pr36-{$seq}.txt",
        'original_name'   => "test-pr36-{$seq}.txt",
        'mime_type'       => 'text/plain',
        'size_bytes'      => 512,
        'md5'             => md5("test-pr36-{$seq}"),
        'bucket'          => 'active',
        'sub_destination' => 'nfe-xml',
        'encrypted'       => false,
        'classified_by'   => 'test-pr36-export-zip',
        'created_at'      => now(),
        'updated_at'      => now(),
        'deleted_at'      => null,
    ];

    return DB::table('arquivos')->insertGetId(array_merge($defaults, $overrides));
}

// ---------------------------------------------------------------------------
// 1. Command registrado em artisan list
// ---------------------------------------------------------------------------

it('command arquivos:export-zip está registrado no artisan', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('arquivos:export-zip');
});

// ---------------------------------------------------------------------------
// 2. --business ausente → exit 1 + mensagem PT-BR
// ---------------------------------------------------------------------------

it('--business ausente retorna exit 1 com mensagem em PT-BR', function () {
    $exitCode = Artisan::call('arquivos:export-zip');

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('--business');
    expect(Artisan::output())->toContain('obrigatório');
});

// ---------------------------------------------------------------------------
// 3. Cria ZIP com files do business + manifest JSON correto
// ---------------------------------------------------------------------------

it('cria ZIP com arquivos do business e manifest JSON correto', function () {
    Storage::fake('local');

    $path1 = 'biz-1/2026/05/test-pr36-zip-1.txt';
    $path2 = 'biz-1/2026/05/test-pr36-zip-2.txt';
    Storage::disk('local')->put($path1, 'conteudo-arquivo-1');
    Storage::disk('local')->put($path2, 'conteudo-arquivo-2');

    $id1 = insertArquivoExport([
        'disk'          => 'local',
        'storage_path'  => $path1,
        'original_name' => 'arquivo-1.txt',
        'size_bytes'    => 18,
    ]);

    $id2 = insertArquivoExport([
        'disk'          => 'local',
        'storage_path'  => $path2,
        'original_name' => 'arquivo-2.txt',
        'size_bytes'    => 18,
    ]);

    $outputPath = sys_get_temp_dir() . '/test-pr36-export-' . uniqid() . '.zip';

    $exitCode = Artisan::call('arquivos:export-zip', [
        '--business' => 1,
        '--output'   => $outputPath,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('ZIP exportado:');
    expect(Artisan::output())->toContain($outputPath);

    // Verifica que o arquivo ZIP foi criado
    expect(file_exists($outputPath))->toBeTrue();

    // Verifica conteúdo do ZIP
    $zip = new \ZipArchive();
    expect($zip->open($outputPath))->toBeTrue();

    // Deve conter _MANIFEST.json
    $manifestJson = $zip->getFromName('_MANIFEST.json');
    expect($manifestJson)->toBeString();

    $manifest = json_decode($manifestJson, true);
    expect($manifest)->toBeArray();
    expect($manifest['business_id'])->toBe(1);
    expect($manifest['total_files'])->toBe(2);
    expect($manifest['include_vault'])->toBeFalse();
    expect($manifest['include_deleted'])->toBeFalse();
    expect(count($manifest['files']))->toBe(2);

    // Verifica estrutura de path no ZIP (bucket/sub/type-slug/filename)
    $paths = collect($manifest['files'])->pluck('path_in_zip')->toArray();
    expect($paths[0])->toContain('active/');
    expect($paths[0])->toContain('nfe-xml/');

    $zip->close();

    // Cleanup
    @unlink($outputPath);
});

// ---------------------------------------------------------------------------
// 4. --dry-run não cria file ZIP nem audit log
// ---------------------------------------------------------------------------

it('--dry-run não cria arquivo ZIP nem insere audit log', function () {
    Storage::fake('local');

    $path = 'biz-1/2026/05/test-pr36-dryrun.txt';
    Storage::disk('local')->put($path, 'conteudo-dryrun');

    $id = insertArquivoExport([
        'disk'         => 'local',
        'storage_path' => $path,
        'original_name'=> 'dryrun.txt',
    ]);

    $outputPath = sys_get_temp_dir() . '/test-pr36-dryrun-' . uniqid() . '.zip';

    $exitCode = Artisan::call('arquivos:export-zip', [
        '--business' => 1,
        '--output'   => $outputPath,
        '--dry-run'  => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('[DRY-RUN]');
    expect(Artisan::output())->toContain('seriam exportados');

    // ZIP NÃO deve ter sido criado
    expect(file_exists($outputPath))->toBeFalse();

    // Audit log NÃO deve ter sido inserido
    $audit = DB::table('arquivos_audit_log')
        ->where('arquivo_id', $id)
        ->where('action', 'exported')
        ->first();
    expect($audit)->toBeNull();
});

// ---------------------------------------------------------------------------
// 5. --include-vault decrypta vault arquivos via VaultEncryptionService
// ---------------------------------------------------------------------------

it('--include-vault decrypta arquivos vault com VaultEncryptionService', function () {
    Storage::fake('local');

    $plaintext = 'conteudo-sensivel-vault-lgpd-portabilidade';
    $ciphertext = Crypt::encryptString($plaintext);

    $vaultPath = 'biz-1/vault/test-pr36-vault.txt';
    Storage::disk('local')->put($vaultPath, $ciphertext);

    $id = insertArquivoExport([
        'disk'          => 'local',
        'storage_path'  => $vaultPath,
        'original_name' => 'vault-file.txt',
        'bucket'        => 'sensitive',
        'encrypted'     => true,
        'size_bytes'    => strlen($ciphertext),
    ]);

    $outputPath = sys_get_temp_dir() . '/test-pr36-vault-' . uniqid() . '.zip';

    $exitCode = Artisan::call('arquivos:export-zip', [
        '--business'      => 1,
        '--output'        => $outputPath,
        '--include-vault' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(file_exists($outputPath))->toBeTrue();

    // Verifica que o ZIP contém o arquivo decryptado (plaintext)
    $zip = new \ZipArchive();
    $zip->open($outputPath);

    // Localiza o arquivo vault no ZIP
    $fileCount = $zip->numFiles;
    $foundDecrypted = false;

    for ($i = 0; $i < $fileCount; $i++) {
        $stat = $zip->statIndex($i);
        if (str_contains($stat['name'], 'vault-file.txt')) {
            $content = $zip->getFromIndex($i);
            // Deve conter o plaintext, NÃO o ciphertext
            expect($content)->toBe($plaintext);
            $foundDecrypted = true;
        }
    }

    $zip->close();

    expect($foundDecrypted)->toBeTrue('arquivo vault decryptado não foi encontrado no ZIP');

    // Cleanup
    @unlink($outputPath);
});

// ---------------------------------------------------------------------------
// 6. Multi-tenant: --business=1 não exporta arquivos biz=99
// ---------------------------------------------------------------------------

it('multi-tenant: --business=1 não exporta arquivos de biz=99', function () {
    Storage::fake('local');

    $pathBiz1  = 'biz-1/2026/05/test-pr36-multitenant-biz1.txt';
    $pathBiz99 = 'biz-99/2026/05/test-pr36-multitenant-biz99.txt';

    Storage::disk('local')->put($pathBiz1, 'conteudo-biz1');
    Storage::disk('local')->put($pathBiz99, 'conteudo-biz99-NAO-deve-exportar');

    $idBiz1 = insertArquivoExport([
        'business_id'   => 1,
        'disk'          => 'local',
        'storage_path'  => $pathBiz1,
        'original_name' => 'biz1-arquivo.txt',
    ]);

    // Insere row de outro business — NÃO deve aparecer na exportação de biz=1
    $idBiz99 = DB::table('arquivos')->insertGetId([
        'business_id'     => 99,
        'arquivable_type' => 'TestModel',
        'arquivable_id'   => 9699,
        'disk'            => 'local',
        'storage_path'    => $pathBiz99,
        'original_name'   => 'biz99-arquivo.txt',
        'mime_type'       => 'text/plain',
        'size_bytes'      => 512,
        'md5'             => md5('biz99'),
        'bucket'          => 'active',
        'encrypted'       => false,
        'classified_by'   => 'test-pr36-export-zip',
        'created_at'      => now(),
        'updated_at'      => now(),
        'deleted_at'      => null,
    ]);

    $outputPath = sys_get_temp_dir() . '/test-pr36-multitenant-' . uniqid() . '.zip';

    $exitCode = Artisan::call('arquivos:export-zip', [
        '--business' => 1,
        '--output'   => $outputPath,
    ]);

    expect($exitCode)->toBe(0);
    expect(file_exists($outputPath))->toBeTrue();

    // Verifica manifest — só deve conter arquivo de biz=1
    $zip = new \ZipArchive();
    $zip->open($outputPath);
    $manifest = json_decode($zip->getFromName('_MANIFEST.json'), true);
    $zip->close();

    expect($manifest['business_id'])->toBe(1);
    expect($manifest['total_files'])->toBe(1);

    $arquivoIds = collect($manifest['files'])->pluck('arquivo_id')->toArray();
    expect($arquivoIds)->toContain($idBiz1);
    expect($arquivoIds)->not->toContain($idBiz99);

    // Cleanup
    @unlink($outputPath);
    DB::table('arquivos')->where('id', $idBiz99)->delete();
    DB::table('arquivos_audit_log')->where('arquivo_id', $idBiz99)->delete();
});

// ---------------------------------------------------------------------------
// 7. Audit log "exported" inserido pra cada arquivo (LGPD trail obrigatório)
// ---------------------------------------------------------------------------

it('audit log "exported" é inserido para cada arquivo exportado (LGPD Art. 18)', function () {
    Storage::fake('local');

    $path1 = 'biz-1/2026/05/test-pr36-audit-1.txt';
    $path2 = 'biz-1/2026/05/test-pr36-audit-2.txt';
    Storage::disk('local')->put($path1, 'conteudo-auditavel-1');
    Storage::disk('local')->put($path2, 'conteudo-auditavel-2');

    $id1 = insertArquivoExport([
        'disk'         => 'local',
        'storage_path' => $path1,
        'original_name'=> 'audit-1.txt',
    ]);

    $id2 = insertArquivoExport([
        'disk'         => 'local',
        'storage_path' => $path2,
        'original_name'=> 'audit-2.txt',
    ]);

    $outputPath = sys_get_temp_dir() . '/test-pr36-audit-' . uniqid() . '.zip';

    $exitCode = Artisan::call('arquivos:export-zip', [
        '--business' => 1,
        '--output'   => $outputPath,
    ]);

    expect($exitCode)->toBe(0);

    // Verifica audit log para arquivo 1
    $audit1 = DB::table('arquivos_audit_log')
        ->where('arquivo_id', $id1)
        ->where('action', 'exported')
        ->where('business_id', 1)
        ->first();

    expect($audit1)->not->toBeNull();
    $payload1 = json_decode($audit1->payload, true);
    expect($payload1['command'])->toBe('arquivos:export-zip');
    expect($payload1['business_id'])->toBe(1);
    expect($payload1['lgpd_art18'])->toBeTrue();
    expect($payload1['exported_to'])->toBe($outputPath);

    // Verifica audit log para arquivo 2
    $audit2 = DB::table('arquivos_audit_log')
        ->where('arquivo_id', $id2)
        ->where('action', 'exported')
        ->where('business_id', 1)
        ->first();

    expect($audit2)->not->toBeNull();
    $payload2 = json_decode($audit2->payload, true);
    expect($payload2['lgpd_art18'])->toBeTrue();

    // Cleanup
    @unlink($outputPath);
});
