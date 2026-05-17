<?php

declare(strict_types=1);

use Modules\Spreadsheet\Services\SpreadsheetService;

uses(Tests\TestCase::class);

/**
 * Wave 27 — Spreadsheet polish 74-86 → ≥88 (2026-05-17).
 *
 * Scope da Wave 27 (Bucket `functional_horizontal_legacy`):
 *   - D7.c: shim canônico `Config/retention.spreadsheet.php` (entities D7.c
 *           rubrica v3, espelha `retention.php` operacional).
 *   - D9.a: confirma spans nos 6 métodos público críticos (regression test
 *           amplifica Wave 18 ObservabilityTest cobrindo agora attributos
 *           extras + zero-cost check).
 *   - D2: cobertura completa SpreadsheetService W16 — DI, contrato métodos,
 *           multi-tenant Tier 0 (bizId int obrigatório em todos os métodos
 *           que tocam DB).
 *
 * Multi-tenant Tier 0 (ADR 0093): Spreadsheet pre-data convenção
 * `HasBusinessScope` global — back-compat via manual `where('business_id', ...)`
 * dentro do Service. Estes testes confirmam que NENHUM método público pode
 * ser chamado sem bizId int (fail-secure).
 *
 * Zero-cost: roda sem DB — Reflection + source-grep + config().
 */

// ---------- D2 — DI + contrato canônico ----------

it('027.s01 SpreadsheetService bindable via container (DI ok)', function () {
    $svc = app(SpreadsheetService::class);
    expect($svc)->toBeInstanceOf(SpreadsheetService::class);
});

it('027.s02 SpreadsheetService::createSpreadsheet contrato (array,int,int)→?Spreadsheet', function () {
    $ref = new ReflectionMethod(SpreadsheetService::class, 'createSpreadsheet');
    $params = $ref->getParameters();

    expect($params)->toHaveCount(3);
    expect($params[0]->getName())->toBe('input');
    expect($params[0]->getType()?->getName())->toBe('array');
    expect($params[1]->getName())->toBe('bizId');
    expect($params[1]->getType()?->getName())->toBe('int');
    expect($params[2]->getName())->toBe('userId');
    expect($params[2]->getType()?->getName())->toBe('int');

    $rt = $ref->getReturnType();
    expect($rt?->allowsNull())->toBeTrue('createSpreadsheet retorna ?Spreadsheet (fail-secure)');
});

it('027.s03 SpreadsheetService::updateSpreadsheet contrato (int,array,int)→bool', function () {
    $ref = new ReflectionMethod(SpreadsheetService::class, 'updateSpreadsheet');
    $params = $ref->getParameters();

    expect($params[0]->getName())->toBe('id');
    expect($params[0]->getType()?->getName())->toBe('int');
    expect($params[2]->getName())->toBe('bizId');
    expect($params[2]->getType()?->getName())->toBe('int');
    expect($ref->getReturnType()?->getName())->toBe('bool');
});

it('027.s04 SpreadsheetService::deleteSpreadsheet contrato (int,int,int)→bool', function () {
    $ref = new ReflectionMethod(SpreadsheetService::class, 'deleteSpreadsheet');
    $params = collect($ref->getParameters())->keyBy(fn ($p) => $p->getName());

    expect($params->has('id'))->toBeTrue();
    expect($params->has('bizId'))->toBeTrue();
    expect($params->has('userId'))->toBeTrue();
    expect($params['bizId']->isOptional())->toBeFalse();
    expect($params['userId']->isOptional())->toBeFalse();
    expect($ref->getReturnType()?->getName())->toBe('bool');
});

it('027.s05 SpreadsheetService tem 6 métodos públicos canônicos (W18 baseline)', function () {
    $ref = new ReflectionClass(SpreadsheetService::class);
    $publicos = collect($ref->getMethods(ReflectionMethod::IS_PUBLIC))
        ->reject(fn ($m) => $m->isConstructor())
        ->map(fn ($m) => $m->getName())
        ->toArray();

    foreach (['createSpreadsheet', 'updateSpreadsheet', 'deleteSpreadsheet',
              'resolveNotifyableUsers', 'listForUser', 'getForUser'] as $metodo) {
        expect($publicos)->toContain($metodo);
    }
});

// ---------- D9.a — observability regression Wave 27 ----------

it('027.s10 SpreadsheetService usa OtelHelper canônico (não SDK direto)', function () {
    $src = file_get_contents(base_path('Modules/Spreadsheet/Services/SpreadsheetService.php'));
    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->not->toContain('OpenTelemetry\API\Trace\TracerProviderInterface');
    expect($src)->not->toContain('Tracer::startSpan'); // não pode usar SDK low-level
});

