<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria tabela `jobs` (queue=database) pro Laravel pra arquitetura Wagner
 * descrita 2026-05-14 02h: "recebe tudo de maneira rapida no redis ou onde,
 * depois sincroniza com o banco verifica se tem mensagem, mais sempre
 * guarda para não perder depois vai tratando".
 *
 * Hostinger NÃO tem Redis local rodando (`REDIS_HOST=127.0.0.1` configurado
 * mas Connection refused). Pra evitar dependência de infra externa,
 * usamos Laravel queue driver=database — mesma arquitetura "buffer
 * persistente + worker async", zero infra extra, sempre disponível em
 * shared hosting.
 *
 * Schema canônico Laravel — mesma migration usada por `php artisan
 * queue:table`. Idempotente — `if (! Schema::hasTable)` skip.
 *
 * @see Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php
 * @see Modules/Whatsapp/Jobs/PersistHistorySyncBatchJob.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // NÃO dropamos — risco de perder jobs pendentes em produção.
        // Se realmente precisar, manual via tinker.
    }
};
