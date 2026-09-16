<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

require_once __DIR__.'/../Support/credential-vectors.php';

/**
 * Comportamento de BANCO do `cc:secret-sweep`: dry-run, `--apply`, idempotência,
 * blob comprimido e `--fail-on-find`.
 *
 * ── POR QUE NÃO DROPA E NÃO PULA ─────────────────────────────────────────────
 * As duas saídas óbvias custavam caro, e cada uma numa catraca ARMADA que só
 * pode DESCER (ADR 0275 §3):
 *
 *   · `Schema::drop` no `beforeEach` destruiria as tabelas REAIS no MySQL
 *     persistente do CT 100/CI, quebrando as runs seguintes — é o que o
 *     `sqlite-test-corruptors` marca, e marcou: `sqlite_corruptors 0 → 1`.
 *   · `markTestSkipped` fora do sqlite (o idioma do vizinho
 *     `CcIngestPersistsFieldsTest`) evita aquilo, mas skip conta como
 *     quarentena: `n_quarantine 252 → 253`.
 *
 * Criar-se-não-existe resolve os dois: no MySQL as tabelas já estão migradas e
 * nenhum DDL roda; no sqlite `:memory:` elas nascem aqui. Efeito colateral bom —
 * o teste roda nas DUAS lanes (MySQL `forja-pest` e sqlite `PHP / Pest (Unit)`),
 * em vez de existir só numa.
 *
 * A parte que não toca banco (vocabulário + registro no Artisan) vive em
 * `CredentialShapesTest`.
 */
beforeEach(function () {
    if (! Schema::hasTable('mcp_cc_messages')) {
        Schema::create('mcp_cc_messages', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('session_id');
            $t->string('msg_uuid', 36)->unique();
            $t->unsignedInteger('user_id')->nullable();
            $t->unsignedInteger('business_id')->nullable();
            $t->string('msg_type', 20);
            $t->mediumText('content_text')->nullable();
            $t->json('content_json')->nullable();
            $t->timestamp('ts')->nullable();
            $t->timestamps();
        });
    }

    if (! Schema::hasTable('mcp_cc_blobs')) {
        Schema::create('mcp_cc_blobs', function ($t) {
            $t->bigIncrements('id');
            $t->string('hash_sha256', 64)->index();
            $t->string('blob_type', 30)->nullable();
            $t->string('mime_type', 100)->nullable();
            $t->unsignedBigInteger('size_original_bytes')->default(0);
            $t->unsignedBigInteger('size_compressed_bytes')->default(0);
            $t->binary('compressed_data')->nullable();
            $t->unsignedInteger('refs_count')->default(1);
            $t->timestamps();
        });
    }
});

/**
 * Some só com o que ESTE teste inseriu — nunca `truncate`, que apagaria dado
 * real no MySQL persistente. O prefixo `sweep-` do `msg_uuid` é a âncora.
 */
afterEach(function () {
    DB::table('mcp_cc_messages')->where('msg_uuid', 'like', 'sweep-%')->delete();
    DB::table('mcp_cc_blobs')->where('blob_type', 'sweep-test')->delete();
});

/**
 * O schema REAL tem `user_id` NOT NULL com FK pra `users`; o sintético do sqlite
 * aceita `null`. Resolver pelo banco cobre os dois sem ramificar por driver.
 */
function userIdParaSweep(): ?int
{
    if (! Schema::hasTable('users')) {
        return null;
    }

    $id = DB::table('users')->orderBy('id')->value('id');

    return $id === null ? null : (int) $id;
}