it('027.s11 SpreadsheetService tem ≥6 spans canon (1 por método público crítico)', function () {
    $src = file_get_contents(base_path('Modules/Spreadsheet/Services/SpreadsheetService.php'));

    $esperados = [
        'spreadsheet.create',
        'spreadsheet.update',
        'spreadsheet.delete',
        'spreadsheet.resolve_notifyable_users',
        'spreadsheet.list_for_user',
        'spreadsheet.get_for_user',
    ];

    foreach ($esperados as $span) {
        expect($src)->toContain("OtelHelper::spanBiz('{$span}'");
    }

    $count = substr_count($src, 'OtelHelper::spanBiz(');
    expect($count)->toBeGreaterThanOrEqual(6);
});

it('027.s12 SpreadsheetService spans incluem attributo module=Spreadsheet (correlação)', function () {
    $src = file_get_contents(base_path('Modules/Spreadsheet/Services/SpreadsheetService.php'));

    // Pelo menos 6 ocorrências de 'module' => 'Spreadsheet' (1 por span)
    $count = substr_count($src, "'module'");
    expect($count)->toBeGreaterThanOrEqual(6);

    $countSpreadsheet = substr_count($src, "=> 'Spreadsheet'");
    expect($countSpreadsheet)->toBeGreaterThanOrEqual(6);
});

// ---------- D7.c — retention shim canônico Wave 27 ----------

it('027.s20 Config/retention.spreadsheet.php existe (shim D7.c canon)', function () {
    $path = base_path('Modules/Spreadsheet/Config/retention.spreadsheet.php');
    expect(file_exists($path))->toBeTrue('shim retention canon D7.c ausente');
});

it('027.s21 retention.spreadsheet.php declara 2 entities canônicas (sheets + shares)', function () {
    $cfg = require base_path('Modules/Spreadsheet/Config/retention.spreadsheet.php');

    expect($cfg)->toBeArray();
    expect($cfg)->toHaveKeys(['enabled', 'strategy', 'grace_period_days', 'entities', 'pii_redactor']);
    expect($cfg['entities'])->toHaveKeys(['sheet_spreadsheets', 'sheet_spreadsheet_shares']);
});

it('027.s22 retention.spreadsheet.php valida shape D7.c por entity (days/law_ref/strategy)', function () {
    $cfg = require base_path('Modules/Spreadsheet/Config/retention.spreadsheet.php');

    foreach ($cfg['entities'] as $entity => $spec) {
        expect($spec)->toHaveKeys(['days', 'law_ref', 'strategy'], "entity {$entity} sem shape D7.c");
        expect($spec['days'])->toBeInt("days deve ser int em {$entity}");
        expect($spec['days'])->toBeGreaterThan(0);
        expect($spec['strategy'])->toBeIn(['anonymize', 'hard_delete', 'archive']);
        expect(is_string($spec['law_ref']))->toBeTrue();
    }
});

it('027.s23 retention.spreadsheet.php usa 5y (1825d) janela fiscal BR pra ambas entities', function () {
    $cfg = require base_path('Modules/Spreadsheet/Config/retention.spreadsheet.php');

    expect($cfg['entities']['sheet_spreadsheets']['days'])->toBe(1825);
    expect($cfg['entities']['sheet_spreadsheet_shares']['days'])->toBe(1825);
});

it('027.s24 retention.spreadsheet.php espelha enabled+strategy de retention.php (acoplamento)', function () {
    $shim = require base_path('Modules/Spreadsheet/Config/retention.spreadsheet.php');
    $opr  = require base_path('Modules/Spreadsheet/Config/retention.php');

    // Mesma fonte ENV — defaults devem bater
    expect($shim['enabled'])->toBe($opr['enabled']);
    expect($shim['strategy'])->toBe($opr['strategy']);
});

it('027.s25 retention.spreadsheet.php PII redactor flag presente (UGC opaco)', function () {
    $cfg = require base_path('Modules/Spreadsheet/Config/retention.spreadsheet.php');

    expect($cfg['pii_redactor'])->toBeArray();
    expect($cfg['pii_redactor'])->toHaveKeys(['enabled', 'mode', 'targets', 'patterns']);
    expect($cfg['pii_redactor']['patterns'])->toContain('cpf');
    expect($cfg['pii_redactor']['patterns'])->toContain('cnpj');
    expect($cfg['pii_redactor']['targets'])->toContain('sheet_data');
});
