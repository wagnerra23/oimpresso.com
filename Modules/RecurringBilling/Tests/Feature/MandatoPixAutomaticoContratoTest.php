<?php
// @covers-us US-RB-020

declare(strict_types=1);
// Cobre UC-RBPA-11..14 (resources/js/Pages/RecurringBilling/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\RecurringBilling\Services\MandatoPixAutomatico;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Jornada de aprovação do mandato do Pix Automático (jornada 2 da API Pix BCB v2.10.0: /locrec →
 * /rec com loc → GET /rec com dadosQR). US-RB-020 · decisão [W] 2026-10-26.
 *
 * VALOR — o `valorRec` que vai ao banco é o do plano (106,07): conferido no corpo enviado
 * ("106.07") e no mandato gravado (10607 centavos), que é o mesmo valor que o gerador fatura.
 * Tenant 98 × 99 (ADR 0358), nunca o 4. ⚠️ SKIP fora do MySQL: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do RecurringBilling (MySQL).');
    }
    $this->b98 = $this->seededTenant();
    $this->b99 = $this->seededSupportClientTenant();
    config(['recurringbilling.cobrar_pix_automatico' => true]);
});

function mpaCred(int $biz): void
{
    PaymentGatewayCredential::query()->create(['business_id' => $biz, 'gateway_key' => 'bcb_pix', 'ambiente' => 'sandbox',
        'ativo' => true, 'nome_display' => 'BCB '.$biz,
        'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'base_url' => 'https://psp.fake.local']]);
}

function mpaAssinatura(int $biz): int
{
    $owner = (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $contato = DB::table('contacts')->insertGetId(['business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente '.uniqid(),
        'mobile' => '', 'contact_id' => 'M-'.uniqid(), 'tax_number' => '1234567'.random_int(1000, 9999),
        'created_by' => $owner, 'created_at' => now(), 'updated_at' => now()]);
    $plano = DB::table('rb_plans')->insertGetId(['business_id' => $biz, 'name' => 'P '.uniqid(), 'slug' => 'p-'.uniqid(),
        'valor' => '106.07', 'ciclo' => 'monthly', 'created_at' => now(), 'updated_at' => now()]);

    return (int) DB::table('rb_subscriptions')->insertGetId(['business_id' => $biz, 'plan_id' => $plano, 'contact_id' => $contato,
        'status' => 'active', 'start_date' => '2031-03-01', 'next_due_date' => '2031-04-05', 'billing_anchor_date' => '2031-04-05',
        'metadata' => json_encode([]), 'created_at' => now(), 'updated_at' => now()]);
}

function mpaBanco(): void
{
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/locrec' => Http::response(['id' => 12069, 'location' => 'pix.example.com/qr/v2/rec/abc'], 201),
        '*/v2/rec' => Http::response(['idRec' => 'RN123456782024011577825445612', 'status' => 'CRIADA'], 201),
        '*/v2/rec/*' => Http::response(['idRec' => 'RN123456782024011577825445612', 'status' => 'CRIADA',
            'dadosQR' => ['jornada' => 'JORNADA_2', 'pixCopiaECola' => '00020126180014br.gov.bcb.pix-QR-TESTE']], 200),
    ]);
}

it('UC-RBPA-11 · pedir a autorização cria location e recorrência com o valor do plano e grava o QR no mandato', function () {
    mpaCred($this->b98->id);
    $sub = mpaAssinatura($this->b98->id);
    mpaBanco();

    $r = app(MandatoPixAutomatico::class)->solicitar($this->b98->id, $sub);

    expect($r)->toBe(['id_rec' => 'RN123456782024011577825445612', 'status' => 'CRIADA',
        'pix_copia_cola' => '00020126180014br.gov.bcb.pix-QR-TESTE']);
    $mandato = json_decode((string) DB::table('rb_subscriptions')->where('id', $sub)->value('metadata'), true)['pix_automatico'];
    expect($mandato['id_rec'])->toBe('RN123456782024011577825445612')
        ->and($mandato['status'])->toBe('CRIADA')
        ->and($mandato['valor_centavos'])->toBe(10607);                       // caminho 1: o que ficou gravado
    $doc = DB::table('contacts')->where('id', DB::table('rb_subscriptions')->where('id', $sub)->value('contact_id'))->value('tax_number');
    Http::assertSent(fn ($req) => $req->method() === 'POST' && str_ends_with($req->url(), '/v2/rec')  // caminho 2: o que foi ao banco
        && $req['loc'] === 12069
        && $req['valor'] === ['valorRec' => '106.07']
        && $req['calendario']['periodicidade'] === 'MENSAL'
        && $req['calendario']['dataInicial'] === '2031-04-05'
        && $req['vinculo']['devedor'] === ['cpf' => $doc]
        && $req['vinculo']['contrato'] === "RB-SUB-{$sub}");
});

it('UC-RBPA-12 · recorrência já pedida não é recriada: devolve o mesmo QR sem chamar o banco', function () {
    mpaCred($this->b98->id);
    $sub = mpaAssinatura($this->b98->id);
    mpaBanco();
    $s = app(MandatoPixAutomatico::class);
    $primeiro = $s->solicitar($this->b98->id, $sub);
    Http::fake(); // de novo: nada deve ir ao banco

    expect($s->solicitar($this->b98->id, $sub))->toBe($primeiro);
    Http::assertNothingSent();
});

it('UC-RBPA-13 · pela rota: flag desligada 404, outra empresa 404, sem credencial 422; com tudo certo devolve o QR', function () {
    $sub98 = mpaAssinatura($this->b98->id);
    $sub99 = mpaAssinatura($this->b99->id);
    $user = $this->usuarioComPermissoes(['recurringbilling.subscriptions.manage'], $this->b98);
    $post = fn (int $id) => $this->actingAs($user)
        ->withSession(['user.business_id' => $this->b98->id, 'user.id' => $user->id, 'business.id' => $this->b98->id])
        ->postJson("/recurring-billing/{$id}/pix-automatico");
    mpaBanco();

    config(['recurringbilling.cobrar_pix_automatico' => false]);
    $post($sub98)->assertNotFound();
    config(['recurringbilling.cobrar_pix_automatico' => true]);

    $post($sub99)->assertNotFound();                       // Tier 0
    $post($sub98)->assertStatus(422)->assertJsonPath('ok', false); // sem credencial bcb_pix

    mpaCred($this->b98->id);
    $post($sub98)->assertOk()->assertJsonPath('mandato.pix_copia_cola', '00020126180014br.gov.bcb.pix-QR-TESTE');
    expect(json_decode((string) DB::table('rb_subscriptions')->where('id', $sub99)->value('metadata'), true))->toBe([]);
});

it('UC-RBPA-14 · o drawer só recebe o bloco com o recurso ligado, e o QR só enquanto aguarda o cliente', function () {
    $sub = mpaAssinatura($this->b98->id);
    $carregar = fn () => \Modules\RecurringBilling\Models\Subscription::withoutGlobalScopes()->with(['plan', 'contact'])->findOrFail($sub);

    config(['recurringbilling.cobrar_pix_automatico' => false]);
    expect(\Modules\RecurringBilling\Http\Presenters\SubscriptionIndexPresenter::toDrawerPayload($carregar())['pix_automatico'])->toBeNull();

    config(['recurringbilling.cobrar_pix_automatico' => true]);
    expect(\Modules\RecurringBilling\Http\Presenters\SubscriptionIndexPresenter::toDrawerPayload($carregar())['pix_automatico'])
        ->toBe(['status' => null, 'pix_copia_cola' => null]);

    DB::table('rb_subscriptions')->where('id', $sub)->update(['metadata' => json_encode(['pix_automatico' => [
        'status' => 'CRIADA', 'id_rec' => 'RR-x', 'pix_copia_cola' => 'QR-CRIADA']])]);
    expect(\Modules\RecurringBilling\Http\Presenters\SubscriptionIndexPresenter::toDrawerPayload($carregar())['pix_automatico'])
        ->toBe(['status' => 'CRIADA', 'pix_copia_cola' => 'QR-CRIADA']);

    DB::table('rb_subscriptions')->where('id', $sub)->update(['metadata' => json_encode(['pix_automatico' => [
        'status' => 'APROVADA', 'id_rec' => 'RR-x', 'pix_copia_cola' => 'QR-VELHO']])]);
    expect(\Modules\RecurringBilling\Http\Presenters\SubscriptionIndexPresenter::toDrawerPayload($carregar())['pix_automatico'])
        ->toBe(['status' => 'APROVADA', 'pix_copia_cola' => null]);
});
