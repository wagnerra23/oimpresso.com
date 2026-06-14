<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Listeners\EnviarDanfeNFCePorEmail;
use Modules\NfeBrasil\Mail\DanfeNotaFiscalMail;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * US-NFE-002 fase 2B · Listener EnviarDanfeNFCePorEmail.
 *
 * Tests garantem (espelham EnviarDanfePorEmailTest do NFe55, com adaptações
 * pro caminho NFC-e: Transaction.contact em vez de Invoice.contact):
 *   1. Listener registrado pra event NFCeAutorizada
 *   2. Flag false (default) → no-op (não chama Mail nem DanfeService)
 *   3. Modelo != 65 → skip (proteção dupla — só processar NFC-e)
 *   4. Emissão sem chave_44 → skip
 *   5. Emissão sem transaction_id → skip silencioso (NFC-e manual futura)
 *   6. Transaction sem contact com email → skip silencioso (consumidor anônimo)
 *   7. Cross-tenant guard (tx.business_id != emissao.business_id) → skip
 *   8. Happy path → Mail::to(email)->send(DanfeNotaFiscalMail) chamado
 *   9. DanfeService::lerOuGerar lança → re-throw pra retry
 *   10. ShouldQueue + queue 'nfe' + tries=3 + backoff=60
 *   11. failed() loga erro estruturado
 */

beforeEach(function () {
    if (! Schema::hasTable('transactions') || ! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('contacts')) {
        test()->markTestSkipped('Tabelas transactions/contacts/nfe_emissoes ausentes — rode migrations.');
    }
    Storage::fake('local');
    config(['nfebrasil.email_danfe_nfce_on_autorizada' => true]);
});

afterEach(function () {
    config(['nfebrasil.email_danfe_nfce_on_autorizada' => false]);
    \Mockery::close();
});

// ── helpers ──────────────────────────────────────────────────────────────

function makeNfceEmissaoAutorizada(?int $transactionId = null, int $businessId = 1): NfeEmissao
{
    return NfeEmissao::create([
        'business_id'    => $businessId,
        'transaction_id' => $transactionId,
        'modelo'         => '65',
        'serie'          => '1',
        'numero'         => 1,
        'chave_44'       => '35210112345678000199650010000000011000000019',
        'status'         => 'autorizada',
        'cstat'          => '100',
        'xml_path'       => "nfe-brasil/{$businessId}/nfce/1-1.xml",
        'valor_total'    => 100.00,
        'emitido_em'     => now(),
    ]);
}

function nfceMakeContact(int $contactId, int $businessId, ?string $email = null): void
{
    DB::table('contacts')->updateOrInsert(
        ['id' => $contactId],
        [
            'name'        => 'Consumidor NFC-e Test',
            'business_id' => $businessId,
            'email'       => $email,
            'type'        => 'customer',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]
    );
}

/**
 * Insere row mínima na tabela transactions (UPos schema legado, NOT NULLs
 * obrigatórios). Retorna o id inserido.
 */
function nfceMakeTransaction(int $businessId, int $contactId, int $locationId = 1): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id'      => $businessId,
        'location_id'      => $locationId,
        'type'             => 'sell',
        'status'           => 'final',
        'payment_status'   => 'paid',
        'contact_id'       => $contactId,
        'transaction_date' => now()->toDateTimeString(),
        'final_total'      => 100.00,
        'invoice_no'       => 'NFCE-TEST-' . uniqid(),
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
}

function fakeNfceDanfeServiceQue(string $pdfBytes = 'PDF-NFCE-FAKE'): DanfeService
{
    $svc = \Mockery::mock(DanfeService::class);
    $svc->shouldReceive('lerOuGerar')->andReturn($pdfBytes);
    return $svc;
}

// ── tests ────────────────────────────────────────────────────────────────

it('listener está registrado pra event NFCeAutorizada', function () {
    $listeners = Event::getRawListeners()[NFCeAutorizada::class] ?? [];

    $hasListener = collect($listeners)->contains(function ($l) {
        $key = is_string($l) ? $l : (is_array($l) ? ($l[0] ?? '') : '');
        return is_string($key) && str_contains($key, 'EnviarDanfeNFCePorEmail');
    });

    expect($hasListener)->toBeTrue();
});

it('flag desabilitada (default false) → no-op (não chama Mail nem DanfeService)', function () {
    config(['nfebrasil.email_danfe_nfce_on_autorizada' => false]);
    Mail::fake();

    $emissao = makeNfceEmissaoAutorizada();
    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('lerOuGerar');

    (new EnviarDanfeNFCePorEmail($danfe))->handle(new NFCeAutorizada($emissao));

    Mail::assertNothingSent();
});

