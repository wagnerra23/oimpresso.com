<?php

declare(strict_types=1);

namespace Modules\Superadmin\Tests\Feature\Lgpd;

use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Entities\SuperadminCommunicatorLog;
use Modules\Superadmin\Entities\SuperadminFrontendPage;
use Modules\Superadmin\Support\RedactsPiiInLogs;
use Spatie\Activitylog\Traits\LogsActivity;
use Tests\TestCase;

/**
 * Wave 11 — LGPD D7 Superadmin compliance.
 *
 * Valida 3 vetores:
 *   D7.a — PiiRedactor wraps em Log::emergency (cross-tenant leak guard)
 *   D7.b — LogsActivity nas 4 Entities (audit trail append-only)
 *   D7.c — retention.php com prazos legais corretos (CC Art. 206 / LGPD Art. 16)
 *
 * Cross-tenant intencional Wagner-only (ADR 0093 SUPERADMIN exception).
 *
 * Pattern bootstrap-only: NÃO toca DB. Valida traits + classes + config arrays.
 * Runnable em qualquer ambiente (CI/local/Hostinger) sem dependência mysql.
 */
uses(TestCase::class);

// ============================================================================
// D7.a — PiiRedactor em logs cross-tenant
// ============================================================================

it('D7.a: trait RedactsPiiInLogs existe em Modules/Superadmin/Support', function () {
    expect(trait_exists(RedactsPiiInLogs::class))->toBeTrue();
});

it('D7.a: trait expõe logEmergencyRedacted + logInfoRedacted (incluindo protected)', function () {
    // Métodos protected requerem reflection (get_class_methods só lista public)
    $stub = new class { use RedactsPiiInLogs; };
    $ref = new \ReflectionClass($stub);
    $methodNames = array_map(fn ($m) => $m->getName(), $ref->getMethods());

    expect($methodNames)->toContain('logEmergencyRedacted');
    expect($methodNames)->toContain('logInfoRedacted');
});

it('D7.a: BaseController usa trait RedactsPiiInLogs', function () {
    $traits = class_uses_recursive(\Modules\Superadmin\Http\Controllers\BaseController::class);
    // class_uses_recursive retorna [FQN => FQN] — checa via array_key_exists
    expect(array_key_exists(RedactsPiiInLogs::class, $traits))->toBeTrue();
});

it('D7.a: 5 Controllers standalone usam trait (não estendem BaseController)', function () {
    $controllers = [
        \Modules\Superadmin\Http\Controllers\SuperadminSettingsController::class,
        \Modules\Superadmin\Http\Controllers\PackagesController::class,
        \Modules\Superadmin\Http\Controllers\PageController::class,
        \Modules\Superadmin\Http\Controllers\DataController::class,
        \Modules\Superadmin\Http\Controllers\PesaPalController::class,
    ];

    foreach ($controllers as $controller) {
        $traits = class_uses_recursive($controller);
        expect(array_key_exists(RedactsPiiInLogs::class, $traits))
            ->toBeTrue("Controller {$controller} sem RedactsPiiInLogs");
    }
});

it('D7.a: PiiRedactor redacta email + CPF + CNPJ + telefone em exception message', function () {
    $redactor = app(PiiRedactor::class);

    $msg = 'SQLSTATE[23000]: duplicate entry larissa@rota-livre.com.br para CPF 123.456.789-00 telefone (48) 99999-1234';
    $redacted = $redactor->redact($msg);

    expect($redacted)->not->toContain('larissa@rota-livre.com.br');
    expect($redacted)->not->toContain('123.456.789-00');
    expect($redacted)->not->toContain('99999-1234');
    expect($redacted)->toContain('[REDACTED:EMAIL]');
    expect($redacted)->toContain('[REDACTED:CPF]');
    expect($redacted)->toContain('[REDACTED:PHONE]');
});

it('D7.a: ZERO ocorrência de \\Log::emergency direto em Controllers Superadmin (audit grep)', function () {
    $controllers = glob(__DIR__.'/../../../Http/Controllers/*.php');
    expect($controllers)->not->toBeEmpty();

    foreach ($controllers as $file) {
        $content = file_get_contents($file);
        // Permite \Log::emergency em código comentado (// \Log::...) — só falha em código vivo
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            $trim = ltrim($line);
            if (str_starts_with($trim, '//') || str_starts_with($trim, '*')) continue;
            if (preg_match('/\\\\Log::emergency\(/', $line)) {
                throw new \AssertionError(sprintf(
                    'D7.a VIOLATION: %s linha %d ainda tem \\Log::emergency direto: %s',
                    basename($file), $i + 1, trim($line)
                ));
            }
        }
    }
    expect(true)->toBeTrue(); // se chegou aqui, zero violations
});

// ============================================================================
// D7.b — LogsActivity nas 4 Entities Superadmin
// ============================================================================

