<?php

declare(strict_types=1);

use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * arquivos:reencrypt-vault lê a chave antiga de APP_PREVIOUS_KEYS.
 *
 * O que prova: sem --old-key, o comando usa config('app.previous_keys') (a mesma lista
 * que o Encrypter do Laravel usa para ler o que foi cifrado antes da rotação), tenta
 * cada chave antiga, e regrava com a APP_KEY atual. Sem chave nenhuma, sai 1 citando
 * APP_PREVIOUS_KEYS. Chave inválida na lista sai 1 citando a posição, nunca a chave.
 * --old-key, quando vem, tem prioridade sobre a lista.
 * Contrato: ADR 0126 (rotação de APP_KEY) + auditoria Arquivos 2026-11-05, lote L2 (2.6):
 * a chave antiga não deve precisar passar pela linha de comando.
 *
 * TENANT: 98 canônico (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 * Linhas em transação revertida (DatabaseTransactions); o disco é fake.
 * NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente).
 */
const RPK_BIZ = Tests\TestCase::SEEDED_TENANT_ID;

function rpkArquivo(string $path, string $cipher): int
{
    return DB::table('arquivos')->insertGetId([
        'business_id'     => RPK_BIZ,
        'arquivable_type' => 'TestModel',
        'arquivable_id'   => 987650400,
        'disk'            => 'vault-test',
        'storage_path'    => $path,
        'original_name'   => basename($path),
        'mime_type'       => 'text/plain',
        'size_bytes'      => strlen($cipher),
        'md5'             => md5($cipher),
        'encrypted'       => true,
        'classified_by'   => 'test-l2-reencrypt-previous-keys',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
}

/** @return array{0: string, 1: Encrypter} chave no formato base64:... + encrypter dela */
function rpkChave(): array
{
    $raw = random_bytes(32);

    return ['base64:'.base64_encode($raw), new Encrypter($raw, 'AES-256-CBC')];
}

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode Modules/Arquivos migrate primeiro');
    }
});

it('sem --old-key e sem APP_PREVIOUS_KEYS sai 1 apontando APP_PREVIOUS_KEYS', function () {
    config(['app.previous_keys' => []]);

    $exit = Artisan::call('arquivos:reencrypt-vault');

    expect($exit)->toBe(1);
    expect(Artisan::output())->toContain('APP_PREVIOUS_KEYS');
});

it('sem --old-key usa APP_PREVIOUS_KEYS, tenta cada chave e regrava com a APP_KEY atual', function () {
    Storage::fake('vault-test');
    [$outra] = rpkChave();
    [$antiga, $enc] = rpkChave();
    $plain = 'vault-previous-keys-'.uniqid();
    $cipherAntigo = $enc->encryptString($plain);

    Storage::disk('vault-test')->put('biz-98/prev.txt', $cipherAntigo);
    rpkArquivo('biz-98/prev.txt', $cipherAntigo);

    // A chave certa é a SEGUNDA da lista: prova que o comando tenta todas.
    config(['app.previous_keys' => [$outra, $antiga]]);

    $exit = Artisan::call('arquivos:reencrypt-vault');
    $out = Artisan::output();

    expect($exit)->toBe(0);
    expect($out)->toContain('via APP_PREVIOUS_KEYS');
    expect($out)->toContain('Re-encryptados: 1');

    $novo = Storage::disk('vault-test')->get('biz-98/prev.txt');
    expect($novo)->not->toBe($cipherAntigo);
    expect(Crypt::decryptString($novo))->toBe($plain);
});

it('chave invalida em APP_PREVIOUS_KEYS sai 1 citando a posicao, sem imprimir a chave', function () {
    $invalida = 'sem-prefixo-'.uniqid();
    config(['app.previous_keys' => [$invalida]]);

    $exit = Artisan::call('arquivos:reencrypt-vault');
    $out = Artisan::output();

    expect($exit)->toBe(1);
    expect($out)->toContain('APP_PREVIOUS_KEYS[0]');
    expect(str_contains($out, $invalida))->toBeFalse();
});

it('--old-key tem prioridade sobre APP_PREVIOUS_KEYS', function () {
    Storage::fake('vault-test');
    [$errada] = rpkChave();
    [$certa, $enc] = rpkChave();
    $plain = 'vault-old-key-prioridade-'.uniqid();
    $cipherAntigo = $enc->encryptString($plain);

    Storage::disk('vault-test')->put('biz-98/prio.txt', $cipherAntigo);
    rpkArquivo('biz-98/prio.txt', $cipherAntigo);

    config(['app.previous_keys' => [$errada]]);

    $exit = Artisan::call('arquivos:reencrypt-vault', ['--old-key' => $certa]);
    $out = Artisan::output();

    expect($exit)->toBe(0);
    expect($out)->toContain('via --old-key');
    expect($out)->toContain('Re-encryptados: 1');
    expect(Crypt::decryptString(Storage::disk('vault-test')->get('biz-98/prio.txt')))->toBe($plain);
});
