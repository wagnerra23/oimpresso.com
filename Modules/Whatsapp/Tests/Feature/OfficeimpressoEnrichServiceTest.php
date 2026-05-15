<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Entities\CustomerMemory;
use Modules\Whatsapp\Services\CustomerMemory\OfficeimpressoEnrichService;
use Modules\Whatsapp\Services\CustomerMemory\Sources\FirebirdLookupSourceContract;
use Modules\Whatsapp\Services\CustomerMemory\Sources\JsonFileFirebirdSource;

uses(Tests\TestCase::class);

/**
 * US-WA-VOZ-002 — OfficeimpressoEnrichService + JsonFileFirebirdSource.
 *
 * Cobertura:
 *   1. JsonFileFirebirdSource — load + lookup por sufixo 8 dígitos
 *   2. JsonFileFirebirdSource — health check (arquivo missing / stale)
 *   3. JsonFileFirebirdSource — formato meta+customers vs array plain
 *   4. JsonFileFirebirdSource — normalize Firebird keys (CODIGO vs cliente_id)
 *   5. EnrichService — popula external_sources com match
 *   6. EnrichService — preserva entries de outras fontes ao re-enrich
 *   7. EnrichService — fail-open quando source unhealthy
 */
beforeEach(function () {
    Schema::dropIfExists('customer_memory');
    Schema::create('customer_memory', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('customer_external_id', 40);
        $table->string('phone_normalized', 20)->nullable();
        $table->unsignedInteger('contact_id')->nullable();
        $table->json('external_sources')->nullable();
        $table->timestamp('external_sources_enriched_at')->nullable();
        $table->timestamps();
        $table->unique(['business_id', 'customer_external_id']);
    });
});

function tempJsonFile(array $payload): string
{
    $path = sys_get_temp_dir() . '/firebird-test-' . uniqid() . '.json';
    file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE));
    return $path;
}

it('JsonFileFirebirdSource — formato {meta,customers} + lookup por sufixo 8', function () {
    $path = tempJsonFile([
        'meta' => ['exported_at' => '2026-05-15T18:00:00-03:00', 'row_count' => 2],
        'customers' => [
            [
                'cliente_id' => 1234,
                'nome' => 'ACME LTDA',
                'fone1' => '+55 (48) 9 9987-2822',
                'fone2' => null,
                'email' => 'contato@acme.com.br',
                'bloqueado' => false,
            ],
            [
                'cliente_id' => 5678,
                'nome' => 'BETA EIRELI',
                'fone1' => '+55 (11) 98765-4321',
                'email' => null,
                'bloqueado' => true,
            ],
        ],
    ]);

    $source = new JsonFileFirebirdSource($path);

    // Lookup matches por sufixo 8 dígitos ("99872822" do fone1)
    $results = $source->lookupByPhone('+5548999872822');
    expect($results)->toHaveCount(1)
        ->and($results[0]['cliente_id'])->toBe(1234)
        ->and($results[0]['nome'])->toBe('ACME LTDA')
        ->and($results[0]['bloqueado'])->toBeFalse();

    // Phone diferente
    $r2 = $source->lookupByPhone('5511987654321');
    expect($r2)->toHaveCount(1)
        ->and($r2[0]['cliente_id'])->toBe(5678)
        ->and($r2[0]['bloqueado'])->toBeTrue();

    unlink($path);
});

it('JsonFileFirebirdSource — formato array plain (sem meta)', function () {
    $path = tempJsonFile([
        ['cliente_id' => 99, 'nome' => 'Plain', 'fone1' => '5511999998888'],
    ]);

    $source = new JsonFileFirebirdSource($path);
    $results = $source->lookupByPhone('5511999998888');

    expect($results)->toHaveCount(1)->and($results[0]['cliente_id'])->toBe(99);
    unlink($path);
});

it('JsonFileFirebirdSource — normaliza keys Firebird raw (CODIGO, FONE1, BLOQUEADO=S)', function () {
    $path = tempJsonFile([
        'customers' => [
            ['CODIGO' => 42, 'RAZAO_SOCIAL' => 'RAW LTDA', 'FONE1' => '5548888888888', 'BLOQUEADO' => 'S'],
        ],
    ]);

    $source = new JsonFileFirebirdSource($path);
    $results = $source->lookupByPhone('5548888888888');

    expect($results)->toHaveCount(1)
        ->and($results[0]['cliente_id'])->toBe(42)
        ->and($results[0]['nome'])->toBe('RAW LTDA')
        ->and($results[0]['bloqueado'])->toBeTrue();
    unlink($path);
});