it('modelo != 65 → skip (não processa NFe 55 por engano)', function () {
    Mail::fake();

    // Cria emissão modelo 55 (NFe normal) — listener deve recusar.
    $emissao = NfeEmissao::create([
        'business_id'    => 1,
        'transaction_id' => null,
        'modelo'         => '55',
        'serie'          => '1',
        'numero'         => 999,
        'chave_44'       => '35210112345678000199550010000009991000000099',
        'status'         => 'autorizada',
        'cstat'          => '100',
        'valor_total'    => 50.00,
        'emitido_em'     => now(),
    ]);

    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('lerOuGerar');

    (new EnviarDanfeNFCePorEmail($danfe))->handle(new NFCeAutorizada($emissao));

    Mail::assertNothingSent();
});

it('emissão sem chave_44 → skip silencioso', function () {
    Mail::fake();
    $emissao = makeNfceEmissaoAutorizada();
    $emissao->update(['chave_44' => null]);

    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('lerOuGerar');

    (new EnviarDanfeNFCePorEmail($danfe))->handle(new NFCeAutorizada($emissao->fresh()));

    Mail::assertNothingSent();
});

it('emissão sem transaction_id (manual futura) → skip silencioso', function () {
    Mail::fake();
    $emissao = makeNfceEmissaoAutorizada(transactionId: null);

    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('lerOuGerar');

    (new EnviarDanfeNFCePorEmail($danfe))->handle(new NFCeAutorizada($emissao));

    Mail::assertNothingSent();
});

it('Transaction.contact sem email (consumidor anônimo) → skip silencioso', function () {
    Mail::fake();

    $contactId = 79991;
    nfceMakeContact($contactId, 1, email: null); // ← sem email
    $txId = nfceMakeTransaction(1, $contactId);
    $emissao = makeNfceEmissaoAutorizada(transactionId: $txId);

    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('lerOuGerar');

    (new EnviarDanfeNFCePorEmail($danfe))->handle(new NFCeAutorizada($emissao));

    Mail::assertNothingSent();

    DB::table('transactions')->where('id', $txId)->delete();
    DB::table('contacts')->where('id', $contactId)->delete();
});

it('cross-tenant: tx.business_id != emissao.business_id → skip', function () {
    Mail::fake();

    $contactId = 79992;
    nfceMakeContact($contactId, 99, 'fraud@evil.com');
    $txId = nfceMakeTransaction(99, $contactId); // ← business 99 (adversário)
    $emissao = makeNfceEmissaoAutorizada(transactionId: $txId, businessId: 1); // ← business 1 (Wagner)

    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('lerOuGerar');

    (new EnviarDanfeNFCePorEmail($danfe))->handle(new NFCeAutorizada($emissao));

    Mail::assertNothingSent();

    DB::table('transactions')->where('id', $txId)->delete();
    DB::table('contacts')->where('id', $contactId)->delete();
});

it('happy path: Transaction.contact com email → envia DanfeNotaFiscalMail com PDF anexo', function () {
    Mail::fake();

    $contactId = 79990;
    nfceMakeContact($contactId, 1, 'consumidor@example.com');
    $txId = nfceMakeTransaction(1, $contactId);
    $emissao = makeNfceEmissaoAutorizada(transactionId: $txId);

    Storage::put($emissao->xml_path, '<nfeProc>fake-nfce-xml</nfeProc>');

    (new EnviarDanfeNFCePorEmail(fakeNfceDanfeServiceQue('PDF-NFCE-BYTES')))
        ->handle(new NFCeAutorizada($emissao));

    Mail::assertSent(DanfeNotaFiscalMail::class, function ($mail) {
        return $mail->hasTo('consumidor@example.com');
    });

    DB::table('transactions')->where('id', $txId)->delete();
    DB::table('contacts')->where('id', $contactId)->delete();
});

it('DanfeService falha → re-throw pra queue retry', function () {
    Mail::fake();

    $contactId = 79989;
    nfceMakeContact($contactId, 1, 'retry@example.com');
    $txId = nfceMakeTransaction(1, $contactId);
    $emissao = makeNfceEmissaoAutorizada(transactionId: $txId);

    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldReceive('lerOuGerar')->andThrow(new \RuntimeException('storage indisponível'));

    expect(fn () => (new EnviarDanfeNFCePorEmail($danfe))->handle(new NFCeAutorizada($emissao)))
        ->toThrow(\RuntimeException::class, 'storage indisponível');

    Mail::assertNothingSent();

    DB::table('transactions')->where('id', $txId)->delete();
    DB::table('contacts')->where('id', $contactId)->delete();
});

it('listener implementa ShouldQueue + queue nfe + tries 3 + backoff 60', function () {
    $listener = new EnviarDanfeNFCePorEmail();

    expect($listener)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class)
        ->and($listener->queue)->toBe('nfe')
        ->and($listener->tries)->toBe(3)
        ->and($listener->backoff)->toBe(60);
});

it('failed() loga erro estruturado', function () {
    Log::spy();

    $emissao = makeNfceEmissaoAutorizada();
    $listener = new EnviarDanfeNFCePorEmail();

    $listener->failed(new NFCeAutorizada($emissao), new \RuntimeException('email broker down'));

    Log::shouldHaveReceived('error')->withArgs(function ($msg, $ctx) {
        return str_contains($msg, 'failed após retries')
            && str_contains($ctx['error'] ?? '', 'email broker down');
    });
});
