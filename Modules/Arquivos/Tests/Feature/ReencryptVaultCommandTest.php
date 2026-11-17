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
 * arquivos:reencrypt-vault — contrato da rotação de APP_KEY.
 *
 * O que prova: quando a APP_KEY gira, o comando lê cada arquivo `encrypted=true` com a
 * chave ANTIGA (--old-key) e o regrava cifrado com a chave atual; --dry-run não toca o
 * disco; rodar de novo pula o que já foi regravado; arquivo ausente conta em skipped,
 * não em errored.
 * Contrato: ADR 0126 (vault, "arquivos:reencrypt-vault ... re-criptografar quando
 * APP_KEY rotacionar") + CHANGELOG 0.2.0 ("comando rotação APP_KEY").
 *
 * TENANT: 98 canônico (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 * O comando varre TODAS as linhas encrypted=true (é de plataforma). As de outros testes
 * ficam em disco real e caem em skipped; por isso os asserts contam só o que este teste
 * semeou no disco fake.
 * Limpeza no afterEach de arquivo, por marcador de classified_by.
 * NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente).
 */
const REENC_BIZ = Tests\TestCase::SEEDED_TENANT_ID;
const REENC_TAG = 'test-l1-reencrypt-vault';

function reencArquivo(string $path, int $sizeBytes, string $md5): int
{
    return DB::table('arquivos')->insertGetId([
        'business_id'     => REENC_BIZ,
        'arquivable_type' => 'TestModel',
        'arquivable_id'   => 987650300,
        'disk'            => 'vault-test',
        'storage_path'    => $path,
        'original_name'   => basename($path),
        'mime_type'       => 'text/plain',
        'size_bytes'      => $sizeBytes,
        'md5'             => $md5,
        'encrypted'       => true,
        'classified_by'   => REENC_TAG,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
}

/** @return array{0: string, 1: Encrypter} chave antiga no formato do --old-key + encrypter dela */
function reencChaveAntiga(): array
{
    $raw = random_bytes(32);

    return ['base64:'.base64_encode($raw), new Encrypter($raw, 'AES-256-CBC')];
}

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode Modules/Arquivos migrate primeiro');
    }
});

afterEach(function () {
    if (Schema::hasTable('arquivos')) {
        DB::table('arquivos')
            ->where('business_id', REENC_BIZ)
            ->where('classified_by', REENC_TAG)
            ->delete();
    }
});

it('command arquivos:reencrypt-vault esta registrado em artisan list', function () {
    expect(Artisan::all())->toHaveKey('arquivos:reencrypt-vault');
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
    [, $enc] = reencChaveAntiga();
    $plain = 'conteudo-ficticio-de-teste';

    expect($enc->decryptString($enc->encryptString($plain)))->toBe($plain);
});

it('--dry-run nao modifica bytes em disk', function () {
    Storage::fake('vault-test');
    [$oldKey, $enc] = reencChaveAntiga();
    $cipher = $enc->encryptString('dry-run-test-content');

    Storage::disk('vault-test')->put('biz-98/dry.txt', $cipher);
    reencArquivo('biz-98/dry.txt', strlen($cipher), md5($cipher));

    $exitCode = Artisan::call('arquivos:reencrypt-vault', [
        '--old-key' => $oldKey,
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(Storage::disk('vault-test')->get('biz-98/dry.txt'))->toBe($cipher);
});

// @covers-us US-ARQ-033
it('roundtrip: encrypt com key A -> reencrypt --old-key=A -> file decryptavel com Crypt facade atual', function () {
    Storage::fake('vault-test');
    [$oldKey, $enc] = reencChaveAntiga();
    $plaintext = 'arquivo-ficticio-roundtrip-'.uniqid();
    $cipherOld = $enc->encryptString($plaintext);

    Storage::disk('vault-test')->put('biz-98/roundtrip.txt', $cipherOld);
    reencArquivo('biz-98/roundtrip.txt', strlen($cipherOld), md5($cipherOld));

    $exitCode = Artisan::call('arquivos:reencrypt-vault', ['--old-key' => $oldKey]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Re-encryptados: 1');

    $newCipher = Storage::disk('vault-test')->get('biz-98/roundtrip.txt');
    expect($newCipher)->not->toBe($cipherOld);
    expect(Crypt::decryptString($newCipher))->toBe($plaintext);
});

it('idempotente — segunda run skipa rows ja re-encryptadas', function () {
    Storage::fake('vault-test');
    [$oldKey, $enc] = reencChaveAntiga();
    $plaintext = 'idempotent-vault-'.uniqid();
    $cipherOld = $enc->encryptString($plaintext);

    Storage::disk('vault-test')->put('biz-98/idem.txt', $cipherOld);
    reencArquivo('biz-98/idem.txt', strlen($cipherOld), md5($cipherOld));

    Artisan::call('arquivos:reencrypt-vault', ['--old-key' => $oldKey]);
    $afterFirst = Storage::disk('vault-test')->get('biz-98/idem.txt');

    // Controle: a 1ª run regravou de fato (senão "não mudou na 2ª" seria verde por vácuo).
    expect(Crypt::decryptString($afterFirst))->toBe($plaintext);

    // 2ª run: a old-key não decifra o cipher novo — skip, não erro.
    $exitCode = Artisan::call('arquivos:reencrypt-vault', ['--old-key' => $oldKey]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Re-encryptados: 0');
    expect(Storage::disk('vault-test')->get('biz-98/idem.txt'))->toBe($afterFirst);
});

it('file ausente no disk conta em skipped (nao errored)', function () {
    Storage::fake('vault-test');
    [$oldKey] = reencChaveAntiga();

    reencArquivo('biz-98/inexistente-'.uniqid().'.txt', 100, str_repeat('a', 32));

    $exitCode = Artisan::call('arquivos:reencrypt-vault', ['--old-key' => $oldKey]);
    // Artisan::output() esvazia o buffer na 1ª leitura (BufferedOutput::fetch): ler 1×.
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Re-encryptados: 0');
    expect($output)->toContain('Errored:        0');
});
