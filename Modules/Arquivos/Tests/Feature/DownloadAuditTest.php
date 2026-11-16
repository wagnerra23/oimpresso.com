<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Arquivos\Entities\Arquivo;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Regressão do bug de audit `signed_url_consumed` (code review adversarial 2026-07-02).
 *
 * Consumir a rota assinada `arquivos.download` DEVE gravar uma linha em
 * `arquivos_audit_log` com action='signed_url_consumed' (ADR 0123 §8 — audit integral).
 *
 * Antes do fix, o enum da migration não tinha esse valor → em MySQL strict mode o
 * INSERT falhava e era engolido pelo try/catch do DownloadController::audit() →
 * NENHUMA consumação de signed URL era auditada.
 *
 * biz=1 (ADR 0101 — nunca biz=4 ROTA LIVRE). MySQL-only: o bug só reproduz onde o
 * ENUM é validado (SQLite ignora), então o smoke real acontece no CT 100.
 *
 * @see Modules/Arquivos/Http/Controllers/DownloadController.php
 * @see Modules/Arquivos/Database/Migrations/2026_07_02_000001_widen_arquivos_audit_log_action_enum.php
 */

const DOWNLOAD_AUDIT_MD5 = 'dddddddddddddddddddddddddddddddd'; // 32× 'd' — fixture isolada

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite ignora ENUM strict — o bug só reproduz em MySQL (CT 100).');
    }
    if (! Schema::hasTable('arquivos') || ! Schema::hasTable('arquivos_audit_log') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tabelas ausentes — rodar Modules/Arquivos migrate primeiro.');
    }
});

// Sem afterEach de limpeza: cada teste roda numa transação revertida (DatabaseTransactions).
// arquivos_audit_log é append-only (ADR 0123 §8) e vai ganhar trigger BEFORE DELETE no L2;
// apagar linhas da trilha no teardown cairia nele.

it('consumir a signed URL grava audit signed_url_consumed', function () {
    Storage::fake('arquivos');

    $user = User::factory()->create(['business_id' => 1]);
    session(['user' => ['business_id' => 1, 'id' => $user->id]]);

    $arquivo = Arquivo::create([
        'business_id'   => 1,
        'disk'          => 'arquivos',
        'storage_path'  => 'biz-1/audit-consume-test.txt',
        'original_name' => 'audit-consume-test.txt',
        'mime_type'     => 'text/plain',
        'size_bytes'    => 14,
        'md5'           => DOWNLOAD_AUDIT_MD5,
        'bucket'        => 'active',
        'encrypted'     => false,
    ]);

    Storage::disk('arquivos')->put($arquivo->storage_path, 'conteudo-teste');

    $url = URL::temporarySignedRoute(
        'arquivos.download',
        now()->addMinutes(10),
        ['arquivo' => $arquivo->id],
    );

    $response = $this->actingAs($user)
        ->withSession(['user' => ['business_id' => 1, 'id' => $user->id]])
        ->get($url);

    $response->assertOk();

    $row = DB::table('arquivos_audit_log')
        ->where('arquivo_id', $arquivo->id)
        ->where('action', 'signed_url_consumed')
        ->first();

    // O CORE do bug: sem o enum ampliado, esta linha nunca existiria.
    expect($row)->not->toBeNull();
    expect((int) $row->business_id)->toBe(1);
    expect((int) $row->user_id)->toBe($user->id);

    // Payload carrega IP (o que alimenta o detector anti-scraping do audit-log --suspicious).
    $payload = json_decode((string) $row->payload, true);
    expect($payload)->toHaveKey('ip');
});

/*
 * Isolamento entre empresas no download (auditoria Arquivos 2026-11-05, lote 0 · D1).
 *
 * A rota `arquivos.download` não roda SetSessionData. Antes do conserto o controller fazia
 * `Arquivo::find()` e confiava no global scope, que só filtra quando a sessão tem
 * `user.business_id`. Sessão autenticada sem empresa (ex.: restaurada por "lembrar de mim")
 * baixava o arquivo de qualquer empresa com um link assinado vazado.
 *
 * Tenant de teste 98, vizinho 99 (ADR 0358). `arquivos.business_id` não tem FK (comentada
 * na migration 2026_05_10_000001), então o arquivo da 99 não depende da empresa existir.
 */

