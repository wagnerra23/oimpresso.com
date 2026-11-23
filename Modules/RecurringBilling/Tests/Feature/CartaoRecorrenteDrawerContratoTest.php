<?php
// @covers-us US-RB-011

declare(strict_types=1);
// Cobre UC-RBCC-05..06 (resources/js/Pages/RecurringBilling/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\RecurringBilling\Http\Presenters\SubscriptionIndexPresenter;
use Modules\RecurringBilling\Models\CartaoSalvo;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-RB-011 · o drawer da assinatura mostra o cartão salvo (bandeira e últimos 4), nunca o token.
 *
 * A prop `subscriptions` da tela é montada pela closure deferida do `index()`:
 * `SubscriptionRepository::paginatedForIndex($biz)` → `SubscriptionIndexPresenter::toDrawerPayload`.
 * O teste chama exatamente esse par e lê o JSON que iria para a tela.
 *
 * Tenant 98 contra o 99 (ADR 0358), nunca o 4. ⚠️ SKIP fora do MySQL: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do RecurringBilling (MySQL).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    config(['recurringbilling.salvar_cartao' => true]);
});

/** Assinatura no cartão do business dado. Devolve o id. */
function crdAssinatura(int $biz, ?int $cartaoId = null): int
{
    $owner = (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $contato = DB::table('contacts')->insertGetId(['business_id' => $biz, 'type' => 'customer',
        'name' => 'Cliente CRD '.uniqid(), 'mobile' => '', 'contact_id' => 'CRD-'.uniqid(),
        'created_by' => $owner, 'created_at' => now(), 'updated_at' => now()]);

    return (int) DB::table('rb_subscriptions')->insertGetId(['business_id' => $biz, 'plan_id' => null,
        'contact_id' => $contato, 'status' => 'active', 'start_date' => '2031-03-01', 'next_due_date' => '2031-04-01',
        'billing_anchor_date' => '2031-04-01', 'payment_method' => 'card', 'cartao_salvo_id' => $cartaoId,
        'metadata' => json_encode([]), 'created_at' => now(), 'updated_at' => now()]);
}

/** Cartão salvo com token conhecido (cifrado pelo cast do modelo). */
function crdCartao(int $biz, string $token, string $ultimos4, string $bandeira, string $status = 'ativo'): int
{
    $contato = (int) DB::table('contacts')->where('business_id', $biz)->value('id');

    // SUPERADMIN: fixture de teste grava o cartão do tenant dado sem sessão.
    return (int) CartaoSalvo::withoutGlobalScopes()->create(['business_id' => $biz, 'contact_id' => $contato,
        'gateway_key' => 'asaas', 'token' => $token, 'token_hash' => hash('sha256', $token),
        'ultimos_4' => $ultimos4, 'bandeira' => $bandeira, 'status' => $status])->id;
}

/** O que a prop `subscriptions` levaria para a tela do business dado, por id da assinatura. */
function crdDrawer(int $biz): array
{
    $pagina = app(SubscriptionRepository::class)->paginatedForIndex($biz, [], 500);

    return collect($pagina->items())
        ->mapWithKeys(fn ($sub) => [$sub->id => SubscriptionIndexPresenter::toDrawerPayload($sub)])
        ->all();
}

it('UC-RBCC-05 · o drawer mostra bandeira e últimos 4 do cartão salvo, e o token nunca vai nas props', function () {
    $cartao = crdCartao($this->b98, 'tok-crd-segredo-98', '8829', 'MASTERCARD');
    $sub = crdAssinatura($this->b98, $cartao);
    $semCartao = crdAssinatura($this->b98);

    $drawer = crdDrawer($this->b98);

    expect($drawer[$sub]['cartao_recorrente'])->toBe(['ultimos_4' => '8829', 'bandeira' => 'MASTERCARD'])
        ->and($drawer[$semCartao]['cartao_recorrente'])->toBe(['ultimos_4' => null, 'bandeira' => null]);

    // O token não aparece em lugar nenhum do JSON, nem cifrado nem o hash.
    $json = json_encode($drawer);
    $linha = DB::table('rb_cartoes_salvos')->where('id', $cartao)->first();
    expect($json)->not->toContain('tok-crd-segredo-98')
        ->and($json)->not->toContain((string) $linha->token)
        ->and($json)->not->toContain((string) $linha->token_hash);

    // Recurso desligado: a seção some.
    config(['recurringbilling.salvar_cartao' => false]);
    expect(crdDrawer($this->b98)[$sub]['cartao_recorrente'])->toBeNull();
});

it('UC-RBCC-06 · a empresa 99 não vê o cartão da 98, nem apontando para ele', function () {
    $cartao98 = crdCartao($this->b98, 'tok-crd-segredo-98b', '1111', 'VISA');
    crdAssinatura($this->b98, $cartao98);

    // Assinatura do 99 com cartao_salvo_id forjado para o cartão do 98.
    $sub99 = crdAssinatura($this->b99, $cartao98);

    $drawer99 = crdDrawer($this->b99);

    expect($drawer99[$sub99]['cartao_recorrente'])->toBe(['ultimos_4' => null, 'bandeira' => null]);
    expect(json_encode($drawer99))->not->toContain('tok-crd-segredo-98b');
    // A lista do 99 não traz assinatura do 98.
    expect(collect($drawer99)->pluck('id')->all())->each->not->toBeIn(
        DB::table('rb_subscriptions')->where('business_id', $this->b98)->pluck('id')->all()
    );

    // Cartão removido também não aparece.
    $removido = crdCartao($this->b98, 'tok-crd-removido', '2222', 'ELO', 'removido');
    $subRemovido = crdAssinatura($this->b98, $removido);
    expect(crdDrawer($this->b98)[$subRemovido]['cartao_recorrente'])->toBe(['ultimos_4' => null, 'bandeira' => null]);
});
