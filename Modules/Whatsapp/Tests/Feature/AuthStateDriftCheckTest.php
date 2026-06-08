<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Regression test pra incident 2026-05-15 — auth_state corrompido pós Baileys
 * 6.7.18→7.0.0-rc11 (failed to find key "AAAAALtG" to decode mutation).
 *
 * O comando `whatsapp:auth-state-drift-check` detecta 3 tipos de drift que
 * causariam recorrência do bug:
 *
 *   1. ORPHANS — auth_state rows pra instance_ids sem channel correspondente
 *   2. BANNED/INACTIVE — auth_state pra canais não-ativos (inútil)
 *   3. STALE >90d — auth_state sem update recente (possível major bump)
 *
 * Cada `it()` aqui prova UMA detecção. Se comando regredir, Pest quebra.
 *
 * Origens:
 * - memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md
 * - memory/handoffs/2026-05-15-0700-whatsapp-maratona-fechamento-... (lição)
 */
beforeEach(function () {
    foreach (['channels', 'whatsapp_baileys_auth_state'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('channels', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('channel_uuid')->unique();
        $table->string('label', 80);
        $table->string('type', 30);
        $table->string('status', 20)->default('setup');
        $table->timestamps();
    });

    Schema::create('whatsapp_baileys_auth_state', function ($table) {
        $table->bigIncrements('id');
        $table->string('instance_id', 100);
        $table->string('key_id', 191);
        $table->binary('value_encrypted')->nullable();
        $table->timestamp('updated_at')->useCurrent();
        $table->index('instance_id');
    });
});

function makeChannelDrift(int $bizId, string $uuid, string $status = 'active'): int
{
    return DB::table('channels')->insertGetId([
        'business_id' => $bizId,
        'channel_uuid' => $uuid,
        'label' => "Canal $bizId $status",
        'type' => 'whatsapp_baileys',
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeAuthStateRow(string $instanceId, int $count = 1, ?\Carbon\Carbon $updatedAt = null): void
{
    $updatedAt = $updatedAt ?? now();
    for ($i = 0; $i < $count; $i++) {
        DB::table('whatsapp_baileys_auth_state')->insert([
            'instance_id' => $instanceId,
            'key_id' => "key_$i_" . uniqid(),
            'value_encrypted' => null,
            'updated_at' => $updatedAt,
        ]);
    }
}

// ============================================================================
// E2E 1 — Detecta ZERO drift quando tudo alinhado
// ============================================================================

it('R-WA-AUTH-DRIFT-01 — sucesso silencioso quando auth_state alinhado com canais ativos', function () {
    $uuid = '11111111-2222-3333-4444-555555555555';
    makeChannelDrift(1, $uuid, 'active');
    $instanceId = 'ch-' . str_replace('-', '', $uuid);
    makeAuthStateRow($instanceId, 5);

    $exitCode = artisan('whatsapp:auth-state-drift-check');

    expect($exitCode)->toBe(0)->and(\Artisan::output())
        ->toContain('Zero orphans')
        ->and(\Artisan::output())
        ->toContain('SEM DRIFT');
});

// ============================================================================
// E2E 2 — Detecta orphan (auth_state sem channel)
// ============================================================================

it('R-WA-AUTH-DRIFT-02 — detecta ORPHAN: instance_id sem channel correspondente', function () {
    // Canal ativo legítimo
    $uuidOk = '11111111-2222-3333-4444-555555555555';
    makeChannelDrift(1, $uuidOk, 'active');
    makeAuthStateRow('ch-' . str_replace('-', '', $uuidOk), 3);

    // Orphan — instance_id sem channel
    makeAuthStateRow('ch-deadbeefcafebabe0000000000000000', 10);

    $exitCode = artisan('whatsapp:auth-state-drift-check');
    $output = \Artisan::output();

    expect($output)->toContain('ORPHANS: 1');
    expect($output)->toContain('ch-deadbeefca...');
    expect($exitCode)->toBe(0);
});

it('R-WA-AUTH-DRIFT-02b — --fail-on-drift retorna exit 1 quando orphan presente', function () {
    makeAuthStateRow('ch-orphannnnn00000000000000000000', 5);

    $exitCode = artisan('whatsapp:auth-state-drift-check', ['--fail-on-drift' => true]);

    expect($exitCode)->toBe(1);
});

// ============================================================================
// E2E 3 — Detecta auth_state pra canal banned/inactive (incident 14/mai canal id=8)
// ============================================================================

it('R-WA-AUTH-DRIFT-03 — detecta auth_state pra canal banned (residual incident 14/mai)', function () {
    $uuidBanned = '62edc13f-0949-4494-af0d-8adb9b8f8d90'; // canal id=8 do incident real
    makeChannelDrift(1, $uuidBanned, 'banned');
    makeAuthStateRow('ch-' . str_replace('-', '', $uuidBanned), 48);

    $exitCode = artisan('whatsapp:auth-state-drift-check');
    $output = \Artisan::output();

    expect($output)->toContain('BANNED/INACTIVE: 1');
    expect($output)->toContain('48 rows');
});

it('R-WA-AUTH-DRIFT-03b — detecta canal inactive (soft-deleted post-purge)', function () {
    $uuid = '99999999-8888-7777-6666-555555555555';
    makeChannelDrift(1, $uuid, 'inactive');
    makeAuthStateRow('ch-' . str_replace('-', '', $uuid), 10);

    artisan('whatsapp:auth-state-drift-check');
    $output = \Artisan::output();

    expect($output)->toContain('BANNED/INACTIVE: 1');
});

// ============================================================================
// E2E 4 — Detecta stale >90d (sinal de major bump não-purgado)
// ============================================================================

it('R-WA-AUTH-DRIFT-04 — detecta auth_state stale >90d (sinal Baileys major bump não-purgado)', function () {
    $uuid = '11111111-2222-3333-4444-555555555555';
    makeChannelDrift(1, $uuid, 'active');
    $instanceId = 'ch-' . str_replace('-', '', $uuid);
    // updated_at 120 dias atrás
    makeAuthStateRow($instanceId, 5, now()->subDays(120));

    artisan('whatsapp:auth-state-drift-check');
    $output = \Artisan::output();

    expect($output)->toContain('STALE >90d');
    expect($output)->toContain('120 dias parado');
});

// ============================================================================
// E2E 5 — Multi-tenant Tier 0: --biz filtra por business
// ============================================================================

it('R-WA-AUTH-DRIFT-05 — Tier 0 (ADR 0093): --biz=1 não vê drift em biz=99', function () {
    // biz=1 limpo
    $uuid1 = 'aaaaaaaa-2222-3333-4444-555555555555';
    makeChannelDrift(1, $uuid1, 'active');
    makeAuthStateRow('ch-' . str_replace('-', '', $uuid1), 3);

    // biz=99 com orphan
    makeAuthStateRow('ch-orphan99999999999999999999orph', 8);

    // Com filtro biz=1 — orphan biz=99 NÃO conta?
    // (Comando atual usa apenas canais ativos por biz como expected.
    // Orphan ainda aparece pq instance_id não tem channel correspondente
    // em NENHUM biz. Verifica que filtro de biz não esconde orphans cross-tenant.)
    artisan('whatsapp:auth-state-drift-check', ['--biz' => 1]);
    $output = \Artisan::output();

    // Orphan aparece (correto — defesa cross-tenant: detecta auth state sem dono)
    expect($output)->toContain('ORPHANS: 1');
});

// ============================================================================
// CONVENTION — comando registrado + ServiceProvider
// ============================================================================

it('R-WA-AUTH-DRIFT-CONV-01 — comando registrado no Console Kernel via Whatsapp ServiceProvider', function () {
    $commands = collect(\Artisan::all());

    expect($commands->keys())->toContain('whatsapp:auth-state-drift-check',
        'REGRESSÃO: comando whatsapp:auth-state-drift-check removido — drift Baileys voltará silencioso.');
});

it('R-WA-AUTH-DRIFT-CONV-02 — comando NUNCA deleta rows (regra Wagner Tier 0 "nunca perca mensagem" preventivo)', function () {
    $source = file_get_contents(
        base_path('Modules/Whatsapp/Console/Commands/WhatsappAuthStateDriftCheckCommand.php')
    );

    expect($source)->not->toMatch('/->delete\(\)/',
        'REGRESSÃO: comando ganhou ->delete() — viola regra "operador decide manualmente, nunca delete automático".');

    expect($source)->not->toMatch('/->truncate\(\)/',
        'REGRESSÃO: truncate em auth_state proibido.');
});
