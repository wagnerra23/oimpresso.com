<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guardião anti-mensagens-perdidas — Camada 2 (status enum + tracking).
 *
 * Adiciona colunas em `messages` pra rastrear o ciclo de vida do download
 * de mídia inbound Baileys. Antes deste guardião, 89 messages biz=1
 * apareceram em prod com `media_mime != null` e `media_url = null`
 * indefinidamente (URL Baileys cripto, decrypt server-side não rolou).
 *
 * Schema:
 *   - media_download_status         ENUM('pending','downloading','success','failed_permanent') default 'pending'
 *   - media_download_attempts       INT UNSIGNED default 0          incrementa a cada tentativa do Job
 *   - media_download_last_attempt_at TIMESTAMP NULL                 quando o Job rodou pela última vez
 *   - media_download_failed_reason  VARCHAR(255) NULL               motivo do failed_permanent
 *
 * Índice composto `msgs_media_pending_idx (media_download_status, created_at)`
 * acelera scan do `RetryFailedMediaDownloadsJob` (Camada 4) e do
 * `ScanMediaDriftCommand` (Camada 5).
 *
 * Idempotente (Schema::hasColumn guards) — pode rodar 2x sem quebrar.
 * Compatibilidade SQLite: ENUM vira VARCHAR (Laravel converte automaticamente
 * via Doctrine DBAL); CHECK constraint NÃO é emitido pra preservar inserts
 * legacy de testes que escrevem strings livres.
 *
 * Multi-tenant Tier 0 (ADR 0093): colunas NÃO carregam business_id (já existe
 * em `messages.business_id` com global scope). Filtros do retry job usam
 * `withoutGlobalScopes()` + `// SUPERADMIN: cron cross-business`.
 *
 * @see Modules/Whatsapp/Jobs/DownloadMediaJob.php (Camada 3)
 * @see Modules/Whatsapp/Jobs/RetryFailedMediaDownloadsJob.php (Camada 4)
 * @see Modules/Whatsapp/Console/Commands/ScanMediaDriftCommand.php (Camada 5)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'media_download_status')) {
                // ENUM em MySQL, VARCHAR em SQLite (DBAL converte).
                // Default 'pending' aplica retroativamente a 89 messages biz=1
                // que ficaram órfãs antes do guardião — vão aparecer no
                // RetryFailedMediaDownloadsJob na primeira hora pós-deploy.
                $table->enum('media_download_status', [
                    'pending', 'downloading', 'success', 'failed_permanent',
                ])->default('pending')->after('media_thumbnail_url');
            }
            if (! Schema::hasColumn('messages', 'media_download_attempts')) {
                $table->unsignedInteger('media_download_attempts')->default(0)
                    ->after('media_download_status');
            }
            if (! Schema::hasColumn('messages', 'media_download_last_attempt_at')) {
                $table->timestamp('media_download_last_attempt_at')->nullable()
                    ->after('media_download_attempts');
            }
            if (! Schema::hasColumn('messages', 'media_download_failed_reason')) {
                $table->string('media_download_failed_reason', 255)->nullable()
                    ->after('media_download_last_attempt_at');
            }
        });

        // Índice composto (criado em statement separado pra idempotência).
        // SQLite ignora INDEX criados via Schema se já existir, MySQL pede
        // try/catch defensivo pra evitar quebrar 2ª migração.
        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->index(
                    ['media_download_status', 'created_at'],
                    'msgs_media_pending_idx',
                );
            });
        } catch (\Throwable $e) {
            // Índice já existe — ok pra idempotência.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropIndex('msgs_media_pending_idx');
            });
        } catch (\Throwable $e) {
            // Índice pode não existir.
        }

        Schema::table('messages', function (Blueprint $table) {
            foreach ([
                'media_download_failed_reason',
                'media_download_last_attempt_at',
                'media_download_attempts',
                'media_download_status',
            ] as $col) {
                if (Schema::hasColumn('messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
