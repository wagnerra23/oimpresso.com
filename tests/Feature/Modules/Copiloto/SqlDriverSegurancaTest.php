<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Copiloto\Drivers\Sql\SqlDriver;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Entities\MetaFonte;
use Modules\Copiloto\Scopes\ScopeByBusiness;
use Modules\Copiloto\Services\Ai\OpenAiDirectDriver;

/**
 * Testa regras de segurança do SqlDriver (adr/tech/0001).
 *
 * Não requer DB — testa apenas a validação de queries.
 */

$driver = new SqlDriver();

function metaComBusinessId(int $businessId): Meta
{
    $meta              = new Meta();
    $meta->id          = 99;
    $meta->business_id = $businessId;

    return $meta;
}

function metaPlataforma(): Meta
{
    $meta              = new Meta();
    $meta->id          = 100;
    $meta->business_id = null;

    return $meta;
}

// ─── Queries inválidas ────────────────────────────────────────────────────────

it('rejeita query que começa com INSERT', function () use ($driver) {
    $meta = metaComBusinessId(1);

    expect(fn () => $driver->validarQuery('INSERT INTO foo VALUES (1)', $meta))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejeita query que começa com UPDATE', function () use ($driver) {
    $meta = metaComBusinessId(1);

    expect(fn () => $driver->validarQuery('UPDATE foo SET x=1', $meta))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejeita query que começa com DROP', function () use ($driver) {
    $meta = metaComBusinessId(1);

    expect(fn () => $driver->validarQuery('DROP TABLE users', $meta))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejeita query que começa com DELETE', function () use ($driver) {
    $meta = metaComBusinessId(1);

    expect(fn () => $driver->validarQuery('DELETE FROM users', $meta))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejeita query com DROP embutido após SELECT', function () use ($driver) {
    $meta = metaComBusinessId(1);

    $sql = "SELECT 1; DROP TABLE users;";

    expect(fn () => $driver->validarQuery($sql, $meta))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejeita query que não tem :business_id para meta com business_id', function () use ($driver) {
    $meta = metaComBusinessId(5);

    $sql = 'SELECT SUM(final_total) FROM transactions WHERE status = :status';

    expect(fn () => $driver->validarQuery($sql, $meta))
        ->toThrow(\InvalidArgumentException::class);
});

// ─── Queries válidas ──────────────────────────────────────────────────────────

it('aceita query SELECT com :business_id para meta de business', function () use ($driver) {
    $meta = metaComBusinessId(1);

    $sql = 'SELECT SUM(final_total) FROM transactions WHERE business_id = :business_id AND transaction_date BETWEEN :data_ini AND :data_fim';

    expect(fn () => $driver->validarQuery($sql, $meta))->not->toThrow(\Throwable::class);
});

it('aceita query WITH (CTE) para meta de business', function () use ($driver) {
    $meta = metaComBusinessId(1);

    $sql = 'WITH base AS (SELECT id FROM transactions WHERE business_id = :business_id) SELECT COUNT(*) FROM base WHERE :data_ini <= :data_fim';

    expect(fn () => $driver->validarQuery($sql, $meta))->not->toThrow(\Throwable::class);
});

it('aceita query sem :business_id para meta de plataforma (business_id null)', function () use ($driver) {
    $meta = metaPlataforma();

    $sql = 'SELECT COUNT(*) FROM businesses WHERE :data_ini <= :data_fim';

    expect(fn () => $driver->validarQuery($sql, $meta))->not->toThrow(\Throwable::class);
});

it('aceita SELECT com comentários leading ignorados', function () use ($driver) {
    $meta = metaComBusinessId(1);

    $sql = "-- cálculo de faturamento\nSELECT SUM(final_total) FROM transactions WHERE business_id = :business_id AND :data_ini <= :data_fim";

    expect(fn () => $driver->validarQuery($sql, $meta))->not->toThrow(\Throwable::class);
});

// ─── Mascaramento de CPF/CNPJ ─────────────────────────────────────────────────

it('mascara CPF no contexto antes de enviar à IA', function () {
    $driver2 = new OpenAiDirectDriver();

    $texto   = 'Cliente CPF 123.456.789-00 solicitou serviço';
    $mascarado = $driver2->mascararDocumentos($texto);

    expect($mascarado)->not->toContain('123.456.789-00')
        ->toContain('XXX.XXX.XXX-NN');
});

it('mascara CPF sem pontuação no contexto', function () {
    $driver2 = new OpenAiDirectDriver();

    $texto   = 'CPF: 12345678900';
    $mascarado = $driver2->mascararDocumentos($texto);

    expect($mascarado)->not->toContain('12345678900');
});
