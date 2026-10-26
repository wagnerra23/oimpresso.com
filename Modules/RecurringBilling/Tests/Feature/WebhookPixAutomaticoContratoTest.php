<?php
// @covers-us US-RB-021

declare(strict_types=1);
// Cobre UC-RBPA-07..10 (resources/js/Pages/RecurringBilling/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Jobs\ProcessarWebhookBcbPixJob;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\ReconciliarCobrancaService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Webhooks do Pix Automático BCB (formatos da API Pix v2.10.0: `cobsr[]` e `recs[]`) processados
 * pelo `ProcessarWebhookBcbPixJob` (US-RB-021 · decisão [W] 2026-10-26).
 *
 * VALOR — a notificação não traz valor; a cobrança paga recebe o próprio `valor_centavos` (10607),
 * e o `CobrancaPaga` sai com esse valor: dois caminhos (linha em `cobrancas` e o evento).
 * Tenant 98 × 99 (ADR 0358), nunca o 4. ⚠️ SKIP fora do MySQL: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do PaymentGateway/RecurringBilling (MySQL).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    config(['recurringbilling.cobrar_pix_automatico' => true]);
});

function wpaCred(int $biz): PaymentGatewayCredential
{
    return PaymentGatewayCredential::query()->create(['business_id' => $biz, 'gateway_key' => 'bcb_pix',
        'ambiente' => 'sandbox', 'ativo' => true, 'nome_display' => 'BCB '.$biz,
        'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'base_url' => 'https://psp.fake.local']]);
}

function wpaCobranca(int $biz, int $credId, string $txid, string $status = 'emitida'): int
{
    return (int) DB::table('cobrancas')->insertGetId(['business_id' => $biz, 'payment_gateway_credential_id' => $credId,
        'tipo' => 'pix_recv', 'status' => $status, 'valor_centavos' => 10607, 'vencimento' => '2031-03-25',
        'contact_id' => 1, 'descricao' => 'Fatura PA', 'idempotency_key' => uniqid('wpa', true),
        'gateway_external_id' => $txid, 'origem_type' => 'invoice', 'origem_id' => 999000 + random_int(1, 999),
        'created_at' => now(), 'updated_at' => now()]);
}

function wpaEvento(int $biz, int $credId, array $payload): int
{
    return (int) GatewayWebhookEvent::query()->create(['business_id' => $biz, 'payment_gateway_credential_id' => $credId,
        'gateway_key' => 'bcb_pix', 'evento' => 'unknown', 'gateway_event_id' => uniqid('ev', true),
        'payload' => $payload, 'signature_valid' => true])->id;
}

function wpaRodar(int $eventoId, int $biz): void
{
    (new ProcessarWebhookBcbPixJob($eventoId, $biz))->handle(app(ReconciliarCobrancaService::class));
}

it('UC-RBPA-07 · cobrança do ciclo CONCLUIDA vira paga com o próprio valor e dispara CobrancaPaga uma vez', function () {
    Event::fake([CobrancaPaga::class]);
    $cred = wpaCred($this->b98);
    $txid = 'RB'.substr(md5(uniqid()), 0, 30);
    $cob = wpaCobranca($this->b98, $cred->id, $txid);
    $ev = wpaEvento($this->b98, $cred->id, ['cobsr' => [['idRec' => 'RR-1', 'txid' => $txid, 'status' => 'CONCLUIDA',
        'atualizacao' => [['status' => 'ATIVA', 'data' => '2031-03-24T09:00:00Z'], ['status' => 'CONCLUIDA', 'data' => '2031-03-25T08:15:00Z']]]]]);

    wpaRodar($ev, $this->b98);
    wpaRodar($ev, $this->b98); // reentrega do job: já processado

    $linha = DB::table('cobrancas')->where('id', $cob)->first();
    expect($linha->status)->toBe('paga')
        ->and((int) $linha->valor_pago_centavos)->toBe(10607)                 // caminho 1
        ->and(substr((string) $linha->paga_em, 0, 10))->toBe('2031-03-25');
    Event::assertDispatchedTimes(CobrancaPaga::class, 1);
    Event::assertDispatched(CobrancaPaga::class, fn ($e) => $e->cobrancaId === $cob   // caminho 2
        && $e->businessId === $this->b98 && $e->valorPagoCentavos === 10607 && $e->origemType === 'invoice');
    expect(GatewayWebhookEvent::query()->find($ev)->processed_at)->not->toBeNull();
});

it('UC-RBPA-08 · recorrência APROVADA atualiza o mandato da assinatura; REJEITADA da cobrança vira erro e CANCELADA não desfaz paga', function () {
    $cred = wpaCred($this->b98);
    $idRec = 'RR'.uniqid();
    $owner = (int) DB::table('business')->where('id', $this->b98)->value('owner_id');
    $contato = DB::table('contacts')->insertGetId(['business_id' => $this->b98, 'type' => 'customer', 'name' => 'C '.uniqid(),
        'mobile' => '', 'contact_id' => 'W-'.uniqid(), 'created_by' => $owner, 'created_at' => now(), 'updated_at' => now()]);
    $plano = DB::table('rb_plans')->insertGetId(['business_id' => $this->b98, 'name' => 'P '.uniqid(), 'slug' => 'p-'.uniqid(),
        'valor' => '106.07', 'ciclo' => 'monthly', 'created_at' => now(), 'updated_at' => now()]);
    $sub = DB::table('rb_subscriptions')->insertGetId(['business_id' => $this->b98, 'plan_id' => $plano, 'contact_id' => $contato,
        'status' => 'active', 'start_date' => '2031-03-01', 'next_due_date' => '2031-04-01', 'billing_anchor_date' => '2031-04-01',
        'metadata' => json_encode(['pix_automatico' => ['status' => 'CRIADA', 'id_rec' => $idRec]]), 'created_at' => now(), 'updated_at' => now()]);
    $rejeitada = wpaCobranca($this->b98, $cred->id, 'RB'.substr(md5(uniqid()), 0, 30));
    $paga = wpaCobranca($this->b98, $cred->id, 'RB'.substr(md5(uniqid()), 0, 30), 'paga');

    wpaRodar(wpaEvento($this->b98, $cred->id, ['recs' => [['idRec' => $idRec, 'status' => 'APROVADA']]]), $this->b98);
    wpaRodar(wpaEvento($this->b98, $cred->id, ['cobsr' => [
        ['txid' => DB::table('cobrancas')->where('id', $rejeitada)->value('gateway_external_id'), 'status' => 'REJEITADA'],
        ['txid' => DB::table('cobrancas')->where('id', $paga)->value('gateway_external_id'), 'status' => 'CANCELADA'],
    ]]), $this->b98);

    $mandato = json_decode((string) DB::table('rb_subscriptions')->where('id', $sub)->value('metadata'), true)['pix_automatico'];
    expect($mandato['status'])->toBe('APROVADA')->and($mandato['id_rec'])->toBe($idRec);
    expect(DB::table('cobrancas')->where('id', $rejeitada)->value('status'))->toBe('erro')
        ->and(DB::table('cobrancas')->where('id', $paga)->value('status'))->toBe('paga');
});

it('UC-RBPA-09 · com a flag desligada o evento fica gravado sem processar', function () {
    config(['recurringbilling.cobrar_pix_automatico' => false]);
    $cred = wpaCred($this->b98);
    $txid = 'RB'.substr(md5(uniqid()), 0, 30);
    $cob = wpaCobranca($this->b98, $cred->id, $txid);
    $ev = wpaEvento($this->b98, $cred->id, ['cobsr' => [['txid' => $txid, 'status' => 'CONCLUIDA']]]);

    wpaRodar($ev, $this->b98);

    expect(DB::table('cobrancas')->where('id', $cob)->value('status'))->toBe('emitida')
        ->and(GatewayWebhookEvent::query()->find($ev)->processed_at)->toBeNull();
});

it('UC-RBPA-10 · Tier 0: evento da 98 não paga cobrança da 99 com o mesmo txid, e evento de outro business não é processado', function () {
    Event::fake([CobrancaPaga::class]);
    $c98 = wpaCred($this->b98);
    $c99 = wpaCred($this->b99);
    $txid = 'RB'.substr(md5(uniqid()), 0, 30);
    $cob99 = wpaCobranca($this->b99, $c99->id, $txid);
    $ev98 = wpaEvento($this->b98, $c98->id, ['cobsr' => [['txid' => $txid, 'status' => 'CONCLUIDA']]]);
    $ev99 = wpaEvento($this->b99, $c99->id, ['cobsr' => [['txid' => $txid, 'status' => 'CONCLUIDA']]]);

    wpaRodar($ev98, $this->b98);  // não acha cobrança na 98
    wpaRodar($ev99, $this->b98);  // evento da 99 pedido com o business errado: não carrega

    expect(DB::table('cobrancas')->where('id', $cob99)->value('status'))->toBe('emitida')
        ->and(GatewayWebhookEvent::query()->find($ev99)->processed_at)->toBeNull();
    Event::assertNotDispatched(CobrancaPaga::class);
});
