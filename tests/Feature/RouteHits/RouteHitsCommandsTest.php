<?php

declare(strict_types=1);

use App\Http\Middleware\ContadorHitsRota;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

// Tests\TestCase já aplicado globalmente em tests/Pest.php — NÃO redeclarar uses() aqui.

/**
 * route-hits:flush + route-hits:export — batch cache→DB e ledger JSON.
 *
 * Sqlite-safe: schema SINTÉTICO (Schema::create espelhando a migration, sem
 * migrate completo — padrão da lane ci-sqlite-pest.list), cache array.
 */

/** Controller-fixture pro teste de atribuição rota→página (Reflection lê ESTE arquivo). */
class RhFixtureExportController
{
    public function show()
    {
        return Inertia::render('RhFixture/Pagina');
    }
}

beforeEach(function () {
    config(['cache.default' => 'array', 'route_hits.retencao_dias' => 90]);
    Cache::flush();
    if (! Schema::hasTable('route_hits')) {
        Schema::create('route_hits', function (Blueprint $table) {
            $table->id();
            $table->date('data');
            $table->string('rota', 191);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamps();
            $table->unique(['data', 'rota'], 'route_hits_data_rota_unique');
        });
    }
    DB::table('route_hits')->delete();
});

it('flush move contador do cache pra route_hits e é idempotente (pull esvazia)', function () {
    Route::get('_rh_teste_flush', fn () => 'ok')->name('rh.teste.flush');
    $chave = ContadorHitsRota::chaveCache('rh.teste.flush', now()->format('Y-m-d'));
    Cache::put($chave, 7, 3600);

    $this->artisan('route-hits:flush')->assertExitCode(0);

    $linha = DB::table('route_hits')->where('rota', 'rh.teste.flush')->first();
    expect($linha)->not->toBeNull()
        ->and((int) $linha->hits)->toBe(7)
        ->and(Cache::get($chave))->toBeNull();

    // segundo flush sem hits novos: não duplica
    $this->artisan('route-hits:flush')->assertExitCode(0);
    expect((int) DB::table('route_hits')->where('rota', 'rh.teste.flush')->sum('hits'))->toBe(7);
});

it('flush acumula no mesmo par rota×dia (upsert-increment)', function () {
    Route::get('_rh_teste_acum', fn () => 'ok')->name('rh.teste.acum');
    $chave = ContadorHitsRota::chaveCache('rh.teste.acum', now()->format('Y-m-d'));

    Cache::put($chave, 3, 3600);
    $this->artisan('route-hits:flush')->assertExitCode(0);
    Cache::put($chave, 4, 3600);
    $this->artisan('route-hits:flush')->assertExitCode(0);

    expect((int) DB::table('route_hits')->where('rota', 'rh.teste.acum')->value('hits'))->toBe(7);
});

it('flush --prune apaga linhas além da retenção e preserva recentes', function () {
    $agora = now();
    DB::table('route_hits')->insert([
        ['data' => $agora->copy()->subDays(200)->format('Y-m-d'), 'rota' => 'rh.velha', 'hits' => 5, 'created_at' => $agora, 'updated_at' => $agora],
        ['data' => $agora->format('Y-m-d'), 'rota' => 'rh.nova', 'hits' => 5, 'created_at' => $agora, 'updated_at' => $agora],
    ]);

    $this->artisan('route-hits:flush --prune')->assertExitCode(0);

    expect(DB::table('route_hits')->where('rota', 'rh.velha')->exists())->toBeFalse()
        ->and(DB::table('route_hits')->where('rota', 'rh.nova')->exists())->toBeTrue();
});

it('export (dry-run) emite ledger route-hits/v1 com a rota agregada e NÃO grava arquivo', function () {
    $agora = now();
    DB::table('route_hits')->insert([
        ['data' => $agora->copy()->subDays(2)->format('Y-m-d'), 'rota' => 'rh.export.rota', 'hits' => 10, 'created_at' => $agora, 'updated_at' => $agora],
        ['data' => $agora->format('Y-m-d'), 'rota' => 'rh.export.rota', 'hits' => 5, 'created_at' => $agora, 'updated_at' => $agora],
        // fora da janela de 30d — não entra
        ['data' => $agora->copy()->subDays(60)->format('Y-m-d'), 'rota' => 'rh.fora.janela', 'hits' => 99, 'created_at' => $agora, 'updated_at' => $agora],
    ]);
    $ledger = base_path('governance/route-hits.json');
    $existiaAntes = file_exists($ledger);

    $this->artisan('route-hits:export')
        ->expectsOutputToContain('route-hits/v1')
        ->expectsOutputToContain('"rh.export.rota"')
        ->expectsOutputToContain('"hits": 15')
        ->assertExitCode(0);

    expect(file_exists($ledger))->toBe($existiaAntes); // dry-run nunca grava
});

it('export atribui hits à página Inertia via Inertia::render do MÉTODO de action', function () {
    Route::get('_rh_teste_fx', [RhFixtureExportController::class, 'show'])->name('rh.fixture');
    $agora = now();
    DB::table('route_hits')->insert([
        'data' => $agora->format('Y-m-d'), 'rota' => 'rh.fixture', 'hits' => 12,
        'created_at' => $agora, 'updated_at' => $agora,
    ]);

    $this->artisan('route-hits:export')
        ->expectsOutputToContain('"RhFixture/Pagina"')
        ->expectsOutputToContain('"hits": 12')
        ->assertExitCode(0);
});