function arquivoDl(int $businessId, string $nome): Arquivo
{
    $arquivo = Arquivo::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste cria arquivo de outra empresa
        'business_id'   => $businessId,
        'disk'          => 'arquivos',
        'storage_path'  => "biz-{$businessId}/{$nome}",
        'original_name' => $nome,
        'mime_type'     => 'text/plain',
        'size_bytes'    => 20,
        'md5'           => DOWNLOAD_AUDIT_MD5,
        'bucket'        => 'active',
        'encrypted'     => false,
    ]);
    Storage::disk('arquivos')->put($arquivo->storage_path, "conteudo-da-{$businessId}");

    return $arquivo;
}

function usuarioDl(?int $businessId, string $sufixo): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'username'    => 'arqdl_l0_'.$sufixo.'_'.uniqid(),
    ]);
}

function linkDl(Arquivo $arquivo): string
{
    return URL::temporarySignedRoute('arquivos.download', now()->addMinutes(10), ['arquivo' => $arquivo->id]);
}

it('UC-ARQ-DL-01: sessão da 98 baixa o próprio arquivo e recebe 404 no arquivo da 99', function () {
    Storage::fake('arquivos');
    $user = usuarioDl(98, 'a');
    $proprio = arquivoDl(98, 'proprio-98.txt');
    $vizinho = arquivoDl(99, 'vizinho-99.txt');
    $sessao = ['user' => ['business_id' => 98, 'id' => $user->id]];

    // Controle positivo: o caminho feliz funciona, então o 404 abaixo é do isolamento.
    $ok = $this->actingAs($user)->withSession($sessao)->get(linkDl($proprio));
    $ok->assertOk();
    expect($ok->streamedContent())->toBe('conteudo-da-98');

    $this->actingAs($user)->withSession($sessao)->get(linkDl($vizinho))->assertNotFound();
});

it('UC-ARQ-DL-02: usuário autenticado sem empresa na sessão não baixa arquivo de outra empresa', function () {
    Storage::fake('arquivos');
    $user = usuarioDl(98, 'b');
    $vizinho = arquivoDl(99, 'vizinho-99.txt');

    // Sem withSession: é a sessão restaurada por "lembrar de mim", sem SetSessionData.
    $resp = $this->actingAs($user)->get(linkDl($vizinho));

    $resp->assertNotFound();
    expect((string) $resp->getContent())->not->toContain('conteudo-da-99');
});

it('UC-ARQ-DL-03: usuário sem empresa nenhuma não baixa nada, nem com link válido', function () {
    Storage::fake('arquivos');
    $user = usuarioDl(null, 'c');
    $arquivo = arquivoDl(98, 'qualquer-98.txt');

    $resp = $this->actingAs($user)->get(linkDl($arquivo));

    $resp->assertNotFound();
    expect((string) $resp->getContent())->not->toContain('conteudo-da-98');
});

it('UC-ARQ-DL-04: sem sessão, a empresa vem do usuário autenticado (ADR 0453) e ele baixa o próprio arquivo', function () {
    Storage::fake('arquivos');
    $user = usuarioDl(98, 'd');
    $proprio = arquivoDl(98, 'proprio-98.txt');

    $resp = $this->actingAs($user)->get(linkDl($proprio));

    $resp->assertOk();
    expect($resp->streamedContent())->toBe('conteudo-da-98');
});

it('UC-ARQ-DL-05: link adulterado ou expirado continua barrado', function () {
    Storage::fake('arquivos');
    $user = usuarioDl(98, 'e');
    $proprio = arquivoDl(98, 'proprio-98.txt');
    $sessao = ['user' => ['business_id' => 98, 'id' => $user->id]];

    $adulterado = linkDl($proprio).'0';
    $this->actingAs($user)->withSession($sessao)->get($adulterado)->assertForbidden();

    $expirado = URL::temporarySignedRoute('arquivos.download', now()->subMinute(), ['arquivo' => $proprio->id]);
    $this->actingAs($user)->withSession($sessao)->get($expirado)->assertForbidden();

    $semAssinatura = route('arquivos.download', ['arquivo' => $proprio->id]);
    $this->actingAs($user)->withSession($sessao)->get($semAssinatura)->assertForbidden();
});
