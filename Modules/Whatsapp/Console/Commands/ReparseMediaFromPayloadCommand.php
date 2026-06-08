<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Message;

/**
 * Reparse meta de mídia a partir do `payload` JSON pra mensagens órfãs
 * (`body=null` + `media_mime=null` + `payload!=null`) que foram persistidas
 * ANTES do PR #664 (fallback aninhado no ChannelBaileysWebhookController).
 *
 * Bug histórico: webhook antigo só lia `data['media_url']` / `data['mime']`
 * do nível raiz. Daemon Baileys em prod entregava payload aninhado (sem
 * flatten) — `message.audioMessage.mimetype`, `.fileLength`, `.seconds`.
 * Resultado: ~88 messages biz=1 com `payload` completo mas metadata vazia.
 *
 * Esta etapa SÓ extrai metadata. Pra de fato baixar a mídia decryptada,
 * rode depois:
 *
 *   php artisan whatsapp:backfill-media-download --since=YYYY-MM-DD
 *
 * Ou deixe `RetryFailedMediaDownloadsJob` (hourly) cuidar — Guardião 6
 * camadas (PR #675) tem Observer que auto-dispatcha `DownloadMediaJob`
 * quando `media_mime != null` E `media_url = null`.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): `--business=all` é cross-
 * business via `withoutGlobalScopes()` — comentário explícito justifica.
 *
 * Implementação chave: usa `DB::table('messages')->update(...)` em vez de
 * `Model::save()`. Isso BYPASS Eloquent events propositalmente — Observer
 * `MessageObserver` (Guardião camada 1) dispatcharia `DownloadMediaJob`
 * síncrono pra cada update, criando avalanche. Batch update silencioso +
 * Retry hourly OU `backfill-media-download` manual disparam jobs depois.
 *
 * Sample output prod biz=1 esperado:
 *
 *   Total candidatas: 88
 *     - 65 audio, 18 image, 3 video, 2 document
 *     - 0 sem mediaProto (skip)
 *
 *   Updated 88 · skipped 0
 *
 * @see Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php (PR #664 fallback)
 * @see Modules/Whatsapp/Jobs/DownloadMediaJob.php
 * @see Modules/Whatsapp/Console/Commands/BackfillMediaDownloadCommand.php
 */
class ReparseMediaFromPayloadCommand extends Command
{
    protected $signature = 'whatsapp:reparse-media-from-payload
                            {--business=all : business_id específico ou "all" (cross-tenant superadmin)}
                            {--since= : Cutoff inferior YYYY-MM-DD (default: processa tudo)}
                            {--limit=1000 : Máx mensagens pra processar (safety cap)}
                            {--dry-run : Preview sem persistir}';

    protected $description = 'Re-popula media_mime/size/duration/filename de messages órfãs com payload pré-PR #664';