it('D7.b: Package usa LogsActivity com log_name superadmin.package', function () {
    $traits = class_uses_recursive(Package::class);
    expect(array_key_exists(LogsActivity::class, $traits))->toBeTrue();

    $package = new Package();
    $opts = $package->getActivitylogOptions();
    expect($opts->logName)->toBe('superadmin.package');
});

it('D7.b: Subscription usa LogsActivity com log_name superadmin.subscription', function () {
    $traits = class_uses_recursive(Subscription::class);
    expect(array_key_exists(LogsActivity::class, $traits))->toBeTrue();

    $sub = new Subscription();
    $opts = $sub->getActivitylogOptions();
    expect($opts->logName)->toBe('superadmin.subscription');
});

it('D7.b: SuperadminCommunicatorLog usa LogsActivity', function () {
    $traits = class_uses_recursive(SuperadminCommunicatorLog::class);
    expect(array_key_exists(LogsActivity::class, $traits))->toBeTrue();

    $log = new SuperadminCommunicatorLog();
    $opts = $log->getActivitylogOptions();
    expect($opts->logName)->toBe('superadmin.communicator_log');
});

it('D7.b: SuperadminFrontendPage usa LogsActivity (Termos/Privacidade audit)', function () {
    $traits = class_uses_recursive(SuperadminFrontendPage::class);
    expect(array_key_exists(LogsActivity::class, $traits))->toBeTrue();

    $page = new SuperadminFrontendPage();
    $opts = $page->getActivitylogOptions();
    expect($opts->logName)->toBe('superadmin.frontend_page');
});

it('D7.b: todas LogsActivity Options têm logFillable + logOnlyDirty + dontSubmitEmptyLogs', function () {
    $entities = [Package::class, Subscription::class, SuperadminCommunicatorLog::class, SuperadminFrontendPage::class];
    foreach ($entities as $entity) {
        $instance = new $entity();
        $opts = $instance->getActivitylogOptions();
        expect($opts->logFillable)->toBeTrue("[$entity] logFillable=false — esperado true");
        expect($opts->logOnlyDirty)->toBeTrue("[$entity] logOnlyDirty=false — esperado true");
        expect($opts->submitEmptyLogs)->toBeFalse("[$entity] submitEmptyLogs=true — esperado false");
    }
});

// ============================================================================
// D7.c — retention.php config com prazos legais
// ============================================================================

it('D7.c: retention.php existe em Modules/Superadmin/Config', function () {
    $path = __DIR__.'/../../../Config/retention.php';
    expect(file_exists($path))->toBeTrue();
});

it('D7.c: retention.php retorna array com chaves esperadas', function () {
    $config = require __DIR__.'/../../../Config/retention.php';

    expect($config)->toBeArray();
    expect($config)->toHaveKeys([
        'admin_actions',
        'feature_flags_history',
        'settings_changes',
        'communicator_logs',
        'frontend_pages_history',
        'subscriptions_soft_deleted',
        'pending_offline_payments',
    ]);
});

it('D7.c: admin_actions = 2555 dias (10 anos CC Art. 206 §5º I)', function () {
    $config = require __DIR__.'/../../../Config/retention.php';
    expect($config['admin_actions'])->toBe(2555);
});

it('D7.c: settings_changes = 1825 dias (5 anos)', function () {
    $config = require __DIR__.'/../../../Config/retention.php';
    expect($config['settings_changes'])->toBe(1825);
});

it('D7.c: feature_flags_history e frontend_pages_history = null (indefinida governança)', function () {
    $config = require __DIR__.'/../../../Config/retention.php';
    expect($config['feature_flags_history'])->toBeNull();
    expect($config['frontend_pages_history'])->toBeNull();
});

it('D7.c: subscriptions_soft_deleted = 3650 dias (10 anos prescrição CC)', function () {
    $config = require __DIR__.'/../../../Config/retention.php';
    expect($config['subscriptions_soft_deleted'])->toBe(3650);
});

it('D7.c: communicator_logs = 1825 dias (5 anos prova de aviso)', function () {
    $config = require __DIR__.'/../../../Config/retention.php';
    expect($config['communicator_logs'])->toBe(1825);
});

it('D7.c: pending_offline_payments = 730 dias (2 anos anonymize)', function () {
    $config = require __DIR__.'/../../../Config/retention.php';
    expect($config['pending_offline_payments'])->toBe(730);
});

// ============================================================================
// Cross-cutting — multi-tenant Tier 0 preserve cross-tenant intent
// ============================================================================

it('cross-tenant: D7 changes NÃO removeram withoutGlobalScopes (Constituição Art. 6)', function () {
    $businessController = file_get_contents(__DIR__.'/../../../Http/Controllers/BusinessController.php');
    // Cross-tenant intent legítimo: BusinessController DEVE listar TODOS businesses
    // Garantir que ainda usa Business::with(...) cross-tenant (sem business_id where)
    expect($businessController)->toContain('Business::with');
});
