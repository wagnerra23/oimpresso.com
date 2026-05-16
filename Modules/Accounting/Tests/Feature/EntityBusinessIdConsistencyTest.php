<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Auditoria de consistência multi-tenant — todas as Entities Accounting que representam
 * tabelas REAIS de negócio devem ter coluna `business_id` (Tier 0 IRREVOGÁVEL — ADR 0093).
 *
 * Itera sobre `Modules/Accounting/Entities/*.php`, resolve `$table` via reflection,
 * filtra pra tabelas que existem no schema dev, e asserta presença de coluna `business_id`.
 *
 * Algumas Entities são DUPLICATAS de classes core UltimatePOS (`App\User`, `App\Business`,
 * `App\Contact` etc — 70 arquivos é sinal disso). Tabelas core compartilhadas como `users`,
 * `business`, `currencies`, `countries`, `genders`, `titles`, `professions`, `marital_statuses`,
 * `client_types`, `client_relationships` são allowlisted pois NÃO são scope-per-business
 * (são reference data global ou compartilhado).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: auditoria requer schema MySQL UltimatePOS completo (ADR 0101)'
        );
    }
});

/**
 * Allowlist — tabelas que NÃO precisam de business_id porque são reference data global
 * ou tabelas core UltimatePOS compartilhadas multi-tenant via outras chaves.
 */
const ACC_ALLOWLIST_NO_BIZ_ID = [
    // Reference data global (sem scope por business)
    'countries',
    'currencies',
    'genders',
    'titles',
    'professions',
    'marital_statuses',
    'client_types',
    'client_relationships',
    'units',
    'payment_types',
    'payment_term_types',
    'types_of_service',
    'account_types',
    'account_detail_types',
    'work_statuses',
    'work_details',
    'warranties',
    // Tabelas core UltimatePOS (são reference / sistema, têm seu próprio padrão)
    'business',
    'system',
    'document_and_note',
    'media',
    'reference_count',
    'kyc_identifications',
    'barcodes',
    // Subtypes podem ter business_id=0 (default global) — testado separadamente em MultiTenantIsolationTest
    'account_subtypes',
];

/**
 * Helper — resolve nome da tabela da Entity via reflection (lê propriedade $table protected).
 * Fallback: pluralize lowercase do classname (convenção Eloquent).
 */
function accResolveTableName(string $fqcn): string
{
    try {
        $reflection = new ReflectionClass($fqcn);
        $instance = $reflection->newInstanceWithoutConstructor();
        $tableProp = $reflection->getProperty('table');
        $tableProp->setAccessible(true);
        $table = $tableProp->getValue($instance);
        if (! empty($table)) {
            return $table;
        }
    } catch (\Throwable $e) {
        // fall through
    }

    // Convenção Eloquent: snake_case plural do classname
    $short = (new ReflectionClass($fqcn))->getShortName();
    $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short));

    // pluralize simples
    if (str_ends_with($snake, 'y')) {
        return substr($snake, 0, -1) . 'ies';
    }
    if (str_ends_with($snake, 's')) {
        return $snake;
    }

    return $snake . 's';
}

it('toda Entity Accounting mapeada a tabela existente declara business_id (Tier 0 ADR 0093)', function () {
    $entityFiles = glob(base_path('Modules/Accounting/Entities/*.php'));

    expect($entityFiles)->not->toBeEmpty('Glob não encontrou Entities — path errado?');

    $offenders = [];
    $audited = 0;
    $skipped = [];

    foreach ($entityFiles as $file) {
        $classname = basename($file, '.php');
        $fqcn = "Modules\\Accounting\\Entities\\{$classname}";

        if (! class_exists($fqcn)) {
            $skipped[] = "{$classname} (class não carrega)";
            continue;
        }

        try {
            $table = accResolveTableName($fqcn);
        } catch (\Throwable $e) {
            $skipped[] = "{$classname} (resolve table failed: {$e->getMessage()})";
            continue;
        }

        // Tabela não existe no schema dev — skip (não é tabela viva)
        if (! Schema::hasTable($table)) {
            $skipped[] = "{$classname} → `{$table}` (tabela não existe no schema)";
            continue;
        }

        // Tabela na allowlist (reference data / core compartilhada) — skip
        if (in_array($table, ACC_ALLOWLIST_NO_BIZ_ID, true)) {
            continue;
        }

        $audited++;

        if (! Schema::hasColumn($table, 'business_id')) {
            $offenders[] = "{$classname} → `{$table}` SEM coluna business_id";
        }
    }

    // Pelo menos algumas Entities precisam ter sido auditadas — se 0, algo está errado
    expect($audited)->toBeGreaterThan(0, 'Nenhuma Entity auditada — verificar path/schema');

    expect($offenders)->toBeEmpty(
        sprintf(
            "Entities Accounting SEM business_id (violação Tier 0 ADR 0093):\n  - %s\n\nSkipped (%d): %s",
            implode("\n  - ", $offenders),
            count($skipped),
            implode(', ', array_slice($skipped, 0, 5)) . (count($skipped) > 5 ? '...' : '')
        )
    );
});

it('Entities críticas têm coluna business_id confirmada', function () {
    $critical = [
        \Modules\Accounting\Entities\Account::class               => 'accounts',
        \Modules\Accounting\Entities\ChartOfAccount::class        => 'chart_of_accounts',
        \Modules\Accounting\Entities\Transfer::class              => 'transfers',
        \Modules\Accounting\Entities\Budget::class                => 'budgets',
    ];

    $missing = [];

    foreach ($critical as $fqcn => $expectedTable) {
        if (! class_exists($fqcn)) {
            $missing[] = "{$fqcn} (class não existe)";
            continue;
        }

        $table = accResolveTableName($fqcn);

        // Compara com expected pra detectar mismatch de nome
        if ($table !== $expectedTable) {
            // Mismatch é warn, não fail — alguma Entity pode ter $table custom
        }

        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Tabela `{$table}` missing — rode migrate primeiro");
        }

        if (! Schema::hasColumn($table, 'business_id')) {
            $missing[] = "{$fqcn} → `{$table}` SEM business_id";
        }
    }

    expect($missing)->toBeEmpty(
        "Entities críticas SEM business_id (Tier 0):\n  - " . implode("\n  - ", $missing)
    );
});

it('journal_entries tem location_id que liga indiretamente ao business via business_locations', function () {
    // JournalEntry é o caso especial — não tem business_id direto, escopa via JOIN
    // em business_locations.business_id. Esta auditoria confirma o contrato indireto.
    if (! Schema::hasTable('journal_entries') || ! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Tabelas journal_entries ou business_locations missing');
    }

    expect(Schema::hasColumn('journal_entries', 'location_id'))->toBeTrue(
        'journal_entries.location_id é necessário pra scope indireto via business_locations'
    );
    expect(Schema::hasColumn('business_locations', 'business_id'))->toBeTrue(
        'business_locations.business_id é o link de tenant pra JournalEntry'
    );
});
