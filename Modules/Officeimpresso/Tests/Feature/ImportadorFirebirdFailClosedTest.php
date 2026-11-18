<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Officeimpresso\Services\FirebirdImporter\FirebirdConnector;
use Modules\Officeimpresso\Services\FirebirdImporter\OfficeimpressoImporterService;

uses(Tests\TestCase::class);

/**
 * `officeimpresso:import` — fail-closed e contador honesto (Lote 2 da auditoria
 * Officeimpresso, 2026-11-18).
 *
 * O que estes casos prendem:
 *   1. Sem `--source-fb` (ou sem `pdo_firebird`) o connector devolve FIXTURES fictícias.
 *      Sem `--dry-run` o comando gravava essas fixtures na empresa real; agora recusa.
 *   2. Linha cujo insert falha conta em `failed`, não em `migrated`.
 *   3. `products`/`transactions`/`licenca_computador` não têm coluna `legacy_id`: a linha
 *      conta em `blocked` e não é gravada (sem chave, um 2º run duplicaria). Criar a
 *      coluna em tabela core é a decisão B5 do [W] — fora deste lote.
 *   4. "Já importado" é checado de verdade em `contacts` (2º run = skipped).
 *
 * Nunca roda o importador contra base Firebird real: a fonte é um connector de
 * fixture em memória. Tenant: `seededTenant()` (fictício 98, ADR 0358). Cada caso roda
 * numa transação revertida — no CT 100 a base persiste entre runs.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS necessário (ADR 0101).');
    }

    $this->biz = $this->seededTenant();
    FirebirdConnector::flushPool();
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

/**
 * Connector que responde SELECT com linhas em memória, por tabela Firebird.
 *
 * @param  array<string, list<array<string, mixed>>>  $porTabela
 */
function oiImportConector(array $porTabela): FirebirdConnector
{
    return new class($porTabela) extends FirebirdConnector
    {
        /** @param array<string, list<array<string, mixed>>> $porTabela */
        public function __construct(private array $porTabela)
        {
            parent::__construct(':fixture:', forceMock: false);
        }

        public function isMock(): bool
        {
            return false;
        }

        public function selectAll(string $sql, array $params = []): array
        {
            $sql = strtoupper($sql);
            foreach ($this->porTabela as $tabela => $rows) {
                if (str_contains($sql, 'FROM ' . $tabela)) {
                    return $rows;
                }
            }

            return [];
        }
    };
}

function oiImportCliente(int $id, string $doc): array
{
    return ['ID' => $id, 'NOME' => 'Cliente fixture L2 ' . $id, 'CNPJ_CPF' => $doc, 'EMAIL' => '', 'TELEFONE' => ''];
}

function oiImportContatosDoTeste(int $bizId): int
{
    return DB::table('contacts')
        ->where('business_id', $bizId)
        ->where('legacy_id', 'like', '{"fb_clientes"%')
        ->count();
}

it('recusa gravar quando não há --source-fb: mock só roda com --dry-run', function () {
    $antes = DB::table('contacts')->where('business_id', $this->biz->id)->count();

    $this->artisan('officeimpresso:import', ['biz' => $this->biz->id])
        ->expectsOutputToContain('Mock só roda com --dry-run')
        ->assertExitCode(1);

    expect(DB::table('contacts')->where('business_id', $this->biz->id)->count())->toBe($antes);
});

it('recusa gravar quando o .fdb foi informado mas pdo_firebird está ausente', function () {
    if (FirebirdConnector::driverAvailable()) {
        $this->markTestSkipped('pdo_firebird presente neste ambiente: o caminho mock-por-falta-de-driver não se aplica.');
    }
    $antes = DB::table('contacts')->where('business_id', $this->biz->id)->count();

    $this->artisan('officeimpresso:import', ['biz' => $this->biz->id, '--source-fb' => '/nao/existe/fixture.fdb'])
        ->expectsOutputToContain('extensão pdo_firebird ausente')
        ->assertExitCode(1);

    expect(DB::table('contacts')->where('business_id', $this->biz->id)->count())->toBe($antes);
});

it('dry-run em mock roda, não grava e sai com erro enquanto houver tabela bloqueada', function () {
    $antes = DB::table('contacts')->where('business_id', $this->biz->id)->count();

    $this->artisan('officeimpresso:import', ['biz' => $this->biz->id, '--dry-run' => true])
        ->expectsOutputToContain('Importação INCOMPLETA')
        ->expectsOutputToContain('sem_coluna_legacy_id')
        ->assertExitCode(1);

    expect(DB::table('contacts')->where('business_id', $this->biz->id)->count())->toBe($antes);
});

it('grava clientes de verdade, com created_by da própria empresa, e o 2º run é skipped', function () {
    $service = new OfficeimpressoImporterService(oiImportConector(['CLIENTES' => [
        oiImportCliente(9800101, 'L2-FIXTURE-' . uniqid('a')),
        oiImportCliente(9800102, 'L2-FIXTURE-' . uniqid('b')),
    ]]));

    $primeiro = $service->importClientes($this->biz->id, dryRun: false);
    expect($primeiro)->toBe(['read' => 2, 'migrated' => 2, 'skipped' => 0, 'failed' => 0, 'blocked' => 0]);

    $donoEsperado = DB::table('users')->where('business_id', $this->biz->id)->orderBy('id')->value('id');
    $gravados = DB::table('contacts')
        ->where('business_id', $this->biz->id)
        ->whereIn('legacy_id', ['{"fb_clientes":9800101}', '{"fb_clientes":9800102}'])
        ->pluck('created_by')
        ->all();
    expect($gravados)->toHaveCount(2)
        ->and(array_unique($gravados))->toBe([(int) $donoEsperado]);

    $segundo = $service->importClientes($this->biz->id, dryRun: false);
    expect($segundo)->toBe(['read' => 2, 'migrated' => 0, 'skipped' => 2, 'failed' => 0, 'blocked' => 0]);
});

