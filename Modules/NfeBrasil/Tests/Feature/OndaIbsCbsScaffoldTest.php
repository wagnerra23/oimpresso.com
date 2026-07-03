<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeFiscalRule;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-021 — Onda 6 IBS/CBS scaffold (Reforma Tributária NT 2025.002).
 *
 * Tests focados em (1) Migration adiciona 5 colunas IBS/CBS, (2) Model
 * NfeFiscalRule expõe novos $fillable + casts, (3) idempotência re-rodar
 * não duplica, (4) tipos corretos (char 3/6 + decimal 7,4).
 *
 * Prazo regulatório (audit sênior 2026-05-25):
 *  - 2026-08-01: HIGHLIGHT CBS+IBS obrigatório NFe (CRT 3)
 *  - 2027-01-01: CBS substitui PIS+COFINS integral
 *
 * Tests rodam em SQLite source-grep + reflection — não exigem MySQL schema.
 */

it('Onda 6: Model NfeFiscalRule expõe c_class_trib em $fillable (cClassTrib NT 2025.002)', function () {
    $model = new NfeFiscalRule;
    $fillable = $model->getFillable();

    expect(in_array('c_class_trib', $fillable, true))->toBeTrue('cClassTrib NT 2025.002');
});

it('Onda 6: Model NfeFiscalRule expõe cst_ibs + cst_cbs em $fillable', function () {
    $model = new NfeFiscalRule;
    $fillable = $model->getFillable();

    expect(in_array('cst_ibs', $fillable, true))->toBeTrue('CST IBS estadual/municipal');
    expect(in_array('cst_cbs', $fillable, true))->toBeTrue('CST CBS federal');
});

it('Onda 6: Model NfeFiscalRule expõe aliquota_ibs + aliquota_cbs em $fillable', function () {
    $model = new NfeFiscalRule;
    $fillable = $model->getFillable();

    expect($fillable)->toContain('aliquota_ibs')
        ->and($fillable)->toContain('aliquota_cbs');
});

it('Onda 6: aliquota_ibs + aliquota_cbs cast como float (decimal 7,4 padrão alíquotas)', function () {
    $model = new NfeFiscalRule;
    $casts = $model->getCasts();

    expect($casts)->toHaveKey('aliquota_ibs')
        ->and($casts)->toHaveKey('aliquota_cbs')
        ->and($casts['aliquota_ibs'])->toBe('float', 'pattern decimal 7,4 → float PHP')
        ->and($casts['aliquota_cbs'])->toBe('float');
});

it('Onda 6: Migration arquivo existe + segue convenção timestamp NfeBrasil', function () {
    $migrationsDir = base_path('Modules/NfeBrasil/Database/Migrations');
    $files = glob($migrationsDir . '/*_add_ibs_cbs_to_nfe_fiscal_rules.php');

    expect($files)->not->toBeEmpty('migration add_ibs_cbs_to_nfe_fiscal_rules deve existir');
});

it('Onda 6: Migration up() é idempotente — usa Schema::hasColumn guard', function () {
    $migrationsDir = base_path('Modules/NfeBrasil/Database/Migrations');
    $files = glob($migrationsDir . '/*_add_ibs_cbs_to_nfe_fiscal_rules.php');
    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    // ADR tech/0008 idempotência — re-rodar migration NÃO deve duplicar coluna
    expect(substr_count($src, 'Schema::hasColumn'))->toBeGreaterThanOrEqual(5,
        'guard Schema::hasColumn pra cada uma das 5 colunas novas');
});

it('Onda 6: Migration down() preserva colunas (ADR 0093 Garantia 8 append-only)', function () {
    $migrationsDir = base_path('Modules/NfeBrasil/Database/Migrations');
    $files = glob($migrationsDir . '/*_add_ibs_cbs_to_nfe_fiscal_rules.php');
    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect(str_contains($src, 'dropColumn'))->toBeFalse('append-only — down() preserva schema');
    expect(str_contains($src, 'append-only'))->toBeTrue('comentário explícito sobre Garantia 8');
});

it('Onda 6: Migration referencia issue sped-nfe #1274 (lib externa pending)', function () {
    $migrationsDir = base_path('Modules/NfeBrasil/Database/Migrations');
    $files = glob($migrationsDir . '/*_add_ibs_cbs_to_nfe_fiscal_rules.php');
    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect(str_contains($src, '1274'))->toBeTrue('rastrear lib sped-nfe issue #1274 release IBS/CBS');
});

it('Onda 6: 5 colunas novas cobrem cClassTrib + 2 CST + 2 alíquotas (NT 2025.002 mínimo)', function () {
    $migrationsDir = base_path('Modules/NfeBrasil/Database/Migrations');
    $files = glob($migrationsDir . '/*_add_ibs_cbs_to_nfe_fiscal_rules.php');
    $src = file_get_contents($files[0]);

    expect($src)->toContain('c_class_trib')
        ->and($src)->toContain('cst_ibs')
        ->and($src)->toContain('cst_cbs')
        ->and($src)->toContain('aliquota_ibs')
        ->and($src)->toContain('aliquota_cbs');
});

it('Onda 6 SCHEMA APLICADO (somente MySQL com migrate rodado): colunas existem na tabela', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: migration roda em CI MySQL UPos (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_fiscal_rules')) {
        $this->markTestSkipped('Tabela nfe_fiscal_rules ausente — rodar migrate primeiro');
    }

    // Quando migrate rodou, todas 5 colunas devem existir
    expect(Schema::hasColumn('nfe_fiscal_rules', 'c_class_trib'))->toBeTrue()
        ->and(Schema::hasColumn('nfe_fiscal_rules', 'cst_ibs'))->toBeTrue()
        ->and(Schema::hasColumn('nfe_fiscal_rules', 'cst_cbs'))->toBeTrue()
        ->and(Schema::hasColumn('nfe_fiscal_rules', 'aliquota_ibs'))->toBeTrue()
        ->and(Schema::hasColumn('nfe_fiscal_rules', 'aliquota_cbs'))->toBeTrue();
});

it('Onda 6 (PR-B): TributoCalculado expõe os 7 campos IBS/CBS (cálculo saiu do scaffold)', function () {
    $props = array_map(
        fn ($p) => $p->getName(),
        (new ReflectionClass(Modules\NfeBrasil\Services\Tributacao\TributoCalculado::class))
            ->getConstructor()->getParameters(),
    );

    expect($props)->toContain('c_class_trib')
        ->and($props)->toContain('cst_ibs')
        ->and($props)->toContain('cst_cbs')
        ->and($props)->toContain('aliquota_ibs')
        ->and($props)->toContain('aliquota_cbs')
        ->and($props)->toContain('valor_ibs')
        ->and($props)->toContain('valor_cbs');
});
