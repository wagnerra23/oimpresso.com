<?php

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Copiloto\Drivers\Sql\SqlDriver;
use Modules\Copiloto\Entities\MetaApuracao;
use Modules\Copiloto\Scopes\ScopeByBusiness;

/**
 * Testa idempotência da apuração (adr/tech/0001).
 *
 * Verifica que updateOrCreate com mesmo (meta_id, data_ref, fonte_query_hash)
 * resulta em apenas 1 linha — o mecanismo que ApurarMetaJob utiliza.
 */

beforeEach(function () {
    Schema::create('copiloto_meta_apuracoes', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('meta_id');
        $table->date('data_ref');
        $table->decimal('valor_realizado', 15, 2);
        $table->timestamp('calculado_em')->useCurrent();
        $table->string('fonte_query_hash', 64);
        $table->timestamps();

        $table->unique(['meta_id', 'data_ref', 'fonte_query_hash'], 'copiloto_apur_unico');
    });
});

afterEach(function () {
    Schema::dropIfExists('copiloto_meta_apuracoes');
});

// ─── Testes ──────────────────────────────────────────────────────────────────

it('dois ApurarMetaJob na mesma data produzem 1 linha (idempotência)', function () {
    $metaId  = 42;
    $hash    = SqlDriver::calcularHash('SELECT 1000.00 AS valor', []);
    $dataRef = Carbon::parse('2026-04-01')->startOfDay();

    // Primeira apuração — deve inserir
    MetaApuracao::updateOrCreate(
        ['meta_id' => $metaId, 'data_ref' => $dataRef, 'fonte_query_hash' => $hash],
        ['valor_realizado' => 1000.00, 'calculado_em' => now()]
    );

    expect(
        DB::table('copiloto_meta_apuracoes')->where('meta_id', $metaId)->count()
    )->toBe(1);

    // Segunda apuração com mesma chave — deve atualizar, não duplicar
    MetaApuracao::updateOrCreate(
        ['meta_id' => $metaId, 'data_ref' => $dataRef, 'fonte_query_hash' => $hash],
        ['valor_realizado' => 2000.00, 'calculado_em' => now()]
    );

    expect(
        DB::table('copiloto_meta_apuracoes')->where('meta_id', $metaId)->count()
    )->toBe(1, 'Deve ter apenas 1 linha mesmo após 2 apurações na mesma data');

    $apuracao = DB::table('copiloto_meta_apuracoes')->where('meta_id', $metaId)->first();
    expect((float) $apuracao->valor_realizado)->toBe(2000.0, 'O valor deve ter sido atualizado');
});

it('hash calculado consistentemente para mesma query e binds', function () {
    $query = 'SELECT SUM(final_total) FROM transactions WHERE business_id = :business_id';
    $binds = ['business_id' => 5, 'data_ini' => '2026-01-01', 'data_fim' => '2026-01-31'];

    $hash1 = SqlDriver::calcularHash($query, $binds);
    $hash2 = SqlDriver::calcularHash($query, $binds);

    expect($hash1)->toBe($hash2)->toHaveLength(64);
});

it('hash diferente para queries diferentes', function () {
    $binds = ['business_id' => 1, 'data_ini' => '2026-01-01', 'data_fim' => '2026-01-31'];

    $hash1 = SqlDriver::calcularHash('SELECT 1', $binds);
    $hash2 = SqlDriver::calcularHash('SELECT 2', $binds);

    expect($hash1)->not->toBe($hash2);
});
