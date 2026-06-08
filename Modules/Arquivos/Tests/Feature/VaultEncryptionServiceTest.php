<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Services\VaultEncryptionService;

uses(Tests\TestCase::class);

/**
 * VaultEncryptionService — Pest tests Sprint 1 dia 4 ADR 0123 §3.
 *
 * Cobertura:
 * - Roundtrip encrypt/decrypt preserva conteúdo
 * - Bytes em disk NÃO são plaintext
 * - UploadedFile encrypted ok
 * - Decrypt de file não-existente retorna null (não throw)
 * - Tampering detectado (DecryptException)
 * - isEncrypted helper acerta
 *
 * @see Modules/Arquivos/Services/VaultEncryptionService.php
 */

beforeEach(function () {
    Storage::fake('vault-test');
});

it('encrypt + decrypt roundtrip preserva conteúdo binary', function () {
    $vault = new VaultEncryptionService();
    $original = 'sensitive-content-' . random_bytes(64);

    $vault->putEncrypted('vault-test', 'biz-1/test.bin', $original);
    $decrypted = $vault->getDecrypted('vault-test', 'biz-1/test.bin');

    expect($decrypted)->toBe($original);
});

it('bytes em disk são ciphertext (NÃO plaintext)', function () {
    $vault = new VaultEncryptionService();
    $plaintext = 'CPF-confidencial-12345678901';

    $vault->putEncrypted('vault-test', 'biz-1/secret.txt', $plaintext);
    $rawDiskBytes = Storage::disk('vault-test')->get('biz-1/secret.txt');

    expect($rawDiskBytes)->not->toContain($plaintext);
    expect(strlen($rawDiskBytes))->toBeGreaterThan(strlen($plaintext)); // overhead Crypt envelope
});

it('UploadedFile encrypted ok via putFileEncrypted', function () {
    $vault = new VaultEncryptionService();
    $payload = 'XML NFe assinado <NFe>...</NFe>';
    $tmp = tempnam(sys_get_temp_dir(), 'vault-test-');
    file_put_contents($tmp, $payload);

    $uploaded = new UploadedFile($tmp, 'nfe-001.xml', 'application/xml', null, true);

    $vault->putFileEncrypted('vault-test', 'biz-1/nfe-001.xml', $uploaded);
    $decrypted = $vault->getDecrypted('vault-test', 'biz-1/nfe-001.xml');

    expect($decrypted)->toBe($payload);

    @unlink($tmp);
});

it('getDecrypted retorna null quando file não existe', function () {
    $vault = new VaultEncryptionService();
    $result = $vault->getDecrypted('vault-test', 'inexistente.txt');
    expect($result)->toBeNull();
});

it('detecta tampering — DecryptException quando bytes corrompidos', function () {
    $vault = new VaultEncryptionService();
    $vault->putEncrypted('vault-test', 'biz-1/tamper.txt', 'original');

    // Tamper: substitui bytes por random
    Storage::disk('vault-test')->put('biz-1/tamper.txt', 'lixo-corrompido-not-encrypted');

    expect(fn () => $vault->getDecrypted('vault-test', 'biz-1/tamper.txt'))
        ->toThrow(DecryptException::class);
});

it('isEncrypted retorna true pra Crypt-payload válido', function () {
    $vault = new VaultEncryptionService();
    $vault->putEncrypted('vault-test', 'biz-1/check.txt', 'whatever');

    $diskBytes = Storage::disk('vault-test')->get('biz-1/check.txt');
    expect($vault->isEncrypted($diskBytes))->toBeTrue();
});

it('isEncrypted retorna false pra plaintext comum', function () {
    $vault = new VaultEncryptionService();
    expect($vault->isEncrypted('hello world plaintext'))->toBeFalse();
    expect($vault->isEncrypted(base64_encode('not-a-payload')))->toBeFalse();
});

