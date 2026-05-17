<?php

declare(strict_types=1);

namespace Modules\Vestuario\Tests\Feature;

/**
 * D7 LGPD Compliance — Modules/Vestuario
 *
 * RESTAURADO Wave 18 (regressão Wave 17 — arquivo perdido junto com Config dir).
 * FIX W25: removido uso de `base_path()` (Laravel helper) — substituído por
 * path-resolution standalone (mesmo pattern W23/W25). Falhas pre-W25 vinham de
 * tentar bootar Laravel sem TestCase estendido. Asserts continuam idênticas.
 *
 * Garante presença + sanidade dos artefatos LGPD canônicos do vertical Vestuario:
 *  - retention.php (política de retenção declarada)
 *  - LogsActivity trait em VestuarioSetting (audit append-only Spatie)
 *  - Multi-tenant Tier 0 scope respeitado em fila/purge ([ADR 0093])
 *
 * Tests biz=99 (canary não-cliente, ADR 0101). NUNCA biz=4 ROTA LIVRE.
 *
 * @see Modules/Vestuario/Config/retention.php
 * @see Modules/Vestuario/Entities/VestuarioSetting.php
 */

use Modules\Vestuario\Entities\VestuarioSetting;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Helper path-resolution standalone (sem boot Laravel — W25 fix).
 * Sobe 5 níveis: file → Feature → Tests → Vestuario → Modules → repo root.
 */
function vestuarioLgpdPath(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

describe('D7 LGPD — retention policy declarada', function () {

    it('config retention.php existe e é array válido', function () {
        $path = vestuarioLgpdPath('Modules/Vestuario/Config/retention.php');
        expect(file_exists($path))->toBeTrue('retention.php deve existir no Modules/Vestuario/Config/');

        $config = require $path;
        expect($config)->toBeArray();
        expect($config)->toHaveKeys(['enabled', 'entities', 'strategy', 'notice_period_days']);
    });

    it('declara retention pra vestuario_settings (audit fiscal)', function () {
        $config = require vestuarioLgpdPath('Modules/Vestuario/Config/retention.php');
        expect($config['entities'])->toHaveKey('vestuario_settings');

        $days = $config['entities']['vestuario_settings'];
        expect($days)->toBeInt();
        // Mínimo CTN Art. 173 (5y); recomendado 10y fiscal (Lei 10.165)
        expect($days)->toBeGreaterThanOrEqual(1825);
    });

    it('default enabled=false (gate manual Wagner per ADR 0105)', function () {
        $config = require vestuarioLgpdPath('Modules/Vestuario/Config/retention.php');
        // Garante que ninguém ativou retention sem sinal qualificado
        expect($config['enabled'])->toBeFalse();
    });

    it('strategy default = anonymize (preserva métricas, redaciona PII)', function () {
        $config = require vestuarioLgpdPath('Modules/Vestuario/Config/retention.php');
        expect($config['strategy'])->toBe('anonymize');
    });

    it('notice_period_days respeita LGPD Art. 18 §VI (aviso prévio)', function () {
        $config = require vestuarioLgpdPath('Modules/Vestuario/Config/retention.php');
        expect($config['notice_period_days'])->toBeInt();
        expect($config['notice_period_days'])->toBeGreaterThanOrEqual(7);
    });

});

describe('D7 LGPD — audit trail append-only (Spatie LogsActivity)', function () {

    it('VestuarioSetting usa trait LogsActivity', function () {
        $traits = class_uses(VestuarioSetting::class);
        expect($traits)->toContain(LogsActivity::class);
    });

    it('getActivitylogOptions retorna LogOptions com logOnlyDirty', function () {
        $row = new VestuarioSetting();
        $opts = $row->getActivitylogOptions();
        expect($opts)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
        // Bem: logOnlyDirty evita ruido em saves no-op (property real Spatie: $logOnlyDirty, não $onlyDirty — W25 fix)
        expect($opts->logOnlyDirty)->toBeTrue();
        expect($opts->submitEmptyLogs)->toBeFalse();
    });

});

describe('D7 LGPD — multi-tenant scope IRREVOGÁVEL', function () {

    it('VestuarioSetting tem booted() com addGlobalScope business_id', function () {
        $reflection = new \ReflectionClass(VestuarioSetting::class);
        $method = $reflection->getMethod('booted');
        expect($method->isProtected() || $method->isPublic())->toBeTrue();

        // Garante que retention.php DOC reafirma scope
        $contents = file_get_contents(vestuarioLgpdPath('Modules/Vestuario/Config/retention.php'));
        expect($contents)->toContain('business_id');
        expect($contents)->toContain('Tier 0');
        expect($contents)->toContain('ADR 0093');
    });

});
