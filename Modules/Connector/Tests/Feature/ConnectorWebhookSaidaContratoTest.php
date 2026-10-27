<?php

declare(strict_types=1);
// @covers-us US-CONN-021

use App\Events\SellCreatedOrModified;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Connector\Entities\WebhookDestino;
use Modules\Connector\Entities\WebhookEntrega;
use Modules\Connector\Jobs\EnviarWebhookSaidaJob;
use Modules\Connector\Listeners\VendaFinalizadaParaWebhookSaida;
use Modules\Connector\Services\WebhookSaida\DisparadorWebhookSaida;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato dos webhooks de saída (US-CONN-021): só a empresa dona recebe, o payload não leva
 * dado pessoal, a entrega é assinada de forma verificável, a mesma venda não avisa duas vezes e
 * destino que recusa não vira "entregue".
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (schema UltimatePOS com FK para business).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: FK para business do schema UltimatePOS (ADR 0358).');
    }
    if (! Schema::hasTable('connector_webhook_destinos')) {
        $this->markTestSkipped('Tabelas de webhook de saída ausentes — migration do Connector não rodou.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
});

function whDestino(int $biz, string $url, array $eventos = [DisparadorWebhookSaida::VENDA_FINALIZADA]): WebhookDestino
{
    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts.
    return WebhookDestino::withoutGlobalScopes()->create([
        'business_id' => $biz, 'url' => $url, 'segredo' => 'segredo-teste-'.$biz, 'eventos' => $eventos,
    ]);
}

function whVenda(int $biz, string $status, int $id = 990001): Transaction
{
    return (new Transaction())->forceFill([
        'id' => $id, 'business_id' => $biz, 'type' => 'sell', 'status' => $status,
        'invoice_no' => 'T-'.$id, 'transaction_date' => '2026-10-26 10:00:00',
        'final_total' => '150.0000', 'payment_status' => 'paid', 'location_id' => null,
    ]);
}

it('US-CONN-021 · sem destino cadastrado nada é criado nem enfileirado', function () {
    Queue::fake();

    expect(app(DisparadorWebhookSaida::class)->disparar($this->b98, DisparadorWebhookSaida::VENDA_FINALIZADA, 1, []))->toBe(0);
    Queue::assertNothingPushed();
});

it('US-CONN-021 · venda da 98 avisa só o destino da 98, uma vez, sem dado pessoal', function () {
    Queue::fake();
    $d98 = whDestino($this->b98, 'https://destino-98.example/hook');
    $d99 = whDestino($this->b99, 'https://destino-99.example/hook');
    $listener = app(VendaFinalizadaParaWebhookSaida::class);

    $listener->handle(new SellCreatedOrModified(whVenda($this->b98, 'draft')));
    expect(WebhookEntrega::withoutGlobalScopes()->whereIn('destino_id', [$d98->id, $d99->id])->count())->toBe(0);

    $listener->handle(new SellCreatedOrModified(whVenda($this->b98, 'final')));
    $listener->handle(new SellCreatedOrModified(whVenda($this->b98, 'final'))); // editada depois

    // SUPERADMIN: leitura das entregas das duas empresas para provar o isolamento.
    $entregas = WebhookEntrega::withoutGlobalScopes()->whereIn('destino_id', [$d98->id, $d99->id])->get();
    expect($entregas)->toHaveCount(1)
        ->and((int) $entregas[0]->destino_id)->toBe((int) $d98->id)
        ->and((int) $entregas[0]->business_id)->toBe($this->b98);

    $dados = $entregas[0]->payload['dados'];
    expect(array_keys($dados))->toBe(['venda_id', 'numero', 'data', 'total', 'situacao_pagamento', 'local_id'])
        ->and($dados['venda_id'])->toBe(990001);

    Queue::assertPushed(EnviarWebhookSaidaJob::class, 1);
    Queue::assertPushed(EnviarWebhookSaidaJob::class, fn ($job) => $job->businessId === $this->b98);
});

