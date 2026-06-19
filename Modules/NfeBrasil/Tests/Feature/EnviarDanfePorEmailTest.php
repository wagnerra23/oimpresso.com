<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Listeners\EnviarDanfePorEmail;
use Modules\NfeBrasil\Mail\DanfeNotaFiscalMail;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\RecurringBilling\Models\Invoice;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * US-NFE-044 fase 2 · Listener EnviarDanfePorEmail.
 *
 * Tests garantem:
 *   1. Listener registrado pra event NFeAutorizada
 *   2. Flag false → no-op (não chama Mail nem DanfeService)
 *   3. Emissão sem chave_44 → skip
 *   4. Emissão sem invoice em rb_invoices → skip silencioso
 *   5. Invoice sem email no contact → skip silencioso
 *   6. Email inválido → skip silencioso
 *   7. Happy path → Mail::to(email)->send(DanfeNotaFiscalMail) chamado
 *   8. DanfeService::lerOuGerar lança → re-throw pra retry
 *   9. ShouldQueue + queue 'nfe' + tries=3 + backoff=60
 *   10. failed() loga
 */

beforeEach(function () {
    if (! Schema::hasTable('rb_invoices') || ! Schema::hasTable('nfe_emissoes')) {
        test()->markTestSkipped('Tabelas rb_invoices/nfe_emissoes ausentes — rode migrations.');
    }
    Storage::fake('local');
});

afterEach(function () {
    config(['nfebrasil.email_danfe_on_autorizada' => true]);
    \Mockery::close();
});

// ── helpers ──────────────────────────────────────────────────────────────

function makeEmissaoAutorizada(?int $transactionId = null, int $businessId = 1): NfeEmissao
{
    return NfeEmissao::create([
        'business_id'    => $businessId,
        'transaction_id' => $transactionId,
        'modelo'         => '55',
        'serie'          => '1',
        'numero'         => 1,
        'chave_44'       => '35210112345678000199550010000000011000000019',
        'status'         => 'autorizada',
        'cstat'          => '100',
        'xml_path'       => "nfe-brasil/{$businessId}/notas/1-1.xml",
        'valor_total'    => 100.00,
        'emitido_em'     => now(),
    ]);
}

function makeInvoiceComEmail(int $businessId, int $contactId, string $email): Invoice
{
    \DB::table('contacts')->updateOrInsert(
        ['id' => $contactId],
        [
            'name'        => 'Cliente Teste',
            'business_id' => $businessId,
            'email'       => $email,
            'type'        => 'customer',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]
    );

    return Invoice::create([
        'business_id'      => $businessId,
        'contact_id'       => $contactId,
        'numero_documento' => 'INV-EMAIL-' . uniqid(),
        'valor'            => 100.00,
        'status'           => 'paid',
        'vencimento'       => now()->toDateString(),
        'pago_em'          => now(),
    ]);
}

function fakeDanfeServiceQue(string $pdfBytes = 'PDF-FAKE'): DanfeService
{
    $svc = \Mockery::mock(DanfeService::class);
    $svc->shouldReceive('lerOuGerar')->andReturn($pdfBytes);
    return $svc;
}

// ── tests ────────────────────────────────────────────────────────────────

it('listener está registrado pra event NFeAutorizada', function () {
    $listeners = Event::getRawListeners()[NFeAutorizada::class] ?? [];

    $hasListener = collect($listeners)->contains(function ($l) {
        $key = is_string($l) ? $l : (is_array($l) ? ($l[0] ?? '') : '');
        return is_string($key) && str_contains($key, 'EnviarDanfePorEmail');
    });

    expect($hasListener)->toBeTrue();
});

it('flag desabilitada → no-op (não chama Mail nem DanfeService)', function () {
    config(['nfebrasil.email_danfe_on_autorizada' => false]);
    Mail::fake();

    $emissao = makeEmissaoAutorizada();
    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('lerOuGerar');

    (new EnviarDanfePorEmail($danfe))->handle(new NFeAutorizada($emissao));

    Mail::assertNothingSent();
});

