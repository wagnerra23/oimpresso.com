<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Arquivos\Entities\Arquivo;

uses(Tests\TestCase::class);

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

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    // Cleanup scope-free (sem global scope): remove audit rows da fixture + o arquivo.
    $ids = DB::table('arquivos')->where('md5', DOWNLOAD_AUDIT_MD5)->pluck('id');
    if ($ids->isNotEmpty()) {
        DB::table('arquivos_audit_log')->whereIn('arquivo_id', $ids)->delete();
    }
    DB::table('arquivos')->where('md5', DOWNLOAD_AUDIT_MD5)->delete();
});

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
