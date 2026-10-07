<?php

declare(strict_types=1);

use App\System;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Forja · instalação reconhecida pela row LEGADA `system.projectmgmt_version`.
 *
 * Contrato: decisão [W] 2026-10-07 (repassada pela fila de merges no #8938) — "a Forja deve
 * aparecer na sidebar em produção", pela checagem de instalado aceitando também o nome antigo,
 * sem gravar nada no banco. O InstallController da Forja grava `projectmgmt_version`
 * (fachada legacy, ADR 0088) e produção só tem essa row (medido 2026-10-07).
 *
 * É o portão que o UC-FORJA-03 atravessa: AdminSidebarMenu → ModuleUtil::getModuleData →
 * isModuleInstalled('Forja'). Aqui ele é testado sozinho, nos 3 estados que importam.
 *
 * Tudo dentro de transação; nada fica no banco. Stack exige MySQL: em sqlite PULA (LC-13).
 *
 * @see app/Utils/ModuleUtil.php (CHAVES_INSTALACAO_LEGADAS · isModuleInstalled)
 */

function forjaChaveLegadaExigeSchema(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: tabela system com schema MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('system')) {
        test()->markTestSkipped('Tabela system ausente — rode com DB_CONNECTION=mysql.');
    }
}

function forjaChaveLegadaEstado(array $presentes): void
{
    DB::table('system')->whereIn('key', ['forja_version', 'projectmgmt_version'])->delete();
    foreach ($presentes as $chave) {
        System::addProperty($chave, '0.1');
    }
}

it('Forja com só a row legada projectmgmt_version (estado de produção) conta como instalada', function () {
    forjaChaveLegadaExigeSchema();
    forjaChaveLegadaEstado(['projectmgmt_version']);

    expect(System::getProperty('forja_version'))->toBeNull();
    expect((new ModuleUtil())->isModuleInstalled('Forja'))->toBeTrue();
});

it('Forja sem nenhuma das duas rows continua NÃO instalada (a chave legada não é carimbo)', function () {
    forjaChaveLegadaExigeSchema();
    forjaChaveLegadaEstado([]);

    expect((new ModuleUtil())->isModuleInstalled('Forja'))->toBeFalse();
});

it('a row legada da Forja não vaza para outro módulo', function () {
    forjaChaveLegadaExigeSchema();
    forjaChaveLegadaEstado(['projectmgmt_version']);
    DB::table('system')->where('key', 'jana_version')->delete();

    // Controle positivo: a Jana existe no nWidart — o false abaixo é da row, não do Module::has.
    expect(\Module::has('Jana'))->toBeTrue();
    expect((new ModuleUtil())->isModuleInstalled('Jana'))->toBeFalse();
});