    public function handle(): int
    {
        $businessOpt = (string) $this->option('business');
        $sinceOpt = $this->option('since');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $since = $sinceOpt ? Carbon::parse($sinceOpt)->startOfDay() : null;

        $this->info('Reparse mídia órfã — payload sem metadata');
        $this->line('  business : ' . $businessOpt);
        $this->line('  since    : ' . ($since ? $since->toDateString() : '—'));
        $this->line('  limit    : ' . $limit);
        $this->line('  dry-run  : ' . ($dryRun ? 'sim' : 'não'));
        $this->newLine();

        // SUPERADMIN: backfill CLI cross-business — webhook reparse não tem
        // session user. ADR 0093 — comentário explícito justifica.
        $query = Message::query()
            ->withoutGlobalScopes()
            ->whereNull('body')
            ->whereNull('media_mime')
            ->whereNotNull('payload');

        if ($businessOpt !== 'all') {
            $businessId = (int) $businessOpt;
            if ($businessId <= 0) {
                $this->error("--business={$businessOpt} inválido (esperado inteiro > 0 ou 'all').");
                return self::FAILURE;
            }
            $query->where('business_id', $businessId);
        }

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        $query->orderBy('created_at', 'asc')->limit($limit);

        $total = (int) $query->count();
        $this->line("Total candidatas: {$total}");

        if ($total === 0) {
            $this->info('Nada pra fazer.');
            return self::SUCCESS;
        }

        // Contagem por tipo detectado + persistência
        $counters = [
            'audio' => 0,
            'image' => 0,
            'video' => 0,
            'document' => 0,
            'sticker' => 0,
        ];
        $skipped = 0;
        $updated = 0;

        $query->get()->each(function (Message $message) use (&$counters, &$skipped, &$updated, $dryRun) {
            $extracted = $this->extractMediaMeta($message->payload ?? []);

            if ($extracted === null) {
                $skipped++;
                return;
            }

            $detectedType = $extracted['type'];
            if (isset($counters[$detectedType])) {
                $counters[$detectedType]++;
            }

            if ($dryRun) {
                return;
            }

            $updateSet = [
                'media_mime' => $extracted['media_mime'],
                'media_size_bytes' => $extracted['media_size_bytes'],
                'media_duration_s' => $extracted['media_duration_s'],
                'media_filename' => $extracted['media_filename'],
                'updated_at' => now(),
            ];

            // Caption sobrescreve body só se realmente existir
            if ($extracted['caption'] !== null) {
                $updateSet['body'] = $extracted['caption'];
            }

            // Corrige type='text' (default match() do webhook quando body=null
            // + sem detecção) pra tipo real de mídia
            if ($message->type === 'text' && $extracted['type'] !== null) {
                // sticker mapeia pra image (consistente com webhook controller)
                $updateSet['type'] = $extracted['type'] === 'sticker' ? 'image' : $extracted['type'];
            }

            // DB::table()->update() BYPASS Eloquent events propositalmente
            // (ver docblock — evita avalanche de DownloadMediaJob sync).
            DB::table('messages')
                ->where('id', $message->id)
                ->update($updateSet);

            $updated++;
        });

        $this->line(sprintf(
            '  - %d audio, %d image, %d video, %d document, %d sticker',
            $counters['audio'],
            $counters['image'],
            $counters['video'],
            $counters['document'],
            $counters['sticker']
        ));
        $this->line(sprintf('  - %d sem mediaProto (skip)', $skipped));
        $this->newLine();

        if ($dryRun) {
            $this->warn("[dry-run] WOULD update {$total} messages (skipped: {$skipped}).");
            return self::SUCCESS;
        }

        $this->info("Updated {$updated} · skipped {$skipped}");
        $this->newLine();
        $this->line('Próximo passo: rodar download das mídias decryptadas');
        $this->line('  php artisan whatsapp:backfill-media-download' . ($since ? ' --since=' . $since->toDateString() : ''));
        $this->line('Ou aguardar RetryFailedMediaDownloadsJob (hourly) — Guardião 6 camadas PR #675.');

        Log::info('[whatsapp.reparse_media_from_payload.completed]', [
            'business_filter' => $businessOpt,
            'since' => $since?->toIso8601String(),
            'limit' => $limit,
            'dry_run' => $dryRun,
            'total' => $total,
            'updated' => $updated,
            'skipped' => $skipped,
            'by_type' => $counters,
        ]);

        return self::SUCCESS;
    }

    /**
     * Extrai meta de mídia do payload aninhado Baileys.
     *
     * Mesma lógica do fallback defensivo em ChannelBaileysWebhookController
     * (PR #664) — fonte canônica. Retorna null se payload não tem nenhum
     * `*Message` reconhecível (msg só de texto, protocol msg, etc).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    protected function extractMediaMeta(array $payload): ?array
    {
        $messageProto = $payload['message'] ?? [];

        // Detecta tipo + extrai proto da mídia (fallback chain)
        if (isset($messageProto['imageMessage'])) {
            $mediaProto = $messageProto['imageMessage'];
            $type = 'image';
        } elseif (isset($messageProto['audioMessage'])) {
            $mediaProto = $messageProto['audioMessage'];
            $type = 'audio';
        } elseif (isset($messageProto['videoMessage'])) {
            $mediaProto = $messageProto['videoMessage'];
            $type = 'video';
        } elseif (isset($messageProto['documentMessage'])) {
            $mediaProto = $messageProto['documentMessage'];
            $type = 'document';
        } elseif (isset($messageProto['stickerMessage'])) {
            $mediaProto = $messageProto['stickerMessage'];
            $type = 'sticker';
        } else {
            return null;
        }

        // Sanitize MIME: Baileys envia `"audio/ogg; codecs=opus"` — strip
        // codec pra match com Message::MEDIA_MIME_WHITELIST e Whisper API
        $mediaMimeRaw = $mediaProto['mimetype'] ?? null;
        $mediaMime = $mediaMimeRaw
            ? trim(explode(';', (string) $mediaMimeRaw, 2)[0])
            : null;

        $mediaSize = isset($mediaProto['fileLength'])
            ? (int) $mediaProto['fileLength']
            : null;
        $mediaDuration = isset($mediaProto['seconds'])
            ? (int) $mediaProto['seconds']
            : null;
        $mediaFilename = $mediaProto['fileName'] ?? null;

        // Caption pode existir em image/video/document mesmo quando body=null
        $caption = $messageProto['imageMessage']['caption']
            ?? $messageProto['videoMessage']['caption']
            ?? $messageProto['documentMessage']['caption']
            ?? null;

        return [
            'type' => $type,
            'media_mime' => $mediaMime,
            'media_size_bytes' => $mediaSize,
            'media_duration_s' => $mediaDuration,
            'media_filename' => $mediaFilename,
            'caption' => $caption,
        ];
    }
}
