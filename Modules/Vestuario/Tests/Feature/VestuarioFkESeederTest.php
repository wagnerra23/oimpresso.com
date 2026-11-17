<?php
// @covers-us US-VEST-002

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Vestuario\Database\Seeders\RepairSettingsSeeder;
use Tests\Support\WithSeededTenant;

/**
 * FK de `business_id` nas 5 tabelas do módulo e o seeder sem empresa padrão.
 *
 * Âncora: ADR 0093 e `.claude/rules/migrations.md` ("business_id indexado + FK"). Auditoria do
 * Vestuario de 2026-11-09, itens P2-3 e P3-11. Tenant 98 × 99 (ADR 0358). NUNCA biz=4.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('vestuario_remarcacoes')) {
        $this->markTestSkipped('Requer MySQL com o schema do UltimatePOS.');
    }
    $this->migration = require __DIR__ . '/../../Database/Migrations/2026_11_09_000001_add_business_fk_to_vestuario_tables.php';
    $this->tabelas = ['vestuario_settings', 'vestuario_devolucoes', 'vestuario_creditos_cliente', 'vestuario_remarcacoes', 'vestuario_remarcacao_itens'];
});

it('as 5 tabelas têm FK de business_id para business, em cascata', function () {
    foreach ($this->tabelas as $tabela) {
        $fk = collect(Schema::getForeignKeys($tabela))->first(fn ($f) => $f['columns'] === ['business_id']);

        expect($fk)->not->toBeNull()
            ->and($fk['foreign_table'])->toBe('business')
            ->and($fk['foreign_columns'])->toBe(['id'])
            ->and(strtolower($fk['on_delete']))->toBe('cascade');
    }
});

it('rodar a migration de novo não muda nada', function () {
    $antes = collect($this->tabelas)->map(fn ($t) => count(Schema::getForeignKeys($t)))->all();

    $this->migration->up();

    expect(collect($this->tabelas)->map(fn ($t) => count(Schema::getForeignKeys($t)))->all())->toBe($antes);
});

it('linha órfã para a migration com mensagem que nomeia a tabela; sem órfã, passa', function () {
    // Tabela temporária: não encerra a transação do teste, ao contrário de derrubar a FK real.
    DB::statement('CREATE TEMPORARY TABLE vest_fk_tmp (business_id INT UNSIGNED NOT NULL)');
    DB::table('vest_fk_tmp')->insert(['business_id' => $this->seededTenant()->id]);

    $this->migration->exigirSemOrfas(['vest_fk_tmp']);

    DB::table('vest_fk_tmp')->insert(['business_id' => 4294967000]);

    expect(fn () => $this->migration->exigirSemOrfas(['vest_fk_tmp']))
        ->toThrow(RuntimeException::class, '1 linha(s) de vest_fk_tmp');
});

it('o seeder exige a empresa: sem ela ou com id inexistente, recusa e não grava', function () {
    $antes = DB::table('business')->whereNotNull('repair_settings')->count();

    expect(fn () => (new RepairSettingsSeeder())->run())->toThrow(InvalidArgumentException::class);
    expect(fn () => (new RepairSettingsSeeder())->run(4294967000))->toThrow(InvalidArgumentException::class);

    expect(DB::table('business')->whereNotNull('repair_settings')->count())->toBe($antes);
});

it('o seeder grava só na empresa informada', function () {
    $empresa = $this->seededTenant();
    $outra = $this->seededSupportClientTenant();
    $daOutra = DB::table('business')->where('id', $outra->id)->value('repair_settings');

    (new RepairSettingsSeeder())->run($empresa->id);

    $gravado = json_decode((string) DB::table('business')->where('id', $empresa->id)->value('repair_settings'), true);
    expect($gravado['labels']['executor'])->toBe('Costureira');
    expect(DB::table('business')->where('id', $outra->id)->value('repair_settings'))->toBe($daOutra);
});
