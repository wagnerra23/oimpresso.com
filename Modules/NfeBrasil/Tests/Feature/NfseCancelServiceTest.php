<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Contracts\NfseCancelDriverInterface;
use Modules\NfeBrasil\Models\NfseEmissao;
use Modules\NfeBrasil\Models\NfseEventoCancelamento;
use Modules\NfeBrasil\Services\NfseCancelService;

uses(Tests\TestCase::class);

/**
 * US-NFSE-CANCEL-001 — NfseCancelService (Manager pattern + driver registry).
 *
 * Não usa RefreshDatabase — roda contra DB dev (UltimatePOS schema). Pula se
 * `nfse_emissoes` / `nfse_eventos_cancelamento` ainda não migraram.
 *
 * 4 specs:
 *   1. resolveDriver retorna driver registrado pra município suportado
 *   2. município sem driver → RuntimeException
 *   3. motivo fora de 15-255 chars → InvalidArgumentException
 *   4. cross-tenant guard — businessId divergente → UnauthorizedActionException
 */

// ── helpers ─────────────────────────────────────────────────────────────────

function nfseCancelBootstrap(): array
{
    if (! Schema::hasTable('nfse_emissoes') || ! Schema::hasTable('nfse_eventos_cancelamento')) {
        test()->markTestSkipped('Tabelas nfse_emissoes/nfse_eventos_cancelamento não existem — rode migrations.');
    }

    try {
        $business = \App\Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: ' . $e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    return [$business];
}

function makeFakeDriver(string $key, array $municipios): NfseCancelDriverInterface
{
    return new class($key, $municipios) implements NfseCancelDriverInterface {
        public function __construct(
            private readonly string $key,
            private readonly array $municipios,
        ) {}

        public function cancelar(NfseEmissao $nfse, string $motivo): NfseEventoCancelamento
        {
            throw new \RuntimeException('Fake driver: cancelar() não deve ser chamado nestes testes');
        }

        public function getDriverKey(): string
        {
            return $this->key;
        }

        public function supportedMunicipios(): array
        {
            return $this->municipios;
        }
    };
}

function criarNfseAutorizadaParaCancelar(int $businessId, string $codIbge = '4218400'): NfseEmissao
{
    return NfseEmissao::withoutGlobalScopes()->create([
        'business_id'           => $businessId,
        'transaction_id'        => null,
        'numero_rps'            => random_int(900000, 999999),
        'item_lc116'            => '17.06',
        'value_servico'         => 100.00,
        'value_iss'             => 5.00,
        'aliquota_iss'          => 0.0500,
        'tomador_doc'           => '12345678000199',
        'tomador_nome'          => 'TESTE NFSE CANCEL',
        'status'                => NfseEmissao::STATUS_AUTHORIZED,
        'municipio_codigo_ibge' => $codIbge,
        'emitted_at'            => now()->subHour(),
    ]);
}

// ── beforeEach / afterEach ───────────────────────────────────────────────────

beforeEach(function () {
    if (! Schema::hasTable('nfse_emissoes') || ! Schema::hasTable('nfse_eventos_cancelamento')) {
        test()->markTestSkipped('Tabelas nfse_* não existem.');
    }
});

afterEach(function () {
    // Cleanup defensivo — emissões/eventos criados nos últimos 5min com tomador_nome=TESTE NFSE CANCEL
    try {
        $emissoes = NfseEmissao::withoutGlobalScopes()
            ->where('tomador_nome', 'TESTE NFSE CANCEL')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->get();

        foreach ($emissoes as $em) {
            NfseEventoCancelamento::withoutGlobalScopes()
                ->where('nfse_emissao_id', $em->id)
                ->delete();
            $em->delete();
        }
    } catch (\Throwable) {
    }
});

// ── testes ───────────────────────────────────────────────────────────────────

it('1. resolveDriver retorna driver registrado pra município suportado', function () {
    [$business] = nfseCancelBootstrap();

    $driverAbrasf = makeFakeDriver('ABRASF_V2.04', ['4218400', '3550308']);
    $driverIpm    = makeFakeDriver('IPM', ['4314902']);

    $service = new NfseCancelService([$driverAbrasf, $driverIpm]);

    $nfseSp = criarNfseAutorizadaParaCancelar((int) $business->id, '3550308');
    $resolvido = $service->resolveDriver($nfseSp);

    expect($resolvido->getDriverKey())->toBe('ABRASF_V2.04');

    $nfsePoa = criarNfseAutorizadaParaCancelar((int) $business->id, '4314902');
    $resolvidoPoa = $service->resolveDriver($nfsePoa);

    expect($resolvidoPoa->getDriverKey())->toBe('IPM');
})->group('nfse', 'nfse-cancel');

it('2. município sem driver registrado lança RuntimeException', function () {
    [$business] = nfseCancelBootstrap();

    $driver = makeFakeDriver('ABRASF_V2.04', ['3550308']); // só SP
    $service = new NfseCancelService([$driver]);

    $nfseMystery = criarNfseAutorizadaParaCancelar((int) $business->id, '9999999'); // IBGE inexistente

    expect(fn () => $service->resolveDriver($nfseMystery))
        ->toThrow(\RuntimeException::class, 'Nenhum driver NFSe de cancelamento');
})->group('nfse', 'nfse-cancel');

it('3. motivo fora de 15-255 chars lança InvalidArgumentException', function () {
    [$business] = nfseCancelBootstrap();

    $driver = makeFakeDriver('ABRASF_V2.04', ['4218400']);
    $service = new NfseCancelService([$driver]);

    $nfse = criarNfseAutorizadaParaCancelar((int) $business->id);

    // Curto demais (< 15)
    expect(fn () => $service->cancelar(
        businessId: (int) $business->id,
        nfseEmissaoId: (int) $nfse->id,
        motivo: 'curto',
    ))->toThrow(\InvalidArgumentException::class, '15-255');

    // Longo demais (> 255)
    expect(fn () => $service->cancelar(
        businessId: (int) $business->id,
        nfseEmissaoId: (int) $nfse->id,
        motivo: str_repeat('a', 256),
    ))->toThrow(\InvalidArgumentException::class, '15-255');
})->group('nfse', 'nfse-cancel');

it('4. cross-tenant guard — businessId divergente lança UnauthorizedActionException', function () {
    [$business] = nfseCancelBootstrap();

    $driver = makeFakeDriver('ABRASF_V2.04', ['4218400']);
    $service = new NfseCancelService([$driver]);

    $nfse = criarNfseAutorizadaParaCancelar((int) $business->id);

    $businessIdFalso = (int) $business->id + 999;

    expect(fn () => $service->cancelar(
        businessId: $businessIdFalso,
        nfseEmissaoId: (int) $nfse->id,
        motivo: 'Cancelamento por engano — teste cross-tenant guard',
    ))->toThrow(UnauthorizedActionException::class, 'Cross-tenant attempt');
})->group('nfse', 'nfse-cancel');
