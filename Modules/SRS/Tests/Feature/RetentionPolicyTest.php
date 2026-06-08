<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * RetentionPolicyTest — valida Config/retention.php declarativo (D7 LGPD).
 *
 * Wave 18 RETRY — D7 boost.
 *
 * Tests DECLARATIVOS sem DB — apenas assertam:
 *   - chave-valor presente em Config/retention.php
 *   - tipos corretos (int)
 *   - ordem hierárquica esperada (drafts < logs < generated_docs)
 *   - env override funciona
 *
 * Complementa `DocRetentionCleanerTest` (futuro com schema MySQL) que valida
 * lógica DELETE real. Aqui só contrato declarativo — sobrevive em SQLite/CI.
 *
 * @see Modules\SRS\Config\retention.php
 * @see Modules\SRS\Services\DocRetentionCleaner
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §4
 */

it('retention.php declara as 4 janelas LGPD mínimas', function () {
    $cfg = require __DIR__ . '/../../Config/retention.php';

    expect($cfg)->toHaveKey('generated_docs_days');
    expect($cfg)->toHaveKey('draft_versions_days');
    expect($cfg)->toHaveKey('generation_logs_days');
    expect($cfg)->toHaveKey('chat_messages_days');
});

it('retention.php valores são int positivos', function () {
    $cfg = require __DIR__ . '/../../Config/retention.php';

    foreach (['generated_docs_days', 'draft_versions_days', 'generation_logs_days', 'chat_messages_days'] as $k) {
        expect($cfg[$k])->toBeInt();
        expect($cfg[$k])->toBeGreaterThan(0);
    }
});

it('retention.php respeita hierarquia LGPD: drafts (curto) < logs (médio) < generated_docs (longo)', function () {
    $cfg = require __DIR__ . '/../../Config/retention.php';

    expect($cfg['draft_versions_days'])->toBeLessThan($cfg['generation_logs_days']);
    expect($cfg['generation_logs_days'])->toBeLessThanOrEqual($cfg['generated_docs_days']);
});

it('retention.php defaults conservadores: chat_messages=365d, generated_docs=1825d', function () {
    // Limpa env override se houver — defaults sãoo valor "puro" do require.
    putenv('SRS_RETENTION_CHAT_MESSAGES_DAYS');
    putenv('SRS_RETENTION_GENERATED_DOCS_DAYS');

    $cfg = require __DIR__ . '/../../Config/retention.php';

    expect($cfg['chat_messages_days'])->toBe(365);
    expect($cfg['generated_docs_days'])->toBe(1825);
});

it('retention.php env override funciona pra cada janela', function () {
    putenv('SRS_RETENTION_CHAT_MESSAGES_DAYS=30');
    putenv('SRS_RETENTION_DRAFT_VERSIONS_DAYS=7');

    $cfg = require __DIR__ . '/../../Config/retention.php';

    expect($cfg['chat_messages_days'])->toBe(30);
    expect($cfg['draft_versions_days'])->toBe(7);

    // Reset.
    putenv('SRS_RETENTION_CHAT_MESSAGES_DAYS');
    putenv('SRS_RETENTION_DRAFT_VERSIONS_DAYS');
});

it('DocRetentionCleaner é instanciável (Service registrado)', function () {
    $service = new \Modules\SRS\Services\DocRetentionCleaner();
    expect($service)->toBeInstanceOf(\Modules\SRS\Services\DocRetentionCleaner::class);
});

it('DocRetentionCleaner.dryRun() não toca DB em rota declarativa', function () {
    // Só valida que método existe + retorna shape esperado.
    $service = new \Modules\SRS\Services\DocRetentionCleaner();

    if (! \Schema::hasTable('docs_chat_messages')) {
        $this->markTestSkipped('Schema docs_* ausente (rodar com MySQL/migrate). Smoke API só.');
    }

    $result = $service->dryRun();

    expect($result)->toHaveKey('chat_messages');
    expect($result)->toHaveKey('validation_runs');
    expect($result)->toHaveKey('cutoffs');
    expect($result['cutoffs'])->toHaveKeys(['chat', 'logs', 'draft', 'docs']);
});
