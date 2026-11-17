<?php

declare(strict_types=1);

use App\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Arquivos\Entities\Arquivo;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Download de arquivo do vault que o servidor não consegue decifrar.
 *
 * O que prova: quando o conteúdo cifrado não abre com a APP_KEY atual (chave rotacionada
 * sem a antiga em APP_PREVIOUS_KEYS e sem `arquivos:reencrypt-vault`, ou arquivo
 * adulterado), o download responde 409 com mensagem clara, em vez de 500. O controle
 * positivo é o mesmo arquivo cifrado com a chave atual, que sai 200 com o conteúdo.
 * Contrato: ADR 0123 §3 (vault cifrado em repouso, servido só pelo DownloadController) +
 * auditoria Arquivos 2026-11-05, lote L2 (2.6).
 *
 * TENANT: 98 canônico (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 * Transação revertida (DatabaseTransactions); disco `vault` fake.
 * NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente).
 */
const DVF_BIZ = Tests\TestCase::SEEDED_TENANT_ID;

function dvfArquivo(string $nome, string $cipher): Arquivo
{
    $arquivo = Arquivo::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste fora de sessão
        'business_id'   => DVF_BIZ,
        'disk'          => 'vault',
        'storage_path'  => 'biz-'.DVF_BIZ."/{$nome}",
        'original_name' => $nome,
        'mime_type'     => 'text/plain',
        'size_bytes'    => strlen($cipher),
        'md5'           => md5($cipher),
        'bucket'        => 'sensitive',
        'encrypted'     => true,
    ]);
    Storage::disk('vault')->put($arquivo->storage_path, $cipher);

    return $arquivo;
}

function dvfBaixar($test, Arquivo $arquivo)
{
    $user = User::factory()->create([
        'business_id' => DVF_BIZ,
        'username'    => 'arq_dvf_'.uniqid(),
    ]);
    $url = URL::temporarySignedRoute('arquivos.download', now()->addMinutes(10), ['arquivo' => $arquivo->id]);

    return $test->actingAs($user)
        ->withSession(['user' => ['business_id' => DVF_BIZ, 'id' => $user->id]])
        ->get($url, ['Accept' => 'application/json']);
}

beforeEach(function () {
    if (! Schema::hasTable('arquivos') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tabelas ausentes — rodar Modules/Arquivos migrate primeiro.');
    }
});

it('controle: arquivo do vault cifrado com a chave atual sai 200 com o conteudo', function () {
    Storage::fake('vault');
    $plain = 'vault-dvf-ok-'.uniqid();
    $arquivo = dvfArquivo('ok.txt', Crypt::encryptString($plain));

    $response = dvfBaixar($this, $arquivo);

    $response->assertOk();
    expect($response->getContent())->toBe($plain);
});

it('arquivo do vault cifrado com chave que o servidor nao tem sai 409 com mensagem clara, nao 500', function () {
    Storage::fake('vault');
    $outraChave = new Encrypter(random_bytes(32), 'AES-256-CBC');
    $arquivo = dvfArquivo('chave-antiga.txt', $outraChave->encryptString('conteudo-inacessivel'));

    $response = dvfBaixar($this, $arquivo);

    $response->assertStatus(409);
    expect((string) $response->json('message'))->toContain('chave');
    expect($response->getContent())->not->toContain('conteudo-inacessivel');
});
