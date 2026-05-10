<?php

declare(strict_types=1);

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * arquivos:reencrypt-vault — Pest tests Sprint 2 ADR 0123 (APP_KEY rotation).
 *
 * Cobertura (min 4 + extras):
 * 1. Command registrado em artisan list
 * 2. --dry-run nao modifica disk
 * 3. --old-key ausente -> exit 1 + mensagem clara
 * 4. Roundtrip: encrypt com key A -> reencrypt --old-key=A -> file decrypt com key atual (Crypt facade)
 * 5. Idempotent: segunda run (old-key invalida pra ja re-encrypted) -> skip sem erro
 * 6. File ausente -> skip (nao errored)
 *
 * Nota: tests que exigem DB usam beforeEach com markTestSkipped se arquivos table ausente.
 * Tests de logica pura (Encrypter roundtrip) NAO dependem de DB schema.
 *
 * @see Modules/Arquivos/Console/Commands/ReencryptVaultCommand.php
 */

// --- Tests sem DB ---

it('command arquivos:reencrypt-vault esta registrado em artisan list', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('arquivos:reencrypt-vault');
});

it('--old-key ausente retorna exit 1 com mensagem clara', function () {
    $exitCode = Artisan::call('arquivos:reencrypt-vault');
    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('--old-key');
});

it('--old-key sem prefixo base64: retorna exit 1', function () {
    $exitCode = Artisan::call('arquivos:reencrypt-vault', [
        '--old-key' => 'chave-sem-prefixo',
    ]);
    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('base64:');
});

it('logica Encrypter roundtrip — encrypt com key A e decrypt com key A preserva conteudo', function () {
    $rawKey  = random_bytes(32);
    $enc     = new Encrypter($rawKey, 'AES-256-CBC');
    $plain   = 'conteudo-sensivel-CPF-fake-12345678901';

    $cipher  = $enc->encryptString($plain);
    $decoded = $enc->decryptString($cipher);

    expect($decoded)->toBe($plain);
});

// --- Tests com DB + Storage::fake ---

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode Modules/Arquivos migrate primeiro');
    }
});

it('--dry-run nao modifica bytes em disk', function () {
    Storage::fake('vault-test');

    $rawOldKey = random_bytes(32);
    $oldKey    = 'base64:' . base64_encode($rawOldKey);
    $enc       = new Encrypter($rawOldKey, 'AES-256-CBC');
    $plaintext = 'dry-run-test-content';
    $cipher    = $enc->encryptString($plaintext);

    Storage::disk('vault-test')->put('biz-1/dry.txt', $cipher);

    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'    => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'  => 8001,
        'disk'           => 'vault-test',
        'storage_path'   => 'biz-1/dry.txt',
        'filename'       => 'dry.txt',
        'mime_type'      => 'text/plain',
        'size_bytes'     => strlen($cipher),
        'md5'            => md5($cipher),
        'encrypted'      => true,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $exitCode = Artisan::call('arquivos:reencrypt-vault', [
        '--old-key' => $oldKey,
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0);

    // Bytes em disk INALTERADOS apos dry-run
    $diskBytes = Storage::disk('vault-test')->get('biz-1/dry.txt');
    expect($diskBytes)->toBe($cipher);

    DB::table('arquivos')->where('id', $arquivoId)->delete();
});

it('roundtrip: encrypt com key A -> reencrypt --old-key=A -> file decryptavel com Crypt facade atual', function () {
    Storage::fake('vault-test');

    $rawOldKey = random_bytes(32);
    $oldKey    = 'base64:' . base64_encode($rawOldKey);
    $enc       = new Encrypter($rawOldKey, 'AES-256-CBC');
    $plaintext = 'arquivo-confidencial-roundtrip-' . uniqid();
    $cipherOld = $enc->encryptString($plaintext);

    Storage::disk('vault-test')->put('biz-1/roundtrip.txt', $cipherOld);

    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'    => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'  => 8002,
        'disk'           => 'vault-test',
        'storage_path'   => 'biz-1/roundtrip.txt',
        'filename'       => 'roundtrip.txt',
        'mime_type'      => 'text/plain',
        'size_bytes'     => strlen($cipherOld),
        'md5'            => md5($cipherOld),
        'encrypted'      => true,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $exitCode = Artisan::call('arquivos:reencrypt-vault', [
        '--old-key' => $oldKey,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Re-encryptados: 1');

    // File em disk foi substituido por novo ciphertext (APP_KEY atual)
    $newCipher = Storage::disk('vault-test')->get('biz-1/roundtrip.txt');
    expect($newCipher)->not->toBe($cipherOld);

    // Decrypt com Crypt facade atual deve funcionar
    $decoded = Crypt::decryptString($newCipher);
    expect($decoded)->toBe($plaintext);

    DB::table('arquivos')->where('id', $arquivoId)->delete();
});

it('idempotente — segunda run skipa rows ja re-encryptadas', function () {
    Storage::fake('vault-test');

    $rawOldKey = random_bytes(32);
    $oldKey    = 'base64:' . base64_encode($rawOldKey);
    $enc       = new Encrypter($rawOldKey, 'AES-256-CBC');
    $plaintext = 'idempotent-vault-' . uniqid();
    $cipherOld = $enc->encryptString($plaintext);

    Storage::disk('vault-test')->put('biz-1/idem.txt', $cipherOld);

    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'    => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'  => 8003,
        'disk'           => 'vault-test',
        'storage_path'   => 'biz-1/idem.txt',
        'filename'       => 'idem.txt',
        'mime_type'      => 'text/plain',
        'size_bytes'     => strlen($cipherOld),
        'md5'            => md5($cipherOld),
        'encrypted'      => true,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    // 1a run: re-encrypta com nova key
    Artisan::call('arquivos:reencrypt-vault', ['--old-key' => $oldKey]);
    $afterFirst = Storage::disk('vault-test')->get('biz-1/idem.txt');

    // 2a run: old-key nao decripta o novo cipher — skip, nao erro
    $exitCode = Artisan::call('arquivos:reencrypt-vault', ['--old-key' => $oldKey]);
    $afterSecond = Storage::disk('vault-test')->get('biz-1/idem.txt');

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Skipped');
    expect($afterSecond)->toBe($afterFirst);

    DB::table('arquivos')->where('id', $arquivoId)->delete();
});

it('file ausente no disk conta em skipped (nao errored)', function () {
    Storage::fake('vault-test');

    $rawOldKey = random_bytes(32);
    $oldKey    = 'base64:' . base64_encode($rawOldKey);

    $arquivoId = DB::table('arquivos')->insertGetId([
        'business_id'    => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'  => 8004,
        'disk'           => 'vault-test',
        'storage_path'   => 'biz-1/inexistente-' . uniqid() . '.txt',
        'filename'       => 'inexistente.txt',
        'mime_type'      => 'text/plain',
        'size_bytes'     => 100,
        'md5'            => 'abc',
        'encrypted'      => true,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $exitCode = Artisan::call('arquivos:reencrypt-vault', [
        '--old-key' => $oldKey,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Skipped');
    expect(Artisan::output())->toContain('Errored:        0');

    DB::table('arquivos')->where('id', $arquivoId)->delete();
});
