<?php

declare(strict_types=1);
// Cobre UC-RBSUB-05 no schema REAL (resources/js/Pages/RecurringBilling/Index.casos.md · US-RB-002).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\RecurringBilling\Http\Controllers\RecurringBillingController;
use Modules\RecurringBilling\Http\Requests\StoreAssinaturaRequest;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;
use Modules\RecurringBilling\Services\InvoiceGeneratorService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * O remédio (a) do UC-RBSUB-05 ([W] 2026-10-19, #9606) faz o gerador faturar a assinatura sem
 * plano pelo valor negociado. Os testes dele (`PlanoSemFaturaContratoTest`) e os do `store()`
 * (`Wave21NewSubscriptionTest`) montam um schema sintético com `plan_id` NULÁVEL — mas na
 * migration e em produção (medido 2026-10-26) a coluna é `NOT NULL`: o `store()` estourava ao
 * gravar `plan_id = null`, e a assinatura negociada nem chegava a existir. Este teste roda no
 * schema real.
 *
 * VALOR — dois caminhos: o `metadata.valor` gravado pelo `store()` e o `valor` da fatura que o
 * gerador cria a partir dele (187,43 nos dois; nenhum plano da 98 tem esse valor).
 * Tenant 98 (ADR 0358). NUNCA biz=4. ⚠️ SKIP fora do MySQL: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do RecurringBilling (MySQL).');
    }
    $this->biz = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['recurringbilling.subscriptions.manage'], $this->biz);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->biz->id, 'user.id' => $this->user->id, 'business.id' => $this->biz->id]);
});

it('UC-RBSUB-05 · assinatura com valor negociado é criada sem plano e o gerador a fatura pelo valor dela', function () {
    $contato = DB::table('contacts')->insertGetId(['business_id' => $this->biz->id, 'type' => 'customer',
        'name' => 'Cliente negociado '.uniqid(), 'mobile' => '', 'contact_id' => 'RBN-'.uniqid(),
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()]);
    // Pré-condição: nenhum plano mensal da 98 tem esse valor — o store() não acha plano.
    expect(DB::table('rb_plans')->where('business_id', $this->biz->id)->where('ciclo', 'monthly')
        ->where('valor', '187.43')->count())->toBe(0);

    $vencimento = Carbon::today()->addDays(2)->toDateString();
    $request = StoreAssinaturaRequest::create('/recurring-billing', 'POST', [
        'contact_id' => $contato, 'plan_id' => null, 'valor' => 187.43, 'ciclo' => 'mensal',
        'data_proxima_cobranca' => $vencimento, 'gateway' => 'inter', 'forma_pagamento' => 'boleto',
        'descricao' => 'Mensalidade negociada',
    ]);
    $request->setUserResolver(fn () => auth()->user());
    $request->setContainer(app())->validateResolved();

    (new RecurringBillingController(new SubscriptionRepository()))->store($request);

    $sub = DB::table('rb_subscriptions')->where('business_id', $this->biz->id)->where('contact_id', $contato)->first();
    expect($sub)->not->toBeNull()
        ->and($sub->plan_id)->toBeNull();
    $meta = json_decode((string) $sub->metadata, true);
    expect((string) $meta['valor'])->toBe('187.43'); // caminho 1

    app(InvoiceGeneratorService::class)->run($this->biz->id, $vencimento);

    $fatura = DB::table('rb_invoices')->where('business_id', $this->biz->id)->where('subscription_id', $sub->id)->first();
    expect($fatura)->not->toBeNull()
        ->and((string) $fatura->valor)->toBe('187.43') // caminho 2
        ->and((int) $fatura->contact_id)->toBe($contato);
});
