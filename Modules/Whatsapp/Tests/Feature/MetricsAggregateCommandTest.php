<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ConversationMetric;

uses(Tests\TestCase::class);

/**
 * MetricsAggregateCommand — US-WA-021/041 (CYCLE-07 PR-3).
 *
 * Cobre:
 *   - counts (inbound/outbound/opened/resolved) corretos pra 1 dia
 *   - avg_first_response_seconds calculado corretamente
 *   - cross-tenant biz=99 isolado de biz=1
 *   - idempotência (2 rodadas mesmo dia = UPSERT, não duplica)
 *   - --date=YYYY-MM-DD específica funciona
 *
 * @see Modules\Whatsapp\Console\Commands\MetricsAggregateCommand
 * @see Modules\Whatsapp\Services\Metrics\MetricsAggregator
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['whatsapp_conversation_metricas', 'messages', 'conversations', 'channels'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('channels', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('channel_uuid')->unique();
        $table->string('label', 80);
        $table->string('type', 30);
        $table->string('status', 20)->default('setup');
        $table->timestamps();
    });

    Schema::create('conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('customer_external_id', 150);
        $table->string('status', 20)->default('open');
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 30)->default('test');
        $table->string('type', 20)->default('text');
        $table->text('body')->nullable();
        $table->string('status', 20)->default('sent');
        $table->string('sender_kind', 20)->nullable();
        $table->unsignedInteger('cost_centavos')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    Schema::create('whatsapp_conversation_metricas', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id');
        $table->date('metric_date');
        $table->unsignedBigInteger('channel_id')->nullable();
        $table->unsignedInteger('conversations_opened')->default(0);
        $table->unsignedInteger('conversations_resolved')->default(0);
        $table->unsignedInteger('messages_inbound')->default(0);
        $table->unsignedInteger('messages_outbound')->default(0);
        $table->unsignedInteger('avg_first_response_seconds')->nullable();
        $table->unsignedInteger('avg_resolution_seconds')->nullable();
        $table->unsignedBigInteger('total_cost_centavos')->default(0);
        $table->timestamps();
        $table->unique(
            ['business_id', 'metric_date', 'channel_id'],
            'wa_metrics_uniq',
        );
    });
});

function mamMakeChannel(int $businessId, string $label = 'Suporte'): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => 'mam-' . $businessId . '-' . uniqid(),
        'label' => $label,
        'type' => 'whatsapp_baileys',
        'status' => 'active',
    ]);
}

function mamMakeConversation(
    int $businessId,
    int $channelId,
    Carbon $createdAt,
    string $status = 'open',
    ?Carbon $updatedAt = null,
): int {
    return DB::table('conversations')->insertGetId([
        'business_id' => $businessId,
        'channel_id' => $channelId,
        'customer_external_id' => '5548' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
        'status' => $status,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt ?? $createdAt,
    ]);
}

function mamMakeMessage(
    int $businessId,
    int $conversationId,
    string $direction,
    Carbon $createdAt,
    ?string $senderKind = null,
    ?int $costCentavos = null,
): void {
    DB::table('messages')->insert([
        'business_id' => $businessId,
        'conversation_id' => $conversationId,
        'direction' => $direction,
        'provider' => 'test',
        'type' => 'text',
        'status' => $direction === 'inbound' ? 'received' : 'sent',
        'sender_kind' => $senderKind,
        'cost_centavos' => $costCentavos,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

it('R-WA-METRICS-001 — counts corretos: 3 inbound + 2 outbound em 1 dia', function () {
    $date = Carbon::yesterday()->startOfDay();
    $ch = mamMakeChannel(1, 'Vendas');
    $conv = mamMakeConversation(1, $ch->id, $date->copy()->addHours(8));

    // 3 inbound
    mamMakeMessage(1, $conv, 'inbound', $date->copy()->addHours(8));
    mamMakeMessage(1, $conv, 'inbound', $date->copy()->addHours(9));
    mamMakeMessage(1, $conv, 'inbound', $date->copy()->addHours(10));

    // 2 outbound (1 humano + 1 bot)
    mamMakeMessage(1, $conv, 'outbound', $date->copy()->addHours(8)->addMinutes(5), null, 12);
    mamMakeMessage(1, $conv, 'outbound', $date->copy()->addHours(11), 'bot', 8);

    $exit = Artisan::call('whatsapp:metrics-aggregate', [
        '--business' => '1',
        '--date' => $date->toDateString(),
    ]);

    expect($exit)->toBe(0);

    $row = ConversationMetric::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->whereNull('channel_id')
        ->where('metric_date', $date->toDateString())
        ->first();

    expect($row)->not->toBeNull();
    expect($row->conversations_opened)->toBe(1);
    expect($row->messages_inbound)->toBe(3);
    expect($row->messages_outbound)->toBe(2);
    expect($row->total_cost_centavos)->toBe(20);
});

it('R-WA-METRICS-002 — avg_first_response_seconds calcula corretamente', function () {
    $date = Carbon::yesterday()->startOfDay();
    $ch = mamMakeChannel(1);

    // Conv 1: criada 8h, resposta humana 8h05 → 300s
    $conv1Start = $date->copy()->addHours(8);
    $conv1 = mamMakeConversation(1, $ch->id, $conv1Start);
    mamMakeMessage(1, $conv1, 'inbound', $conv1Start);
    mamMakeMessage(1, $conv1, 'outbound', $conv1Start->copy()->addMinutes(5), null);

    // Conv 2: criada 10h, resposta humana 10h10 → 600s
    $conv2Start = $date->copy()->addHours(10);
    $conv2 = mamMakeConversation(1, $ch->id, $conv2Start);
    mamMakeMessage(1, $conv2, 'inbound', $conv2Start);
    mamMakeMessage(1, $conv2, 'outbound', $conv2Start->copy()->addMinutes(10), null);

    // Conv 3: só bot respondeu — não conta (sender_kind=bot ignorado)
    $conv3Start = $date->copy()->addHours(11);
    $conv3 = mamMakeConversation(1, $ch->id, $conv3Start);
    mamMakeMessage(1, $conv3, 'inbound', $conv3Start);
    mamMakeMessage(1, $conv3, 'outbound', $conv3Start->copy()->addMinutes(1), 'bot');

    Artisan::call('whatsapp:metrics-aggregate', [
        '--business' => '1',
        '--date' => $date->toDateString(),
    ]);

    $row = ConversationMetric::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->whereNull('channel_id')
        ->first();

    expect($row)->not->toBeNull();
    // Média de 300s + 600s = 450s (conv3 só tem bot, ignora)
    expect($row->avg_first_response_seconds)->toBe(450);
});

it('R-WA-METRICS-003 — cross-tenant biz=99 isolado de biz=1', function () {
    $date = Carbon::yesterday()->startOfDay();
    $ch1 = mamMakeChannel(1, 'Biz1');
    $ch99 = mamMakeChannel(99, 'Biz99');

    $conv1 = mamMakeConversation(1, $ch1->id, $date->copy()->addHours(8));
    mamMakeMessage(1, $conv1, 'inbound', $date->copy()->addHours(8));
    mamMakeMessage(1, $conv1, 'inbound', $date->copy()->addHours(9));

    $conv99 = mamMakeConversation(99, $ch99->id, $date->copy()->addHours(10));
    mamMakeMessage(99, $conv99, 'inbound', $date->copy()->addHours(10));
    mamMakeMessage(99, $conv99, 'inbound', $date->copy()->addHours(11));
    mamMakeMessage(99, $conv99, 'inbound', $date->copy()->addHours(12));

    // Roda só biz=1
    Artisan::call('whatsapp:metrics-aggregate', [
        '--business' => '1',
        '--date' => $date->toDateString(),
    ]);

    $biz1Row = ConversationMetric::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->whereNull('channel_id')
        ->first();

    $biz99Row = ConversationMetric::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 99)
        ->whereNull('channel_id')
        ->first();

    expect($biz1Row)->not->toBeNull();
    expect($biz1Row->messages_inbound)->toBe(2);
    expect($biz99Row)->toBeNull(); // biz=99 não foi processado
});

it('R-WA-METRICS-004 — idempotente: 2 runs mesmo dia não duplicam', function () {
    $date = Carbon::yesterday()->startOfDay();
    $ch = mamMakeChannel(1);
    $conv = mamMakeConversation(1, $ch->id, $date->copy()->addHours(8));
    mamMakeMessage(1, $conv, 'inbound', $date->copy()->addHours(8));

    // 1ª rodada
    Artisan::call('whatsapp:metrics-aggregate', [
        '--business' => '1',
        '--date' => $date->toDateString(),
    ]);

    $countAfter1 = ConversationMetric::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->count();

    // 2ª rodada — não deve duplicar
    Artisan::call('whatsapp:metrics-aggregate', [
        '--business' => '1',
        '--date' => $date->toDateString(),
    ]);

    $countAfter2 = ConversationMetric::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->count();

    expect($countAfter2)->toBe($countAfter1);
    // 1 agregada (channel_id=null) + 1 per-canal = 2 rows totais
    expect($countAfter2)->toBe(2);
});

it('R-WA-METRICS-005 — --date=YYYY-MM-DD específica funciona', function () {
    $targetDate = Carbon::parse('2026-05-01')->startOfDay();
    $otherDate = Carbon::parse('2026-05-02')->startOfDay();

    $ch = mamMakeChannel(1);
    $convTarget = mamMakeConversation(1, $ch->id, $targetDate->copy()->addHours(10));
    mamMakeMessage(1, $convTarget, 'inbound', $targetDate->copy()->addHours(10));

    $convOther = mamMakeConversation(1, $ch->id, $otherDate->copy()->addHours(10));
    mamMakeMessage(1, $convOther, 'inbound', $otherDate->copy()->addHours(10));
    mamMakeMessage(1, $convOther, 'inbound', $otherDate->copy()->addHours(11));

    Artisan::call('whatsapp:metrics-aggregate', [
        '--business' => '1',
        '--date' => $targetDate->toDateString(),
    ]);

    $targetRow = ConversationMetric::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('metric_date', $targetDate->toDateString())
        ->whereNull('channel_id')
        ->first();

    $otherRow = ConversationMetric::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('metric_date', $otherDate->toDateString())
        ->first();

    expect($targetRow)->not->toBeNull();
    expect($targetRow->messages_inbound)->toBe(1);
    expect($otherRow)->toBeNull(); // outra data não processada
});

it('R-WA-METRICS-006 — --date inválido retorna FAILURE', function () {
    $exit = Artisan::call('whatsapp:metrics-aggregate', [
        '--business' => '1',
        '--date' => 'data-invalida',
    ]);

    expect($exit)->toBe(1);
});
