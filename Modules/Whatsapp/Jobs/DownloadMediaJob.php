<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Message;

/**
 * Guardião 6 camadas — Camada 3 (DownloadMediaJob refatorado).
 *
 * Histórico: US-WA-072 versão #648 fazia HTTP GET direto na URL do provider.
 * Funcionou pra Z-API/Meta Cloud (URLs públicas direct-download), MAS NÃO
 * pra Baileys — URL Baileys é criptografada (.enc + mediaKey) e Hostinger
 * PHP não consegue decrypt sem rodar Baileys SDK (Node).
 *
 * Versão guardião: delega o decrypt pro daemon CT 100 via endpoint
 * `POST /media/decrypt-url` (paralelo Agent J). Daemon retorna octet-stream
 * com os bytes decifrados. Hostinger salva localmente + gera thumb + dispara
 * TranscribeAudioJob.
 *
 * Ciclo de vida (Camada 2 schema):
 *   pending → downloading → success    (caminho feliz)
 *   pending → downloading → pending    (soft fail, attempts < 5 → retry hourly Camada 4)
 *   pending → downloading → failed_permanent (attempts >= MAX → cap, sem retry)
 *
 * Multi-tenant Tier 0 (ADR 0093): `$businessId` no constructor — NUNCA
 * `session()` em job (fila não tem session). Path inclui business_id pra
 * defense-in-depth + facilita backup per-tenant.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-072
 * @see Modules/Whatsapp/Observers/MessageObserver.php (Camada 1)
 * @see Modules/Whatsapp/Jobs/RetryFailedMediaDownloadsJob.php (Camada 4)
 */
class DownloadMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tries Laravel-side. Hostinger queue=sync ignora, mas mantém pra outros drivers. */
    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    /**
     * @param int    $businessId   Tier 0 — sempre passar (job não tem session)
     * @param int    $messageId    Message row já persistida em status='received'
     * @param string $sourceUrl    URL do provider (Z-API/Meta direct OU Baileys .enc) — vazio = ler do payload
     * @param string $expectedMime MIME esperado (Baileys envia no payload)
     */
    public function __construct(
        public int $businessId,
        public int $messageId,
        public string $sourceUrl = '',
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

        // Skip se já success (Observer + webhook controller podem disparar
        // 2x em race condition). Idempotente.
        if ($message->media_download_status === Message::DOWNLOAD_STATUS_SUCCESS
            && $message->media_url !== null) {
            return;
        }

        // Camada 2 — increment attempts atomic + marca downloading.
        // DB::table() bypass Eloquent events/observers (evita recursão observer
        // → maybeDispatchMediaDownload → handle infinito em queue=sync).
        // Atomic SQL increment evita race entre Observer dispatch + Retry job.
        $currentAttempts = (int) ($message->getAttribute('media_download_attempts') ?? 0);
        $newAttempts = $currentAttempts + 1;
        DB::table('messages')
            ->where('id', $message->id)
            ->update([
                'media_download_attempts' => $newAttempts,
                'media_download_status' => Message::DOWNLOAD_STATUS_DOWNLOADING,
                'media_download_last_attempt_at' => now(),
            ]);
        // Atualiza atributos in-memory pra handlers downstream lerem o novo
        // valor (sem precisar refresh + roundtrip).
        $message->setRawAttributes(array_merge($message->getAttributes(), [
            'media_download_attempts' => $newAttempts,
            'media_download_status' => Message::DOWNLOAD_STATUS_DOWNLOADING,
            'media_download_last_attempt_at' => now()->toDateTimeString(),
        ]), true);

        $attempts = $newAttempts;

        // Valida MIME whitelist ANTES de baixar (Tier 0 — anti-XSS SVG)
        $mime = $this->expectedMime ?: ($message->media_mime ?? '');
        if ($mime && ! in_array($mime, Message::MEDIA_MIME_WHITELIST, true)) {
            $this->markPermanentFailed($message, "MIME bloqueado pelo whitelist: {$mime}");
            return;
        }

        try {
            $content = $this->fetchMediaBytes($message);
            $contentType = $this->resolveContentType($message, $mime);
        } catch (HttpFetchException $e) {
            // Soft fail estruturado — verificar attempts cap.
            $this->handleFailedAttempt($message, $attempts, $e->getMessage(), $e->isRetryable());
            return;
        } catch (\Throwable $e) {
            // Exceção não-estruturada (ex: I/O storage, parser) — sempre retryable.
            $this->handleFailedAttempt(
                $message,
                $attempts,
                mb_substr($e->getMessage(), 0, 200),
                retryable: true,
            );
            return;
        }

        $sizeBytes = strlen($content);

        if ($sizeBytes > Message::MEDIA_MAX_SIZE_BYTES) {
            $this->markPermanentFailed(
                $message,
                "Mídia > 16MB ({$sizeBytes}b) — descartada.",
            );
            return;
        }

        if ($contentType && ! in_array($contentType, Message::MEDIA_MIME_WHITELIST, true)) {
            $this->markPermanentFailed($message, "Content-Type bloqueado: {$contentType}");
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

        // Thumbnail só pra imagem (256x256 jpeg). GD nativo PHP.
        $thumbnailPath = null;
        if ($message->type === 'image' && function_exists('imagecreatefromstring')) {
            $thumbnailPath = $this->generateThumbnail($content, $this->businessId, $uuid);
        }

        // Bypass Eloquent events pra evitar re-trigger MessageObserver::updated
        // (que pode disparar OmnichannelMessageSent duplicado em status sent).
        DB::table('messages')
            ->where('id', $message->id)
            ->update([
                'media_url' => $relativePath,
                'media_mime' => $contentType ?: $mime,
                'media_size_bytes' => $sizeBytes,
                'media_thumbnail_url' => $thumbnailPath,
                'media_download_status' => Message::DOWNLOAD_STATUS_SUCCESS,
                'media_download_failed_reason' => null,
            ]);

        Log::info('[download_media] saved', [
            'message_id' => $message->id,
            'business_id' => $this->businessId,
            'size_bytes' => $sizeBytes,
            'mime' => $contentType,
            'has_thumb' => $thumbnailPath !== null,
            'attempts' => $attempts,
        ]);

        // Audio → encadeia transcrição assíncrona
        if ($message->type === 'audio') {
            TranscribeAudioJob::dispatch($this->businessId, $message->id);
        }
    }

    /**
     * Baixa os bytes da mídia. Estratégia em 2 níveis:
     *
     *   1. Se `sourceUrl` é HTTP normal (Z-API/Meta Cloud direct download) →
     *      `Http::get()` clássico. Funciona pros drivers públicos.
     *
     *   2. Se Baileys (URL .enc + mediaKey no payload) → chama daemon CT 100
     *      `POST /media/decrypt-url` com `{url, mediaKey, mimetype, type}`.
     *      Daemon decifra com Baileys SDK e devolve octet-stream.
     *
     * Heurística pra escolher caminho: detecta `mediaKey` no payload Baileys.
     * Se existe → daemon. Se não → HTTP direto.
     */
    protected function fetchMediaBytes(Message $message): string
    {
        $payload = $message->payload ?? [];
        $mediaKey = $this->extractMediaKey($payload, $message->type);

        // Caminho 2: Baileys decrypt via daemon CT 100.
        if ($mediaKey !== null && $message->provider === 'whatsapp_baileys') {
            return $this->fetchViaDaemonDecrypt($message, $payload, $mediaKey);
        }

        // Caminho 1: HTTP direto (Z-API / Meta Cloud) ou Baileys já flattado.
        $url = $this->sourceUrl ?: $this->extractDirectUrl($payload, $message->type);
        if (! $url) {
            throw new HttpFetchException(
                'Sem sourceUrl nem url no payload — não é possível baixar.',
                retryable: false,
            );
        }

        try {
            $response = Http::timeout(30)->get($url);
        } catch (\Throwable $e) {
            throw new HttpFetchException(
                'HTTP exception: ' . mb_substr($e->getMessage(), 0, 200),
                retryable: true,
            );
        }

        if (! $response->successful()) {
            // 4xx (404, 410) = URL expirou = não retryable.
            // 5xx + timeout = retryable.
            $retryable = $response->status() >= 500;
            throw new HttpFetchException(
                "Download falhou: HTTP {$response->status()}",
                retryable: $retryable,
            );
        }

        return $response->body();
    }

    /**
     * Chama o daemon CT 100 pra decifrar mídia Baileys.
     *
     * Endpoint: POST {daemon_url}/media/decrypt-url
     * Body JSON: {url, mediaKey, mimetype, type, message_id}
     * Response: 200 octet-stream (bytes brutos) OU 4xx/5xx JSON erro.
     *
     * Auth: Bearer = WHATSAPP_BAILEYS_API_KEY (mesma chave outros endpoints).
     * Timeout: 60s — decrypt grande (vídeo 50MB) pode demorar.
     *
     * Pre-requisito EXTERNO: endpoint vem do PR paralelo Agent J. Antes
     * desse deploy o Job vai bater 404 → soft fail → retry hourly.
     */
    protected function fetchViaDaemonDecrypt(Message $message, array $payload, string $mediaKey): string
    {
        $baseUrl = rtrim((string) config('whatsapp.baileys.daemon_url'), '/');
        $apiKey = (string) config('whatsapp.baileys.api_key', '');
        if ($baseUrl === '' || $apiKey === '') {
            throw new HttpFetchException(
                'Daemon Baileys não configurado (daemon_url/api_key vazio)',
                retryable: false,
            );
        }

        $url = $this->sourceUrl ?: $this->extractDirectUrl($payload, $message->type);
        $mimetype = $this->expectedMime ?: ($message->media_mime ?? '');

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->timeout(60)
                ->accept('application/octet-stream')
                ->post('/media/decrypt-url', [
                    'url' => $url,
                    'mediaKey' => $mediaKey,
                    'mimetype' => $mimetype,
                    'type' => $message->type,
                    'message_id' => $message->id,
                ]);
        } catch (\Throwable $e) {
            throw new HttpFetchException(
                'Daemon HTTP exception: ' . mb_substr($e->getMessage(), 0, 200),
                retryable: true,
            );
        }

        if (! $response->successful()) {
            $body = mb_substr($response->body(), 0, 200);
            // 404 endpoint não existe (daemon antigo) = retryable (deploy pendente)
            // 4xx outros = checar Content-Type pra ver se erro estruturado.
            $retryable = $response->status() === 404 || $response->status() >= 500;
            throw new HttpFetchException(
                "Daemon decrypt falhou HTTP {$response->status()}: {$body}",
                retryable: $retryable,
            );
        }

        return $response->body();
    }

    /**
     * Lê mediaKey do payload Baileys aninhado.
     *
     * Estrutura típica:
     *   payload.message.audioMessage.mediaKey
     *   payload.message.imageMessage.mediaKey
     *   payload.message.videoMessage.mediaKey
     *   payload.message.documentMessage.mediaKey
     *   payload.message.stickerMessage.mediaKey
     *
     * Daemon pode flatten pra `payload.mediaKey` — verificamos os 2 níveis.
     */
    protected function extractMediaKey(array $payload, ?string $type): ?string
    {
        // Caminho 1: flat (daemon normalizou).
        if (isset($payload['mediaKey']) && is_string($payload['mediaKey'])) {
            return $payload['mediaKey'];
        }

        // Caminho 2: aninhado raw Baileys.
        $messageProto = $payload['message'] ?? [];

        $protoKey = match ($type) {
            'audio' => 'audioMessage',
            'image' => 'imageMessage',
            'video' => 'videoMessage',
            'document' => 'documentMessage',
            'sticker' => 'stickerMessage',
            default => null,
        };

        if ($protoKey && isset($messageProto[$protoKey]['mediaKey'])) {
            return (string) $messageProto[$protoKey]['mediaKey'];
        }

        return null;
    }

    /**
     * Lê URL direta do payload (quando mediaKey ausente — Z-API/Meta Cloud).
     */
    protected function extractDirectUrl(array $payload, ?string $type): ?string
    {
        if (isset($payload['media_url']) && is_string($payload['media_url'])) {
            return $payload['media_url'];
        }

        $messageProto = $payload['message'] ?? [];
        $protoKey = match ($type) {
            'audio' => 'audioMessage',
            'image' => 'imageMessage',
            'video' => 'videoMessage',
            'document' => 'documentMessage',
            'sticker' => 'stickerMessage',
            default => null,
        };

        if ($protoKey && isset($messageProto[$protoKey]['url'])) {
            return (string) $messageProto[$protoKey]['url'];
        }

        return null;
    }

    /**
     * Resolve Content-Type após download. Daemon decrypt já devolve com header,
     * HTTP direto pode trazer no response — fallback no expected.
     */
    protected function resolveContentType(Message $message, string $expectedMime): string
    {
        return $expectedMime ?: ($message->media_mime ?? '');
    }

    /**
     * Soft fail: decide se ainda dá pra retentar ou cap MAX_ATTEMPTS atingido.
     *
     * `retryable=false` (4xx URL expirou, daemon não configurado) → marca
     * failed_permanent IMEDIATAMENTE, sem esperar 5 tentativas.
     *
     * `retryable=true` + attempts < MAX → volta pra pending (Retry Camada 4 pega).
     * `retryable=true` + attempts >= MAX → failed_permanent.
     */
    protected function handleFailedAttempt(Message $message, int $attempts, string $reason, bool $retryable): void
    {
        $reason = mb_substr($reason, 0, 255);

        if (! $retryable) {
            $this->markPermanentFailed($message, $reason);
            return;
        }

        if ($attempts >= Message::MEDIA_DOWNLOAD_MAX_ATTEMPTS) {
            $this->markPermanentFailed(
                $message,
                "Max retries exceeded ({$attempts}): {$reason}",
            );
            return;
        }

        // Volta pra pending → Camada 4 (hourly) pega no próximo ciclo.
        // DB::table bypass observer (evita re-dispatch loop).
        DB::table('messages')
            ->where('id', $message->id)
            ->update([
                'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
                'media_download_failed_reason' => $reason,
            ]);

        Log::warning('[download_media] soft fail', [
            'message_id' => $message->id,
            'business_id' => $this->businessId,
            'attempts' => $attempts,
            'reason' => $reason,
        ]);
    }

    /**
     * Hard fail: cap atingido OU erro permanente (MIME bloqueado, > 16MB,
     * daemon mal configurado, URL inválida). Não retentar.
     */
    protected function markPermanentFailed(Message $message, string $reason): void
    {
        $reason = mb_substr($reason, 0, 255);

        DB::table('messages')
            ->where('id', $message->id)
            ->update([
                'media_download_status' => Message::DOWNLOAD_STATUS_FAILED_PERMANENT,
                'media_download_failed_reason' => $reason,
                'failed_reason' => $reason, // backward-compat com UI legacy
            ]);

        Log::error('[download_media] failed_permanent', [
            'message_id' => $message->id,
            'business_id' => $this->businessId,
            'attempts' => $message->media_download_attempts,
            'reason' => $reason,
        ]);
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

/**
 * Exception interna pra controlar retryable vs non-retryable em handle().
 * Mantida no mesmo arquivo pra evitar poluir Modules\Whatsapp\Exceptions
 * com classe interna do Job (acoplada à lógica de fail tracking Camada 2).
 */
class HttpFetchException extends \RuntimeException
{
    public function __construct(string $message, protected bool $retryable = true)
    {
        parent::__construct($message);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