it('JsonFileFirebirdSource — isHealthy false quando arquivo missing', function () {
    $source = new JsonFileFirebirdSource('/nonexistent/path/file.json');
    expect($source->isHealthy())->toBeFalse();
});

it('JsonFileFirebirdSource — isHealthy true para arquivo recente', function () {
    $path = tempJsonFile(['customers' => []]);
    $source = new JsonFileFirebirdSource($path);
    expect($source->isHealthy())->toBeTrue();
    unlink($path);
});

it('EnrichService — popula external_sources com match', function () {
    $path = tempJsonFile([
        'customers' => [
            ['cliente_id' => 1234, 'nome' => 'ACME', 'fone1' => '5548999872822'],
        ],
    ]);

    $source = new JsonFileFirebirdSource($path);
    $service = new OfficeimpressoEnrichService($source);

    // Cria customer_memory
    $memId = DB::table('customer_memory')->insertGetId([
        'business_id' => 1,
        'customer_external_id' => '5548999872822',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $memory = CustomerMemory::find($memId);
    $matched = $service->enrich($memory);

    expect($matched)->toBe(1);

    $memory->refresh();
    expect($memory->external_sources)->not->toBeNull();
    expect($memory->external_sources[0]['cliente_id'])->toBe(1234);
    expect($memory->external_sources[0]['nome'])->toBe('ACME');
    expect($memory->external_sources_enriched_at)->not->toBeNull();

    unlink($path);
});

it('EnrichService — preserva entries de OUTRAS fontes ao re-enrich', function () {
    $path = tempJsonFile([
        'customers' => [
            ['cliente_id' => 1234, 'nome' => 'ACME ATUALIZADO', 'fone1' => '5548999872822'],
        ],
    ]);

    // Memory já tem entry de outra fonte (asaas) + entry firebird velha
    $memId = DB::table('customer_memory')->insertGetId([
        'business_id' => 1,
        'customer_external_id' => '5548999872822',
        'external_sources' => json_encode([
            ['source' => 'asaas:2026-04', 'customer_id' => 'cus_xyz'],
            ['source' => 'firebird_office_json:2026-04', 'cliente_id' => 9999, 'nome' => 'VELHO'],
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $source = new JsonFileFirebirdSource($path);
    $service = new OfficeimpressoEnrichService($source);
    $memory = CustomerMemory::find($memId);

    $service->enrich($memory);

    $memory->refresh();
    $sources = $memory->external_sources;

    // Entry asaas preservada
    $asaasEntry = collect($sources)->firstWhere('source', 'asaas:2026-04');
    expect($asaasEntry)->not->toBeNull();

    // Entry firebird antiga REMOVIDA + nova entrou
    $firebirdEntries = collect($sources)->filter(fn ($e) => str_starts_with($e['source'] ?? '', 'firebird_office_json'));
    expect($firebirdEntries->count())->toBe(1);
    expect($firebirdEntries->first()['nome'])->toBe('ACME ATUALIZADO'); // não 'VELHO'

    unlink($path);
});

it('EnrichService — fail-open com source unhealthy retorna 0', function () {
    $source = new JsonFileFirebirdSource('/nonexistent.json');
    $service = new OfficeimpressoEnrichService($source);

    $memId = DB::table('customer_memory')->insertGetId([
        'business_id' => 1,
        'customer_external_id' => '5548999872822',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $memory = CustomerMemory::find($memId);

    $matched = $service->enrich($memory);

    expect($matched)->toBe(0);
});

it('EnrichService.enrichBusiness — processa em batch + retorna stats', function () {
    $path = tempJsonFile([
        'customers' => [
            ['cliente_id' => 1, 'nome' => 'A', 'fone1' => '5548111111111'],
            ['cliente_id' => 2, 'nome' => 'B', 'fone1' => '5548222222222'],
        ],
    ]);

    foreach (['5548111111111', '5548222222222', '5548999999999'] as $ext) {
        DB::table('customer_memory')->insert([
            'business_id' => 1,
            'customer_external_id' => $ext,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $service = new OfficeimpressoEnrichService(new JsonFileFirebirdSource($path));
    $stats = $service->enrichBusiness(1, limit: 10);

    expect($stats['processed'])->toBe(3);
    expect($stats['matched'])->toBe(2); // 2 dos 3 tem match no JSON
    expect($stats['skipped'])->toBe(1);

    unlink($path);
});
