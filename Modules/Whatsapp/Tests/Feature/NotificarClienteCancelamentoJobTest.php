<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Jobs\NotificarClienteCancelamentoJob;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;
use Modules\Whatsapp\Templates\CancelamentoVendaTemplate;

uses(Tests\TestCase::class);

/**
 * US-SELL-034 · NotificarClienteCancelamentoJob — best-effort WhatsApp ao
 * cancelar venda (CASCADE-NOTIFY-001).
 *
 * Cobre:
 * (1) contact com mobile + phone outbound_default → SendWhatsappMessageJob dispatch
 * (2) cross-tenant ($businessId != transaction.business_id) → log error + no dispatch
 * (3) transaction sem contact_id (walk-in) → log info + no dispatch
 * (4) contact sem mobile/landline → log info + no dispatch (TODO fallback email)
 * (5) corpo da mensagem contém invoice_no + motivo + business_name
 *
 * Padrão SQLite friendly (cria tabelas em beforeEach).
 */

beforeEach(function () {
    foreach ([
        'transactions',
        'contacts',
        'whatsapp_business_phones',
        'business',
    ] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('business', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('contacts', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('name', 191)->nullable();
        $table->string('mobile', 60)->nullable();
        $table->string('landline', 60)->nullable();
        $table->string('email', 191)->nullable();
        // LGPD consent (migration 2026_05_12_060001_add_consent_columns_to_contacts)
        $table->boolean('whatsapp_consent')->nullable();
        $table->boolean('email_consent')->nullable();
        $table->timestamp('consent_updated_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('transactions', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('contact_id')->nullable();
        $table->string('invoice_no', 191)->nullable();
        $table->decimal('final_total', 22, 4)->default(0);
        $table->date('transaction_date')->nullable();
        $table->timestamps();
    });

    Schema::create('whatsapp_business_phones', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('phone_uuid')->unique();
        $table->string('label', 80);
        $table->string('driver', 20)->default('zapi');
        $table->string('fallback_driver', 20)->default('meta_cloud');
        $table->string('display_phone', 20)->nullable();
        $table->string('meta_phone_number_id', 64)->nullable();
        $table->text('meta_access_token')->nullable();
        $table->text('meta_app_secret')->nullable();
        $table->string('meta_webhook_verify_token', 64)->nullable();
        $table->string('zapi_instance_id', 64)->nullable();
        $table->text('zapi_instance_token')->nullable();
        $table->text('zapi_client_token')->nullable();
        $table->string('baileys_instance_id', 64)->nullable();
        $table->string('baileys_phone_e164', 20)->nullable();
        $table->string('baileys_verified_name', 100)->nullable();
        $table->string('baileys_profile_pic_url', 255)->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->boolean('handles_repair_status')->default(false);
        $table->boolean('handles_billing')->default(false);
        $table->boolean('handles_jana_bot')->default(true);
        $table->boolean('handles_outbound_default')->default(false);
        $table->boolean('bot_enabled')->default(false);
        $table->string('driver_health', 20)->default('never_checked');
        $table->unsignedInteger('driver_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamps();
    });

    // Business fixture canônico
    DB::table('business')->insert([
        'id' => 1,
        'name' => 'Loja Teste LTDA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('business')->insert([
        'id' => 2,
        'name' => 'Outro Business',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

function makePhone(int $businessId = 1, bool $outboundDefault = true): WhatsappBusinessPhone
{
    return WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'zapi_instance_id' => 'inst-test',
        'zapi_instance_token' => 'tok-test',
        'handles_outbound_default' => $outboundDefault,
    ]);
}

it('1. dispatch SendWhatsappMessageJob quando contact tem mobile + phone outbound_default existe', function () {
    Bus::fake();

    $phone = makePhone(1, true);

    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'João Silva',
        'mobile' => '+5511987654321',
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => $contactId,
        'invoice_no' => 'INV-001',
        'final_total' => 199.90,
        'transaction_date' => '2026-05-10',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Erro de estoque'))->handle();

    Bus::assertDispatched(SendWhatsappMessageJob::class, function ($job) use ($phone) {
        return $job->businessId === 1
            && $job->whatsappBusinessPhoneId === $phone->id
            && $job->to === '+5511987654321'
            && $job->kind === 'freeform'
            && is_string($job->payload['body'])
            && str_contains($job->payload['body'], 'INV-001');
    });
});

it('2. cross-tenant: businessId != transaction.business_id loga error e não dispatch', function () {
    Bus::fake();

    makePhone(1, true);

    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 2,
        'name' => 'Alheio',
        'mobile' => '+5511999999999',
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 2,                 // ← business REAL = 2
        'contact_id' => $contactId,
        'invoice_no' => 'INV-X',
        'final_total' => 50.00,
        'transaction_date' => '2026-05-10',
    ]);

    // Caller "mentindo" — alega biz=1 mas transaction é biz=2
    (new NotificarClienteCancelamentoJob(1, $txId, 'Teste'))->handle();

    Bus::assertNothingDispatched();
});

it('3. transaction sem contact_id loga info e não dispatch (walk-in)', function () {
    Bus::fake();

    makePhone(1, true);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => null,
        'invoice_no' => 'INV-WALKIN',
        'final_total' => 10.00,
        'transaction_date' => '2026-05-10',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Walk-in'))->handle();

    Bus::assertNothingDispatched();
});

it('4. contact sem mobile nem landline + sem email → no dispatch + Mail vazio (notificação manual)', function () {
    Bus::fake();
    Mail::fake();

    makePhone(1, true);

    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Sem Fone',
        'mobile' => null,
        'landline' => null,
        'email' => null,
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => $contactId,
        'invoice_no' => 'INV-NO-CONTACT',
        'final_total' => 25.50,
        'transaction_date' => '2026-05-10',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Sem canal'))->handle();

    Bus::assertNothingDispatched();
    Mail::assertNothingSent();
});

it('5. mensagem contém invoice_no + motivo + business_name + primeiro nome do contact', function () {
    Bus::fake();

    makePhone(1, true);

    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Maria Aparecida da Silva',
        'mobile' => '+5548999990000',
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => $contactId,
        'invoice_no' => 'INV-777',
        'final_total' => 1234.56,
        'transaction_date' => '2026-05-10',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Pedido duplicado'))->handle();

    Bus::assertDispatched(SendWhatsappMessageJob::class, function ($job) {
        $body = $job->payload['body'] ?? '';
        return str_contains($body, 'INV-777')
            && str_contains($body, 'Pedido duplicado')
            && str_contains($body, 'Loja Teste LTDA')
            && str_contains($body, 'Maria')              // primeiro nome
            && str_contains($body, '1.234,56')           // valor PT-BR formatado
            && str_contains($body, '10/05/2026');        // data PT-BR
    });
});

it('6. fallback landline quando mobile vazio → dispatch usa landline', function () {
    Bus::fake();

    makePhone(1, true);

    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Só Fixo',
        'mobile' => null,
        'landline' => '+554833334444',
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => $contactId,
        'invoice_no' => 'INV-FIX',
        'final_total' => 99.00,
        'transaction_date' => '2026-05-10',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Teste fixo'))->handle();

    Bus::assertDispatched(SendWhatsappMessageJob::class, fn ($job) =>
        $job->to === '+554833334444'
    );
});

it('7. business sem phone outbound_default → no dispatch (silencioso)', function () {
    Bus::fake();

    // Phone existe mas sem outbound_default
    makePhone(1, false);

    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Cliente',
        'mobile' => '+5511900000000',
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => $contactId,
        'invoice_no' => 'INV-NO-PHONE-CFG',
        'final_total' => 10.00,
        'transaction_date' => '2026-05-10',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Sem outbound'))->handle();

    Bus::assertNothingDispatched();
});

// CASCADE-NOTIFY-002 — fallback email

it('8. fallback email: contact sem phone mas com email válido envia Mail::raw', function () {
    Bus::fake();
    Mail::fake();

    makePhone(1, true);

    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Carlos Email',
        'mobile' => null,
        'landline' => null,
        'email' => 'carlos@example.com',
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => $contactId,
        'invoice_no' => 'INV-EMAIL-001',
        'final_total' => 199.90,
        'transaction_date' => '2026-05-12',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Cliente arrependeu'))->handle();

    // WhatsApp não disparou (sem phone)
    Bus::assertNothingDispatched();
    // Mail::raw enviado pro email do contact
    Mail::assertSent(\Illuminate\Mail\SentMessage::class, fn ($m) => true);
});

it('9. sem phone E email inválido → Bus e Mail vazios (warning log)', function () {
    Bus::fake();
    Mail::fake();

    makePhone(1, true);

    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Email Inválido',
        'mobile' => null,
        'landline' => null,
        'email' => 'isso-nao-eh-email',
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => $contactId,
        'invoice_no' => 'INV-INVALID-EMAIL',
        'final_total' => 50.00,
        'transaction_date' => '2026-05-12',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Email inválido'))->handle();

    Bus::assertNothingDispatched();
    Mail::assertNothingSent();
});

it('10. contact com phone NÃO tenta email (WhatsApp tem prioridade)', function () {
    Bus::fake();
    Mail::fake();

    makePhone(1, true);

    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Phone + Email',
        'mobile' => '+5511988888888',
        'email' => 'ambos@example.com',
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => $contactId,
        'invoice_no' => 'INV-BOTH',
        'final_total' => 100.00,
        'transaction_date' => '2026-05-12',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Tem os dois'))->handle();

    Bus::assertDispatched(SendWhatsappMessageJob::class);
    Mail::assertNothingSent();
});

// US-LGPD — consent gates (migration 2026_05_12_060001)

it('11. whatsapp_consent=false bloqueia WhatsApp + tenta email fallback', function () {
    Bus::fake();
    Mail::fake();

    makePhone(1, true);

    // Contact com phone E email, mas opt-out WhatsApp explícito.
    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Recusou WhatsApp',
        'mobile' => '+5511977777777',
        'email' => 'fallback@example.com',
        'whatsapp_consent' => false,    // ← LGPD opt-out
        'email_consent' => null,        // legacy → permite
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => $contactId,
        'invoice_no' => 'INV-LGPD-WA',
        'final_total' => 75.00,
        'transaction_date' => '2026-05-12',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Consent off'))->handle();

    // WhatsApp bloqueado pelo consent
    Bus::assertNothingDispatched();
    // Email fallback enviado (consent NULL = permite)
    Mail::assertSent(\Illuminate\Mail\SentMessage::class, fn ($m) => true);
});

it('12. whatsapp_consent=false + email_consent=false não envia nada (Bus + Mail vazios)', function () {
    Bus::fake();
    Mail::fake();

    makePhone(1, true);

    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Recusou Tudo',
        'mobile' => '+5511966666666',
        'email' => 'nao-quero@example.com',
        'whatsapp_consent' => false,
        'email_consent' => false,
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => $contactId,
        'invoice_no' => 'INV-LGPD-FULL-OPT-OUT',
        'final_total' => 33.00,
        'transaction_date' => '2026-05-12',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Tudo off'))->handle();

    Bus::assertNothingDispatched();
    Mail::assertNothingSent();
});

it('13. consent NULL (legacy) permite WhatsApp (back-compat)', function () {
    Bus::fake();
    Mail::fake();

    makePhone(1, true);

    // Contact criado antes da migration consent — colunas ficam NULL.
    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Cliente Legacy',
        'mobile' => '+5511955555555',
        'email' => 'legacy@example.com',
        'whatsapp_consent' => null,     // ← NULL = pre-coluna
        'email_consent' => null,
    ]);

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => 1,
        'contact_id' => $contactId,
        'invoice_no' => 'INV-LGPD-LEGACY',
        'final_total' => 88.00,
        'transaction_date' => '2026-05-12',
    ]);

    (new NotificarClienteCancelamentoJob(1, $txId, 'Legacy NULL'))->handle();

    // NULL = permite → WhatsApp dispara normalmente, email não (phone tem prioridade)
    Bus::assertDispatched(SendWhatsappMessageJob::class);
    Mail::assertNothingSent();
});
