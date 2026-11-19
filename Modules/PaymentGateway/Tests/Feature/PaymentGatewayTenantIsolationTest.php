<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Jobs\CnabRetornoProcessor;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\InterWebhookLog;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Isolamento multi-tenant Tier 0 do PaymentGateway (ADR 0093, Garantia 4).
 *
 * Nasceu como esqueleto de 3 ->todo() na auditoria RBAC de 2026-11-14. Aqui cada caso
 * negativo (sessão da 98 sobre registro da 99 → 404 e banco intacto) tem o seu controle
 * positivo (o mesmo pedido sobre registro da própria 98 passa e muda o banco). Sem o
 * positivo, um 403/404 em tudo — rota quebrada, permissão faltando — passaria verde.
 *
 * Cobertura:
 *   - update e destroy de credencial               (PaymentGatewaysController)
 *   - index e store do retorno CNAB por credencial  (PaymentGatewaysCnabRetornoController)
 *   - history e webhook-events de credencial        (activity_log e gateway_webhook_events)
 *   - inter_webhook_log: nenhuma rota de Settings lê essa tabela (medido 2026-11-19,
 *     `git grep InterWebhookLog -- Modules/PaymentGateway/Http` só acha o controller que
 *     GRAVA). O que se prova aqui é o escopo global do model que qualquer leitor futuro
 *     usará, e que webhook-events não devolve linha dela.
 *
 * Tenants (ADR 0358): sessão = 98, adversário = 99 (seededSupportClientTenant()).
 * Usuário com só `business_settings.access`, a permissão do grupo de rotas `settings.`.
 * Nenhuma chamada a API externa de gateway; segredos de fixture são marcadores inertes.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

const PGISO_TENANT = 98;

/** Par (gateway_key, ambiente) livre no business — respeita a UNIQUE da tabela. */
function pgisoCriarCredencial(int $businessId, array $attrs = []): PaymentGatewayCredential
{
    foreach (['inter', 'c6', 'asaas', 'bcb_pix', 'pesapal'] as $key) {
        foreach (['sandbox', 'production'] as $amb) {
            $existe = DB::table('payment_gateway_credentials')
                ->where('business_id', $businessId)->where('gateway_key', $key)->where('ambiente', $amb)
                ->exists();
            if (! $existe) {
                // SUPERADMIN: fixture de teste cria credencial em business arbitrário (98 ou 99).
                return PaymentGatewayCredential::withoutGlobalScopes()->create(array_merge([
                    'business_id' => $businessId,
                    'gateway_key' => $key,
                    'ambiente' => $amb,
                    'ativo' => true,
                    'nome_display' => 'CT PGISO ' . uniqid(),
                    'config_json' => ['marcador' => 'inerte'],
                    'health_status' => 'ok',
                ], $attrs));
            }
        }
    }

    throw new RuntimeException("Sem par (gateway_key, ambiente) livre para o business {$businessId}.");
}

function pgisoEvento(int $businessId, int $credentialId, string $evento): void
{
    // SUPERADMIN: fixture grava evento em business arbitrário (inclusive linha forjada).
    GatewayWebhookEvent::withoutGlobalScopes()->create([
        'business_id' => $businessId,
        'payment_gateway_credential_id' => $credentialId,
        'gateway_key' => 'inter',
        'evento' => $evento,
        'gateway_event_id' => 'evt_' . uniqid(),
        'payload' => ['marcador' => $evento],
        'signature_valid' => true,
    ]);
}

beforeEach(function () {
    $this->tenant = \App\Business::find(PGISO_TENANT);
    if (! $this->tenant) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358).');
    }
    $this->adversario = $this->seededSupportClientTenant();

    $this->user = $this->usuarioComPermissoes(['business_settings.access'], $this->tenant);
    $this->sessao = ['user.business_id' => PGISO_TENANT, 'business.id' => PGISO_TENANT];

    $this->propria = pgisoCriarCredencial(PGISO_TENANT);
    $this->alheia = pgisoCriarCredencial($this->adversario->id, ['nome_display' => 'NOME-DA-99-' . uniqid()]);

    $this->linhaAlheia = fn () => (array) DB::table('payment_gateway_credentials')->where('id', $this->alheia->id)->first();
});

// ─── update ────────────────────────────────────────────────────────────────

it('update de credencial da 99 com sessão da 98 dá 404 e não altera a 99; a própria 98 é alterada', function () {
    $antes = ($this->linhaAlheia)();

    $this->actingAs($this->user)->withSession($this->sessao)
        ->putJson("/settings/payment-gateways/{$this->alheia->id}", ['nome_display' => 'INVADIDO', 'ativo' => false])
        ->assertNotFound();

    expect(($this->linhaAlheia)())->toEqual($antes);

    // Controle positivo: o mesmo pedido na credencial da 98 passa e grava.
    $this->actingAs($this->user)->withSession($this->sessao)
        ->putJson("/settings/payment-gateways/{$this->propria->id}", ['nome_display' => 'Renomeada pela 98'])
        ->assertSuccessful();

    expect(DB::table('payment_gateway_credentials')->where('id', $this->propria->id)->value('nome_display'))
        ->toBe('Renomeada pela 98');
});

// ─── destroy ───────────────────────────────────────────────────────────────

it('destroy de credencial da 99 com sessão da 98 dá 404 e a 99 continua lá; a própria 98 é removida', function () {
    $this->actingAs($this->user)->withSession($this->sessao)
        ->deleteJson("/settings/payment-gateways/{$this->alheia->id}")
        ->assertNotFound();

    expect(DB::table('payment_gateway_credentials')->where('id', $this->alheia->id)->exists())->toBeTrue();

    $this->actingAs($this->user)->withSession($this->sessao)
        ->deleteJson("/settings/payment-gateways/{$this->propria->id}")
        ->assertSuccessful();

    expect(DB::table('payment_gateway_credentials')->where('id', $this->propria->id)->exists())->toBeFalse();
});

