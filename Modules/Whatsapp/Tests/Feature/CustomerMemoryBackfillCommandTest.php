<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Jobs\RebuildCustomerMemoryJob;

uses(Tests\TestCase::class);

/**
 * US-WA-VOZ-001 — CustomerMemoryBackfillCommand.
 *
 * Cobertura:
 *   1. --business obrigatório (Tier 0) → INVALID
 *   2. --dry-run NÃO grava customer_memory
 *   3. --queue dispatcha N jobs com biz correto
 *   4. Tier 0: --business=99 só vê convs biz=99
 *   5. --channel filtra por channel_id
 */
beforeEach(function () {
    foreach (['customer_memory', 'messages', 'conversations', 'contacts'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('contacts', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('name', 120)->nullable();
        $table->string('mobile', 40)->nullable();
        $table->string('landline', 40)->nullable();
        $table->string('alternate_number', 40)->nullable();
        $table->boolean('whatsapp_consent')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('customer_external_id', 40)->nullable();
        $table->timestamps();
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 20);
        $table->string('type', 20)->default('text');
        $table->text('body')->nullable();
        $table->boolean('is_internal_note')->default(false);
        $table->timestamps();
    });

    Schema::create('customer_memory', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('customer_external_id', 40);
        $table->string('phone_normalized', 20)->nullable();
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('identity_match_method', 24)->nullable();
        $table->decimal('identity_match_confidence', 3, 2)->nullable();
        $table->timestamp('identity_match_at')->nullable();
        $table->string('display_name', 120)->nullable();
        $table->unsignedInteger('n_conversations')->default(0);
        $table->unsignedInteger('n_msgs_inbound')->default(0);
        $table->unsignedInteger('n_msgs_outbound')->default(0);
        $table->timestamp('first_interaction_at')->nullable();
        $table->timestamp('last_interaction_at')->nullable();
        $table->json('temas_recorrentes')->nullable();
        $table->decimal('sentimento_score', 3, 2)->nullable();
        $table->decimal('churn_risk_score', 3, 2)->nullable();
        $table->json('comunicacao_preferida')->nullable();
        $table->text('notas_jana')->nullable();
        $table->timestamp('notas_atualizada_em')->nullable();
        $table->json('flags')->nullable();
        $table->string('consent_status', 16)->nullable();
        $table->timestamp('erasure_requested_at')->nullable();
        $table->timestamp('last_rebuilt_at')->nullable();
        $table->string('rebuilt_via', 24)->nullable();
        $table->timestamps();
        $table->unique(['business_id', 'customer_external_id']);
    });
});

function backfillSeedConv(int $bizId, string $extId, int $channelId = 10): int
{
    return DB::table('conversations')->insertGetId([
        'business_id' => $bizId,
        'channel_id' => $channelId,
        'customer_external_id' => $extId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('sem --business retorna INVALID', function () {
    $this->artisan('customer-memory:backfill')
        ->expectsOutputToContain('--business=N obrigatório')
        ->assertExitCode(2); // Command::INVALID
});

it('--dry-run NÃO grava customer_memory', function () {
    backfillSeedConv(1, '5548999872822');
    backfillSeedConv(1, '5511987654321');

    $this->artisan('customer-memory:backfill', ['--business' => 1, '--dry-run' => true])
        ->expectsOutputToContain('Customers únicos elegíveis biz=1')
        ->expectsOutputToContain('DRY-RUN')
        ->assertExitCode(0);

    expect(DB::table('customer_memory')->count())->toBe(0);
});

it('--queue dispatcha N jobs com biz correto', function () {
    backfillSeedConv(1, '5548999872822');
    backfillSeedConv(1, '5511987654321');
    backfillSeedConv(1, '5527998765432');
    Bus::fake();

    $this->artisan('customer-memory:backfill', ['--business' => 1, '--queue' => true])
        ->assertExitCode(0);

    Bus::assertDispatchedTimes(RebuildCustomerMemoryJob::class, 3);
    Bus::assertDispatched(RebuildCustomerMemoryJob::class, function ($job) {
        return $job->businessId === 1;
    });
});

it('Tier 0: --business=99 só vê convs biz=99', function () {
    backfillSeedConv(1, '5548999872822');
    backfillSeedConv(1, '5511987654321');
    backfillSeedConv(99, '5527998765432');
    Bus::fake();

    $this->artisan('customer-memory:backfill', ['--business' => 99, '--queue' => true])
        ->assertExitCode(0);

    Bus::assertDispatchedTimes(RebuildCustomerMemoryJob::class, 1);
    Bus::assertDispatched(RebuildCustomerMemoryJob::class, function ($job) {
        return $job->businessId === 99;
    });
});

it('--channel filtra por channel_id', function () {
    backfillSeedConv(1, '5548999872822', channelId: 10);
    backfillSeedConv(1, '5511987654321', channelId: 8);
    backfillSeedConv(1, '5527998765432', channelId: 10);
    Bus::fake();

    $this->artisan('customer-memory:backfill', ['--business' => 1, '--channel' => 10, '--queue' => true])
        ->assertExitCode(0);

    // Só 2 convs do channel=10
    Bus::assertDispatchedTimes(RebuildCustomerMemoryJob::class, 2);
});

it('DISTINCT customer_external_id — não duplica jobs por mesma pessoa', function () {
    // 3 convs do mesmo customer (cliente abriu 3 sessões diferentes)
    backfillSeedConv(1, '5548999872822');
    backfillSeedConv(1, '5548999872822');
    backfillSeedConv(1, '5548999872822');
    backfillSeedConv(1, '5511987654321');
    Bus::fake();

    $this->artisan('customer-memory:backfill', ['--business' => 1, '--queue' => true])
        ->assertExitCode(0);

    // Só 2 jobs (DISTINCT external_id)
    Bus::assertDispatchedTimes(RebuildCustomerMemoryJob::class, 2);
});
