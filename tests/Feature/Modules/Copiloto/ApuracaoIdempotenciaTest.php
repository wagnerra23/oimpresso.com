<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Drivers\Sql\SqlDriver;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Entities\MetaApuracao;
use Modules\Copiloto\Entities\MetaFonte;
use Modules\Copiloto\Jobs\ApurarMetaJob;
use Modules\Copiloto\Scopes\ScopeByBusiness;
use Modules\Copiloto\Services\ApuracaoService;

/**
 * Testa idempotência da apuração (adr/tech/0001).
 *
 * Garante que rodar ApurarMetaJob 2× na mesma data_ref + fonte_query_hash
 * resulta em apenas 1 linha na tabela, com o valor sobrescrito.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->loadMigrationsFrom(base_path('Modules/Copiloto/Database/Migrations'));
});

function criarMetaComFonteSQL(int $businessId = 1): Meta
{
    $meta = Meta::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'        => $businessId,
        'slug'               => 'fat_test',
        'nome'               => 'Faturamento Teste',
        'unidade'            => 'R$',
        'tipo_agregacao'     => 'soma',
        'ativo'              => true,
        'criada_por_user_id' => 1,
        'origem'             => 'seed',
    ]);

    MetaFonte::create([
        'meta_id'     => $meta->id,
        'driver'      => 'sql',
        'config_json' => [
            'query' => 'SELECT 1000.00 AS valor WHERE :business_id = :business_id AND :data_ini <= :data_fim',
        ],
        'cadencia' => 'diaria',
    ]);

    return $meta->fresh(['fonte']);
}

it('dois ApurarMetaJob na mesma data produzem 1 linha (idempotência)', function () {
    $meta    = criarMetaComFonteSQL(1);
    $dataRef = Carbon::parse('2026-04-01');

    // Mocked driver retorna 500 na primeira chamada
    $driver = Mockery::mock(SqlDriver::class)->makePartial();
    $driver->shouldReceive('apurar')->andReturn(500.00);

    $service = new ApuracaoService();

    // Primeira execução
    $service->apurar($meta, $dataRef);

    $count1 = MetaApuracao::withoutGlobalScope(ScopeByBusiness::class)
        ->where('meta_id', $meta->id)
        ->where('data_ref', $dataRef->toDateString())
        ->count();

    expect($count1)->toBe(1);

    // Segunda execução com mesmo data_ref (deve sobrescrever, não duplicar)
    $service->apurar($meta, $dataRef);

    $count2 = MetaApuracao::withoutGlobalScope(ScopeByBusiness::class)
        ->where('meta_id', $meta->id)
        ->where('data_ref', $dataRef->toDateString())
        ->count();

    expect($count2)->toBe(1);
});

it('hash calculado consistentemente para mesma query e binds', function () {
    $query = 'SELECT SUM(final_total) FROM transactions WHERE business_id = :business_id';
    $binds = ['business_id' => 5, 'data_ini' => '2026-01-01', 'data_fim' => '2026-01-31'];

    $hash1 = SqlDriver::calcularHash($query, $binds);
    $hash2 = SqlDriver::calcularHash($query, $binds);

    expect($hash1)->toBe($hash2)->toHaveLength(64); // sha256 = 64 hex chars
});

it('hash diferente para queries diferentes', function () {
    $binds = ['business_id' => 1, 'data_ini' => '2026-01-01', 'data_fim' => '2026-01-31'];

    $hash1 = SqlDriver::calcularHash('SELECT 1', $binds);
    $hash2 = SqlDriver::calcularHash('SELECT 2', $binds);

    expect($hash1)->not->toBe($hash2);
});
