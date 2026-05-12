<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Message;

/**
 * US-WA-072 — Download de mídia inbound (provider URL → disco público).
 *
 * Acionado pelo `ChannelBaileysWebhookController::handleMessage()` quando
 * detecta `type in (image|audio|video|document|sticker)`. Faz HTTP GET no
 * URL provider-side, valida MIME contra whitelist, salva em
 * `storage/app/public/whatsapp/{business_id}/{yyyy-mm}/{uuid}.{ext}`,
 * gera thumbnail (imagem) e atualiza a Message row.
 *
 * Multi-tenant Tier 0 (ADR 0093): `$businessId` no constructor — NUNCA
 * `session()` em job (fila não tem session). Path inclui business_id pra
 * defense-in-depth + facilita backup per-tenant.
 *
 * Encadeia `TranscribeAudioJob` quando o tipo é áudio (Whisper inline pra
 * atendente ler texto em segundos).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-072
 */
class DownloadMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    /**
     * @param int    $businessId  Tier 0 — sempre passar (job não tem session)
     * @param int    $messageId   Message row já persistida em status='received'
     * @param string $sourceUrl   URL do provider (Baileys/Z-API/Meta) pra fetch
     * @param string $expectedMime MIME esperado (Baileys envia no payload)
     */
    public function __construct(
        public int $businessId,
        public int $messageId,
        public string $sourceUrl,
        public string $expectedMime = '',
    ) {}

    public function handle(): void
    {
        // Pula global scope — job roda sem session user
        $message = Message::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->where('id', $this->messageId)
            ->first();

        if (! $message) {
            Log::warning('[download_media] message not found', [
                'business_id' => $this->businessId,
                'message_id' => $this->messageId,
            ]);
            return;
        }

        // Valida MIME whitelist ANTES de baixar (Tier 0 — anti-XSS SVG)
        $mime = $this->expectedMime ?: ($message->media_mime ?? '');
        if ($mime && ! in_array($mime, Message::MEDIA_MIME_WHITELIST, true)) {
            Log::warning('[download_media] MIME not allowed', [
                'message_id' => $message->id,
                'mime' => $mime,
            ]);
            $message->forceFill([
                'failed_reason' => "MIME bloqueado pelo whitelist: {$mime}",
            ])->save();
            return;
        }

        try {
            $response = Http::timeout(30)->get($this->sourceUrl);
        } catch (\Throwable $e) {
            Log::warning('[download_media] HTTP exception', [
                'message_id' => $message->id,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            throw $e; // permite retry
        }

        if (! $response->successful()) {
            Log::warning('[download_media] HTTP non-2xx', [
                'message_id' => $message->id,
                'status' => $response->status(),
            ]);
            $message->forceFill([
                'failed_reason' => "Download falhou: HTTP {$response->status()}",
            ])->save();
            return;
        }

        $content = $response->body();
        $sizeBytes = strlen($content);

        if ($sizeBytes > Message::MEDIA_MAX_SIZE_BYTES) {
            $message->forceFill([
                'failed_reason' => "Mídia > 16MB ({$sizeBytes}b) — descartada.",
            ])->save();
            return;
        }

        $contentType = $response->header('Content-Type') ?: $mime;
        if ($contentType && ! in_array($contentType, Message::MEDIA_MIME_WHITELIST, true)) {
            $message->forceFill([
                'failed_reason' => "Content-Type bloqueado: {$contentType}",
            ])->save();
            return;
        }

        $ext = $this->guessExtension($contentType, $message->type);
        $uuid = Str::uuid()->toString();
        $relativePath = sprintf(
            'whatsapp/%d/%s/%s.%s',
            $this->businessId,
            now()->format('Y-m'),
            $uuid,
            $ext,
        );

        Storage::disk('public')->put($relativePath, $content);

        // Thumbnail só pra imagem (256x256 jpeg). GD nativo PHP — sem
        // dependência nova (Intervention/Image evita instalar lib externa).
        $thumbnailPath = null;
        if ($message->type === 'image' && function_exists('imagecreatefromstring')) {
            $thumbnailPath = $this->generateThumbnail($content, $this->businessId, $uuid);
        }

        $message->forceFill([
            'media_url' => $relativePath,
            'media_mime' => $contentType ?: $mime,
            'media_size_bytes' => $sizeBytes,
            'media_thumbnail_url' => $thumbnailPath,
        ])->save();

        Log::info('[download_media] saved', [
            'message_id' => $message->id,
            'business_id' => $this->businessId,
            'size_bytes' => $sizeBytes,
            'mime' => $contentType,
            'has_thumb' => $thumbnailPath !== null,
        ]);

        // Audio → encadeia transcrição assíncrona
        if ($message->type === 'audio') {
            TranscribeAudioJob::dispatch($this->businessId, $message->id);
        }
    }

    /**
     * Adivinha extensão baseada no MIME (fallback pelo tipo).
     */
    protected function guessExtension(string $mime, ?string $type): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'audio/ogg' => 'ogg',
            'audio/opus' => 'opus',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/m4a' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'audio/wav' => 'wav',
            'video/mp4' => 'mp4',
            'video/mpeg' => 'mpeg',
            'video/3gpp' => '3gp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
        ];

        if (isset($map[$mime])) {
            return $map[$mime];
        }

        return match ($type) {
            'image' => 'jpg',
            'audio' => 'ogg',
            'video' => 'mp4',
            'document' => 'bin',
            default => 'bin',
        };
    }

    /**
     * Gera thumbnail 256x256 JPEG mantendo aspect ratio (centro-crop).
     * Retorna path relativo no disco public ou null se falhar.
     *
     * Usa GD nativo PHP — sem Intervention\Image (lib externa). 256x256
     * é suficiente pra preview na bubble do inbox; clicar abre original.
     */
    protected function generateThumbnail(string $imageBytes, int $businessId, string $uuid): ?string
    {
        try {
            $src = @imagecreatefromstring($imageBytes);
            if (! $src) {
                return null;
            }

            $srcW = imagesx($src);
            $srcH = imagesy($src);
            $targetSize = 256;

            // Square crop centralizado
            $minDim = min($srcW, $srcH);
            $cropX = (int) (($srcW - $minDim) / 2);
            $cropY = (int) (($srcH - $minDim) / 2);

            $thumb = imagecreatetruecolor($targetSize, $targetSize);
            imagecopyresampled(
                $thumb, $src,
                0, 0, $cropX, $cropY,
                $targetSize, $targetSize, $minDim, $minDim
            );

            $thumbRel = sprintf(
                'whatsapp/%d/%s/%s_thumb.jpg',
                $businessId,
                now()->format('Y-m'),
                $uuid,
            );

            ob_start();
            imagejpeg($thumb, null, 80);
            $thumbBytes = ob_get_clean();
            imagedestroy($src);
            imagedestroy($thumb);

            if ($thumbBytes !== false) {
                Storage::disk('public')->put($thumbRel, (string) $thumbBytes);
                return $thumbRel;
            }
        } catch (\Throwable $e) {
            Log::warning('[download_media] thumbnail failed', [
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
        }
        return null;
    }
}
