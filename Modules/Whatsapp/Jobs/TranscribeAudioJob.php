<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Audio\Contracts\AudioTranscriber;

/**
 * US-WA-072 — Transcrição de áudio inbound via Whisper.
 *
 * Encadeado pelo `DownloadMediaJob` quando `type=audio`. Lê arquivo do disco
 * public, chama `WhisperTranscriber::transcribe()` e grava em
 * `messages.media_transcription`.
 *
 * Rate limit anti-abuse: 100min/business/dia. Cache key per-business per-day
 * incrementa a duração estimada (10s/áudio default — Baileys nem sempre
 * envia duration, assumimos 10s no pior caso conservador).
 *
 * Custo metering: Log estruturado com tag `whatsapp_audio` — facilita query
 * agregada pro Daily Brief. Não cria row em `mcp_usage_costs` ainda (US
 * separada — adiar até estabilizar).
 *
 * Multi-tenant Tier 0 (ADR 0093): `$businessId` no constructor.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-072
 */
class TranscribeAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function backoff(): array
    {
        return [60, 600];
    }

    public function __construct(
        public int $businessId,
        public int $messageId,
    ) {}

    public function handle(AudioTranscriber $transcriber): void
    {
        $message = Message::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->where('id', $this->messageId)
            ->first();

        if (! $message) {
            Log::warning('[transcribe_audio] message not found', [
                'business_id' => $this->businessId,
                'message_id' => $this->messageId,
            ]);
            return;
        }

        if ($message->type !== 'audio') {
            return;
        }

        if (! $message->media_url) {
            Log::warning('[transcribe_audio] media_url empty (download falhou?)', [
                'message_id' => $message->id,
            ]);
            return;
        }

        // Rate limit anti-abuse: 100min/business/dia (config-driven)
        $maxMinutesDay = (int) config('whatsapp.audio.transcription.rate_limit_minutes_per_day', 100);
        $cacheKey = sprintf('wa_audio_minutes:%d:%s', $this->businessId, now()->format('Y-m-d'));
        $estDurationS = $message->media_duration_s ?? 10;
        $estDurationMin = max(1, (int) ceil($estDurationS / 60));

        $usedMinutes = (int) Cache::get($cacheKey, 0);
        if (($usedMinutes + $estDurationMin) > $maxMinutesDay) {
            Log::warning('[transcribe_audio] rate limit hit — skipping', [
                'business_id' => $this->businessId,
                'used_minutes' => $usedMinutes,
                'limit' => $maxMinutesDay,
            ]);
            $message->forceFill([
                'media_transcription' => '[transcrição não disponível: limite diário atingido]',
            ])->save();
            return;
        }

        $absolutePath = Storage::disk('public')->path($message->media_url);
        if (! is_file($absolutePath)) {
            Log::warning('[transcribe_audio] file not found on disk', [
                'message_id' => $message->id,
                'path' => $message->media_url,
            ]);
            return;
        }

        try {
            $text = $transcriber->transcribe($absolutePath, 'pt');
        } catch (\Throwable $e) {
            Log::warning('[transcribe_audio] transcriber failed', [
                'message_id' => $message->id,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            throw $e; // permite retry com backoff
        }

        $message->forceFill([
            'media_transcription' => $text !== '' ? $text : '[áudio sem fala detectada]',
        ])->save();

        // Incrementa rate-limit counter + TTL 24h
        Cache::put(
            $cacheKey,
            $usedMinutes + $estDurationMin,
            Carbon::tomorrow()->endOfDay(),
        );

        Log::info('[transcribe_audio] done', [
            'business_id' => $this->businessId,
            'message_id' => $message->id,
            'duration_s' => $estDurationS,
            'chars' => mb_strlen($text),
            'tag' => 'whatsapp_audio',
        ]);
    }
}