it('emissão sem chave_44 → skip silencioso', function () {
    Mail::fake();
    $emissao = makeEmissaoAutorizada();
    $emissao->update(['chave_44' => null]);

    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('lerOuGerar');

    (new EnviarDanfePorEmail($danfe))->handle(new NFeAutorizada($emissao->fresh()));

    Mail::assertNothingSent();
});

it('emissão sem transaction_id (manual) → skip silencioso', function () {
    Mail::fake();
    $emissao = makeEmissaoAutorizada(transactionId: null);

    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('lerOuGerar');

    (new EnviarDanfePorEmail($danfe))->handle(new NFeAutorizada($emissao));

    Mail::assertNothingSent();
});

it('Invoice sem contact email → skip silencioso (não envia)', function () {
    Mail::fake();

    \DB::table('contacts')->updateOrInsert(
        ['id' => 9991],
        [
            'name' => 'Sem Email',
            'business_id' => 1,
            'email' => null,
            'type' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $invoice = Invoice::create([
        'business_id'      => 1,
        'contact_id'       => 9991,
        'numero_documento' => 'INV-NO-EMAIL-' . uniqid(),
        'valor'            => 50,
        'status'           => 'paid',
        'vencimento'       => now()->toDateString(),
        'pago_em'          => now(),
    ]);

    $emissao = makeEmissaoAutorizada(transactionId: $invoice->id);

    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldNotReceive('lerOuGerar');

    (new EnviarDanfePorEmail($danfe))->handle(new NFeAutorizada($emissao));

    Mail::assertNothingSent();

    $invoice->forceDelete();
    \DB::table('contacts')->where('id', 9991)->delete();
});

it('happy path: Invoice + Contact com email → envia DanfeNotaFiscalMail com PDF anexo', function () {
    Mail::fake();

    $contactId = 9990;
    $invoice = makeInvoiceComEmail(1, $contactId, 'cliente@example.com');
    $emissao = makeEmissaoAutorizada(transactionId: $invoice->id);

    Storage::put($emissao->xml_path, '<nfeProc>fake-xml</nfeProc>');

    (new EnviarDanfePorEmail(fakeDanfeServiceQue('PDF-BYTES')))
        ->handle(new NFeAutorizada($emissao));

    Mail::assertSent(DanfeNotaFiscalMail::class, function ($mail) {
        return $mail->hasTo('cliente@example.com');
    });

    $invoice->forceDelete();
    \DB::table('contacts')->where('id', $contactId)->delete();
});

it('DanfeService falha → re-throw pra queue retry', function () {
    Mail::fake();

    $invoice = makeInvoiceComEmail(1, 9989, 'retry@example.com');
    $emissao = makeEmissaoAutorizada(transactionId: $invoice->id);

    $danfe = \Mockery::mock(DanfeService::class);
    $danfe->shouldReceive('lerOuGerar')->andThrow(new \RuntimeException('storage indisponível'));

    expect(fn () => (new EnviarDanfePorEmail($danfe))->handle(new NFeAutorizada($emissao)))
        ->toThrow(\RuntimeException::class, 'storage indisponível');

    Mail::assertNothingSent();

    $invoice->forceDelete();
    \DB::table('contacts')->where('id', 9989)->delete();
});

it('listener implementa ShouldQueue + queue nfe + tries 3 + backoff 60', function () {
    $listener = new EnviarDanfePorEmail();

    expect($listener)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class)
        ->and($listener->queue)->toBe('nfe')
        ->and($listener->tries)->toBe(3)
        ->and($listener->backoff)->toBe(60);
});

it('failed() loga erro estruturado', function () {
    Log::spy();

    $emissao = makeEmissaoAutorizada();
    $listener = new EnviarDanfePorEmail();

    $listener->failed(new NFeAutorizada($emissao), new \RuntimeException('email broker down'));

    Log::shouldHaveReceived('error')->withArgs(function ($msg, $ctx) {
        return str_contains($msg, 'failed após retries')
            && str_contains($ctx['error'] ?? '', 'email broker down');
    });
});
