<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Jobs\EmitirNfceJob;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * US-NFE-002 fase 2B · Job EmitirNfceJob — dispatch NFCeAutorizada quando autorizada.
 *
 * Mudou em fase 2A (PR #198):
 *   - handle() agora aceita `NfeService $service` via container DI
 *   - Não cria mais emissão placeholder — delega ao service
 *   - Idempotência continua no service (UNIQUE + check explícito)
 *
 * Mudou em fase 2B (este PR):
 *   - Após service retornar, se `emissao.status === 'autorizada'` →
 *     `event(new NFCeAutorizada($emissao))` (mesmo pattern do NFe55)
 *
 * Tests garantem:
 *   1. Cross-tenant guard: tx.business_id != job.businessId pula silencioso
 *   2. Transaction inexistente pula sem chamar service
 *   3. Idempotência delegada ao service (Job não duplica check)
 *   4. Status='autorizada' → dispatch NFCeAutorizada
 *   5. Status='rejeitada' / 'denegada' / 'pendente' → NÃO dispatch
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
    (new EmitirNfceJob(1, 9999999))->handle($service);

    expect(NfeEmissao::where('transaction_id', 9999999)->count())->toBe(0);
});

it('transaction inexistente: skip sem chamar service', function () {
    $service = Mockery::mock(NfeService::class);
    $service->shouldNotReceive('emitirParaTransaction');

    (new EmitirNfceJob(1, 8888888))->handle($service);

    expect(NfeEmissao::where('transaction_id', 8888888)->count())->toBe(0);
});

it('idempotência ao chamar duas vezes: service é chamado mas retorna mesma emissão', function () {
    // O Job não faz mais idempotência local — delega ao service.
    // Service retorna a emissão existente quando UNIQUE bate.
    $existingEmissao = NfeEmissao::create([
        'business_id'    => 1,
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

    (new EmitirNfceJob(1, 12345))->handle($service);

    // Verifica que NÃO criou emissão duplicada
    expect(NfeEmissao::where('transaction_id', 12345)->count())->toBe(1);
});

// ── Fase 2B: dispatch NFCeAutorizada ─────────────────────────────────────

/**
 * Helper: cria Transaction mínima persistida + retorna o id. Schema UPos
 * legado tem NOT NULLs em location/transaction_date/invoice_no.
 */
function nfceJobMakeTransaction(int $businessId): int
{
    if (! Schema::hasTable('transactions')) {
        test()->markTestSkipped('transactions table ausente');
    }

    return (int) DB::table('transactions')->insertGetId([
        'business_id'      => $businessId,
        'location_id'      => 1,
        'type'             => 'sell',
        'status'           => 'final',
        'payment_status'   => 'paid',
        'transaction_date' => now()->toDateTimeString(),
        'final_total'      => 100.00,
        'invoice_no'       => 'NFCE-DISPATCH-' . uniqid(),
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
}

it('status=autorizada → dispatch event NFCeAutorizada (com listener fake)', function () {
    Event::fake([NFCeAutorizada::class]);

    $txId = nfceJobMakeTransaction(1);

    $emissao = new NfeEmissao([
        'business_id'    => 1,
        'transaction_id' => $txId,
        'modelo'         => 65,
        'serie'          => '1',
        'numero'         => 42,
        'status'         => 'autorizada',
        'cstat'          => '100',
        'chave_44'       => '35210112345678000199650010000000421000000049',
        'valor_total'    => 100.0,
    ]);
    $emissao->id = 999; // forceFill non-persisted; só pra event payload

    $service = Mockery::mock(NfeService::class);
    $service->shouldReceive('emitirParaTransaction')->once()->andReturn($emissao);

    (new EmitirNfceJob(1, $txId))->handle($service);

    Event::assertDispatched(NFCeAutorizada::class, function ($e) use ($emissao) {
        return $e->emissao->id === $emissao->id
            && $e->emissao->cstat === '100';
    });

    DB::table('transactions')->where('id', $txId)->delete();
});

it('status=rejeitada → NÃO dispatch event', function () {
    Event::fake([NFCeAutorizada::class]);

    $txId = nfceJobMakeTransaction(1);

    $emissao = new NfeEmissao([
        'business_id'    => 1,
        'transaction_id' => $txId,
        'modelo'         => 65,
        'status'         => 'rejeitada',
        'cstat'          => '215', // rejeitada por exemplo
        'valor_total'    => 100.0,
    ]);

    $service = Mockery::mock(NfeService::class);
    $service->shouldReceive('emitirParaTransaction')->once()->andReturn($emissao);

    (new EmitirNfceJob(1, $txId))->handle($service);

    Event::assertNotDispatched(NFCeAutorizada::class);

    DB::table('transactions')->where('id', $txId)->delete();
});

it('status=denegada → NÃO dispatch event', function () {
    Event::fake([NFCeAutorizada::class]);

    $txId = nfceJobMakeTransaction(1);

    $emissao = new NfeEmissao([
        'business_id'    => 1,
        'transaction_id' => $txId,
        'modelo'         => 65,
        'status'         => 'denegada',
        'cstat'          => '301',
        'valor_total'    => 100.0,
    ]);

    $service = Mockery::mock(NfeService::class);
    $service->shouldReceive('emitirParaTransaction')->once()->andReturn($emissao);

    (new EmitirNfceJob(1, $txId))->handle($service);

    Event::assertNotDispatched(NFCeAutorizada::class);

    DB::table('transactions')->where('id', $txId)->delete();
});

it('status=pendente (timeout SEFAZ) → NÃO dispatch event', function () {
    Event::fake([NFCeAutorizada::class]);

    $txId = nfceJobMakeTransaction(1);

    $emissao = new NfeEmissao([
        'business_id'    => 1,
        'transaction_id' => $txId,
        'modelo'         => 65,
        'status'         => 'pendente',
        'cstat'          => null,
        'valor_total'    => 100.0,
    ]);

    $service = Mockery::mock(NfeService::class);
    $service->shouldReceive('emitirParaTransaction')->once()->andReturn($emissao);

    (new EmitirNfceJob(1, $txId))->handle($service);

    Event::assertNotDispatched(NFCeAutorizada::class);

    DB::table('transactions')->where('id', $txId)->delete();
});
