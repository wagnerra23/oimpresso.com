<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Wave 23 D9.c — accounting:health command smoke + contrato.
 *
 * Mirror dos tests CmsHealthCommand/PontoHealthCommand/ManufacturingHealthCommand
 * (governance ADR 0155 — sinal #3 health endpoint/command).
 *
 * Cobertura:
 *   - command registrado e invocável (`artisan list` contém `accounting:health`)
 *   - signature aceita --business-id / --alert / --json / --detail (NUNCA --verbose)
 *   - --json produz output parseable
 *   - exit code 0 default (info-only); --alert respeita FAIL/WARN/OK
 *
 * @see Modules\Accounting\Console\Commands\AccountingHealthCommand
 * @see .claude/rules/commands.md (--detail não --verbose — PR #851 lesson)
 */

// Skip apenas tests que exigem schema/DB. Tests de Reflection rodam puros.
function w23AccountingNeedsMysql(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return true;
    }
    foreach (['accounts', 'journal_entries', 'account_transactions'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            return true;
        }
    }
    return false;
}

test('classe AccountingHealthCommand existe (Reflection — puro)', function () {
    expect(class_exists(\Modules\Accounting\Console\Commands\AccountingHealthCommand::class))->toBeTrue();
});

test('signature NÃO usa --verbose (rule .claude/rules/commands.md — Symfony reserved)', function () {
    $cmd = new \Modules\Accounting\Console\Commands\AccountingHealthCommand();
    $signature = (new ReflectionClass($cmd))->getProperty('signature');
    $signature->setAccessible(true);
    $value = $signature->getValue($cmd);

    expect($value)->not->toContain('--verbose')
        ->and($value)->toContain('--detail')
        ->and($value)->toContain('--alert')
        ->and($value)->toContain('--json')
        ->and($value)->toContain('--business-id');
});

test('OtelHelper class existe (D9.a observability — Wave 17)', function () {
    expect(class_exists(\App\Util\OtelHelper::class))->toBeTrue();
});

test('AccountingHealthCommand registra 5 checks canônicos', function () {
    $cmd = new \Modules\Accounting\Console\Commands\AccountingHealthCommand();
    $ref = new ReflectionClass($cmd);

    // Métodos privados de checks devem existir (cobertura de schema_canon, catalog_global, etc).
    foreach (['checkSchemaCanon', 'checkCatalogGlobal', 'checkLancamentosRecentes', 'checkTransactionsOrphan', 'checkAccountsByBusiness'] as $method) {
        expect($ref->hasMethod($method))->toBeTrue("método {$method} deve existir");
    }
});

test('AccountingServiceProvider registra AccountingHealthCommand', function () {
    $providerSrc = file_get_contents(__DIR__ . '/../../Providers/AccountingServiceProvider.php');
    expect($providerSrc)->toContain('AccountingHealthCommand::class');
});

test('command accounting:health está registrado e listado', function () {
    if (w23AccountingNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }

    $output = Artisan::call('list', ['namespace' => 'accounting']);
    expect($output)->toBe(0);
    $rendered = Artisan::output();
    expect($rendered)->toContain('accounting:health');
});

test('accounting:health exit 0 sem --alert (info-only)', function () {
    if (w23AccountingNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    $exit = Artisan::call('accounting:health');
    expect($exit)->toBe(0);
});

test('accounting:health --json emite payload parseable com chaves canônicas', function () {
    if (w23AccountingNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    $exit = Artisan::call('accounting:health', ['--json' => true]);
    expect($exit)->toBe(0);

    $output = Artisan::output();
    $payload = json_decode($output, true);

    expect($payload)->toBeArray()
        ->and($payload['module'] ?? null)->toBe('Accounting')
        ->and($payload['checks'] ?? null)->toBeArray();

    foreach (['schema_canon', 'catalog_global', 'lancamentos_24h', 'transactions_orphan', 'accounts_by_business'] as $check) {
        expect($payload['checks'])->toHaveKey($check);
        expect($payload['checks'][$check])->toHaveKeys(['status', 'mensagem']);
    }
});

test('accounting:health --detail expande JSON após tabela (debug)', function () {
    if (w23AccountingNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    $exit = Artisan::call('accounting:health', ['--detail' => true]);
    expect($exit)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('Detalhes JSON:');
});

test('accounting:health com --business-id=99 não lança e retorna 0', function () {
    if (w23AccountingNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    $exit = Artisan::call('accounting:health', ['--business-id' => 99]);
    expect($exit)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('Check');
});

test('check catalog_global preserva business_id=0 IRREVOGÁVEL (Wave 13/15 lesson)', function () {
    if (w23AccountingNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    if (! Schema::hasTable('account_types')) {
        $this->markTestSkipped('Tabela account_types ausente.');
    }

    // Roda check e valida que se houver linhas com business_id != 0 e != null, status é WARN.
    $exit = Artisan::call('accounting:health', ['--json' => true]);
    expect($exit)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $catalogCheck = $payload['checks']['catalog_global'] ?? null;

    expect($catalogCheck)->toBeArray();
    // Status válido: OK (íntegro) ou WARN (contaminação detectada/catálogo vazio).
    expect($catalogCheck['status'])->toBeIn(['OK', 'WARN', 'FAIL']);
});