// ─── retorno CNAB (cnab_retorno_uploads) ─────────────────────────────────────

it('index do retorno CNAB na credencial da 99 dá 404; na própria 98 abre a tela', function () {
    $this->actingAs($this->user)->withSession($this->sessao)
        ->get("/settings/payment-gateways/{$this->alheia->id}/cnab-retorno")
        ->assertNotFound();

    $this->actingAs($this->user)->withSession($this->sessao)
        ->get("/settings/payment-gateways/{$this->propria->id}/cnab-retorno")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/PaymentGateways/CnabRetorno')
            ->where('credential.id', $this->propria->id));
});

it('store do retorno CNAB na credencial da 99 dá 404 e não grava upload; na própria 98 grava', function () {
    Storage::fake('local');
    Queue::fake();

    $uploads = fn (int $credId) => DB::table('cnab_retorno_uploads')->where('payment_gateway_credential_id', $credId)->count();
    $alheiaAntes = $uploads($this->alheia->id);

    $this->actingAs($this->user)->withSession($this->sessao)
        ->post("/settings/payment-gateways/{$this->alheia->id}/cnab-retorno", [
            'arquivo' => UploadedFile::fake()->createWithContent('retorno.ret', str_repeat('0', 240)),
        ])
        ->assertNotFound();

    expect($uploads($this->alheia->id))->toBe($alheiaAntes);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Queue::assertNotPushed(CnabRetornoProcessor::class);

    // Controle positivo: mesmo arquivo na credencial da 98 grava upload no business 98.
    $this->actingAs($this->user)->withSession($this->sessao)
        ->post("/settings/payment-gateways/{$this->propria->id}/cnab-retorno", [
            'arquivo' => UploadedFile::fake()->createWithContent('retorno.ret', str_repeat('0', 240)),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(DB::table('cnab_retorno_uploads')
        ->where('payment_gateway_credential_id', $this->propria->id)
        ->where('business_id', PGISO_TENANT)
        ->count())->toBe(1);
    Queue::assertPushed(CnabRetornoProcessor::class);
});

// ─── leitura: history (activity_log) e webhook-events (gateway_webhook_events) ──

it('history da credencial da 99 dá 404 sem vazar o nome; a da 98 devolve as entradas dela', function () {
    $resp = $this->actingAs($this->user)->withSession($this->sessao)
        ->getJson("/settings/payment-gateways/{$this->alheia->id}/history");

    $resp->assertNotFound();
    expect($resp->getContent())->not->toContain($this->alheia->nome_display);

    // Controle positivo: uma alteração na 98 gera entrada no activity_log e ela aparece.
    $this->actingAs($this->user)->withSession($this->sessao)
        ->putJson("/settings/payment-gateways/{$this->propria->id}", ['nome_display' => 'Historico 98'])
        ->assertSuccessful();

    $this->actingAs($this->user)->withSession($this->sessao)
        ->getJson("/settings/payment-gateways/{$this->propria->id}/history")
        ->assertOk()
        ->assertJsonPath('total', fn ($t) => $t >= 1);
});

it('webhook-events: credencial da 99 dá 404; na 98 vem o evento dela e nunca o da 99, nem forjado', function () {
    pgisoEvento($this->adversario->id, $this->alheia->id, 'EVENTO_DA_99');
    // Linha forjada: business 99 apontando para a credencial da 98.
    pgisoEvento($this->adversario->id, $this->propria->id, 'FORJADO_DA_99');
    pgisoEvento(PGISO_TENANT, $this->propria->id, 'EVENTO_DA_98');

    $resp = $this->actingAs($this->user)->withSession($this->sessao)
        ->getJson("/settings/payment-gateways/{$this->alheia->id}/webhook-events");
    $resp->assertNotFound();
    expect($resp->getContent())->not->toContain('EVENTO_DA_99');

    $propria = $this->actingAs($this->user)->withSession($this->sessao)
        ->getJson("/settings/payment-gateways/{$this->propria->id}/webhook-events");
    $propria->assertOk();

    $eventos = collect($propria->json('events'))->pluck('evento')->all();
    expect($eventos)->toContain('EVENTO_DA_98');
    expect($eventos)->not->toContain('FORJADO_DA_99');
    expect($eventos)->not->toContain('EVENTO_DA_99');
});

it('inter_webhook_log: com sessão da 98 o model só devolve a linha da 98', function () {
    $txid98 = 'tx98' . uniqid();
    $txid99 = 'tx99' . uniqid();
    foreach ([[PGISO_TENANT, $this->propria->id, $txid98], [$this->adversario->id, $this->alheia->id, $txid99]] as [$biz, $cred, $txid]) {
        // SUPERADMIN: fixture grava log de webhook em business arbitrário.
        InterWebhookLog::withoutGlobalScopes()->create([
            'business_id' => $biz,
            'payment_gateway_credential_id' => $cred,
            'txid' => $txid,
            'valor_centavos' => 100,
            'signature_valid' => true,
            'status' => 'received',
            'payload' => ['txid' => $txid],
        ]);
    }

    $this->actingAs($this->user);
    session(['user.business_id' => PGISO_TENANT]);

    $vistos = InterWebhookLog::query()->whereIn('txid', [$txid98, $txid99])->pluck('txid')->all();
    expect($vistos)->toBe([$txid98]);

    // E nenhuma rota de leitura de Settings o expõe: webhook-events da 98 não traz o txid da 99.
    $this->withSession($this->sessao)
        ->getJson("/settings/payment-gateways/{$this->propria->id}/webhook-events")
        ->assertOk()
        ->assertDontSee($txid99);
});
