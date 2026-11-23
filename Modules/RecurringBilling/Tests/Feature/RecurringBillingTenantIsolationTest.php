<?php

declare(strict_types=1);

/**
 * Isolamento multi-tenant Tier 0 do RecurringBilling PELA ROTA (ADR 0093 · auditoria RB 2026-11-19, d12).
 *
 * Até aqui os testes de isolamento do módulo chamavam o controller direto, sem rota nem
 * middleware (`makeControllerSkippingAuth()` no Wave6). Nada provava o 404 pela URL com a sessão
 * de outra empresa. Este arquivo faz a requisição HTTP com a pilha inteira de middleware do grupo
 * `/recurring-billing` (inclusive o gate de pacote `RecurringBillingLiberado`, #11247).
 *
 * Forma de cada caso, sempre no MESMO registro:
 *   1. a empresa 99 (atacante) usa o id de um registro da 98 → 404/403;
 *   2. o registro da 98 continua igual no banco;
 *   3. CONTROLE POSITIVO — a empresa 98, dona do registro, faz a MESMA chamada e ela passa.
 * Sem o 3 o caso fica verde até se a sessão der 403 em tudo (falso-verde Tier 0).
 *
 * Os dois usuários têm as MESMAS permissões, as que cada rota exige (DataController do módulo,
 * permissões do #11247), e o pacote `recurringbilling_module` está ligado para as duas empresas.
 * Assim o 404 da 99 só pode vir da empresa, não de permissão ou pacote faltando.
 *
 * Tenants fictícios 98 × 99 (ADR 0358). Nunca biz=4. ⚠️ Fora do MySQL pula: leia as assertions (LC-13).
 */

use App\Http\Middleware\HandleInertiaRequests;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\RecurringBilling\Http\Middleware\RecurringBillingLiberado;

uses(Tests\TestCase::class, DatabaseTransactions::class);

const RB_ISO_PERMISSOES = [
    'recurringbilling.access',
    'recurringbilling.notes.write',
    'recurringbilling.favorites.write',
    'recurringbilling.subscriptions.manage',
    'recurringbilling.invoices.cancel',
    'recurringbilling.plans.manage',
];

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Pilha completa de middleware e schema real do RecurringBilling (MySQL).');
    }

    $this->b98 = $this->seededTenant();
    $this->b99 = $this->seededSupportClientTenant();

    // Pacote ligado para as duas empresas: o 404 da 99 não pode vir do gate de pacote.
    $this->partialMock(ModuleUtil::class)
        ->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $permissao = null, ...$resto) => true);

    $this->u98 = $this->usuarioComPermissoes(RB_ISO_PERMISSOES, $this->b98);
    $this->u99 = $this->usuarioComPermissoes(RB_ISO_PERMISSOES, $this->b99);

    // Sessão como o SetSessionData deixa: business_id do usuário e da empresa.
    $this->como = fn (\App\User $u) => $this->actingAs($u)->withSession([
        'user.business_id' => $u->business_id,
        'user.id'          => $u->id,
        'business.id'      => $u->business_id,
    ]);
});

