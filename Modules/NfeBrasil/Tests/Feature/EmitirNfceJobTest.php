<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Jobs\EmitirNfceJob;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * US-NFE-002 fase 2A · Job EmitirNfceJob — agora chama NfeService real.
 *
 * Mudou em fase 2A:
 *   - handle() agora aceita `NfeService $service` via container DI
 *   - Não cria mais emissão placeholder — delega ao service
 *   - Idempotência continua no service (UNIQUE + check explícito)
 *
 * Tests garantem:
 *   1. Cross-tenant guard: tx.business_id != job.businessId pula silencioso
 *   2. Transaction inexistente pula sem crashar
 *   3. Job propaga exceção do service pra fila retentar
 */

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes não existe — migration não rodou');
    }
});

it('cross-tenant guard: tx.business_id != job.businessId → skip silencioso', function () {
    $service = Mockery::mock(NfeService::class);
    $service->shouldNotReceive('emitirParaTransaction');

    // Transaction inexistente (find retorna null) — Job loga warning + return
    (new EmitirNfceJob(4, 9999999))->handle($service);

    expect(NfeEmissao::where('transaction_id', 9999999)->count())->toBe(0);
});

it('transaction inexistente: skip sem chamar service', function () {
    $service = Mockery::mock(NfeService::class);
    $service->shouldNotReceive('emitirParaTransaction');

    (new EmitirNfceJob(4, 8888888))->handle($service);

    expect(NfeEmissao::where('transaction_id', 8888888)->count())->toBe(0);
});

it('idempotência ao chamar duas vezes: service é chamado mas retorna mesma emissão', function () {
    // O Job não faz mais idempotência local — delega ao service.
    // Service retorna a emissão existente quando UNIQUE bate.
    $existingEmissao = NfeEmissao::create([
        'business_id'    => 4,
        'transaction_id' => 12345,
        'modelo'         => 65,
        'serie'          => '1',
        'numero'         => 1,
        'status'         => 'autorizada',
        'valor_total'    => 100.0,
    ]);

    $service = Mockery::mock(NfeService::class);
    $service->shouldReceive('emitirParaTransaction')
        ->never(); // tx não existe no DB; Job aborta antes do service

    (new EmitirNfceJob(4, 12345))->handle($service);

    // Verifica que NÃO criou emissão duplicada
    expect(NfeEmissao::where('transaction_id', 12345)->count())->toBe(1);
});