function inserirMensagemSweep(string $texto): int
{
    return (int) DB::table('mcp_cc_messages')->insertGetId([
        'session_id' => 1,
        'msg_uuid' => 'sweep-'.bin2hex(random_bytes(8)),
        'user_id' => userIdParaSweep(),
        // ADR 0358: tenant fictício 98 — nunca biz=4 (cliente) em teste.
        'business_id' => 98,
        'msg_type' => 'tool_result',
        'content_text' => $texto,
        'ts' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('dry-run NAO escreve, --apply escreve preservando o rotulo, e a 2a passada e idempotente', function () {
    $segredo = valorSinteticoDe('assign_generic');
    $original = 'DB_USERNAME=staging DB_PASSWORD='.$segredo.' APP_ENV=staging';
    $id = inserirMensagemSweep($original);

    Artisan::call('cc:secret-sweep');
    expect(DB::table('mcp_cc_messages')->where('id', $id)->value('content_text'))
        ->toBe($original, 'dry-run escreveu — deveria ser somente leitura');

    Artisan::call('cc:secret-sweep', ['--apply' => true]);
    $depois = (string) DB::table('mcp_cc_messages')->where('id', $id)->value('content_text');
    expect(str_contains($depois, $segredo))->toBeFalse('o segredo sobreviveu ao --apply');
    expect($depois)->toContain('DB_PASSWORD=[REDACTED:assign_generic]');
    expect($depois)->toContain('DB_USERNAME=staging');

    Artisan::call('cc:secret-sweep', ['--apply' => true]);
    expect(DB::table('mcp_cc_messages')->where('id', $id)->value('content_text'))
        ->toBe($depois, 'a 2a passada mudou o conteudo — nao e idempotente');
});

it('CONTROLE: mensagem benigna passa intacta pelo --apply', function () {
    $benigno = 'chamei mcp__ccd_session_mgmt__list_sessions e o SELECT devolveu 12 linhas';
    $id = inserirMensagemSweep($benigno);

    Artisan::call('cc:secret-sweep', ['--apply' => true]);

    expect(DB::table('mcp_cc_messages')->where('id', $id)->value('content_text'))
        ->toBe($benigno, 'redigiu conteudo benigno — falso-positivo');
});

it('redige BLOB, que vive comprimido, e mantem os quatro campos coerentes', function () {
    $segredo = valorSinteticoDe('aws_akia');
    $conteudo = 'log do deploy: usando '.$segredo.' no cluster';
    $comprimido = zlib_encode($conteudo, ZLIB_ENCODING_DEFLATE, 6);

    $id = (int) DB::table('mcp_cc_blobs')->insertGetId([
        'hash_sha256' => hash('sha256', $conteudo),
        'blob_type' => 'sweep-test',
        'size_original_bytes' => strlen($conteudo),
        'size_compressed_bytes' => strlen((string) $comprimido),
        'compressed_data' => $comprimido,
        'refs_count' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('cc:secret-sweep', ['--apply' => true]);

    $b = DB::table('mcp_cc_blobs')->where('id', $id)->first();
    $texto = zlib_decode($b->compressed_data);

    expect(str_contains((string) $texto, $segredo))->toBeFalse('o segredo sobreviveu no blob');
    expect($texto)->toContain('[REDACTED:aws_akia]');
    // os quatro campos andam juntos ou o registro fica incoerente
    expect($b->hash_sha256)->toBe(hash('sha256', (string) $texto));
    expect((int) $b->size_original_bytes)->toBe(strlen((string) $texto));
    expect((int) $b->size_compressed_bytes)->toBe(strlen((string) $b->compressed_data));
});

it('--fail-on-find devolve exit 1 com credencial e 0 depois de limpo', function () {
    $id = inserirMensagemSweep(valorSinteticoDe('aws_akia'));

    expect(Artisan::call('cc:secret-sweep', ['--fail-on-find' => true]))->toBe(1);

    Artisan::call('cc:secret-sweep', ['--apply' => true]);

    expect(DB::table('mcp_cc_messages')->where('id', $id)->value('content_text'))
        ->toContain('[REDACTED:aws_akia]');
    expect(Artisan::call('cc:secret-sweep', ['--fail-on-find' => true]))->toBe(0);
});
