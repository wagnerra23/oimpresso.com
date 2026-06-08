<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-072 — Mídia (image/audio/document/sticker) + Whisper transcricao.
 *
 * Adiciona colunas `media_*` em `messages` (omnichannel novo, ADR 0135) E
 * `whatsapp_messages` (legacy). Defense-in-depth dual-schema durante
 * coexistência.
 *
 * Schema:
 *   - media_url            varchar 500 nullable   URL relativa no disco public (whatsapp/{biz}/{ym}/{uuid}.ext)
 *   - media_mime           varchar 100 nullable   MIME type (image/jpeg, audio/ogg, application/pdf, ...)
 *   - media_size_bytes     unsignedBigInteger     tamanho do arquivo
 *   - media_duration_s     unsignedSmallInteger   só audio/video (segundos)
 *   - media_thumbnail_url  varchar 500            só image/video (thumb 256x256 jpg)
 *   - media_transcription  text                   só audio (Whisper output)
 *   - media_filename       varchar 255            só document (nome original)
 *
 * Tier 0 multi-tenant: paths sempre contém {business_id} (ADR 0093).
 * URLs assinadas 24h via Storage::temporaryUrl() — NUNCA pública direto.
 *
 * Migration idempotente (Schema::hasColumn guards) — pode rodar 2x sem
 * quebrar. Drop colunas no down() preserva ordem reversa pra evitar
 * conflitos de índice.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-072
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (! Schema::hasColumn('messages', 'media_url')) {
                    $table->string('media_url', 500)->nullable()
                        ->after('payload')
                        ->comment('US-WA-072: path relativo no disco public');
                }
                if (! Schema::hasColumn('messages', 'media_mime')) {
                    $table->string('media_mime', 100)->nullable()
                        ->after('media_url');
                }
                if (! Schema::hasColumn('messages', 'media_size_bytes')) {
                    $table->unsignedBigInteger('media_size_bytes')->nullable()
                        ->after('media_mime');
                }
                if (! Schema::hasColumn('messages', 'media_duration_s')) {
                    $table->unsignedSmallInteger('media_duration_s')->nullable()
                        ->after('media_size_bytes')
                        ->comment('audio/video duration em segundos');
                }
                if (! Schema::hasColumn('messages', 'media_thumbnail_url')) {
                    $table->string('media_thumbnail_url', 500)->nullable()
                        ->after('media_duration_s');
                }
                if (! Schema::hasColumn('messages', 'media_transcription')) {
                    $table->text('media_transcription')->nullable()
                        ->after('media_thumbnail_url')
                        ->comment('Whisper output (audio only)');
                }
                if (! Schema::hasColumn('messages', 'media_filename')) {
                    $table->string('media_filename', 255)->nullable()
                        ->after('media_transcription');
                }
            });
        }

        // Defense-in-depth legacy (whatsapp_messages) — drivers Zapi/Meta
        // legacy ainda escrevem aqui até refactor pro schema novo.
        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('whatsapp_messages', 'media_url')) {
                    $table->string('media_url', 500)->nullable();
                }
                if (! Schema::hasColumn('whatsapp_messages', 'media_mime')) {
                    $table->string('media_mime', 100)->nullable();
                }
                if (! Schema::hasColumn('whatsapp_messages', 'media_size_bytes')) {
                    $table->unsignedBigInteger('media_size_bytes')->nullable();
                }
                if (! Schema::hasColumn('whatsapp_messages', 'media_duration_s')) {
                    $table->unsignedSmallInteger('media_duration_s')->nullable();
                }
                if (! Schema::hasColumn('whatsapp_messages', 'media_thumbnail_url')) {
                    $table->string('media_thumbnail_url', 500)->nullable();
                }
                if (! Schema::hasColumn('whatsapp_messages', 'media_transcription')) {
                    $table->text('media_transcription')->nullable();
                }
                if (! Schema::hasColumn('whatsapp_messages', 'media_filename')) {
                    $table->string('media_filename', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $cols = [
            'media_filename',
            'media_transcription',
            'media_thumbnail_url',
            'media_duration_s',
            'media_size_bytes',
            'media_mime',
            'media_url',
        ];

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) use ($cols) {
                foreach ($cols as $c) {
                    if (Schema::hasColumn('messages', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) use ($cols) {
                foreach ($cols as $c) {
                    if (Schema::hasColumn('whatsapp_messages', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
