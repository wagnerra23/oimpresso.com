<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Jobs\AnalisarMensagemInboundJob;

uses(Tests\TestCase::class);

/**
 * US-WA-095 — Command whatsapp:analyze-backlog.
 *
 * Cobertura:
 *   1. Sem --business retorna INVALID
 *   2. --dry-run NÃO dispatcha mas mostra contagem + amostra
 *   3. Happy path dispatcha N jobs com biz correto
 *   4. Idempotência: msgs com analise_at IS NOT NULL são puladas por default
 *   5. Tier 0: --business=99 só vê msgs biz=99 (não vaza biz=1)
 */
beforeEach(function () {
    foreach (['messages', 'conversations'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->string('contact_name', 80)->nullable();
        $table->timestamps();
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 20);
        $table->string('provider', 20)->nullable();
        $table->string('type', 20)->default('text');
        $table->text('body')->nullable();
        $table->string('status', 20)->default('received');
        $table->boolean('is_internal_note')->default(false);
        $table->timestamp('analise_at')->nullable();
        $table->timestamps();
    });

    Config::set('whatsapp.analise.enabled', true);
});

function seedConv(int $bizId, int $channelId = 10): int
{
    return DB::table('conversations')->insertGetId([
        'business_id' => $bizId,
        'channel_id' => $channelId,
        'contact_name' => 'Cliente Teste',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedMsg(int $bizId, int $convId, array $over = []): int
{
    return DB::table('messages')->insertGetId(array_merge([
        'business_id' => $bizId,
        'conversation_id' => $convId,
        'direction' => 'inbound',
        'type' => 'text',
        'body' => 'mensagem teste cliente',
        'status' => 'received',
        'is_internal_note' => false,
        'analise_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $over));
}

it('sem --business retorna INVALID', function () {
    $this->artisan('whatsapp:analyze-backlog')
        ->expectsOutputToContain('--business=N obrigatório')
        ->assertExitCode(2); // Command::INVALID
});

it('--dry-run não dispatcha mas conta + mostra amostra', function () {
    $conv = seedConv(1);
    seedMsg(1, $conv, ['body' => 'reclamação atraso']);
    seedMsg(1, $conv, ['body' => 'pergunta de preço']);
    Bus::fake();

    $this->artisan('whatsapp:analyze-backlog', ['--business' => 1, '--dry-run' => true])
        ->expectsOutputToContain('Mensagens elegíveis biz=1')
        ->expectsOutputToContain('DRY-RUN')
        ->assertExitCode(0);

    Bus::assertNotDispatched(AnalisarMensagemInboundJob::class);
});

it('happy path dispatcha N jobs com biz correto', function () {
    $conv = seedConv(1);
    seedMsg(1, $conv);
    seedMsg(1, $conv);
    seedMsg(1, $conv);
    Bus::fake();

    $this->artisan('whatsapp:analyze-backlog', ['--business' => 1])
        ->assertExitCode(0);

    Bus::assertDispatchedTimes(AnalisarMensagemInboundJob::class, 3);
});

it('pula msgs com analise_at preenchido (idempotência)', function () {
    $conv = seedConv(1);
    seedMsg(1, $conv, ['analise_at' => now()]);
    seedMsg(1, $conv, ['analise_at' => null]);
    Bus::fake();

    $this->artisan('whatsapp:analyze-backlog', ['--business' => 1])
        ->assertExitCode(0);

    Bus::assertDispatchedTimes(AnalisarMensagemInboundJob::class, 1);
});

it('Tier 0: --business=99 NÃO vê msgs de biz=1', function () {
    $conv1 = seedConv(1);
    seedMsg(1, $conv1);
    seedMsg(1, $conv1);
    $conv99 = seedConv(99);
    seedMsg(99, $conv99);
    Bus::fake();

    $this->artisan('whatsapp:analyze-backlog', ['--business' => 99])
        ->assertExitCode(0);

    Bus::assertDispatchedTimes(AnalisarMensagemInboundJob::class, 1);
});

it('--include-analyzed reanalisa msgs já feitas', function () {
    $conv = seedConv(1);
    seedMsg(1, $conv, ['analise_at' => now()]);
    seedMsg(1, $conv, ['analise_at' => now()]);
    Bus::fake();

    $this->artisan('whatsapp:analyze-backlog', ['--business' => 1, '--include-analyzed' => true])
        ->assertExitCode(0);

    Bus::assertDispatchedTimes(AnalisarMensagemInboundJob::class, 2);
});