it('insert que falha conta como failed, não como migrated, e o erro sai sem valores da linha', function () {
    $doc = 'L2-FIXTURE-' . uniqid('dup');
    $service = new OfficeimpressoImporterService(oiImportConector(['CLIENTES' => [
        oiImportCliente(9800201, $doc),
        oiImportCliente(9800202, $doc), // mesmo documento: viola uk_contacts_biz_tax
    ]]));

    $report = $service->runFullImport($this->biz->id, dryRun: false);

    expect($report['clientes'])->toBe(['read' => 2, 'migrated' => 1, 'skipped' => 0, 'failed' => 1, 'blocked' => 0]);

    $falha = collect($report['errors'])->firstWhere('tabela', 'contacts');
    expect($falha)->not->toBeNull()
        ->and($falha['legacy_id'])->toBe(9800202)
        ->and($falha['erro'])->toBe('sql:1062');
    expect(json_encode($report['errors']))->not->toContain($doc);
});

it('o log da linha que falhou leva só o código do erro, sem o SQL com os valores', function () {
    $doc = 'L2-FIXTURE-' . uniqid('log');
    $service = new OfficeimpressoImporterService(oiImportConector(['CLIENTES' => [
        oiImportCliente(9800501, $doc),
        oiImportCliente(9800502, $doc), // mesmo documento: viola uk_contacts_biz_tax
    ]]));

    $avisos = [];
    Log::listen(function ($evento) use (&$avisos) {
        if ($evento->level === 'warning') {
            $avisos[] = ['mensagem' => $evento->message, 'contexto' => $evento->context];
        }
    });

    $service->importClientes($this->biz->id, dryRun: false);

    $doImportador = array_values(array_filter($avisos, fn ($a) => $a['mensagem'] === 'officeimpresso.import.linha_falhou'));
    expect($doImportador)->toHaveCount(1)
        ->and($doImportador[0]['contexto'])->toBe(['tabela' => 'contacts', 'legacy_id' => 9800502, 'erro' => 'sql:1062']);
    expect(json_encode($avisos))->not->toContain($doc);
});

it('sem senha no ambiente o connector recusa conectar no .fdb (não há senha padrão)', function () {
    // Construído sem senha, como o comando faz quando OFFICEIMPRESSO_FB_PASSWORD falta.
    $conector = new class('/nao/existe/fixture.fdb') extends FirebirdConnector
    {
        public function isMock(): bool
        {
            return false;
        }
    };

    expect(fn () => $conector->connect())
        ->toThrow(RuntimeException::class, 'OFFICEIMPRESSO_FB_PASSWORD');

    $saude = $conector->healthCheck();
    expect($saude['ok'])->toBeFalse()
        ->and($saude['error'])->toContain('OFFICEIMPRESSO_FB_PASSWORD');
});

it('o comando não aceita mais --pass: a senha só entra por ambiente', function () {
    $definicao = Artisan::all()['officeimpresso:import']->getDefinition();

    expect($definicao->hasOption('pass'))->toBeFalse()
        ->and($definicao->hasOption('user'))->toBeTrue()
        ->and($definicao->getOption('user')->getDefault())->toBeNull();
});

it('dry-run não grava nada mesmo com a checagem de já-importado respondendo', function () {
    $service = new OfficeimpressoImporterService(oiImportConector(['CLIENTES' => [
        oiImportCliente(9800301, 'L2-FIXTURE-' . uniqid('c')),
    ]]));
    $antes = oiImportContatosDoTeste($this->biz->id);

    $stats = $service->importClientes($this->biz->id, dryRun: true);

    expect($stats['migrated'])->toBe(1)
        ->and(oiImportContatosDoTeste($this->biz->id))->toBe($antes);
});

it('tabela sem coluna legacy_id fica blocked e nada é gravado nela', function () {
    expect(Schema::hasColumn('products', 'legacy_id'))->toBeFalse(
        'products ganhou legacy_id (decisão B5?): este caso deixa de valer, reescreva-o.'
    );

    $service = new OfficeimpressoImporterService(oiImportConector(['PRODUTOS' => [
        ['ID' => 9800401, 'NOME' => 'Produto fixture L2', 'PRECO_VENDA' => 1, 'ESTOQUE_ATUAL' => 1],
        ['ID' => 9800402, 'NOME' => 'Produto fixture L2', 'PRECO_VENDA' => 1, 'ESTOQUE_ATUAL' => 1],
    ]]));

    $stats = $service->importProdutos($this->biz->id, dryRun: false);

    expect($stats)->toBe(['read' => 2, 'migrated' => 0, 'skipped' => 0, 'failed' => 0, 'blocked' => 2]);
    expect(DB::table('products')->where('business_id', $this->biz->id)
        ->whereIn('sku', ['FB-9800401', 'FB-9800402'])->count())->toBe(0);
});
