<?php

declare(strict_types=1);

use App\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Arquivos\Entities\Arquivo;

uses(Tests\TestCase::class);

/**
 * DownloadController — auditoria de download (fix gap silencioso 2026-07-02).
 *
 * Bug (refutação adversarial G5): o controller emitia a ação `signed_url_consumed`,
 * que NÃO é membro do enum `arquivos_audit_log.action`
 * (upload|download|classify|reclassify|soft_delete|restore|hard_delete|signed_url_issued).
 * Em MySQL strict o INSERT era rejeitado e engolido pelo try/catch de audit()
 * → nenhum registro de download no audit trail (gap LGPD Art. 37). Fix: emitir
 * `download` (membro do enum, já usado como ação canônica no fixture de
 * AuditLogCommandTest e pareado com `signed_url_issued`).
 *
 * Cobertura:
 *  1. (behavioral) todo download via signed URL gera EXATAMENTE 1 audit entry
 *     com action `download` (membro válido do enum). Guarda contra o gap.
 *  2. (sentinela de regressão) o enum aceita `download` e NUNCA persiste
 *     `signed_url_consumed` — trava reintrodução do valor inválido.
 *  3. (contrato de source) DownloadController emite `download`, não
 *     `signed_url_consumed` — roda mesmo em CI sqlite (pega revert).
 *
 * Tier 0: biz=1 (Wagner WR2), nunca biz=4 (ROTA LIVRE — ADR 0101).
 * Cleanup por arquivo_id/test_marker — nunca afeta dados reais.
 *
 * @see Modules/Arquivos/Http/Controllers/DownloadController.php
 * @see Modules/Arquivos/Database/Migrations/2026_05_10_000002_create_arquivos_audit_log_table.php
 * @see memory/decisions/0123-modules-arquivos-backbone.md §6+§8
 */

// ---------------------------------------------------------------------------
// Setup / Teardown
// ---------------------------------------------------------------------------

beforeEach(function () {
    if (! Schema::hasTable('arquivos_audit_log') || ! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('Tabelas Arquivos ausentes — rode Modules/Arquivos migrate primeiro.');
    }
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }

    // Remove apenas rows marcadas pelo teste — nunca toca dados reais.
    DB::table('arquivos_audit_log')
        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.test_marker')) = 'download-audit-test'")
        ->delete();
});

// ---------------------------------------------------------------------------
// 1. Behavioral — download real gera exatamente 1 audit entry com ação válida
// ---------------------------------------------------------------------------

it('todo download via signed URL gera exatamente 1 audit entry com action download', function () {
    $user = User::where('business_id', 1)->first();

    if (! $user) {
        $this->markTestSkipped('biz=1 user (Wagner WR2) ausente no DB de teste — seed antes.');
    }

    Storage::fake('arquivos');

    $storagePath = 'biz-1/download-audit-' . uniqid() . '.txt';
    Storage::disk('arquivos')->put($storagePath, 'conteudo-do-arquivo-de-teste');

    // Global scope Arquivo lê business da sessão (session('user.business_id')).
    // A rota de download não tem SetSessionData; em prod usa a sessão de login.
    session(['user.business_id' => 1]);

    $arquivo = Arquivo::create([
        'business_id'   => 1,
        'disk'          => 'arquivos',
        'storage_path'  => $storagePath,
        'original_name' => 'download-audit-test.txt',
        'mime_type'     => 'text/plain',
        'size_bytes'    => 28,
        'md5'           => md5('conteudo-do-arquivo-de-teste'),
        'bucket'        => 'active',
        'visibility'    => 'private',
        'encrypted'     => false,
    ]);

    $this->actingAs($user);

    $url = URL::signedRoute('arquivos.download', ['arquivo' => $arquivo->id]);

    $response = $this->withSession(['user.business_id' => 1])->get($url);

    $response->assertOk();

    // Exatamente 1 audit entry pra este arquivo, com ação válida do enum.
    $rows = DB::table('arquivos_audit_log')->where('arquivo_id', $arquivo->id)->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->action)->toBe('download');

    // Cleanup explícito (o controller escreve o próprio payload, sem test_marker).
    DB::table('arquivos_audit_log')->where('arquivo_id', $arquivo->id)->delete();
    $arquivo->forceDelete();
});

// ---------------------------------------------------------------------------
// 2. Sentinela de regressão — enum aceita `download`, rejeita `signed_url_consumed`
// ---------------------------------------------------------------------------

it('enum arquivos_audit_log aceita download e nunca persiste signed_url_consumed', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Enum só é enforced em MySQL — sqlite trata como texto livre.');
    }

    $base = [
        'arquivo_id'  => 990201,
        'business_id' => 1,
        'user_id'     => 100,
        'payload'     => json_encode(['test_marker' => 'download-audit-test', 'ip' => '10.0.0.1']),
        'created_at'  => now(),
    ];

    // `download` é membro do enum → INSERT sucede.
    DB::table('arquivos_audit_log')->insert(array_merge($base, ['action' => 'download']));

    $ok = DB::table('arquivos_audit_log')
        ->where('arquivo_id', 990201)
        ->where('action', 'download')
        ->exists();

    expect($ok)->toBeTrue();

    // `signed_url_consumed` NÃO é membro → strict rejeita (throw), non-strict trunca
    // pra ''. Em ambos os casos o valor NUNCA é persistido. Cobre os dois modos.
    try {
        DB::table('arquivos_audit_log')->insert(array_merge($base, [
            'arquivo_id' => 990202,
            'action'     => 'signed_url_consumed',
        ]));
    } catch (QueryException $e) {
        // Esperado em MySQL strict (Data truncated for column 'action').
    }

    $persistiu = DB::table('arquivos_audit_log')
        ->where('action', 'signed_url_consumed')
        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.test_marker')) = 'download-audit-test'")
        ->exists();

    expect($persistiu)->toBeFalse();
});

// ---------------------------------------------------------------------------
// 3. Contrato de source — controller emite `download`, não `signed_url_consumed`
// ---------------------------------------------------------------------------

it('DownloadController emite action download e não o valor fora do enum', function () {
    $source = file_get_contents(__DIR__ . '/../../Http/Controllers/DownloadController.php');

    expect($source)->toContain("audit(\$row, 'download'");
    expect($source)->not->toContain("audit(\$row, 'signed_url_consumed'");
});