it('US-CONN-021 · entrega sai assinada e o destino consegue conferir a assinatura', function () {
    Queue::fake();
    $d = whDestino($this->b98, 'https://destino-98.example/hook');
    app(DisparadorWebhookSaida::class)->disparar($this->b98, DisparadorWebhookSaida::VENDA_FINALIZADA, 7, ['venda_id' => 7]);
    // SUPERADMIN: busca da entrega recém-criada na fixture.
    $entrega = WebhookEntrega::withoutGlobalScopes()->where('destino_id', $d->id)->firstOrFail();

    Http::fake(['destino-98.example/*' => Http::response('', 204)]);
    (new EnviarWebhookSaidaJob($this->b98, (int) $entrega->id))->handle();

    Http::assertSent(function (HttpRequest $r) use ($entrega) {
        [$t, $v1] = array_map(fn ($p) => explode('=', $p, 2)[1], explode(',', $r->header('X-Oimpresso-Signature')[0]));
        $esperado = hash_hmac('sha256', $t.'.'.$r->body(), 'segredo-teste-'.$this->b98);

        return $r->url() === 'https://destino-98.example/hook'
            && $r->header('X-Oimpresso-Delivery')[0] === $entrega->entrega_uuid
            && hash_equals($esperado, $v1);
    });
    expect($entrega->fresh()->status)->toBe(WebhookEntrega::ENTREGUE)
        ->and((int) $entrega->fresh()->http_status)->toBe(204);
});

it('US-CONN-021 · destino que recusa não vira entregue e, esgotadas as tentativas, fica falhou', function () {
    Queue::fake();
    $d = whDestino($this->b98, 'https://destino-98.example/hook');
    app(DisparadorWebhookSaida::class)->disparar($this->b98, DisparadorWebhookSaida::VENDA_FINALIZADA, 8, ['venda_id' => 8]);
    // SUPERADMIN: busca da entrega recém-criada na fixture.
    $entrega = WebhookEntrega::withoutGlobalScopes()->where('destino_id', $d->id)->firstOrFail();
    $job = new EnviarWebhookSaidaJob($this->b98, (int) $entrega->id);

    Http::fake(['destino-98.example/*' => Http::response('erro', 500)]);
    expect(fn () => $job->handle())->toThrow(RuntimeException::class);

    $depois = $entrega->fresh();
    expect($depois->status)->toBe(WebhookEntrega::PENDENTE)
        ->and((int) $depois->tentativas)->toBe(1)
        ->and($depois->ultimo_erro)->toBe('HTTP 500');

    $job->failed(null);
    expect($entrega->fresh()->status)->toBe(WebhookEntrega::FALHOU);
});

it('US-CONN-021 · job com o business errado não envia a entrega de outra empresa', function () {
    Queue::fake();
    $d = whDestino($this->b98, 'https://destino-98.example/hook');
    app(DisparadorWebhookSaida::class)->disparar($this->b98, DisparadorWebhookSaida::VENDA_FINALIZADA, 9, ['venda_id' => 9]);
    // SUPERADMIN: busca da entrega recém-criada na fixture.
    $entrega = WebhookEntrega::withoutGlobalScopes()->where('destino_id', $d->id)->firstOrFail();

    Http::fake();
    (new EnviarWebhookSaidaJob($this->b99, (int) $entrega->id))->handle();

    Http::assertNothingSent();
    expect($entrega->fresh()->status)->toBe(WebhookEntrega::PENDENTE);
});

it('US-CONN-021 · segredo do destino fica cifrado no banco e fora do JSON', function () {
    $d = whDestino($this->b98, 'https://destino-98.example/hook');

    $cru = (string) DB::table('connector_webhook_destinos')->where('id', $d->id)->value('segredo');
    expect(str_contains($cru, 'segredo-teste'))->toBeFalse()
        ->and($d->toArray())->not->toHaveKey('segredo');
});
