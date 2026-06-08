<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * biz=1 virou REAL (2026-06-08) — dado de teste/demo vai pra biz=99.
 *
 * Smoke estrutural (não boota app, sobrevive DB greenfield) garantindo que:
 *   - config canon test_business_id (99) + protected_business_ids ([1,4]) existem
 *   - FinanceiroDemoSeeder usa test_business_id por default e RECUSA tenant protegido
 *   - workflow run-financeiro-demo-seeder default = 99
 *   - TestBusinessSeeder existe e cria via via canônica (createNewBusiness)
 */

const DEMO_SEEDER = __DIR__ . '/../../Database/Seeders/FinanceiroDemoSeeder.php';
const APP_CONFIG = __DIR__ . '/../../../../config/app.php';
const SEED_WORKFLOW = __DIR__ . '/../../../../.github/workflows/run-financeiro-demo-seeder.yml';
const TEST_BIZ_SEEDER = __DIR__ . '/../../../../database/seeders/TestBusinessSeeder.php';

describe('Config canon de tenant de teste', function () {
    it('config/app.php define test_business_id e protected_business_ids', function () {
        $src = file_get_contents(APP_CONFIG);
        expect($src)->toContain("'test_business_id'");
        expect($src)->toContain("'protected_business_ids'");
    });

    it('config carrega com defaults 99 e [1,4]', function () {
        expect((int) config('app.test_business_id'))->toBe(99);
        expect(config('app.protected_business_ids'))->toContain(1);
    });
});

describe('FinanceiroDemoSeeder não polui tenant real', function () {
    it('default = test_business_id (não mais 1)', function () {
        $src = file_get_contents(DEMO_SEEDER);
        expect($src)->toContain("config('app.test_business_id'");
        expect($src)->not->toContain("env('BIZ_ID') ?? 1");
    });

    it('recusa gravar em tenant protegido (guard)', function () {
        $src = file_get_contents(DEMO_SEEDER);
        expect($src)->toContain("config('app.protected_business_ids'");
        expect($src)->toContain('RECUSADO');
    });
});

describe('Workflow + seeder de tenant de teste', function () {
    it('workflow default biz_id = 99', function () {
        $src = file_get_contents(SEED_WORKFLOW);
        expect($src)->toContain("default: '99'");
    });

    it('TestBusinessSeeder cria via createNewBusiness (via canônica)', function () {
        $src = file_get_contents(TEST_BIZ_SEEDER);
        expect($src)->toContain('createNewBusiness(');
        expect($src)->toContain("config('app.test_business_id'");
    });
});