function rbIsoAssinatura(int $biz, string $status = 'active'): int
{
    $owner = (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $contato = DB::table('contacts')->insertGetId(['business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente '.uniqid(),
        'mobile' => '', 'contact_id' => 'ISO-'.uniqid(), 'created_by' => $owner, 'created_at' => now(), 'updated_at' => now()]);
    $plano = rbIsoPlano($biz);

    return (int) DB::table('rb_subscriptions')->insertGetId(['business_id' => $biz, 'plan_id' => $plano, 'contact_id' => $contato,
        'status' => $status, 'start_date' => '2031-03-01', 'next_due_date' => '2031-04-05', 'billing_anchor_date' => '2031-04-05',
        'metadata' => json_encode([]), 'created_at' => now(), 'updated_at' => now()]);
}

function rbIsoPlano(int $biz): int
{
    return (int) DB::table('rb_plans')->insertGetId(['business_id' => $biz, 'name' => 'Plano '.uniqid(), 'slug' => 'p-'.uniqid(),
        'valor' => '50.00', 'ciclo' => 'monthly', 'created_at' => now(), 'updated_at' => now()]);
}

function rbIsoFatura(int $biz, int $sub, string $status = 'open'): int
{
    return (int) DB::table('rb_invoices')->insertGetId(['business_id' => $biz, 'subscription_id' => $sub,
        'contact_id' => DB::table('rb_subscriptions')->where('id', $sub)->value('contact_id'),
        'numero_documento' => 'ISO-'.uniqid(), 'valor' => '50.00', 'status' => $status, 'vencimento' => '2031-04-05',
        'created_at' => now(), 'updated_at' => now()]);
}

function rbIsoNota(int $biz, int $sub, int $autor): int
{
    return (int) DB::table('rb_subscription_notes')->insertGetId(['business_id' => $biz, 'subscription_id' => $sub,
        'user_id' => $autor, 'body' => 'nota da '.$biz, 'is_pinned' => false, 'created_at' => now(), 'updated_at' => now()]);
}

it('notas · a 99 não apaga nem fixa nota da 98; a 98 fixa e apaga a mesma nota', function () {
    $sub = rbIsoAssinatura($this->b98->id);
    $nota = rbIsoNota($this->b98->id, $sub, $this->u98->id);

    ($this->como)($this->u99)->postJson("/recurring-billing/{$sub}/notes/{$nota}/pin")->assertNotFound();
    ($this->como)($this->u99)->deleteJson("/recurring-billing/{$sub}/notes/{$nota}")->assertNotFound();
    $linha = DB::table('rb_subscription_notes')->where('id', $nota)->first();
    expect($linha->deleted_at)->toBeNull()
        ->and((int) $linha->is_pinned)->toBe(0)
        ->and($linha->body)->toBe('nota da '.$this->b98->id);

    ($this->como)($this->u98)->postJson("/recurring-billing/{$sub}/notes/{$nota}/pin")
        ->assertOk()->assertJsonPath('is_pinned', true);
    ($this->como)($this->u98)->deleteJson("/recurring-billing/{$sub}/notes/{$nota}")
        ->assertOk()->assertJsonPath('ok', true);
    expect(DB::table('rb_subscription_notes')->where('id', $nota)->value('deleted_at'))->not->toBeNull();
});

it('favorito · a 99 não favorita assinatura da 98; a 98 favorita a mesma', function () {
    $sub = rbIsoAssinatura($this->b98->id);

    ($this->como)($this->u99)->postJson("/recurring-billing/{$sub}/favorite")->assertNotFound();
    expect(DB::table('rb_subscription_favorites')->where('subscription_id', $sub)->count())->toBe(0);

    ($this->como)($this->u98)->postJson("/recurring-billing/{$sub}/favorite")
        ->assertOk()->assertJsonPath('is_favorite', true);
    expect(DB::table('rb_subscription_favorites')->where('subscription_id', $sub)->where('user_id', $this->u98->id)->count())->toBe(1);
});

it('eventos · a 99 não lê nem escreve na linha do tempo da 98; a 98 lê e escreve na mesma', function () {
    $sub = rbIsoAssinatura($this->b98->id);
    DB::table('rb_subscription_events')->insert(['business_id' => $this->b98->id, 'subscription_id' => $sub,
        'kind' => 'event-create', 'by_actor' => 'sistema', 'body' => 'evento da 98', 'occurred_at' => now(),
        'created_at' => now(), 'updated_at' => now()]);

    ($this->como)($this->u99)->getJson("/recurring-billing/{$sub}/events")->assertNotFound();
    ($this->como)($this->u99)->postJson("/recurring-billing/{$sub}/events", ['body' => 'intruso'])->assertNotFound();
    expect(DB::table('rb_subscription_events')->where('subscription_id', $sub)->pluck('body')->all())->toBe(['evento da 98']);

    ($this->como)($this->u98)->getJson("/recurring-billing/{$sub}/events")
        ->assertOk()->assertJsonPath('events.0.body', 'evento da 98');
    ($this->como)($this->u98)->postJson("/recurring-billing/{$sub}/events", ['body' => 'nota da dona'])
        ->assertStatus(201)->assertJsonPath('event.body', 'nota da dona');
    expect(DB::table('rb_subscription_events')->where('subscription_id', $sub)->count())->toBe(2);
});

it('reenviar-nfe · a 99 recebe 404 na assinatura da 98; a 98 passa da checagem de empresa', function () {
    $sub = rbIsoAssinatura($this->b98->id);

    ($this->como)($this->u99)->postJson("/recurring-billing/{$sub}/reenviar-nfe")->assertNotFound();

    // A dona chega até a regra de negócio: sem fatura paga, 422 com o motivo. O 404 acima
    // portanto não é de rota inexistente nem de permissão.
    ($this->como)($this->u98)->postJson("/recurring-billing/{$sub}/reenviar-nfe")
        ->assertStatus(422)->assertJsonPath('error', 'sem_invoice_paga');
});

it('cancelar fatura · a 99 não cancela fatura da 98; a 98 cancela a mesma', function () {
    $sub = rbIsoAssinatura($this->b98->id);
    $fatura = rbIsoFatura($this->b98->id, $sub);

    ($this->como)($this->u99)->postJson("/financeiro/rb-invoices/{$fatura}/cancelar", ['motivo' => 'ACERTOS'])
        ->assertNotFound();
    expect(DB::table('rb_invoices')->where('id', $fatura)->value('status'))->toBe('open');

    ($this->como)($this->u98)->postJson("/financeiro/rb-invoices/{$fatura}/cancelar", ['motivo' => 'ACERTOS'])
        ->assertOk()->assertJsonPath('ok', true);
    expect(DB::table('rb_invoices')->where('id', $fatura)->value('status'))->toBe('canceled');
});

it('plano · a 99 não edita nem apaga plano da 98; a 98 edita e apaga o mesmo', function () {
    $plano = rbIsoPlano($this->b98->id);
    $nomeAntes = DB::table('rb_plans')->where('id', $plano)->value('name');
    $payload = ['name' => 'Plano renomeado', 'slug' => 'plano-renomeado-'.$plano, 'valor' => '50.00',
        'ciclo' => 'monthly', 'fiscal_type' => 'none'];

    ($this->como)($this->u99)->put("/recurring-billing/planos/{$plano}", $payload)->assertNotFound();
    ($this->como)($this->u99)->delete("/recurring-billing/planos/{$plano}")->assertNotFound();
    $linha = DB::table('rb_plans')->where('id', $plano)->first();
    expect($linha->name)->toBe($nomeAntes)->and($linha->deleted_at)->toBeNull();

    ($this->como)($this->u98)->put("/recurring-billing/planos/{$plano}", $payload)
        ->assertRedirect(route('recurring-billing.planos.index'));
    expect(DB::table('rb_plans')->where('id', $plano)->value('name'))->toBe('Plano renomeado');

    ($this->como)($this->u98)->delete("/recurring-billing/planos/{$plano}")
        ->assertRedirect(route('recurring-billing.planos.index'));
    expect(DB::table('rb_plans')->where('id', $plano)->value('deleted_at'))->not->toBeNull();
});

it('pausar e reativar · a 99 não muda o status de assinatura da 98; a 98 pausa e reativa a mesma', function () {
    $sub = rbIsoAssinatura($this->b98->id, 'active');

    ($this->como)($this->u99)->postJson("/recurring-billing/{$sub}/pausar")->assertNotFound();
    expect(DB::table('rb_subscriptions')->where('id', $sub)->value('status'))->toBe('active');

    ($this->como)($this->u98)->postJson("/recurring-billing/{$sub}/pausar")->assertOk()->assertJsonPath('ok', true);
    expect(DB::table('rb_subscriptions')->where('id', $sub)->value('status'))->toBe('paused');

    ($this->como)($this->u99)->postJson("/recurring-billing/{$sub}/reativar")->assertNotFound();
    expect(DB::table('rb_subscriptions')->where('id', $sub)->value('status'))->toBe('paused');

    ($this->como)($this->u98)->postJson("/recurring-billing/{$sub}/reativar")->assertOk()->assertJsonPath('ok', true);
    expect(DB::table('rb_subscriptions')->where('id', $sub)->value('status'))->toBe('active');
});

it('rb_charge_attempts · a lista da 99 não traz assinatura da 98 nem conta tentativa da 98; a da 98 conta as suas', function () {
    // 98: assinatura em atraso com 2 tentativas na última fatura.
    $sub98 = rbIsoAssinatura($this->b98->id, 'past_due');
    $fat98 = rbIsoFatura($this->b98->id, $sub98, 'overdue');
    // 99: assinatura em atraso com 1 tentativa própria.
    $sub99 = rbIsoAssinatura($this->b99->id, 'past_due');
    $fat99 = rbIsoFatura($this->b99->id, $sub99, 'overdue');

    $tentativa = fn (int $biz, int $fatura, int $n) => DB::table('rb_charge_attempts')->insert([
        'business_id' => $biz, 'invoice_id' => $fatura, 'gateway' => 'inter', 'attempt_n' => $n,
        'status' => 'failed', 'created_at' => now()]);
    $tentativa($this->b98->id, $fat98, 1);
    $tentativa($this->b98->id, $fat98, 2);
    $tentativa($this->b99->id, $fat99, 1);
    // Linha da 98 apontando para a fatura da 99: se o escopo de empresa falhar, a 99 passa
    // a ver 3 tentativas e a assinatura dela vira "falhou".
    $tentativa($this->b98->id, $fat99, 3);

    $lista = function (\App\User $u) {
        return ($this->como)($u)->withHeaders([
            'X-Requested-With'            => 'XMLHttpRequest',
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'RecurringBilling/Index',
            'X-Inertia-Partial-Data'      => 'subscriptions',
        ])->get('/recurring-billing')->assertOk()->json('props.subscriptions.data');
    };

    $da99 = collect($lista($this->u99))->keyBy('id');
    expect($da99->has($sub98))->toBeFalse()
        ->and($da99->get($sub99)['status'])->toBe('retentando')
        ->and($da99->get($sub99)['retry'])->toBe(1);

    $da98 = collect($lista($this->u98))->keyBy('id');
    expect($da98->has($sub99))->toBeFalse()
        ->and($da98->get($sub98)['status'])->toBe('retentando')
        ->and($da98->get($sub98)['retry'])->toBe(2);

    // Nada da 98 mudou com a leitura da 99.
    expect(DB::table('rb_charge_attempts')->where('business_id', $this->b98->id)->count())->toBe(3);
});
