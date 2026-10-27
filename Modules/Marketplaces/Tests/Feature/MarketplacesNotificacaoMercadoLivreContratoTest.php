<?php

declare(strict_types=1);
// @covers-us US-MKT-006

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Modules\Marketplaces\Entities\WebhookRecebido;
use Modules\Marketplaces\Jobs\ProcessarNotificacaoMercadoLivreJob;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do receptor de notificações do Mercado Livre (US-MKT-006).
 *
 * A notificação do ML não é assinada; a defesa provada aqui é não confiar no conteúdo:
 * app alheio é ignorado, vendedor não conectado não grava nada, a empresa vem da conta
 * (nunca da requisição), o pedido é buscado na API com o token da conta e pedido de outro
 * vendedor é recusado. Notificação repetida atualiza o pedido em vez de duplicar.
 * HTTP do ML é falso. Tenant 98 contra 99 (ADR 0358). SKIP em SQLite.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('mkt_webhook_log')) {
        $this->markTestSkipped('Precisa do schema MySQL com as tabelas mkt_* (ADR 0358).');
    }
    config(['marketplaces.mercado_livre.client_id' => 'app-nosso']);
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->ml = Marketplace::query()->where('slug', Marketplace::MERCADO_LIVRE)->firstOrFail();
    // SUPERADMIN: fixture grava a conta conectada da 98; a 99 tem outro vendedor.
    $this->conta98 = ContaVendedor::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'marketplace_id' => $this->ml->id, 'seller_id_external' => '7001',
        'access_token' => 'token-98', 'refresh_token' => 'r-98', 'access_token_expires_at' => now()->addHours(3),
    ]);
    ContaVendedor::withoutGlobalScopes()->create([
        'business_id' => $this->b99, 'marketplace_id' => $this->ml->id, 'seller_id_external' => '9009',
        'access_token' => 'token-99', 'refresh_token' => 'r-99', 'access_token_expires_at' => now()->addHours(3),
    ]);
});

function mlNotificacao(array $extra = []): array
{
    return $extra + ['resource' => '/orders/2000001', 'user_id' => 7001, 'topic' => 'orders_v2', 'application_id' => 'app-nosso', 'attempts' => 1];
}

it('US-MKT-006 · notificação do vendedor da 98 grava o log na 98 e enfileira o processamento', function () {
    Queue::fake();

    $this->postJson('/webhook/marketplaces/mercado-livre', mlNotificacao())->assertOk();

    // SUPERADMIN: leitura das duas empresas para provar onde o log foi parar.
    $logs = WebhookRecebido::withoutGlobalScopes()->where('resource_external', '/orders/2000001')->get();
    expect($logs)->toHaveCount(1)->and((int) $logs[0]->business_id)->toBe($this->b98);
    Queue::assertPushed(ProcessarNotificacaoMercadoLivreJob::class, fn ($j) => $j->businessId === $this->b98 && $j->contaId === $this->conta98->id);
});

it('US-MKT-006 · app alheio ou vendedor não conectado não grava nada', function () {
    Queue::fake();

    $this->postJson('/webhook/marketplaces/mercado-livre', mlNotificacao(['application_id' => 'app-de-outro']))->assertOk();
    $this->postJson('/webhook/marketplaces/mercado-livre', mlNotificacao(['user_id' => 555]))->assertOk();
    $this->postJson('/webhook/marketplaces/mercado-livre', ['qualquer' => 'coisa'])->assertStatus(400);

    // SUPERADMIN: confere que nenhuma empresa recebeu log.
    expect(WebhookRecebido::withoutGlobalScopes()->where('resource_external', '/orders/2000001')->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('US-MKT-006 · processar busca o pedido com o token da conta e repetir não duplica', function () {
    Queue::fake();
    $this->postJson('/webhook/marketplaces/mercado-livre', mlNotificacao())->assertOk();
    // SUPERADMIN: busca do log recém-gravado.
    $log = WebhookRecebido::withoutGlobalScopes()->where('business_id', $this->b98)->where('resource_external', '/orders/2000001')->firstOrFail();

    Http::fake(['api.mercadolibre.com/orders/2000001' => Http::response([
        'id' => 2000001, 'status' => 'paid', 'total_amount' => 120.5, 'date_created' => '2026-10-26T10:00:00.000-03:00',
        'seller' => ['id' => 7001], 'buyer' => ['id' => 333, 'nickname' => 'COMPRADOR_TESTE'],
    ])]);

    foreach ([1, 2] as $_) {
        app()->call([new ProcessarNotificacaoMercadoLivreJob($this->b98, (int) $this->conta98->id, (int) $log->id), 'handle']);
    }

    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer token-98'));
    // SUPERADMIN: leitura das duas empresas.
    $pedidos = PedidoMarketplace::withoutGlobalScopes()->where('order_id_external', '2000001')->get();
    expect($pedidos)->toHaveCount(1)
        ->and((int) $pedidos[0]->business_id)->toBe($this->b98)
        ->and($pedidos[0]->status_external)->toBe('paid')
        ->and($pedidos[0]->transaction_id)->toBeNull();
    expect(WebhookRecebido::withoutGlobalScopes()->where('superseded_id', $log->id)->where('processing_status', WebhookRecebido::PROCESSADO)->count())->toBe(2);
});

it('US-MKT-006 · pedido de outro vendedor devolvido pela API não é gravado', function () {
    Queue::fake();
    $this->postJson('/webhook/marketplaces/mercado-livre', mlNotificacao(['resource' => '/orders/2000002']))->assertOk();
    // SUPERADMIN: busca do log recém-gravado.
    $log = WebhookRecebido::withoutGlobalScopes()->where('business_id', $this->b98)->where('resource_external', '/orders/2000002')->firstOrFail();

    Http::fake(['api.mercadolibre.com/orders/2000002' => Http::response(['id' => 2000002, 'status' => 'paid', 'seller' => ['id' => 9009]])]);
    $job = new ProcessarNotificacaoMercadoLivreJob($this->b98, (int) $this->conta98->id, (int) $log->id);
    $job->tries = 1;
    app()->call([$job, 'handle']);

    // SUPERADMIN: confere em todas as empresas.
    expect(PedidoMarketplace::withoutGlobalScopes()->where('order_id_external', '2000002')->exists())->toBeFalse()
        ->and(WebhookRecebido::withoutGlobalScopes()->where('superseded_id', $log->id)->value('processing_status'))->toBe(WebhookRecebido::FALHOU);
});