it('VaultEncryptionService é singleton no container', function () {
    $first  = app(VaultEncryptionService::class);
    $second = app(VaultEncryptionService::class);
    expect($first)->toBe($second);
});

// ─── Testes cap de tamanho Sprint 2 (ADR 0126) ───────────────────────────────

it('putEncrypted lança RuntimeException quando contents excede cap 50MB', function () {
    // Usa config override pra evitar alocar 51MB em memória nos testes
    config(['arquivos.vault_max_file_size_mb' => 1]);

    $vault    = new VaultEncryptionService();
    $tooBig   = str_repeat('x', 1 * 1024 * 1024 + 1); // 1MB + 1 byte

    expect(fn () => $vault->putEncrypted('vault-test', 'biz-1/big.bin', $tooBig))
        ->toThrow(\RuntimeException::class, 'cap');
});

it('putEncrypted menciona ADR 0126 na mensagem de erro', function () {
    config(['arquivos.vault_max_file_size_mb' => 1]);

    $vault  = new VaultEncryptionService();
    $tooBig = str_repeat('x', 1 * 1024 * 1024 + 1);

    expect(fn () => $vault->putEncrypted('vault-test', 'biz-1/big.bin', $tooBig))
        ->toThrow(\RuntimeException::class, 'ADR 0126');
});

it('putFileEncrypted lança RuntimeException quando UploadedFile size excede cap', function () {
    config(['arquivos.vault_max_file_size_mb' => 1]);

    $vault = new VaultEncryptionService();

    // UploadedFile com size customizado (simulando arquivo grande sem alocar memória)
    $tmp = tempnam(sys_get_temp_dir(), 'vault-test-big-');
    file_put_contents($tmp, 'small-actual-content');

    // Subclasse anônima pra sobrescrever getSize() sem alocar memória real
    $bigFile = new class($tmp, 'big.bin', 'application/octet-stream', null, true) extends UploadedFile {
        public function getSize(): int
        {
            return 2 * 1024 * 1024; // 2MB — excede cap de 1MB do config override
        }
    };

    expect(fn () => $vault->putFileEncrypted('vault-test', 'biz-1/big.bin', $bigFile))
        ->toThrow(\RuntimeException::class, 'cap');

    @unlink($tmp);
});

it('respeita override config vault_max_file_size_mb — rejeita em 11MB quando config=10', function () {
    config(['arquivos.vault_max_file_size_mb' => 10]);

    $vault  = new VaultEncryptionService();
    $tooBig = str_repeat('x', 10 * 1024 * 1024 + 1); // 10MB + 1 byte

    expect(fn () => $vault->putEncrypted('vault-test', 'biz-1/over10mb.bin', $tooBig))
        ->toThrow(\RuntimeException::class);
});

it('aceita conteúdo exatamente no limite do cap configurado', function () {
    config(['arquivos.vault_max_file_size_mb' => 1]);

    $vault    = new VaultEncryptionService();
    $exactly  = str_repeat('x', 1 * 1024 * 1024); // exatamente 1MB

    // Não deve lançar exceção
    $result = $vault->putEncrypted('vault-test', 'biz-1/exactly.bin', $exactly);
    expect($result)->toBeTrue();
});

it('lança RuntimeException de config quando vault_max_file_size_mb<=0', function () {
    config(['arquivos.vault_max_file_size_mb' => 0]);

    $vault = new VaultEncryptionService();

    expect(fn () => $vault->putEncrypted('vault-test', 'biz-1/any.bin', 'qualquer'))
        ->toThrow(\RuntimeException::class, 'vault_max_file_size_mb deve ser > 0');
});

it('lança RuntimeException de config quando vault_max_file_size_mb negativo', function () {
    config(['arquivos.vault_max_file_size_mb' => -5]);

    $vault = new VaultEncryptionService();

    expect(fn () => $vault->putEncrypted('vault-test', 'biz-1/any.bin', 'qualquer'))
        ->toThrow(\RuntimeException::class, 'ADR 0126');
});
