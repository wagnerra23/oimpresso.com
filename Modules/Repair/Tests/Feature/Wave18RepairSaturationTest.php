<?php

declare(strict_types=1);

namespace Modules\Repair\Tests\Feature;

/**
 * Wave 18 — Repair saturação D2/D8/D9
 *
 * Smoke tests Pest pra garantir presenca dos artefatos FormRequest + Config retention
 * sem booting Laravel (rapido, isolated, biz=99 friendly).
 *
 * Tests biz=99 / pure assertion (ADR 0101 — NUNCA biz=4 ROTA LIVRE em test).
 */

describe('Wave 18 Repair — D2 Code Quality FormRequests', function () {

    it('StartFsmActionRequest existe e tem rules', function () {
        $path = base_path('Modules/Repair/Http/Requests/StartFsmActionRequest.php');
        expect(file_exists($path))->toBeTrue();

        $class = 'Modules\\Repair\\Http\\Requests\\StartFsmActionRequest';
        expect(class_exists($class))->toBeTrue();

        $req = new $class();
        $rules = $req->rules();
        expect($rules)->toHaveKey('jobsheet_id');
        expect($rules['jobsheet_id'])->toContain('required');
        expect($rules['jobsheet_id'])->toContain('integer');
    });

    it('ExecuteRepairFsmActionRequest existe (canon Wave anterior)', function () {
        $class = 'Modules\\Repair\\Http\\Requests\\ExecuteRepairFsmActionRequest';
        expect(class_exists($class))->toBeTrue();
    });

    it('StoreJobSheetRequest/UpdateJobSheetRequest existem', function () {
        expect(class_exists('Modules\\Repair\\Http\\Requests\\StoreJobSheetRequest'))->toBeTrue();
        expect(class_exists('Modules\\Repair\\Http\\Requests\\UpdateJobSheetRequest'))->toBeTrue();
    });

});

describe('Wave 18 Repair — D7 retention canonica', function () {

    it('Config/retention.php declara repair_job_sheets', function () {
        $config = require base_path('Modules/Repair/Config/retention.php');
        expect($config)->toHaveKeys(['enabled', 'tabelas', 'strategy', 'notice_period_days']);
        expect($config['tabelas'])->toHaveKey('repair_job_sheets');
        expect($config['tabelas']['repair_job_sheets'])->toBeGreaterThanOrEqual(1825);
    });

    it('default enabled=false (gate manual ADR 0105)', function () {
        $config = require base_path('Modules/Repair/Config/retention.php');
        expect($config['enabled'])->toBeFalse();
    });

});

describe('Wave 18 Repair — D8 Security multi-tenant rules', function () {

    it('StartFsmActionRequest tem withValidator (IDOR defense)', function () {
        $class = 'Modules\\Repair\\Http\\Requests\\StartFsmActionRequest';
        $reflection = new \ReflectionClass($class);
        expect($reflection->hasMethod('withValidator'))->toBeTrue();
    });

});
